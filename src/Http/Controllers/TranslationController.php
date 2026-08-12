<?php

namespace Dwoydig\L18nTranslator\Http\Controllers;

use Dwoydig\L18nTranslator\TranslationManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TranslationController extends Controller
{
    /**
     * Languages overview — lists all language files with an "Add Language" modal.
     */
    public function index(): View
    {
        $manager = new TranslationManager(config('l18n-translator.main_language', 'en'));
        $languageFiles = $manager->getLanguageFiles();
        $mainLanguage = $manager->getMainLanguageIso();
        $existing = $languageFiles->pluck('filename')->flip()->all();
        $availableLanguages = array_diff_key(TranslationManager::getAllLocales(), $existing);
        return view('l18n-translator::index', compact('languageFiles', 'mainLanguage', 'availableLanguages'));
    }

    /**
     * Per-language translation editor — shows all keys side-by-side with the source language.
     *
     * @param  string  $lang  BCP-47 locale code of the language to edit (e.g. "de", "fr").
     */
    public function show(string $lang): View
    {
        $manager = new TranslationManager($lang);
        $translations = TranslationManager::mergeTranslations($manager->getMainLanguage(), $manager->getTranslationLanguage());
        $mainLanguage = $manager->getMainLanguageIso();
        $languageFiles = $manager->getLanguageFiles();
        $orphaned = $manager->orphanedTranslations();
        $isRtl = $languageFiles->firstWhere('filename', $lang)?->rtl ?? false;
        return view('l18n-translator::show', compact('lang', 'translations', 'mainLanguage', 'languageFiles', 'orphaned', 'isRtl'));
    }

    /**
     * Creates a new empty language file pre-populated with all keys from the main language.
     *
     * @param  Request  $request  Must contain `targetLanguage` (BCP-47 locale code).
     */
    public function store(Request $request): RedirectResponse
    {
        $lang = $request->validate(['targetLanguage' => 'required|string|max:10'])['targetLanguage'];
        $manager = new TranslationManager($lang);
        $manager->createEmptyTranslationFile();
        $manager->saveTranslationFile();
        session()->flash('success', ["Language file '{$lang}.json' created — fill in the translations below."]);
        return redirect()->route('l18n.show', ['lang' => $lang]);
    }

    /**
     * Saves the full translation dictionary for a single language file.
     *
     * @param  Request  $request  Must contain `lang` and `dict` (key → value map).
     */
    public function storeDictionary(Request $request): RedirectResponse
    {
        $lang = $request->input('lang');
        $dict = $request->input('dict', []);
        $manager = new TranslationManager($lang);
        foreach ($dict as $key => $value) {
            $manager->setTranslation($key, $value);
        }
        $manager->saveTranslationFile();
        session()->flash('success', ['Translation saved.']);
        return redirect()->route('l18n.show', ['lang' => $lang]);
    }

    /**
     * Add-new-string form — renders the editstring view in "new" mode with all language fields empty.
     */
    public function addString(): View
    {
        $manager = new TranslationManager(config('l18n-translator.main_language', 'en'));
        $languageFiles = $manager->getLanguageFiles();
        $mainLanguage = $manager->getMainLanguageIso();
        $isNew = true;
        return view('l18n-translator::editstring', compact('languageFiles', 'mainLanguage', 'isNew'));
    }

    /**
     * Persists a new translation key with its values across all language files.
     * Languages with an empty or null value are skipped.
     *
     * @param  Request  $request  Must contain `key` and `languages` (locale → value map).
     */
    public function appendToTranslations(Request $request): RedirectResponse
    {
        $key = $request->input('key');
        $languages = $request->input('languages', []);
        foreach ($languages as $iso => $string) {
            if ($string !== '' && $string !== null) {
                $manager = new TranslationManager($iso);
                $manager->setTranslation($key, $string);
                $manager->saveTranslationFile();
            }
        }
        session()->flash('success', ["Key '{$key}' added to all translation files."]);
        return redirect()->back();
    }

    /**
     * Updates an existing translation key across all language files.
     *
     * @param  Request  $request  Must contain `key` and `languages` (locale → value map).
     */
    public function updateAllTranslations(Request $request): RedirectResponse
    {
        $key = $request->input('key');
        $languages = $request->input('languages', []);
        foreach ($languages as $iso => $string) {
            $manager = new TranslationManager($iso);
            $manager->setTranslation($key, $string ?? '');
            $manager->saveTranslationFile();
        }
        session()->flash('success', ["Key '{$key}' updated across all languages."]);
        return redirect()->route('l18n.editstrings', ['key' => $key]);
    }

    /**
     * Edit-existing-string form — renders the editstring view pre-filled with current translations.
     *
     * @param  Request  $request  Optional `key` query parameter selects which key to edit.
     */
    public function editStrings(Request $request): View
    {
        $key = $request->query('key', '');
        $manager = new TranslationManager(config('l18n-translator.main_language', 'en'));
        $languageFiles = $manager->getLanguageFiles();
        $mainLanguage = $manager->getMainLanguageIso();
        $translations = $key !== '' ? $manager->getAllForKey($key) : [];
        $isNew = false;
        return view('l18n-translator::editstring', compact('key', 'translations', 'languageFiles', 'mainLanguage', 'isNew'));
    }

    /**
     * Coverage report — per-language translation completeness stats sorted by percentage.
     */
    public function coverage(): View
    {
        $mainIso  = config('l18n-translator.main_language', 'en');
        $manager  = new TranslationManager($mainIso);
        $main     = $manager->getMainLanguage();
        $mainCount = count($main);

        $stats = $manager->getLanguageFiles()
            ->reject(fn($f) => $f->filename === $mainIso)
            ->map(function ($file) use ($main, $mainCount) {
                $lang = TranslationManager::loadJson($file->filename);
                $translated    = 0;
                $missing       = 0;
                $missingChars  = 0;
                foreach ($main as $key => $value) {
                    if (isset($lang[$key]) && $lang[$key] !== '') {
                        $translated++;
                    } else {
                        $missing++;
                        $missingChars += mb_strlen((string) $value);
                    }
                }
                $orphaned = count(array_diff_key($lang, $main));
                return [
                    'file'          => $file,
                    'total'         => $mainCount,
                    'translated'    => $translated,
                    'missing'       => $missing,
                    'missingChars'  => $missingChars,
                    'orphaned'      => $orphaned,
                    'pct'           => $mainCount > 0 ? ($missing === 0 ? 100 : (int) floor($translated / $mainCount * 100)) : 0,
                ];
            })
            ->sortBy('pct');

        $mainFile   = $manager->getLanguageFiles()->firstWhere('filename', $mainIso);
        $languageFiles = $manager->getLanguageFiles();
        return view('l18n-translator::coverage', compact('stats', 'mainIso', 'mainCount', 'mainFile', 'languageFiles'));
    }

    /**
     * Missing-translations view — all untranslated keys across every non-source language.
     */
    public function missingAll(): View
    {
        $mainIso  = config('l18n-translator.main_language', 'en');
        $manager  = new TranslationManager($mainIso);
        $main     = $manager->getMainLanguage();
        $languageFiles = $manager->getLanguageFiles();

        $missing = [];
        foreach ($languageFiles->reject(fn($f) => $f->filename === $mainIso) as $file) {
            $lang = TranslationManager::loadJson($file->filename);
            foreach ($main as $key => $value) {
                if (!isset($lang[$key]) || $lang[$key] === '') {
                    $missing[] = [
                        'lang'     => $file->filename,
                        'langName' => $file->name,
                        'langFlag' => $file->flag,
                        'langRtl'  => $file->rtl,
                        'key'      => $key,
                        'original' => $value,
                    ];
                }
            }
        }

        $langCount = collect($missing)->pluck('lang')->unique()->count();
        return view('l18n-translator::missing', compact('missing', 'languageFiles', 'mainIso', 'langCount'));
    }

    /**
     * Saves filled-in values from the missing-translations bulk editor.
     * Empty values are ignored so partially completed submissions are safe.
     *
     * @param  Request  $request  Must contain `dict` (locale → key → value).
     */
    public function storeMissingAll(Request $request): RedirectResponse
    {
        $dict = $request->input('dict', []);
        $saved = 0;
        foreach ($dict as $lang => $keys) {
            $manager = new TranslationManager($lang);
            $hasChanges = false;
            foreach ($keys as $key => $value) {
                if ($value !== null && $value !== '') {
                    $manager->setTranslation($key, $value);
                    $saved++;
                    $hasChanges = true;
                }
            }
            if ($hasChanges) {
                $manager->saveTranslationFile();
            }
        }
        session()->flash('success', [$saved . ' translation(s) saved.']);
        return redirect()->route('l18n.missing');
    }

    /**
     * Copies selected orphaned keys into the main language file with empty values
     * so they can be filled in via the normal editor.
     *
     * @param  Request  $request  Must contain `lang` (source locale) and `keys` (array of key names).
     */
    public function adoptOrphans(Request $request): RedirectResponse
    {
        $lang = $request->input('lang');
        $keys = $request->input('keys', []);
        $mainLang = config('l18n-translator.main_language', 'en');

        $manager = new TranslationManager($mainLang);
        foreach ($keys as $key) {
            $manager->setTranslation($key, '');
        }
        $manager->saveTranslationFile();

        session()->flash('success', [count($keys) . ' key(s) added to ' . $mainLang . ' — fill in the values.']);
        return redirect()->route('l18n.show', ['lang' => $lang]);
    }

    /**
     * Permanently removes selected orphaned keys from the given language file.
     *
     * @param  Request  $request  Must contain `lang` (locale) and `keys` (array of key names to delete).
     */
    public function removeOrphans(Request $request): RedirectResponse
    {
        $lang = $request->input('lang');
        $keys = $request->input('keys', []);

        $manager = new TranslationManager($lang);
        foreach ($keys as $key) {
            $manager->removeTranslation($key);
        }
        $manager->saveTranslationFile();

        session()->flash('success', [count($keys) . ' orphaned key(s) removed from ' . $lang . '.']);
        return redirect()->route('l18n.show', ['lang' => $lang]);
    }

    /**
     * Returns all translation keys from the main language file as a JSON array.
     * Used by the header key-search autocomplete.
     */
    public function keys(): JsonResponse
    {
        $manager = new TranslationManager(config('l18n-translator.main_language', 'en'));
        return response()->json(array_keys($manager->getMainLanguage()));
    }
}
