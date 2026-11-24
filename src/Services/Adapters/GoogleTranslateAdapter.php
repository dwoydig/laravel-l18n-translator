<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Services\Adapters;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleTranslateAdapter extends AbstractTranslatorAdapter
{
    public function isEnabled(): bool
    {
        return (bool) config('l18n-translator.google.api_key');
    }

    /**
     * @throws RuntimeException when Google Translate is not configured or the API call fails.
     */
    public function translate(string $text, string $targetLang, ?string $sourceLang = null): string
    {
        $cfg = $this->cfg();

        // Google uses ISO 639-1 primary subtags (e.g. "en", "de", "pt").
        $sourcePrimary = strtolower(explode('-', $sourceLang ?? config('l18n-translator.main_language', 'en'))[0]);
        $targetPrimary = strtolower(explode('-', $targetLang)[0]);

        if ($sourcePrimary === $targetPrimary) {
            return $text;
        }

        [$encoded, $placeholders] = $this->encodePlaceholders($text);

        $resp = Http::timeout(20)->post(
            'https://translation.googleapis.com/language/translate/v2',
            [
                'q'      => $encoded,
                'source' => $sourcePrimary,
                'target' => $targetPrimary,
                'format' => 'text',
                'key'    => $cfg['api_key'],
            ]
        );

        if (!$resp->successful()) {
            $error = $resp->json('error.message') ?? $resp->body();
            throw new RuntimeException('Google Translate request failed (' . $resp->status() . '): ' . $error);
        }

        $translated = $resp->json('data.translations.0.translatedText');
        if ($translated === null) {
            throw new RuntimeException('Missing translation in Google Translate response');
        }

        return $this->decodePlaceholders($translated, $placeholders);
    }

    /** @throws RuntimeException when Google Translate is not configured. */
    private function cfg(): array
    {
        if (!config('l18n-translator.google.api_key')) {
            throw new RuntimeException('Google Translate is not configured. Set GOOGLE_TRANSLATE_API_KEY in your .env.');
        }

        return config('l18n-translator.google');
    }
}
