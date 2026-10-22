<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Fatal errors in a real web request (PHP built-in server, display_errors off as in production):
 * the shutdown handler must send the 500 response and call the application's report hook.
 */
class FatalErrorResponseTest extends TestCase
{
    private string $dir;

    /** @var resource|null */
    private $server = null;

    private int $port;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/mimimal_fatal_' . bin2hex(random_bytes(4));
        mkdir($this->dir);

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->port = (int)substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        $this->server = proc_open(
            [
                PHP_BINARY, '-d', 'display_errors=0', '-d', 'log_errors=0', '-d', 'memory_limit=32M',
                '-S', '127.0.0.1:' . $this->port, __DIR__ . '/fixtures/fatal_error.php',
            ],
            [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']],
            $pipes,
            null,
            ['MIMIMAL_TEST_DIR' => $this->dir]
        );

        // The framework's error handler turns warnings into exceptions even under @
        for ($i = 0; $i < 100; $i++) {
            try {
                fclose(fsockopen('127.0.0.1', $this->port));
                return;
            } catch (\ErrorException) {
                usleep(50000);
            }
        }
        $this->fail('built-in server did not start');
    }

    protected function tearDown(): void
    {
        if ($this->server) {
            proc_terminate($this->server);
            proc_close($this->server);
        }
        array_map('unlink', glob($this->dir . '/*'));
        rmdir($this->dir);
    }

    public function testFatalErrorReturns500AndCallsReportHook(): void
    {
        [$status, $body] = $this->get('/');

        $this->assertStringContainsString(' 500 ', $status);
        $this->assertStringContainsString('500', $body, 'the error page is rendered');
        $this->assertStringContainsString('Allowed memory size', $this->read('hook.txt'));
        $this->assertStringContainsString('Allowed memory size', $this->read('exception.log'));
    }

    public function testReportHookRunsEvenAfterOutputWasSent(): void
    {
        $this->get('/?output=1');

        $this->assertStringContainsString('Allowed memory size', $this->read('hook.txt'));
    }

    private function read(string $file): string
    {
        return is_file($this->dir . '/' . $file) ? (string)file_get_contents($this->dir . '/' . $file) : '';
    }

    /** @return array{0: string, 1: string} [status line, body] */
    private function get(string $path): array
    {
        $body = file_get_contents(
            "http://127.0.0.1:{$this->port}{$path}",
            false,
            stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]])
        );

        return [$http_response_header[0] ?? '', (string)$body];
    }
}
