<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// getIP() caches its result per process, so each case runs in a fresh PHP process.
class GetIpTest extends TestCase
{
    private function getIpWith(array $trustedHeaders, array $server): string
    {
        $code = 'require ' . var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true) . ';'
            . '\Shared\MimimalCmsConfig::$trustedIpHeaders = ' . var_export($trustedHeaders, true) . ';'
            . '$_SERVER = array_merge($_SERVER, ' . var_export($server, true) . ');'
            . 'echo getIP();';

        return (string)shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code));
    }

    public function testProxyHeadersAreIgnoredByDefault(): void
    {
        $this->assertSame('198.51.100.7', $this->getIpWith([], [
            'REMOTE_ADDR' => '198.51.100.7',
            'HTTP_X_FORWARDED_FOR' => '8.8.8.8',
            'HTTP_CLIENT_IP' => '8.8.4.4',
            'HTTP_CF_CONNECTING_IP' => '8.8.8.8',
        ]));
    }

    public function testTrustedHeaderIsUsed(): void
    {
        $this->assertSame('8.8.8.8', $this->getIpWith(['HTTP_CF_CONNECTING_IP'], [
            'REMOTE_ADDR' => '172.68.0.1',
            'HTTP_CF_CONNECTING_IP' => '8.8.8.8',
            'HTTP_X_FORWARDED_FOR' => '1.1.1.1',
        ]));
    }

    public function testInvalidTrustedHeaderFallsBackToRemoteAddr(): void
    {
        $this->assertSame('127.0.0.1', $this->getIpWith(['HTTP_CF_CONNECTING_IP'], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_CF_CONNECTING_IP' => 'not-an-ip',
        ]));
    }
}
