# L18n Translator

A Laravel package for managing JSON language files through a web UI, with optional [DeepL](https://www.deepl.com) auto-translation.

**No frontend build step required.** The UI uses Tailwind CSS and Alpine.js loaded from CDN.

---

## Features

- List, create, and edit `resources/lang/*.json` files
- Side-by-side editor: source language vs. target language
- Search / filter keys and values
- "Missing only" filter to focus on untranslated strings
- One-click DeepL auto-translation for missing strings (optional)
- Add or edit a single key across all languages at once
- Export any language pair as a TMX file
- Laravel placeholder tokens (`:name`, `:count`, …) are preserved through translation
- Drop-in standalone layout (Tailwind + Alpine CDN) — or use your own

---

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

---

## Installation

```bash
composer require dwoydig/l18n-translator
```

Laravel auto-discovers the service provider. No manual registration needed.

### Publish the config (optional)

```bash
php artisan vendor:publish --tag=l18n-translator-config
```

This creates `config/l18n-translator.php` where you can change the route prefix, middleware, available languages, and DeepL settings.

---

## Configuration

```php
// config/l18n-translator.php

return [
    'route_prefix'  => 'admin/translations', // URL prefix for all routes
    'middleware'    => ['web', 'auth'],       // protect the UI
    'main_language' => 'en',                 // source language for translation

    // null  = use built-in layout (Tailwind + Alpine CDN)
    // string = your own layout, e.g. 'layouts.admin'
    //          must @yield('content') and @yield('scripts')
    'layout' => null,

    'deepl' => [
        'enabled'   => (bool) env('DEEPL_AUTH_KEY'),
        'auth_key'  => env('DEEPL_AUTH_KEY'),
        'endpoint'  => env('DEEPL_ENDPOINT', 'https://api.deepl.com/v2/translate'),
        'formality' => 'prefer_less',
        'context'   => '',
    ],

    'available_languages' => [
        'en' => 'English',
        'de' => 'Deutsch',
        'fr' => 'Français',
        // add your own ...
    ],

    'deepl_lang_map' => [
        'de' => 'DE',
        'fr' => 'FR',
        // ISO 639-1 -> DeepL target_lang
    ],
];
```

### DeepL auto-translation

Add your DeepL API key to `.env`:

```env
DEEPL_AUTH_KEY=your-deepl-auth-key
```

The "DeepL: fill missing" button in the editor will appear automatically. Without a key the UI works normally — only the auto-translate buttons are hidden.

---

## Usage

Navigate to `/admin/translations` (or whatever `route_prefix` is set to).

| Screen | What you can do |
|---|---|
| **Languages** | Overview of all configured languages; create missing ones |
| **Edit language** | Side-by-side editor with search, missing-only filter, DeepL batch-translate |
| **Edit string** | Edit one key across all languages; DeepL translates from source in one click |
| **+ Language** | Creates an empty `{lang}.json` with all source-language keys pre-filled |
| **+ String** | Add a new key/value pair to all language files at once |
| **TMX export** | Download any language pair as a TMX file for use in CAT tools |

---

## Customising the views

Publish the Blade views to `resources/views/vendor/l18n-translator/`:

```bash
php artisan vendor:publish --tag=l18n-translator-views
```

Edit them freely — published views take precedence over the package's built-in ones.

If you use a custom `layout`, set it in the config:

```php
'layout' => 'layouts.admin',
```

Your layout must include `@yield('content')` and `@yield('scripts')`.

---

## Routes

All routes are named with the `l18n.` prefix:

| Name | Method | URL |
|---|---|---|
| `l18n.index` | GET | `/admin/translations` |
| `l18n.show` | GET | `/admin/translations/{lang}` |
| `l18n.create` | GET | `/admin/translations/create` |
| `l18n.store` | POST | `/admin/translations/store` |
| `l18n.storedictionary` | POST | `/admin/translations/storedictionary` |
| `l18n.addstring` | GET | `/admin/translations/addstring` |
| `l18n.editstrings` | GET | `/admin/translations/editstrings/{key}` |
| `l18n.updatealltranslations` | POST | `/admin/translations/updatealltranslations` |
| `l18n.appendtotranslation` | POST | `/admin/translations/appendtotranslation` |
| `l18n.tmx` | GET | `/admin/translations/tmx/{lang}` |
| `l18n.deepl` | POST | `/admin/translations/deepl` |

---

## License

MIT
