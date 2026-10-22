<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Shadow\StringCryptor;

class StringCryptorTest extends TestCase
{
    public function testHkdfRoundTrip(): void
    {
        $cryptor = new StringCryptor;
        $cryptor->setSeacretKey('hkdf-key', 'openssl-key');

        $hash = $cryptor->hashHkdf('value');
        $this->assertTrue($cryptor->hkdfEquals('value', $hash));
        $this->assertFalse($cryptor->hkdfEquals('other', $hash));
    }

    public function testSecretsAreHiddenFromTraces(): void
    {
        $previous = ini_set('zend.exception_ignore_args', '0');
        try {
            $cryptor = new StringCryptor;
            $cryptor->setSeacretKey('hkdf-key', 'openssl-key');
            $cryptor->hkdfEquals('SECRET-ADMIN-KEY', 'not-a-hash');
            $this->fail('hkdfEquals should reject a malformed hash');
        } catch (\RuntimeException $e) {
            $trace = $e->getTraceAsString();
            $this->assertStringContainsString('SensitiveParameterValue', $trace);
            $this->assertStringNotContainsString('SECRET', $trace);
            $this->assertStringNotContainsString('not-a-hash', $trace);
        } finally {
            ini_set('zend.exception_ignore_args', (string) $previous);
        }
    }
}
