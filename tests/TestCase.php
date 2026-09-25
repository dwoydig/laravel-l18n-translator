<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Tests;

use Dwoydig\L18nTranslator\Contracts\TranslatorContract;
use Dwoydig\L18nTranslator\Services\Adapters\AwsTranslateAdapter;
use Dwoydig\L18nTranslator\Services\Adapters\GoogleTranslateAdapter;
use Dwoydig\L18nTranslator\TranslationServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * Boots the package in a minimal Laravel app with a fresh copy of tests/Fixtures/lang per test.
 *
 * Fixtures: en + de JSON, en + de `auth.php` groups, and `vendor/cashier/{en,de,fr}/messages.php`.
 */
abstract class TestCase extends BaseTestCase
{
    /** Private per-test directory; the lang directory lives inside it so escapes via `../` stay contained. */
    protected string $sandboxPath;

    protected string $langPath;

    protected function setUp(): void
    {
        $this->sandboxPath = sys_get_temp_dir() . '/l18n-translator-tests-' . bin2hex(random_bytes(6));
        $this->langPath = $this->sandboxPath . '/lang';
        (new Filesystem())->copyDirectory(__DIR__ . '/Fixtures/lang', $this->langPath);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        (new Filesystem())->deleteDirectory($this->sandboxPath);
    }

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [TranslationServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('l18n-translator.lang_path', $this->langPath);
        $app['config']->set('l18n-translator.middleware', ['web']);
        $app['config']->set('l18n-translator.main_language', 'en');
    }

    /**
     * Enables the DeepL integration with a dummy key; combine with Http::fake().
     */
    protected function enableDeepl(): void
    {
        config()->set('l18n-translator.deepl.enabled', true);
        config()->set('l18n-translator.deepl.auth_key', 'test-key');
        config()->set('l18n-translator.deepl.endpoint', 'https://api.deepl.test/v2/translate');
        config()->set('l18n-translator.translator.enabled', true);
    }

    /**
     * Enables the Google Translate integration with a dummy key; combine with Http::fake().
     * Also re-binds TranslatorContract to GoogleTranslateAdapter.
     */
    protected function enableGoogle(): void
    {
        config()->set('l18n-translator.google.api_key', 'test-google-key');
        config()->set('l18n-translator.translator.enabled', true);
        $this->app->singleton(TranslatorContract::class, fn ($app) => $app->make(GoogleTranslateAdapter::class));
    }

    /**
     * Enables the AWS Translate integration with dummy credentials.
     * Also re-binds TranslatorContract to AwsTranslateAdapter.
     */
    protected function enableAws(): void
    {
        config()->set('l18n-translator.aws.key', 'test-aws-key');
        config()->set('l18n-translator.aws.secret', 'test-aws-secret');
        config()->set('l18n-translator.aws.region', 'eu-west-1');
        config()->set('l18n-translator.translator.enabled', true);
        $this->app->singleton(TranslatorContract::class, fn ($app) => $app->make(AwsTranslateAdapter::class));
    }

    protected function langFile(string $relativePath): string
    {
        return $this->langPath . '/' . $relativePath;
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function readJson(string $relativePath): array
    {
        return json_decode((new Filesystem())->get($this->langFile($relativePath)), true);
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function readPhp(string $relativePath): array
    {
        return require $this->langFile($relativePath);
    }

    protected function fixture(string $relativePath): string
    {
        return (new Filesystem())->get(__DIR__ . '/Fixtures/lang/' . $relativePath);
    }
}
