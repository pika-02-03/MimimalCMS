# MimimalCMS

小さな PHP の MVC フレームワーク。ルーティング・入力バリデーション・DI コンテナ・ビュー・ヘルパー関数だけを持ち、SQL は生で書く。

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE.md) PHP 8.1+ ・ [English](README_EN.md)

使用例: [オプチャグラフ](https://openchat-review.me)（[ソース](https://github.com/Open-Chat-Graph/Open-Chat-Graph)）

## 導入（Composer）

フレームワークは `vendor/` ではなく、プロジェクト直下へ同じ配置でコピーして使います（Drupal の `core/` と同じ考え方）。
コピーは composer が作るので、普通は `.gitignore` に入れておけば十分です。

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
# .gitignore（composer install で作られるフレームワークのコピー）
/vendor/
/shadow/
/shared/Exceptions/
/shared/MimimalCMS_Enums.php
/shared/MimimalCMS_ExceptionHandler.php
/shared/MimimalCMS_HelperFunctions.php
```

`composer install`（本番は `composer install --no-dev`）のたびにフレームワークのファイルがコピーされます（変わったファイルだけ書き換える）。

| パス | 持ち主 | コピー時の扱い |
|---|---|---|
| `shadow/` | フレームワーク | 丸ごと同期（パッケージから消えたファイルは削除） |
| `shared/MimimalCMS_{Enums,ExceptionHandler,HelperFunctions}.php`・`shared/Exceptions/` | フレームワーク | 上書き |
| `shared/MimimalCmsConfig.php`・`shared/MimimalCMS_Settings.php` | アプリ | 無いときだけ雛形を置く（以後は触らない。コミットする） |

更新は `composer update pika-02-03/mimimalcms`。フレームワークを直すときはこのリポジトリで直してタグを打つ（コピー先を直接編集しても次の同期で戻る）。
`composer mimimalcms-check` はコピー先がパッケージと一致しているかを確認する（違えば失敗する）。

本番で composer を動かさない場合は、コピーもコミットして使える（[オプチャグラフ](https://github.com/Open-Chat-Graph/Open-Chat-Graph)はこの形）。
`.gitignore` からフレームワークの行を外し、パッケージは `require-dev`、スクリプトは `post-update-cmd` だけでよい（本番は `vendor/` のフレームワークを読まない）。

## ページを作る

規約ルーティング: `GET /foo/bar` → `App\Controllers\Pages\FooPageController::bar()`、GET 以外 → `App\Controllers\Api\FooApiController::bar()`。
パスを省くと `Index` / `index`。URL から呼べるのは public・非 static・`__` で始まらないメソッドだけ。

```php
namespace App\Controllers\Pages;

class FooPageController
{
    public function index(SomeService $service)   // 引数は DI で注入される
    {
        return view('foo_content', ['message' => $service->message()]);
    }
}
```

```php
<!-- app/Views/foo_content.php：渡した文字列は自動で HTML エスケープされる（キーが _ 始まりの値はそのまま） -->
<h1><?php echo $message ?></h1>
```

## ルート定義（`app/Config/routing.php`）

```php
use Shadow\Kernel\Route;

Route::path('user/{id}@get@post', [UserPageController::class, 'show', 'get'], [UserApiController::class, 'update', 'post'])
    ->matchNum('id', min: 1)                       // パス・クエリ・ボディの値を検証して引数に渡す
    ->matchStr('name', 'post', maxLen: 50)
    ->matchFile('icon', ['image/png', 'image/jpeg'], emptyAble: true)
    ->middleware([VerifyCsrfToken::class])
    ->match(fn (int $id) => $id !== 0)             // false → GET は 404・他は 400。配列を返すと入力にマージ
    ->fails(redirect('user'));                     // 検証エラー時の遷移（無ければ GET は 404・他は 400）

Route::run();
```

処理順: URI 解析 → ルーティング → 入力検証 → ミドルウェア → `match` → コントローラー → レスポンス（[`shadow/Kernel/Kernel.php`](shadow/Kernel/Kernel.php)）。

ミドルウェアの `handle()` は `null` で続行、`false` で `fails()` のレスポンス、配列で入力にマージ、`View` / `Response` を返すとそこで終了。

## DI コンテナ

- コンストラクタとコントローラーの引数は型から自動で注入される
- インターフェースの実装は `shared/MimimalCmsConfig.php` の `$constructorInjectionMap` で指定する
- 実行時の差し替えは `app()->bind(Interface::class, Impl::class)` / `app()->singleton(...)`、取得は `app(Foo::class)`

## データベース

`Shadow\DB`（PDO の薄いラッパー）。接続情報は `MimimalCmsConfig` の `$dbHost` などで設定する。

```php
DB::fetch('SELECT * FROM user WHERE id = :id', ['id' => $id]);
DB::fetchAll(...);  DB::fetchColumn(...);  DB::execute(...);
DB::executeAndGetLastInsertId(...);  DB::transaction(fn () => ...);
```

## セキュリティ

- **XSS**: ビューに渡した文字列は自動エスケープ。それ以外で出すときは `h()`
- **CSRF**: GET でトークンを発行（セッション + `CSRF-Token` クッキー）、POST などでは `_csrf` フィールドか
  `X-CSRF-Token` ヘッダのトークンを検証する（`verifyCsrfToken()`）。クッキーだけでは通らない。
  フォームは `<?php csrfField() ?>`、JS は `CSRF-Token` クッキーを読んで `X-CSRF-Token` ヘッダに入れる
- **クライアント IP**: `getIP()` は既定で `REMOTE_ADDR`。プロキシの後ろでは `MimimalCmsConfig::$trustedIpHeaders`（例: `['HTTP_CF_CONNECTING_IP']`）を設定する
- **例外ログ**: `$_SERVER` を記録するが、Cookie・認証ヘッダ・パスワードや鍵らしい名前の値は `[masked]` にする
- **暗号化**: `Shadow\StringCryptor`（HKDF + AES-256-GCM）

## 例外とエラーページ

- `MimimalCmsConfig::$httpErrors` で例外クラスと HTTP ステータスを対応づける。未登録の例外は 500
- エラーページは `app/Views/errors/{code}.php`、無ければ `errors/error.php`
- `App\Exceptions\Handlers\ApplicationExceptionHandler` を置くと、`$exceptionMap` に載せた例外を自前で処理できる。
  `public static function reportUnhandledException(\Throwable $e)` を定義すると 500 のときに呼ばれる（通知などに使う）

## ヘルパー関数（[`shared/MimimalCMS_HelperFunctions.php`](shared/MimimalCMS_HelperFunctions.php)）

`view` `response` `redirect` `url` `fileUrl` `session` `cookie` `old` `app` `h` `getIP` `getUA` `csrfField` `getCsrfToken` `verifyCsrfToken`
`safeFileRewrite` `saveSerializedFile` `getUnserializedFile` `mkdirIfNotExists` `deleteDirectory` ほか。

## このリポジトリで開発する

このリポジトリ自体がサンプルアプリ（`app/`・`public/`）付きの雛形です。

```bash
composer install
vendor/bin/phpunit                                # フレームワークのテスト（DB 不要）
php -S 127.0.0.1:8000 -t public public/index.php  # サンプルアプリ
```

## ライセンス

MIT（[LICENSE.md](LICENSE.md)）
