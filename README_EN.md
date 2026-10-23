# MimimalCMS

A small PHP MVC framework: routing, input validation, a DI container, views and helper functions. SQL is written by hand.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE.md) PHP 8.1+ ・ [日本語](README.md)

Used by: [OpenChat Graph](https://openchat-review.me) ([source](https://github.com/Open-Chat-Graph/Open-Chat-Graph))

## Installation (Composer)

The framework runs from a copy in your project root with the same layout, not from `vendor/` (the same idea as Drupal's `core/`).
Composer creates the copy, so normally you just add it to `.gitignore`.

```jsonc
// composer.json
{
    "repositories": [{ "type": "vcs", "url": "https://github.com/pika-02-03/MimimalCMS" }],
    "require": { "pika-02-03/mimimalcms": "^1.1" },
    "autoload": {
        "psr-4": { "Shadow\\": "shadow/", "Shared\\": "shared/", "App\\": "app/" },
        "files": [
            "shared/MimimalCMS_Settings.php",
            "shared/MimimalCMS_Enums.php",
            "shared/MimimalCMS_ExceptionHandler.php",
            "shared/MimimalCMS_HelperFunctions.php"
        ]
    },
    "scripts": {
        "post-install-cmd": ["MimimalCMS\\Composer\\Installer::sync"],
        "post-update-cmd": ["MimimalCMS\\Composer\\Installer::sync"],
        "mimimalcms-sync": "MimimalCMS\\Composer\\Installer::sync",
        "mimimalcms-check": "MimimalCMS\\Composer\\Installer::check"
    }
}
```

```gitignore
# .gitignore (framework copy created by composer install)
/vendor/
/shadow/
/shared/Exceptions/
/shared/MimimalCMS_Enums.php
/shared/MimimalCMS_ExceptionHandler.php
/shared/MimimalCMS_HelperFunctions.php
```

Every `composer install` (`composer install --no-dev` in production) copies the framework files into the project (only changed files are written).

| Path | Owner | On sync |
|---|---|---|
| `shadow/` | framework | mirrored (files removed from the package are deleted) |
| `shared/MimimalCMS_{Enums,ExceptionHandler,HelperFunctions}.php`, `shared/Exceptions/` | framework | overwritten |
| `shared/MimimalCmsConfig.php`, `shared/MimimalCMS_Settings.php` | application | stub created only if missing, never touched afterwards (commit them) |

Upgrade with `composer update pika-02-03/mimimalcms`. To change the framework, fix it in this repository and tag a release (edits made to the copies are reverted by the next sync).
`composer mimimalcms-check` fails when the copies differ from the installed package.

If production does not run composer, you can commit the copies instead ([OpenChat Graph](https://github.com/Open-Chat-Graph/Open-Chat-Graph) does this).
Drop the framework lines from `.gitignore`, put the package in `require-dev`, and `post-update-cmd` alone is enough (production never reads the framework from `vendor/`).

## Pages

Convention routing: `GET /foo/bar` → `App\Controllers\Pages\FooPageController::bar()`; other methods → `App\Controllers\Api\FooApiController::bar()`.
Missing segments default to `Index` / `index`. Only public, non-static methods not starting with `__` can be reached from a URL.

```php
namespace App\Controllers\Pages;

class FooPageController
{
    public function index(SomeService $service)   // arguments are injected by type
    {
        return view('foo_content', ['message' => $service->message()]);
    }
}
```

```php
<!-- app/Views/foo_content.php: strings passed to the view are HTML-escaped (keys starting with _ are left raw) -->
<h1><?php echo $message ?></h1>
```

## Routes (`app/Config/routing.php`)

```php
use Shadow\Kernel\Route;

Route::path('user/{id}@get@post', [UserPageController::class, 'show', 'get'], [UserApiController::class, 'update', 'post'])
    ->matchNum('id', min: 1)                       // validate path/query/body values and pass them as arguments
    ->matchStr('name', 'post', maxLen: 50)
    ->matchFile('icon', ['image/png', 'image/jpeg'], emptyAble: true)
    ->middleware([VerifyCsrfToken::class])
    ->match(fn (int $id) => $id !== 0)             // false → 404 for GET, 400 otherwise; an array is merged into the input
    ->fails(redirect('user'));                     // where to go on validation errors (default: 404 for GET, 400 otherwise)

Route::run();
```

Order: parse URI → routing → input validation → middleware → `match` → controller → response ([`shadow/Kernel/Kernel.php`](shadow/Kernel/Kernel.php)).

Middleware `handle()`: `null` continues, `false` returns the `fails()` response, an array is merged into the input, a `View` / `Response` ends the request.

## DI container

- Constructor and controller arguments are resolved from their types
- Bind interfaces in `$constructorInjectionMap` of `shared/MimimalCmsConfig.php`
- At runtime: `app()->bind(Interface::class, Impl::class)` / `app()->singleton(...)`; resolve with `app(Foo::class)`

## Database

`Shadow\DB`, a thin PDO wrapper configured by `$dbHost` etc. in `MimimalCmsConfig`.

```php
DB::fetch('SELECT * FROM user WHERE id = :id', ['id' => $id]);
DB::fetchAll(...);  DB::fetchColumn(...);  DB::execute(...);
DB::executeAndGetLastInsertId(...);  DB::transaction(fn () => ...);
```

## Security

- **XSS**: strings passed to views are escaped; use `h()` elsewhere
- **CSRF**: a GET issues the token (session + `CSRF-Token` cookie); POST etc. must send it as the `_csrf` field or the
  `X-CSRF-Token` header (`verifyCsrfToken()`). The cookie alone is not accepted.
  Forms use `<?php csrfField() ?>`; scripts read the `CSRF-Token` cookie and send it in `X-CSRF-Token`
- **Client IP**: `getIP()` returns `REMOTE_ADDR` by default. Behind a proxy, set `MimimalCmsConfig::$trustedIpHeaders` (e.g. `['HTTP_CF_CONNECTING_IP']`)
- **Exception log**: `$_SERVER` is logged with cookies, auth headers and password/key-like values replaced by `[masked]`
- **Encryption**: `Shadow\StringCryptor` (HKDF + AES-256-GCM)

## Exceptions and error pages

- `MimimalCmsConfig::$httpErrors` maps exception classes to HTTP statuses; anything else is a 500
- Error pages: `app/Views/errors/{code}.php`, falling back to `errors/error.php`
- `App\Exceptions\Handlers\ApplicationExceptionHandler` handles the exceptions listed in its `$exceptionMap`.
  Define `public static function reportUnhandledException(\Throwable $e)` to be called on 500s (notifications, etc.)

## Helper functions ([`shared/MimimalCMS_HelperFunctions.php`](shared/MimimalCMS_HelperFunctions.php))

`view` `response` `redirect` `url` `fileUrl` `session` `cookie` `old` `app` `h` `getIP` `getUA` `csrfField` `getCsrfToken` `verifyCsrfToken`
`safeFileRewrite` `saveSerializedFile` `getUnserializedFile` `mkdirIfNotExists` `deleteDirectory` and more.

## Developing this repository

This repository is also a skeleton with a sample app (`app/`, `public/`).

```bash
composer install
vendor/bin/phpunit                                # framework tests (no database needed)
php -S 127.0.0.1:8000 -t public public/index.php  # sample app
```

## License

MIT ([LICENSE.md](LICENSE.md))
