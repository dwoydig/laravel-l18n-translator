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
     * Absolute path to the Laravel lang directory. The following files are managed:
     *   {locale}.json                              — JSON translations (origin "app")
     *   {locale}/{group}.php                       — PHP group files (origin "app")
     *   vendor/{package}/{locale}/{group}.php      — vendor package overrides (origin "{package}")
     * Override via L18N_LANG_PATH in your .env if your language files don't live in
     * the default location (resources/lang).
     */
    'lang_path' => env('L18N_LANG_PATH', resource_path('lang')),

    /*
     * Override the layout the views extend.
     * null  = use the package's built-in standalone layout (Tailwind + Alpine CDN).
     * string = e.g. 'layouts.admin' — your own layout must yield 'content' and 'scripts'.
     */
    'layout' => null,

    /*
     * Active translation driver.
     * Supported: "deepl", "google", "aws"
     */
    'translator' => [
        'driver' => env('TRANSLATOR_DRIVER', 'deepl'),
    ],

    /*
     * DeepL — set DEEPL_AUTH_KEY in your .env to enable.
     * https://www.deepl.com/pro-api
     */
    'deepl' => [
        'enabled'     => (bool) env('DEEPL_AUTH_KEY'),
        'auth_key'    => env('DEEPL_AUTH_KEY'),
        'endpoint'    => env('DEEPL_ENDPOINT', 'https://api.deepl.com/v2/translate'),
        'formality'   => 'prefer_less',
        'context'     => '',
        'concurrency' => 5,
        // ISO code overrides for DeepL's non-standard language codes.
        'lang_map'    => [
            'no' => 'NB',    // DeepL uses NB (Bokmål), not NO
            'pt' => 'PT-PT', // DeepL distinguishes PT-PT / PT-BR; change to PT-BR if needed
        ],
    ],

    /*
     * Google Cloud Translation — set GOOGLE_TRANSLATE_API_KEY in your .env to enable.
     * https://cloud.google.com/translate/docs/reference/rest
     * Uses ISO 639-1 language codes (e.g. "de", "fr", "pt").
     */
    'google' => [
        'api_key'     => env('GOOGLE_TRANSLATE_API_KEY'),
        'concurrency' => 5,
        'lang_map'    => [], // Add overrides here if needed
    ],

    /*
     * AWS Translate — set AWS credentials in your .env to enable.
     * Requires: composer require aws/aws-sdk-php
     * https://docs.aws.amazon.com/translate/latest/dg/what-is.html
     * Uses ISO 639-1 language codes (e.g. "de", "fr", "pt").
     */
    'aws' => [
        'key'         => env('AWS_ACCESS_KEY_ID'),
        'secret'      => env('AWS_SECRET_ACCESS_KEY'),
        'region'      => env('AWS_DEFAULT_REGION', 'eu-west-1'),
        'concurrency' => 5,
        'lang_map'    => [], // Add overrides here if needed
    ],

    /*
     * Kept for backward compatibility with published configs.
     * New installs: use deepl.lang_map instead.
     */
    'deepl_lang_map' => [
        'no' => 'NB',
        'pt' => 'PT-PT',
    ],
];
