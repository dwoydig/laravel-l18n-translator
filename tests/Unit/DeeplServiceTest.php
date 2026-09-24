<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Tests\Unit;

use Dwoydig\L18nTranslator\Services\DeeplService;
use Dwoydig\L18nTranslator\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class DeeplServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableDeepl();
    }

    public function test_translate_preserves_laravel_placeholders(): void
    {
        Http::fake(function (Request $request) {
            // Echo the encoded text back in "German" so we can check the placeholder round trip.
            return Http::response(['translations' => [['text' => 'Hallo ' . substr($request['text'][0], 6)]]]);
        });

        $result = $this->app->make(DeeplService::class)->translate('Hello :name, you have :count items', 'de');

        $this->assertSame('Hallo :name, you have :count items', $result);
        Http::assertSent(fn(Request $request) => $request['target_lang'] === 'DE'
            && $request['source_lang'] === 'EN'
            && !str_contains($request['text'][0], ':name')
            && $request->hasHeader('Authorization', 'DeepL-Auth-Key test-key'));
    }

    public function test_translate_skips_the_api_for_the_same_primary_language(): void
    {
        Http::fake();

        $this->assertSame('Colour', $this->app->make(DeeplService::class)->translate('Colour', 'en-GB'));
        Http::assertNothingSent();
    }

    public function test_translate_throws_on_api_errors(): void
    {
        Http::fake(['*' => Http::response('Quota exceeded', 456)]);

        $this->expectException(RuntimeException::class);

        $this->app->make(DeeplService::class)->translate('Hello', 'de');
    }

    public function test_usage_calls_the_usage_endpoint(): void
    {
        Http::fake(['https://api.deepl.test/v2/usage' => Http::response(['character_count' => 42, 'character_limit' => 500000])]);

        $this->assertSame(
            ['character_count' => 42, 'character_limit' => 500000],
            $this->app->make(DeeplService::class)->usage(),
        );
    }

    public function test_throws_when_deepl_is_disabled(): void
    {
        config()->set('l18n-translator.deepl.enabled', false);

        $this->expectException(RuntimeException::class);

        $this->app->make(DeeplService::class)->translate('Hello', 'de');
    }
}
