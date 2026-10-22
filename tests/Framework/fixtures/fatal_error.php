<?php

// Served by FatalErrorResponseTest with the PHP built-in server. Runs out of memory (a fatal error)
// so the shutdown handler has to build the 500 response and call the application's report hook.

namespace App\Exceptions\Handlers {
    class ApplicationExceptionHandler
    {
        public static function reportUnhandledException(\Throwable $e): void
        {
            file_put_contents(getenv('MIMIMAL_TEST_DIR') . '/hook.txt', $e->getMessage());
        }
    }
}

namespace {
    require __DIR__ . '/../../../vendor/autoload.php';

    \Shared\MimimalCmsConfig::$exceptionLogDirectory = getenv('MIMIMAL_TEST_DIR') . '/exception.log';

    if (($_GET['output'] ?? '') === '1') {
        echo str_repeat('.', 10000);
        flush();
    }

    str_repeat('x', 64 * 1024 * 1024);
}
