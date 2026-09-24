<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Tests\Feature;

use Dwoydig\L18nTranslator\Tests\TestCase;
use Illuminate\Support\Facades\Http;

final class DeeplControllerTest extends TestCase
{
    private const PREFIX = '/admin/translations';

    public function test_endpoints_return_503_when_disabled(): void
    {
        Http::fake();

        $this->postJson(self::PREFIX . '/deepl', ['text' => 'Hello', 'target_lang' => 'DE'])->assertStatus(503);
        $this->getJson(self::PREFIX . '/deepl/usage')->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_translate_returns_the_translated_text(): void
    {
        $this->enableDeepl();
        Http::fake(['*' => Http::response(['translations' => [['text' => 'Hallo']]])]);

        $this->postJson(self::PREFIX . '/deepl', ['text' => 'Hello', 'target_lang' => 'DE'])
            ->assertOk()
            ->assertExactJson(['text' => 'Hallo']);
    }

    public function test_translate_validates_input(): void
    {
        $this->enableDeepl();
        Http::fake();

        $this->postJson(self::PREFIX . '/deepl', ['target_lang' => 'DE'])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_api_errors_become_502(): void
    {
        $this->enableDeepl();
        Http::fake(['*' => Http::response('Forbidden', 403)]);

        $this->postJson(self::PREFIX . '/deepl', ['text' => 'Hello', 'target_lang' => 'DE'])->assertStatus(502);
        $this->getJson(self::PREFIX . '/deepl/usage')->assertStatus(502);
    }

    public function test_usage_returns_count_and_limit(): void
    {
        $this->enableDeepl();
        Http::fake(['*' => Http::response(['character_count' => 1200, 'character_limit' => 500000])]);

        $this->getJson(self::PREFIX . '/deepl/usage')
            ->assertOk()
            ->assertExactJson(['character_count' => 1200, 'character_limit' => 500000]);
    }
}
