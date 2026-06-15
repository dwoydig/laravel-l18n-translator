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
        'enabled'   => (bool) env('DEEPL_AUTH_KEY'),
        'auth_key'  => env('DEEPL_AUTH_KEY'),
        'endpoint'  => env('DEEPL_ENDPOINT', 'https://api.deepl.com/v2/translate'),
        'formality' => 'prefer_less',
        'context'   => '',
    ],

    /*
     * Languages shown in the UI. Add or remove as needed.
     * Format: 'iso-code' => 'Display name'
     */
    'available_languages' => [
        'en' => '🇬🇧 English',
        'de' => '🇩🇪 Deutsch',
        'fr' => '🇫🇷 Français',
        'it' => '🇮🇹 Italiano',
        'es' => '🇪🇸 Español',
        'pt' => '🇵🇹 Português',
        'nl' => '🇳🇱 Nederlands',
        'sv' => '🇸🇪 Svenska',
        'no' => '🇳🇴 Norsk',
        'da' => '🇩🇰 Dansk',
        'fi' => '🇫🇮 Suomi',
        'pl' => '🇵🇱 Polski',
        'ru' => '🇷🇺 Русский',
        'ja' => '🇯🇵 日本語',
        'zh' => '🇨🇳 中文',
    ],

    /*
     * Mapping from ISO 639-1 codes to DeepL target_lang codes.
     * Add entries here when you add languages above.
     */
    'deepl_lang_map' => [
        'de' => 'DE',
        'fr' => 'FR',
        'it' => 'IT',
        'es' => 'ES',
        'pt' => 'PT-PT',
        'nl' => 'NL',
        'sv' => 'SV',
        'no' => 'NB',
        'da' => 'DA',
        'fi' => 'FI',
        'pl' => 'PL',
        'ru' => 'RU',
        'ja' => 'JA',
        'zh' => 'ZH',
    ],

];
