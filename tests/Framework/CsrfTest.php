<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Shared\Exceptions\BadRequestException;
use Shared\Exceptions\ValidationException;

class CsrfTest extends TestCase
{
    private string $token = '0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        $_POST = [];
        $_COOKIE = [];
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        $_SESSION['_csrf'] = hash('sha256', $this->token);
    }

    public function testCookieAloneIsNotAccepted(): void
    {
        // Browsers send cookies on cross-site requests, so the cookie must not count as the request token.
        $_COOKIE['CSRF-Token'] = $this->token;

        $this->expectException(BadRequestException::class);
        verifyCsrfToken();
    }

    public function testHeaderTokenIsAccepted(): void
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $this->token;
        verifyCsrfToken();
        $this->addToAssertionCount(1);
    }

    public function testPostFieldTokenIsAccepted(): void
    {
        $_POST['_csrf'] = $this->token;
        verifyCsrfToken();
        $this->addToAssertionCount(1);
    }

    public function testWrongTokenIsRejected(): void
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong';

        $this->expectException(ValidationException::class);
        verifyCsrfToken();
    }

    public function testExistingTokenIsReused(): void
    {
        // csrfField() and the X-CSRF-Token header must carry the same token as the cookie.
        $_COOKIE['CSRF-Token'] = $this->token;

        $this->assertSame($this->token, getCsrfToken());
        $this->assertSame(hash('sha256', $this->token), $_SESSION['_csrf']);
    }

    public function testMismatchedCookieIsReissued(): void
    {
        // setcookie() needs a process that has not printed anything yet
        $code = 'require ' . var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true) . ';'
            . '$_COOKIE["CSRF-Token"] = "stale";'
            . '$token = \\Shadow\\Kernel\\Cookie::csrfToken();'
            . 'echo json_encode([$token !== "stale", $token === $_COOKIE["CSRF-Token"], hash("sha256", $token) === $_SESSION["_csrf"]]);';

        $result = shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code));

        $this->assertSame('[true,true,true]', $result);
    }
}
