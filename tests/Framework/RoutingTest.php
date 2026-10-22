<?php

declare(strict_types=1);

namespace App\Controllers\Pages {
    class RoutingFixturePageController
    {
        public function __construct() {}
        public function index() {}
        public function show() {}
        protected function prot() {}
        private function secret() {}
        public static function stat() {}
        public function __toString(): string { return ''; }
    }
}

namespace {
    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\TestCase;
    use Shadow\Kernel\Dispatcher\Routing;
    use Shadow\Kernel\RouteClasses\RouteDTO;
    use Shared\Exceptions\MethodNotAllowedException;
    use Shared\Exceptions\NotFoundException;

    class RoutingTest extends TestCase
    {
        private function resolve(array $paths): RouteDTO
        {
            $dto = new RouteDTO;
            $dto->parsedPathArray = $paths;
            $dto->requestMethod = 'GET';
            $dto->setRouteArrayKey('dynamic');

            $routing = new Routing;
            $routing->setRouteDto($dto);
            $routing->resolveController();
            return $dto;
        }

        public function testPublicActionsResolve(): void
        {
            $this->assertSame('index', $this->resolve(['routingfixture'])->methodName);
            $this->assertSame('show', $this->resolve(['routingfixture', 'show'])->methodName);
        }

        public static function unreachable(): array
        {
            return [
                'constructor' => [['routingfixture', '__construct']],
                'magic' => [['routingfixture', '__tostring']],
                'protected' => [['routingfixture', 'prot']],
                'private' => [['routingfixture', 'secret']],
                'static' => [['routingfixture', 'stat']],
                'missing' => [['routingfixture', 'nope']],
                'invalid first segment' => [['routing-fixture', 'show']],
                'invalid second segment' => [['routingfixture', 'sh.ow']],
                'magic first segment' => [['__routingfixture']],
                'three segments' => [['routingfixture', 'show', 'x']],
            ];
        }

        #[DataProvider('unreachable')]
        public function testUnreachableActionsAre404(array $paths): void
        {
            $this->expectException(NotFoundException::class);
            $this->resolve($paths);
        }

        private function validateMethod(string $requestMethod, array|false $allowed): void
        {
            $dto = new RouteDTO;
            $dto->requestMethod = $requestMethod;
            $dto->routeRequestMethodArray = $allowed;

            $routing = new Routing;
            $routing->setRouteDto($dto);
            $routing->validateAllowedMethods();
        }

        public function testGetIsAllowedOnGetRoute(): void
        {
            $this->validateMethod('GET', ['GET']);
            $this->validateMethod('GET', ['POST', 'GET']);
            $this->addToAssertionCount(1);
        }

        public static function notAllowed(): array
        {
            return [
                'POST to GET route' => ['POST', ['GET']],
                'HEAD to POST route' => ['HEAD', ['POST']],
                'POST to default (GET/HEAD) route' => ['POST', false],
            ];
        }

        #[DataProvider('notAllowed')]
        public function testDisallowedMethodIs405(string $method, array|false $allowed): void
        {
            $this->expectException(MethodNotAllowedException::class);
            $this->validateMethod($method, $allowed);
        }
    }
}
