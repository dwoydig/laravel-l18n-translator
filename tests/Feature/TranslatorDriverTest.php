<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Tests\Feature;

use Dwoydig\L18nTranslator\Contracts\TranslatorContract;
use Dwoydig\L18nTranslator\Services\Adapters\AwsTranslateAdapter;
use Dwoydig\L18nTranslator\Services\Adapters\DeeplAdapter;
use Dwoydig\L18nTranslator\Services\Adapters\GoogleTranslateAdapter;
use Dwoydig\L18nTranslator\Tests\TestCase;
use Illuminate\Support\Facades\Http;

final class TranslatorDriverTest extends TestCase
{
    private const PREFIX = '/admin/translations';

    // ── ServiceProvider bindings ──────────────────────────────────────────────

    public function test_default_driver_binds_deepl_adapter(): void
    {
        $this->assertInstanceOf(DeeplAdapter::class, $this->app->make(TranslatorContract::class));
    }

    public function test_google_driver_binds_google_adapter(): void
    {
        $this->enableGoogle();

        $this->assertInstanceOf(GoogleTranslateAdapter::class, $this->app->make(TranslatorContract::class));
    }

    public function test_aws_driver_binds_aws_adapter(): void
    {
        $this->enableAws();

        $this->assertInstanceOf(AwsTranslateAdapter::class, $this->app->make(TranslatorContract::class));
    }

    // ── Controller: usage endpoint ────────────────────────────────────────────

    public function test_usage_returns_503_when_driver_does_not_support_it(): void
    {
        $this->enableGoogle();
        Http::fake();

        $this->getJson(self::PREFIX . '/deepl/usage')->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_usage_returns_503_when_aws_driver_active(): void
    {
        $this->enableAws();
        Http::fake();

        $this->getJson(self::PREFIX . '/deepl/usage')->assertStatus(503);
        Http::assertNothingSent();
    }

    // ── Controller: translate endpoint ────────────────────────────────────────

    public function test_translate_returns_503_when_google_not_configured(): void
    {
        $this->enableGoogle();
        config()->set('l18n-translator.google.api_key', null);
        Http::fake();

        $this->postJson(self::PREFIX . '/deepl', ['text' => 'Hello', 'target_lang' => 'de'])
            ->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_translate_returns_503_when_aws_not_configured(): void
    {
        $this->enableAws();
        config()->set('l18n-translator.aws.key', null);
        Http::fake();

        $this->postJson(self::PREFIX . '/deepl', ['text' => 'Hello', 'target_lang' => 'de'])
            ->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_translate_with_google_driver_returns_translated_text(): void
    {
        $this->enableGoogle();
        Http::fake(['https://translation.googleapis.com/*' => Http::response([
            'data' => ['translations' => [['translatedText' => 'Hallo']]],
        ])]);

        $this->postJson(self::PREFIX . '/deepl', ['text' => 'Hello', 'target_lang' => 'de'])
            ->assertOk()
            ->assertExactJson(['text' => 'Hallo']);
    }
}
