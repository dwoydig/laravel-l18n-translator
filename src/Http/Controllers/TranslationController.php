<?php

namespace Dwoydig\L18nTranslator\Http\Controllers;

use Dwoydig\L18nTranslator\Translation\TranslationKey;
use Dwoydig\L18nTranslator\Translation\TranslationManagerFactory;
use Dwoydig\L18nTranslator\TranslationManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TranslationController extends Controller
{
    public function __construct(private readonly TranslationManagerFactory $managers)
    {
    }

    /**
     * Languages overview — lists all language files with an "Add Language" modal.
     */
    public function index(): View
    {
        $manager = $this->managers->main();
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
        $manager = $this->managers->make($lang);
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
        $lang = $request->validate(['targetLanguage' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9_-]+$/']])['targetLanguage'];
        $manager = $this->managers->make($lang);
        $manager->createEmptyTranslationFile();
        $manager->saveTranslationFile();
        session()->flash('success', ["Language file '{$lang}.json' created — fill in the translations below."]);
        return redirect()->route('l18n.show', ['lang' => $lang]);
    }

    /**
     * Saves the full translation dictionary for a single language; emptied values are removed.
     *
     * @param  Request  $request  Must contain `lang` and `dict` (translation id → value map).
     */
    public function storeDictionary(Request $request): RedirectResponse
    {
        $lang = $request->input('lang');
        $dict = $request->input('dict', []);
        $manager = $this->managers->make($lang);
        foreach ($dict as $key => $value) {
            $manager->applyTranslation($key, $value);
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
        $manager = $this->managers->main();
        $languageFiles = $manager->getLanguageFiles();
        $mainLanguage = $manager->getMainLanguageIso();
        $targets = $manager->getTargets();
        $isNew = true;
        return view('l18n-translator::editstring', compact('languageFiles', 'mainLanguage', 'targets', 'isNew'));
    }

    /**
     * Persists a new translation key with its values across all language files.
     * Languages with an empty or null value are skipped.
     *
     * @param  Request  $request  Must contain `target` (`{origin}|{group}`), `key` and `languages` (locale → value map).
     */
    public function appendToTranslations(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'target'      => 'required|string',
            'key'         => 'required|string',
            'languages'   => 'array',
            'languages.*' => 'nullable|string',
        ]);
        $key = TranslationKey::fromId($input['target'] . '|' . $input['key']);
        foreach ($input['languages'] ?? [] as $iso => $string) {
            if ($string !== '' && $string !== null) {
                $manager = $this->managers->make($iso);
                $manager->setTranslation($key->id(), $string);
                $manager->saveTranslationFile();
            }
        }
        session()->flash('success', ["Key '{$key->label()}' added to all translation files."]);
        return redirect()->back();
    }

    /**
     * Updates an existing translation key across all language files.
     * Emptied values are removed so Laravel falls back to the fallback locale.
     *
     * @param  Request  $request  Must contain `key` (translation id) and `languages` (locale → value map).
     */
    public function updateAllTranslations(Request $request): RedirectResponse
    {
        $key = $request->input('key');
        $languages = $request->input('languages', []);
        foreach ($languages as $iso => $string) {
            $manager = $this->managers->make($iso);
            $manager->applyTranslation($key, $string);
            $manager->saveTranslationFile();
        }
        session()->flash('success', ["Key '" . TranslationKey::fromId($key)->label() . "' updated across all languages."]);
        return redirect()->route('l18n.editstrings', ['key' => $key]);
    }

    /**
     * Edit-existing-string form — renders the editstring view pre-filled with current translations.
     *
     * @param  Request  $request  Required `key` query parameter (translation id) selects which key to edit.
     */
    public function editStrings(Request $request): View
    {
        $key = (string) $request->query('key', '');
        abort_if($key === '', 404);
        $entry = TranslationManager::describe($key);
        $manager = $this->managers->main();
        $languageFiles = $manager->getLanguageFiles();
        $mainLanguage = $manager->getMainLanguageIso();
        $translations = $manager->getAllForKey($key);
        $isNew = false;
        return view('l18n-translator::editstring', compact('key', 'entry', 'translations', 'languageFiles', 'mainLanguage', 'isNew'));
    }

    /**
     * Coverage report — per-language translation completeness stats sorted by percentage.
     */
    public function coverage(): View
    {
        $mainIso  = $this->managers->mainLanguageIso();
        $manager  = $this->managers->make($mainIso);
        $main     = $manager->getMainLanguage();
        $mainCount = count($main);

        $stats = $manager->getLanguageFiles()
            ->reject(fn($f) => $f->filename === $mainIso)
            ->map(function ($file) use ($manager, $main, $mainCount) {
                $lang = $manager->loadLanguage($file->filename);
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
        $mainIso  = $this->managers->mainLanguageIso();
        $manager  = $this->managers->make($mainIso);
        $main     = $manager->getMainLanguage();
        $languageFiles = $manager->getLanguageFiles();

        $missing = [];
        foreach ($languageFiles->reject(fn($f) => $f->filename === $mainIso) as $file) {
            $lang = $manager->loadLanguage($file->filename);
            foreach ($main as $key => $value) {
                if (!isset($lang[$key]) || $lang[$key] === '') {
                    $missing[] = TranslationManager::describe($key) + [
                        'lang'     => $file->filename,
                        'langName' => $file->name,
                        'langFlag' => $file->flag,
                        'langRtl'  => $file->rtl,
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
            $manager = $this->managers->make($lang);
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
        $mainLang = $this->managers->mainLanguageIso();

        $manager = $this->managers->make($mainLang);
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

        $manager = $this->managers->make($lang);
        foreach ($keys as $key) {
            $manager->removeTranslation($key);
        }
        $manager->saveTranslationFile();

        session()->flash('success', [count($keys) . ' orphaned key(s) removed from ' . $lang . '.']);
        return redirect()->route('l18n.show', ['lang' => $lang]);
    }

    /**
     * Returns all translation keys of the main language as a JSON array of `{id, label, origin}` objects.
     * Used by the header key-search autocomplete.
     */
    public function keys(): JsonResponse
    {
        $manager = $this->managers->main();
        return response()->json(array_map(
            TranslationManager::describe(...),
            array_keys($manager->getMainLanguage()),
        ));
    }
}
