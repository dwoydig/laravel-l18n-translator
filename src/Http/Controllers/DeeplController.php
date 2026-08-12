<?php

namespace Dwoydig\L18nTranslator\Http\Controllers;

use Dwoydig\L18nTranslator\Services\DeeplService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;

class DeeplController extends Controller
{
    public function __construct(private DeeplService $deepl) {}

    /**
     * Returns the current DeepL character usage and monthly limit.
     * Returns 503 if DeepL is not configured, 502 on API errors.
     */
    public function usage(): JsonResponse
    {
        if (!config('l18n-translator.deepl.enabled')) {
            return response()->json(['error' => 'DeepL is not configured. Set DEEPL_AUTH_KEY in your .env.'], 503);
        }

        try {
            return response()->json($this->deepl->usage());
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    /**
     * Translates a single string via the DeepL API.
     * Laravel :placeholder tokens are encoded before sending and restored afterwards.
     * Skips the API call when source and target language share the same primary subtag.
     *
     * @param  Request  $request  Must contain `text` (string, max 20 000 chars) and `target_lang` (BCP-47).
     */
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

        try {
            $translated = $this->deepl->translate($data['text'], $data['target_lang']);
            return response()->json(['text' => $translated]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }
}
