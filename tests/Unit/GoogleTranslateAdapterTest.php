<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Tests\Unit;

use Dwoydig\L18nTranslator\Services\Adapters\GoogleTranslateAdapter;
use Dwoydig\L18nTranslator\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GoogleTranslateAdapterTest extends TestCase
{
    private GoogleTranslateAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableGoogle();
        $this->adapter = $this->app->make(GoogleTranslateAdapter::class);
    }

    public function test_is_enabled_with_api_key(): void
    {
        $this->assertTrue($this->adapter->isEnabled());
    }

    public function test_is_not_enabled_without_api_key(): void
    {
        config()->set('l18n-translator.google.api_key', null);

        $this->assertFalse($this->adapter->isEnabled());
    }

    public function test_does_not_support_usage(): void
    {
        $this->assertFalse($this->adapter->supportsUsage());
        $this->assertSame([], $this->adapter->usage());
    }

    public function test_translate_calls_google_api(): void
    {
        Http::fake(['https://translation.googleapis.com/*' => Http::response([
            'data' => ['translations' => [['translatedText' => 'Hallo Welt']]],
        ])]);

        $result = $this->adapter->translate('Hello World', 'de');

        $this->assertSame('Hallo Welt', $result);
        Http::assertSent(fn (Request $request) =>
            str_contains($request->url(), 'translation.googleapis.com') &&
            $request['q'] === 'Hello World' &&
            $request['source'] === 'en' &&
            $request['target'] === 'de' &&
            $request['key'] === 'test-google-key'
        );
    }

    public function test_translate_preserves_laravel_placeholders(): void
    {
        Http::fake(function (Request $request) {
            // Simulate Google returning the encoded text in "German".
            $body = $request['q'];
            $translated = 'Hallo ' . substr($body, strpos($body, '{') ?: strlen($body));
            return Http::response(['data' => ['translations' => [['translatedText' => $translated]]]]);
        });

        $result = $this->adapter->translate('Hello :name, you have :count items', 'de');

        $this->assertSame('Hallo :name, you have :count items', $result);
        Http::assertSent(fn (Request $request) =>
            !str_contains($request['q'], ':name') &&
            !str_contains($request['q'], ':count')
        );
    }

    public function test_translate_skips_api_for_same_primary_language(): void
    {
        Http::fake();

        $this->assertSame('Hello', $this->adapter->translate('Hello', 'en-GB'));
        Http::assertNothingSent();
    }

    public function test_translate_throws_on_api_error(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Invalid key']], 400)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Google Translate/');

        $this->adapter->translate('Hello', 'de');
    }

    public function test_translate_throws_when_not_configured(): void
    {
        config()->set('l18n-translator.google.api_key', null);
        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/GOOGLE_TRANSLATE_API_KEY/');

        $this->adapter->translate('Hello', 'de');
    }

    public function test_translate_uses_iso_primary_subtag_for_source_and_target(): void
    {
        Http::fake(['*' => Http::response(['data' => ['translations' => [['translatedText' => 'Bonjour']]]])]);

        $this->adapter->translate('Hello', 'fr-CH');

        Http::assertSent(fn (Request $request) =>
            $request['source'] === 'en' &&
            $request['target'] === 'fr'
        );
    }
}
