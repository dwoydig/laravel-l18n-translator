<?php

return [

    /*
     * URL prefix for all translation manager routes.
     */
    'route_prefix' => 'admin/translations',

    /*
     * Middleware applied to all routes.
     */
    'middleware' => ['web', 'auth'],

    /*
     * The source language all other languages are translated from.
     */
    'main_language' => 'en',

    /*
     * Override the layout the views extend.
     * null  = use the package's built-in standalone layout (Tailwind + Alpine CDN).
     * string = e.g. 'layouts.admin' — your own layout must yield 'content' and 'scripts'.
     */
    'layout' => null,

    /*
     * DeepL proxy — set DEEPL_AUTH_KEY in your .env to enable in-browser auto-translation.
     */
    'deepl' => [
        'enabled'     => (bool) env('DEEPL_AUTH_KEY'),
        'auth_key'    => env('DEEPL_AUTH_KEY'),
        'endpoint'    => env('DEEPL_ENDPOINT', 'https://api.deepl.com/v2/translate'),
        'formality'   => 'prefer_less',
        'context'     => '',
        'concurrency' => 5,
    ],

    /*
     * Overrides for ISO codes that differ from DeepL's target_lang codes.
     * Most languages work automatically (e.g. "de" → "DE").
     * Only add entries here where DeepL deviates from the ISO code.
     */
    'deepl_lang_map' => [
        'no' => 'NB',      // DeepL uses NB (Bokmål), not NO
        'pt' => 'PT-PT',   // DeepL distinguishes PT-PT / PT-BR; change to PT-BR if needed
    ],

];
