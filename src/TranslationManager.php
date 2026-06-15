<?php

namespace Dwoydig\L18nTranslator;

use Illuminate\Support\Facades\File;

class TranslationManager
{
    private string $mainLanguageIso;
    private array $mainLanguage = [];
    private string $translationLanguageIso;
    private array $translationLanguage = [];

    public function __construct(string $languageIso, ?string $mainLanguageIso = null)
    {
        $this->mainLanguageIso = strtolower($mainLanguageIso ?? config('l18n-translator.main_language', 'en'));
        $this->translationLanguageIso = strtolower($languageIso);
        $this->mainLanguage = static::loadJson($this->mainLanguageIso);
        $this->translationLanguage = static::loadJson($this->translationLanguageIso);
    }

    public function getMainLanguageIso(): string
    {
        return $this->mainLanguageIso;
    }

    public function getMainLanguage(): array
    {
        return $this->mainLanguage;
    }

    public function getTranslationLanguage(): array
    {
        return $this->translationLanguage;
    }

    public static function loadJson(string $isoLanguage): array
    {
        $path = resource_path('lang/' . $isoLanguage . '.json');
        if (!File::exists($path)) {
            return [];
        }
        $decoded = json_decode(File::get($path), true);
        return is_array($decoded) ? $decoded : [];
    }

    public function saveTranslationFile(): bool
    {
        $path = resource_path('lang/' . $this->translationLanguageIso . '.json');
        $contents = json_encode($this->translationLanguage, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return (bool) File::put($path, $contents);
    }

    /**
     * Pre-populate all keys from the main language with empty strings,
     * ready for manual or DeepL translation in the UI.
     */
    public function createEmptyTranslationFile(): void
    {
        foreach (array_keys($this->mainLanguage) as $key) {
            $this->translationLanguage[$key] ??= '';
        }
    }

    /**
     * Merge main and target language arrays into a list of arrays
     * with keys: 'key', 'original', 'translation' for easy iteration in views.
     */
    public static function mergeTranslations(array $main, array $target): array
    {
        $result = [];
        foreach ($main as $key => $value) {
            $result[] = [
                'key'         => $key,
                'original'    => $value,
                'translation' => $target[$key] ?? null,
            ];
        }
        return $result;
    }

    public function setTranslation(string $key, string $value): void
    {
        $this->translationLanguage[$key] = $value;
    }

    /**
     * Return the current value of $key across all configured languages.
     */
    public function getAllForKey(string $key): array
    {
        $translations = [];
        foreach ($this->getLanguageFiles() as $file) {
            $contents = static::loadJson($file->filename);
            $translations[$file->filename] = $contents[$key] ?? null;
        }
        return $translations;
    }

    public function getLanguageFiles(): \Illuminate\Support\Collection
    {
        $main = $this->mainLanguageIso;
        $files = collect();
        foreach (File::glob(resource_path('lang/*.json')) as $file) {
            $iso = pathinfo($file, PATHINFO_FILENAME);
            $files->push((object) [
                'basename'  => basename($file),
                'filename'  => $iso,
                'extension' => pathinfo($file, PATHINFO_EXTENSION),
                'name'      => static::resolveLocaleName($iso),
                'flag'      => static::localeToFlag($iso),
                'rtl'       => static::isRtl($iso),
            ]);
        }
        return $files->sortBy(fn($f) => $f->filename === $main ? 0 : 1);
    }

    public static function resolveLocaleName(string $locale): string
    {
        if (class_exists(\Locale::class)) {
            $name = \Locale::getDisplayName($locale, 'en');
            if ($name && $name !== $locale) {
                return $name;
            }
        }
        return $locale;
    }

    /**
     * Convert a locale code to a Unicode flag emoji.
     * Uses the country suffix when present (en-AU → 🇦🇺),
     * otherwise falls back to a language-to-country map (de → 🇩🇪).
     */
    public static function localeToFlag(string $locale): string
    {
        $parts = explode('-', strtolower($locale));
        $countryCode = count($parts) >= 2
            ? strtoupper(end($parts))
            : strtoupper(static::LANGUAGE_COUNTRY_MAP[$parts[0]] ?? $parts[0]);

        if (strlen($countryCode) !== 2) {
            return '';
        }

        return mb_chr(ord($countryCode[0]) - ord('A') + 0x1F1E6)
             . mb_chr(ord($countryCode[1]) - ord('A') + 0x1F1E6);
    }

    // Fallback map for bare language codes without a country suffix
    private const LANGUAGE_COUNTRY_MAP = [
        'en' => 'GB', 'de' => 'DE', 'fr' => 'FR', 'es' => 'ES',
        'it' => 'IT', 'pt' => 'PT', 'nl' => 'NL', 'pl' => 'PL',
        'ru' => 'RU', 'ja' => 'JP', 'zh' => 'CN', 'ko' => 'KR',
        'ar' => 'SA', 'tr' => 'TR', 'sv' => 'SE', 'da' => 'DK',
        'fi' => 'FI', 'nb' => 'NO', 'cs' => 'CZ', 'sk' => 'SK',
        'hu' => 'HU', 'ro' => 'RO', 'bg' => 'BG', 'hr' => 'HR',
        'uk' => 'UA', 'el' => 'GR', 'he' => 'IL', 'th' => 'TH',
        'vi' => 'VN', 'id' => 'ID', 'ms' => 'MY', 'ca' => 'ES',
    ];

    public static function isRtl(string $locale): bool
    {
        // Try to get the script tag from the locale (works for explicit tags like fa_Arab, sr_Cyrl)
        $script = class_exists(\Locale::class) ? \Locale::getScript($locale) : '';

        if ($script) {
            return in_array($script, ['Arab', 'Hebr', 'Thaa', 'Syrc', 'Nkoo', 'Adlm', 'Rohg', 'Sogd'], true);
        }

        // Fallback: language codes that implicitly use RTL scripts
        $rtlPrimary = ['ar', 'arc', 'dv', 'fa', 'ha', 'he', 'iw', 'ks', 'ku', 'ps', 'sd', 'ug', 'ur', 'yi'];
        $primary = strtolower(class_exists(\Locale::class)
            ? (\Locale::getPrimaryLanguage($locale) ?: $locale)
            : explode('-', $locale)[0]);

        return in_array($primary, $rtlPrimary, true);
    }

    public static function getAllLocales(): array
    {
        if (!class_exists(\ResourceBundle::class)) {
            return [];
        }
        $locales = [];
        foreach (\ResourceBundle::getLocales('') as $icu) {
            $bcp47 = str_replace('_', '-', $icu);
            $name  = static::resolveLocaleName($bcp47);
            if ($name === $bcp47) continue; // no display name available
            $locales[$bcp47] = $name;
        }
        asort($locales);
        return $locales;
    }

    public function orphanedTranslations(): array
    {
        $orphaned = [];
        foreach ($this->translationLanguage as $key => $value) {
            if (!array_key_exists($key, $this->mainLanguage)) {
                $orphaned[$key] = $value;
            }
        }
        return $orphaned;
    }

    public function missingTranslations(): array
    {
        $missing = [];
        foreach ($this->mainLanguage as $key => $value) {
            if (!array_key_exists($key, $this->translationLanguage)
                || $this->translationLanguage[$key] === '') {
                $missing[$key] = $value;
            }
        }
        return $missing;
    }
}