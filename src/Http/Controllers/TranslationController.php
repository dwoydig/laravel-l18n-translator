<?php

namespace Dwoydig\L18nTranslator\Http\Controllers;

use Dwoydig\L18nTranslator\TranslationManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TranslationController extends Controller
{
    public function index(): View
    {
        $manager = new TranslationManager(config('l18n-translator.main_language', 'en'));
        $languageFiles = $manager->getLanguageFiles();
        return view('l18n-translator::index', compact('languageFiles'));
    }

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

        session()->flash('success', count($keys) . ' key(s) added to ' . $mainLang . ' — fill in the values.');
        return redirect()->route('l18n.show', ['lang' => $lang]);
    }

    public function create(): View
    {
        $existing = (new TranslationManager(config('l18n-translator.main_language', 'en')))
            ->getLanguageFiles()->pluck('filename')->flip()->all();
        $availableLanguages = array_diff_key(TranslationManager::getAllLocales(), $existing);
        return view('l18n-translator::create', compact('availableLanguages'));
    }

    public function store(Request $request): RedirectResponse
    {
        $lang = $request->validate(['targetLanguage' => 'required|string|max:10'])['targetLanguage'];
        $manager = new TranslationManager($lang);
        $manager->createEmptyTranslationFile();
        $manager->saveTranslationFile();
        session()->flash('success', "Language file '{$lang}.json' created — fill in the translations below.");
        return redirect()->route('l18n.show', ['lang' => $lang]);
    }

    public function storeDictionary(Request $request): RedirectResponse
    {
        $lang = $request->input('lang');
        $dict = $request->input('dict', []);
        $manager = new TranslationManager($lang);
        foreach ($dict as $key => $value) {
            $manager->setTranslation($key, $value);
        }
        $manager->saveTranslationFile();
        session()->flash('success', 'Translation saved.');
        return redirect()->route('l18n.show', ['lang' => $lang]);
    }

    public function addString(): View
    {
        $manager = new TranslationManager(config('l18n-translator.main_language', 'en'));
        $languageFiles = $manager->getLanguageFiles();
        $mainLanguage = $manager->getMainLanguageIso();
        $isNew = true;
        return view('l18n-translator::editstring', compact('languageFiles', 'mainLanguage', 'isNew'));
    }

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
        session()->flash('success', "Key '{$key}' added to all translation files.");
        return redirect()->back();
    }

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
                $translated = 0;
                $missing    = 0;
                foreach ($main as $key => $_) {
                    if (isset($lang[$key]) && $lang[$key] !== '') {
                        $translated++;
                    } else {
                        $missing++;
                    }
                }
                $orphaned = count(array_diff_key($lang, $main));
                return [
                    'file'       => $file,
                    'total'      => $mainCount,
                    'translated' => $translated,
                    'missing'    => $missing,
                    'orphaned'   => $orphaned,
                    'pct'        => $mainCount > 0 ? round($translated / $mainCount * 100) : 0,
                ];
            })
            ->sortBy('pct');

        $mainFile   = $manager->getLanguageFiles()->firstWhere('filename', $mainIso);
        $languageFiles = $manager->getLanguageFiles();
        return view('l18n-translator::coverage', compact('stats', 'mainIso', 'mainCount', 'mainFile', 'languageFiles'));
    }

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

    public function updateAllTranslations(Request $request): RedirectResponse
    {
        $key = $request->input('key');
        $languages = $request->input('languages', []);
        foreach ($languages as $iso => $string) {
            $manager = new TranslationManager($iso);
            $manager->setTranslation($key, $string ?? '');
            $manager->saveTranslationFile();
        }
        session()->flash('success', "Key '{$key}' updated across all languages.");
        return redirect()->route('l18n.editstrings', ['key' => $key]);
    }
}
