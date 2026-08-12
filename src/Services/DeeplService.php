<?php

namespace Dwoydig\L18nTranslator\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeeplService
{
    /**
     * Returns the current DeepL character usage and monthly limit.
     *
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
     * Translate a string via the DeepL API.
     * Laravel :placeholder tokens are preserved through the translation.
     * Returns the original text unchanged when source and target share the same primary language tag.
     *
     * @param  string       $text        Text to translate.
     * @param  string       $targetLang  BCP-47 target locale (e.g. "DE", "FR").
     * @param  string|null  $sourceLang  BCP-47 source locale; defaults to l18n-translator.main_language.
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

    /**
     * @return array<string, mixed>
     * @throws RuntimeException when DeepL is not configured.
     */
    private function cfg(): array
    {
        if (!config('l18n-translator.deepl.enabled')) {
            throw new RuntimeException('DeepL is not configured. Set DEEPL_AUTH_KEY in your .env.');
        }

        return config('l18n-translator.deepl');
    }

    /**
     * Replace Laravel :placeholder tokens with XML-safe tokens DeepL won't touch.
     *
     * @param  string  $text
     * @return array{0: string, 1: array<string, string>}
     */
    private function encodePlaceholders(string $text): array
    {
        $placeholders = [];
        $encoded = preg_replace_callback(
            '/(?<!\w):([a-zA-Z_][a-zA-Z0-9_]*)/',
            function ($m) use (&$placeholders) {
                $token = '__PH_' . count($placeholders) . '__';
                $placeholders[$token] = ':' . $m[1];
                return "{{$token}}";
            },
            $text
        );
        return [$encoded, $placeholders];
    }

    /**
     * Restore original :placeholder tokens after translation.
     * Handles minor formatting variations DeepL may introduce around the token.
     *
     * @param  string                 $text
     * @param  array<string, string>  $placeholders
     */
    private function decodePlaceholders(string $text, array $placeholders): string
    {
        foreach ($placeholders as $token => $original) {
            $core    = preg_replace('/^__|__$/', '', $token);
            $pattern = '/\{+\s*(?:__)?' . preg_quote($core, '/') . '(?:__)?\s*\}+/u';
            $text    = preg_replace($pattern, $original, $text);
        }
        return $text;
    }
}
