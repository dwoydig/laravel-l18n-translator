<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Tests\Browser;

use Illuminate\Support\ServiceProvider;

/**
 * Configures the package for the Playwright browser tests served via `vendor/bin/testbench serve`:
 * no auth middleware and a lang directory that the tests reset from tests/Fixtures/lang.
 */
final class BrowserTestServiceProvider extends ServiceProvider
{
    public const LANG_PATH = __DIR__ . '/.lang';

    public function register(): void
    {
        config()->set('l18n-translator.lang_path', self::LANG_PATH);
        config()->set('l18n-translator.middleware', ['web']);
        config()->set('l18n-translator.main_language', 'en');
    }
}
