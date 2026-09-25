<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Services\Adapters;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeeplAdapter extends AbstractTranslatorAdapter
{
    public function isEnabled(): bool
    {
        return (bool) config('l18n-translator.deepl.enabled');
    }

    public function supportsUsage(): bool
    {
        return true;
    }

    /**
     * @return array{character_count: int, character_limit: int}
     * @throws RuntimeException when DeepL is not configured or the API call fails.
     */
    public function usage(): array
    {
        $cfg      = $this->cfg();
        $endpoint = preg_replace('#/translate$#', '/usage', $cfg['endpoint']);

        $resp = Http::withHeaders([
            'Authorization' => 'DeepL-Auth-Key ' . $cfg['auth_key'],
            'Accept'        => 'application/json',
        ])->timeout(10)->get($endpoint);

        if (!$resp->successful()) {
            throw new RuntimeException('DeepL usage request failed with status ' . $resp->status());
        }

        return [
            'character_count' => (int) $resp->json('character_count'),
            'character_limit' => (int) $resp->json('character_limit'),
        ];
    }

    /**
     * @throws RuntimeException when DeepL is not configured or the API call fails.
     */
    public function translate(string $text, string $targetLang, ?string $sourceLang = null): string
    {
        $cfg        = $this->cfg();
        $targetLang = strtoupper($targetLang);
        $sourceLang = strtoupper($sourceLang ?? config('l18n-translator.main_language', 'en'));

        if (explode('-', $sourceLang)[0] === explode('-', $targetLang)[0]) {
            return $text;
        }

        [$encoded, $placeholders] = $this->encodePlaceholders($text);

        // DeepL only supports EN-GB and EN-US as English target variants.
        if (str_starts_with($targetLang, 'EN-') && !in_array($targetLang, ['EN-GB', 'EN-US'], true)) {
            $targetLang = 'EN-GB';
        }

        $payload = [
            'text'         => [$encoded],
            'target_lang'  => $targetLang,
            'source_lang'  => $sourceLang,
            'tag_handling' => 'xml',
        ];
        if (!empty($cfg['formality'])) {
            $payload['formality'] = $cfg['formality'];
        }
        if (!empty($cfg['context'])) {
            $payload['context'] = $cfg['context'];
        }

        $resp = Http::withHeaders([
            'Authorization' => 'DeepL-Auth-Key ' . $cfg['auth_key'],
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])->timeout(20)->post($cfg['endpoint'], $payload);

        if (!$resp->successful()) {
            throw new RuntimeException('DeepL translate request failed with status ' . $resp->status());
        }

        $translated = $resp->json('translations.0.text');
        if (!$translated) {
            throw new RuntimeException('Missing translation in DeepL response');
        }

        return $this->decodePlaceholders($translated, $placeholders);
    }

    /** @throws RuntimeException when DeepL is not configured. */
    private function cfg(): array
    {
        if (!config('l18n-translator.deepl.enabled')) {
            throw new RuntimeException('DeepL is not configured. Set DEEPL_AUTH_KEY in your .env.');
        }

        return config('l18n-translator.deepl');
    }
}
