<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Shadow\Kernel\Dispatcher\ConstructorInjection;

class DummyOutputClass
{
    public function output()
    {
        return "DummyOutputClass output";
    }
}

class DummyClass
{
    public function __construct(
        private ?DummyOutputClass $nullableClass,
        private DummyOutputClass $nonNullableClass,
        private string $someString
    ) {
        $this->nullableClass = $nullableClass;
        $this->nonNullableClass = $nonNullableClass;
        $this->someString = $someString;
    }

    public function testMethod()
    {
        return [
            'nullableClass' => $this->nullableClass,
            'nonNullableClass' => $this->nonNullableClass,
            'someString' => $this->someString,
        ];
    }
}

class ConstructorInjectionTest extends TestCase
{
    public function testConstructorInjection()
    {
        $injectionParameters = [
            'someString' => 'test string',
        ];
        $constructorInjection = new ConstructorInjection($injectionParameters);

        $testClassInstance = $constructorInjection->constructorInjection(DummyClass::class);

        $this->assertInstanceOf(DummyClass::class, $testClassInstance);
        $result = $testClassInstance->testMethod();

        $this->assertNull($result['nullableClass']);
        $this->assertInstanceOf(DummyOutputClass::class, $result['nonNullableClass']);
        $this->assertEquals('test string', $result['someString']);
    }
}
