<?php

namespace Dwoydig\L18nTranslator;

use Dwoydig\L18nTranslator\Translation\TranslationKey;
use Dwoydig\L18nTranslator\Translation\TranslationRepository;
use Illuminate\Support\Collection;

class TranslationManager
{
    private string $mainLanguageIso;
    private array $mainLanguage = [];
    private string $translationLanguageIso;
    private array $translationLanguage = [];

    /** @var array<string, string|null> Pending changes (translation id → value, null = removed) since the last save. */
    private array $changes = [];

    /**
     * @param  TranslationRepository  $repository       Reads and writes all translation sources.
     * @param  string                 $languageIso      Locale to load as the translation target.
     * @param  string|null            $mainLanguageIso  Override for the source language; defaults to `l18n-translator.main_language`.
     */
    public function __construct(
        private readonly TranslationRepository $repository,
        string $languageIso,
        ?string $mainLanguageIso = null,
    ) {
        $this->mainLanguageIso = $mainLanguageIso ?? config('l18n-translator.main_language', 'en');
        $this->translationLanguageIso = $languageIso;
        $this->mainLanguage = $this->loadLanguage($this->mainLanguageIso);
        $this->translationLanguage = $this->loadLanguage($this->translationLanguageIso);
    }

    /**
     * Returns the ISO code of the configured source/main language (e.g. "en").
     */
    public function getMainLanguageIso(): string
    {
        return $this->mainLanguageIso;
    }

    /**
     * Returns the full translation id → value map of the source language.
     *
     * @return array<string, string>
     */
    public function getMainLanguage(): array
    {
        return $this->mainLanguage;
    }

    /**
     * Returns the full translation id → value map of the target translation language.
     *
     * @return array<string, string>
     */
    public function getTranslationLanguage(): array
    {
        return $this->translationLanguage;
    }

    /**
     * Loads all translations of a locale (JSON, PHP groups and vendor overrides) as a flat id → value map.
     *
     * @param  string  $isoLanguage  Locale code (e.g. "de", "pt_BR").
     * @return array<string, string>
     */
    public function loadLanguage(string $isoLanguage): array
    {
        return $this->repository->load($isoLanguage);
    }

    /**
     * Returns the files new keys can be added to, based on the main language's files.
     *
     * @return list<array{target: string, origin: string, label: string}>
     */
    public function getTargets(): array
    {
        return $this->repository->targets($this->mainLanguageIso);
    }

    /**
     * Writes all pending changes of the translation language back to their source files.
     * Files without changes are left untouched.
     */
    public function saveTranslationFile(): void
    {
        $this->repository->write($this->translationLanguageIso, $this->changes);
        $this->changes = [];
    }

    /**
     * Pre-populate all JSON keys from the main language with empty strings,
     * ready for manual or DeepL translation in the UI. PHP group and vendor
     * files are created on the first save of a translated value.
     */
    public function createEmptyTranslationFile(): void
    {
        foreach (array_keys($this->mainLanguage) as $id) {
            if (TranslationKey::fromId($id)->isJson() && !array_key_exists($id, $this->translationLanguage)) {
                $this->setTranslation($id, '');
            }
        }
    }

    /**
     * Describes a translation id for display: its code label (e.g. `auth.failed`) and origin ("app" or package).
     *
     * @return array{id: string, label: string, origin: string}
     */
    public static function describe(string $id): array
    {
        $key = TranslationKey::fromId($id);
        return ['id' => $id, 'label' => $key->label(), 'origin' => $key->origin];
    }

    /**
     * Merge main and target language arrays into a flat list suitable for view iteration.
     *
     * @param  array<string, string>  $main    Source language id→value map.
     * @param  array<string, string>  $target  Target language id→value map.
     * @return list<array{id: string, label: string, origin: string, original: string, translation: string|null}>
     */
    public static function mergeTranslations(array $main, array $target): array
    {
        $result = [];
        foreach ($main as $id => $value) {
            $result[] = static::describe($id) + [
                'original'    => $value,
                'translation' => $target[$id] ?? null,
            ];
        }
        return $result;
    }

    /**
     * Sets or overwrites a single translation in the in-memory target language array.
     * Call saveTranslationFile() afterwards to persist.
     *
     * @param  string  $id     Translation id (`{origin}|{group}|{key}`).
     * @param  string  $value  Translated string.
     */
    public function setTranslation(string $id, string $value): void
    {
        if (($this->translationLanguage[$id] ?? null) === $value) {
            return;
        }
        $this->translationLanguage[$id] = $value;
        $this->changes[$id] = $value;
    }

    /**
     * Sets a translation, or removes it when the value is empty so Laravel falls back
     * to the fallback locale instead of rendering an empty string.
     * Call saveTranslationFile() afterwards to persist.
     *
     * @param  string       $id     Translation id (`{origin}|{group}|{key}`).
     * @param  string|null  $value  Translated string; null or '' removes the translation.
     */
    public function applyTranslation(string $id, ?string $value): void
    {
        if ($value === null || $value === '') {
            $this->removeTranslation($id);
        } else {
            $this->setTranslation($id, $value);
        }
    }

    /**
     * Removes a single translation from the in-memory target language array.
     * Call saveTranslationFile() afterwards to persist.
     *
     * @param  string  $id  Translation id to remove.
     */
    public function removeTranslation(string $id): void
    {
        if (!array_key_exists($id, $this->translationLanguage)) {
            return;
        }
        unset($this->translationLanguage[$id]);
        $this->changes[$id] = null;
    }

    /**
     * Returns the current value of a single translation across all languages.
     *
     * @param  string  $id  Translation id to look up.
     * @return array<string, string|null>  Locale → value map; null when the key is absent.
     */
    public function getAllForKey(string $id): array
    {
        $translations = [];
        foreach ($this->getLanguageFiles() as $file) {
            $translations[$file->filename] = $this->loadLanguage($file->filename)[$id] ?? null;
        }
        return $translations;
    }

    /**
     * Returns all locales found in any translation source (JSON, PHP groups, vendor overrides).
     * Each object exposes: filename (locale code), name, flag, rtl.
     * The main/source language is always sorted first.
     *
     * @return Collection<int, object>
     */
    public function getLanguageFiles(): Collection
    {
        $main = $this->mainLanguageIso;
        return collect($this->repository->locales())
            ->map(fn(string $iso): object => (object) [
                'filename' => $iso,
                'name'     => static::resolveLocaleName($iso),
                'flag'     => static::localeToFlag($iso),
                'rtl'      => static::isRtl($iso),
            ])
            ->sortBy(fn(object $f): int => $f->filename === $main ? 0 : 1);
    }

    /**
     * Resolves a BCP-47 locale code to a human-readable English name via the intl extension.
     * Falls back to the raw locale string when intl is unavailable or returns no display name.
     *
     * @param  string  $locale  BCP-47 locale code (e.g. "de", "en-AU").
     */
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
     *
     * @param  string  $locale  BCP-47 locale code.
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

    /**
     * Returns true when the given locale is written right-to-left.
     * Uses the intl script tag when available; falls back to a hardcoded primary-language list.
     *
     * @param  string  $locale  BCP-47 locale code.
     */
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

    /**
     * Returns all ICU locales known to the intl extension as a BCP-47 → display name map,
     * sorted alphabetically by display name. Returns an empty array if intl is unavailable.
     *
     * @return array<string, string>
     */
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

    /**
     * Returns translations that exist in the target language but are absent from the main language.
     *
     * @return list<array{id: string, label: string, origin: string, value: string}>
     */
    public function orphanedTranslations(): array
    {
        $orphaned = [];
        foreach (array_diff_key($this->translationLanguage, $this->mainLanguage) as $id => $value) {
            $orphaned[] = static::describe($id) + ['value' => $value];
        }
        return $orphaned;
    }

    /**
     * Returns keys that exist in the main language but are absent or empty in the target language.
     *
     * @return array<string, string>  Translation id → source value map of missing entries.
     */
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
