<?php

declare(strict_types=1);

use ExceptionHandler\ExceptionHandler;
use PHPUnit\Framework\TestCase;
use Shared\MimimalCmsConfig;

class ExceptionLogTest extends TestCase
{
    private string $logFile;
    private string $originalLogFile;
    private array $originalServer;

    protected function setUp(): void
    {
        $this->logFile = tempnam(sys_get_temp_dir(), 'mimimal_log_');
        $this->originalLogFile = MimimalCmsConfig::$exceptionLogDirectory;
        $this->originalServer = $_SERVER;
        MimimalCmsConfig::$exceptionLogDirectory = $this->logFile;
    }

    protected function tearDown(): void
    {
        MimimalCmsConfig::$exceptionLogDirectory = $this->originalLogFile;
        $_SERVER = $this->originalServer;
        @unlink($this->logFile);
    }

    public function testCredentialsAreMasked(): void
    {
        $_SERVER['HTTP_COOKIE'] = 'session=cookie-secret-value';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic auth-secret-value';
        $_SERVER['PHP_AUTH_PW'] = 'pw-secret-value';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'csrf-secret-value';
        $_SERVER['DB_PASSWORD'] = 'env-secret-value';
        $_SERVER['REQUEST_URI'] = '/some/page';
        $_SERVER['REMOTE_ADDR'] = '198.51.100.7';

        ExceptionHandler::errorLog(new \RuntimeException('boom'));
        $log = file_get_contents($this->logFile);

        $this->assertStringNotContainsString('secret-value', $log);
        $this->assertStringContainsString('HTTP_COOKIE: [masked]', $log);
        $this->assertStringContainsString('PHP_AUTH_PW: [masked]', $log);
        // Fields used to investigate errors stay as they are
        $this->assertStringContainsString('REQUEST_URI: /some/page', $log);
        $this->assertStringContainsString('REMOTE_ADDR: 198.51.100.7', $log);
        $this->assertStringContainsString('RuntimeException: boom', $log);
    }
}
