<?php

namespace Dwoydig\L18nTranslator\Http\Controllers;

use Dwoydig\L18nTranslator\Contracts\TranslatorContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;

class DeeplController extends Controller
{
    public function __construct(private TranslatorContract $translator) {}

    /**
     * Returns usage/quota statistics for the active translator.
     * Returns 503 when no translator is configured or the adapter does not support usage reporting.
     * Returns 502 on API errors.
     */
    public function usage(): JsonResponse
    {
        if (!$this->translator->isEnabled()) {
            return response()->json(['error' => 'No translator is configured.'], 503);
        }

        if (!$this->translator->supportsUsage()) {
            return response()->json(['error' => 'Usage stats are not supported by the active translator driver.'], 503);
        }

        try {
            return response()->json($this->translator->usage());
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    /**
     * Translates a single string via the active translator adapter.
     * Laravel :placeholder tokens are preserved through translation.
     * Skips the API call when source and target share the same primary language subtag.
     *
     * @param  Request  $request  Must contain `text` (string, max 20 000 chars) and `target_lang` (BCP-47).
     */
    public function translate(Request $request): JsonResponse
    {
        if (!$this->translator->isEnabled()) {
            return response()->json(['error' => 'No translator is configured.'], 503);
        }

        $data = $request->validate([
            'text'        => ['required', 'string', 'max:20000'],
            'target_lang' => ['required', 'string', 'max:10'],
        ]);

        // Release the session lock so concurrent translation requests are not serialised by PHP's session file locking.
        session()->save();

        try {
            $translated = $this->translator->translate($data['text'], $data['target_lang']);
            return response()->json(['text' => $translated]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }
}
