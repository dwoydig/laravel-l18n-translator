<?php

namespace Dwoydig\L18nTranslator;

use Illuminate\Support\Facades\File;

class TranslationManager
{
    public string $mainLanguageIso;
    public array $mainLanguage = [];
    public string $translationLanguageIso;
    public array $translationLanguage = [];
    public array $availableLanguages = [];

    public function __construct(string $languageIso, string $mainLanguageIso = null)
    {
        $this->mainLanguageIso = $mainLanguageIso ?? config('l18n-translator.main_language', 'en');
        $this->translationLanguageIso = strtolower($languageIso);
        $this->availableLanguages = config('l18n-translator.available_languages', []);
        $this->mainLanguage = static::loadJson($this->mainLanguageIso);
        $this->translationLanguage = static::loadJson($this->translationLanguageIso);
    }

    public static function loadJson(string $isoLanguage): array
    {
        $path = resource_path('lang/' . $isoLanguage . '.json');
        if (!file_exists($path)) {
            return [];
        }
        $contents = File::get($path);
        $decoded = json_decode($contents, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function saveTranslationFile(): bool
    {
        $path = resource_path('lang/' . $this->translationLanguageIso . '.json');
        $contents = json_encode($this->translationLanguage, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return (bool) file_put_contents($path, $contents);
    }

    /**
     * Pre-populate all keys from the main language with empty strings,
     * ready for manual or DeepL translation in the UI.
     */
    public function createEmptyTranslationFile(): void
    {
        foreach (array_keys($this->mainLanguage) as $key) {
            $this->translationLanguage[$key] = '';
        }
    }

    /**
     * Merge main and target language arrays into a list of objects
     * with ->key, ->original, ->translation for easy iteration in views.
     */
    public function mergeTranslations(array $main, array $target): array
    {
        $result = [];
        foreach ($main as $key => $value) {
            $obj = new \stdClass();
            $obj->key = $key;
            $obj->original = $value;
            $obj->translation = array_key_exists($key, $target) ? $target[$key] : null;
            $result[] = $obj;
        }
        return $result;
    }

    public function appendString(string $key, string $value): void
    {
        $this->translationLanguage[$key] = $value;
    }

    /**
     * Return the current value of $key across all configured languages.
     */
    public function getAllForKey(string $key): array
    {
        $translations = [];
        foreach ($this->availableLanguages as $iso => $name) {
            $file = static::loadJson($iso);
            $translations[$iso] = $file[$key] ?? null;
        }
        return $translations;
    }

    public function getLanguageFiles(): \Illuminate\Support\Collection
    {
        $files = collect();
        foreach (glob(resource_path('lang/*.json')) as $file) {
            $files->push((object) [
                'basename'  => basename($file),
                'filename'  => pathinfo($file, PATHINFO_FILENAME),
                'extension' => pathinfo($file, PATHINFO_EXTENSION),
            ]);
        }
        return $files;
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
