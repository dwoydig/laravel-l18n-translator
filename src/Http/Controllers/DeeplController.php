<?php

namespace Dwoydig\L18nTranslator\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;

class DeeplController extends Controller
{
    public function translate(Request $request): JsonResponse
    {
        if (!config('l18n-translator.deepl.enabled')) {
            return response()->json(['error' => 'DeepL is not configured. Set DEEPL_AUTH_KEY in your .env.'], 503);
        }

        $data = $request->validate([
            'text'        => ['required', 'string', 'max:20000'],
            'target_lang' => ['required', 'string', 'max:10'],
        ]);

        // Release the session lock so concurrent DeepL requests are not serialised by PHP's session file locking.
        session()->save();

        $targetLang  = strtoupper($data['target_lang']);
        $sourceLang  = strtoupper(config('l18n-translator.main_language', 'en'));
        $samePrimary = explode('-', $sourceLang)[0] === explode('-', $targetLang)[0];
        if ($samePrimary) {
            return response()->json(['text' => $data['text']]);
        }

        [$encoded, $placeholders] = $this->encodePlaceholders($data['text']);

        $cfg = config('l18n-translator.deepl');
        // DeepL currently only supports EN-GB and EN-US, so we'll enforce that.
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
            return response()->json([
                'error'  => 'DeepL request failed',
                'status' => $resp->status(),
            ], 502);
        }

        $translated = $resp->json('translations.0.text');
        if (!$translated) {
            return response()->json(['error' => 'Missing translation in DeepL response'], 502);
        }

        return response()->json(['text' => $this->decodePlaceholders($translated, $placeholders)]);
    }

    /**
     * Replace Laravel :placeholder tokens with XML-safe tokens DeepL won't touch.
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
     */
    private function decodePlaceholders(string $text, array $placeholders): string
    {
        foreach ($placeholders as $token => $original) {
            $core = preg_replace('/^__|__$/', '', $token);
            $pattern = '/\{+\s*(?:__)?' . preg_quote($core, '/') . '(?:__)?\s*\}+/u';
            $text = preg_replace($pattern, $original, $text);
        }
        return $text;
    }
}
