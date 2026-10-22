<?php

namespace Shared;

class MimimalCmsConfig
{
    /**
     * Maps interface classes to their concrete implementations.
     * Keys are interface classes, and values are the corresponding injected concrete classes.
     *
     * @var array<string, string>
     */
    public static array $constructorInjectionMap = [
        \Shadow\StringCryptorInterface::class => \Shadow\StringCryptor::class,
        \Shadow\Kernel\ViewInterface::class => \Shadow\Kernel\View::class,
        \Shadow\File\FileValidatorInterface::class => \Shadow\File\FileValidator::class,
        \Shadow\File\Image\ImageStoreInterface::class => \Shadow\File\Image\ImageStore::class,
        \Shadow\File\Image\GdImageFactoryInterface::class => \Shadow\File\Image\GdImageFactory::class,
    ];

    // URL root
    public static string $urlRoot = '';

    // Directories
    public static string $publicDir = __DIR__ . '/../public';
    public static string $viewsDir = __DIR__ . '/../app/Views';

    // Default options for cookies
    public static bool $cookieDefaultSecure = false;
    public static bool $cookieDefaultHttpOnly = true;
    public static string $cookieDefaultSameSite = 'lax';

    // Proxy headers that getIP() may trust, e.g. ['HTTP_CF_CONNECTING_IP'] behind Cloudflare.
    // Leave empty unless every request goes through that proxy (clients can send these headers themselves).
    public static array $trustedIpHeaders = [];

    // Options for session
    public static string $flashSessionKeyName = 'mimimalFlashSession';
    public static string $sessionKeyName = 'mimimalSession';

    // File validator
    public static int $defaultMaxFileSize = 20480;

    // Database configuration
    public static string $dbHost = 'mysql';
    public static string $dbName = 'test_db';
    public static string $dbUserName = 'test_user';
    public static string $dbPassword = 'IdvMM[DkcK*FLW3y';
    public static bool $dbAttrPersistent = false;
    public static string $dbCharset = 'utf8mb4';

    // String cryptor configuration
    public static string $stringCryptorHkdfKey = 'YOUR_KEY';
    public static string $stringCryptorOpensslKey = 'YOUR_KEY';

    /**
     * Defines a mapping of HTTP errors to their corresponding HTTP status codes and messages.
     * 
     * Keys are the classes of the exceptions, and values are arrays with two elements:
     *   - httpCode: the HTTP status code to be returned
     *   - httpStatusMessage: the corresponding HTTP status message
     * 
     * @var array<string, array{httpCode: int, log: bool, httpStatusMessage: string}>
     */
    public static array $httpErrors = [
        \Shared\Exceptions\NotFoundException::class =>         ['httpCode' => 404, 'log' => false, 'httpStatusMessage' => 'Not Found'],
        \Shared\Exceptions\MethodNotAllowedException::class => ['httpCode' => 405, 'log' => false, 'httpStatusMessage' => 'Method Not Allowed'],
        \Shared\Exceptions\BadRequestException::class =>       ['httpCode' => 400, 'log' => true,  'httpStatusMessage' => 'Bad Request'],
        \Shared\Exceptions\ValidationException::class =>       ['httpCode' => 400, 'log' => true,  'httpStatusMessage' => 'Bad Request'],
        \Shared\Exceptions\InvalidInputException::class =>     ['httpCode' => 400, 'log' => true,  'httpStatusMessage' => 'Bad Request'],
        \Shared\Exceptions\UploadException::class =>           ['httpCode' => 400, 'log' => true,  'httpStatusMessage' => 'Bad Request'],
        \Shared\Exceptions\SessionTimeoutException::class =>   ['httpCode' => 401, 'log' => true,  'httpStatusMessage' => 'Unauthorized'],
        \Shared\Exceptions\UnauthorizedException::class =>     ['httpCode' => 401, 'log' => true,  'httpStatusMessage' => 'Unauthorized'],
        \Shared\Exceptions\ThrottleRequestsException::class => ['httpCode' => 429, 'log' => true,  'httpStatusMessage' => 'Too Many Requests'],
    ];

    // Display exceptions
    public static bool $exceptionHandlerDisplayBeforeObClean = true;
    public static bool $exceptionHandlerDisplayErrorTraceDetails = true;

    // Exceptions Log directory.
    public static string $exceptionLogDirectory = __DIR__ . '/../logs/exception.log';

    /**
     * The path to hide from exception error trace.
     * This constant is used to remove the unnecessary path from the beginning of
     * the path included in exception error trace.
     */
    public static string $errorPageHideDirectory = '/var/www/html';

    /**
     * This constant is used to specify the document root path name.
     * The path name after this constant is concatenated with the GitHub URL.
     */
    public static string $errorPageDocumentRootName = 'html';

    /**
     * This constant is used to specify the GitHub URL for displaying the source code in the exception error trace.
     * The path name after the DOCUMENT_ROOT_NAME constant is concatenated with this URL.
     */
    public static string $errorPageGitHubUrl = 'https://github.com/mimimiku778/MimimalCMS/blob/master/';
}
