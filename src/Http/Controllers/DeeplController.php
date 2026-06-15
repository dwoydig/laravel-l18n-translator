<?php

namespace Dwoydig\L18nTranslator\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;

class DeeplController extends Controller
{
    public function translate(Request $request)
    {
        if (!config('l18n-translator.deepl.enabled')) {
            return response()->json(['error' => 'DeepL is not configured. Set DEEPL_AUTH_KEY in your .env.'], 503);
        }

        $data = $request->validate([
            'text'        => ['required', 'string', 'max:20000'],
            'target_lang' => ['required', 'string', 'max:10'],
        ]);

        [$encoded, $placeholders] = $this->encodePlaceholders($data['text']);

        $cfg = config('l18n-translator.deepl');
        $payload = [
            'text'         => [$encoded],
            'target_lang'  => $data['target_lang'],
            'source_lang'  => 'EN',
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
                'body'   => $resp->body(),
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
        $i = 0;
        $encoded = preg_replace_callback(
            '/(?<!\w):([a-zA-Z_][a-zA-Z0-9_]*)/',
            function ($m) use (&$placeholders, &$i) {
                $token = "__PH_{$i}__";
                $placeholders[$token] = ':' . $m[1];
                $i++;
                return "{{{$token}}}";
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
