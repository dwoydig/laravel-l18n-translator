<?php

namespace Dwoydig\L18nTranslator\Http\Controllers;

use Dwoydig\L18nTranslator\TranslationManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TranslationController extends Controller
{
    public function index()
    {
        $manager = new TranslationManager(config('l18n-translator.main_language', 'en'));
        $existingFiles = $manager->getLanguageFiles()->pluck('filename')->toArray();
        $availableLanguages = config('l18n-translator.available_languages', []);
        return view('l18n-translator::index', compact('existingFiles', 'availableLanguages'));
    }

    public function show(string $lang)
    {
        $manager = new TranslationManager($lang);
        $translations = $manager->mergeTranslations($manager->mainLanguage, $manager->translationLanguage);
        $mainLanguage = $manager->mainLanguageIso;
        $availableLanguages = $manager->availableLanguages;
        return view('l18n-translator::show', compact('lang', 'translations', 'mainLanguage', 'availableLanguages'));
    }

    public function create()
    {
        $manager = new TranslationManager(config('l18n-translator.main_language', 'en'));
        $availableLanguages = $manager->availableLanguages;
        $existingFiles = $manager->getLanguageFiles()->pluck('filename')->toArray();
        return view('l18n-translator::create', compact('availableLanguages', 'existingFiles'));
    }

    public function store(Request $request)
    {
        $lang = $request->validate(['targetLanguage' => 'required|string|max:10'])['targetLanguage'];
        $manager = new TranslationManager($lang);
        $manager->createEmptyTranslationFile();
        $manager->saveTranslationFile();
        session()->flash('success', "Language file '{$lang}.json' created — fill in the translations below.");
        return redirect()->route('l18n.show', ['lang' => $lang]);
    }

    public function storeDictionary(Request $request)
    {
        $lang = $request->input('lang');
        $dict = $request->input('dict', []);
        $manager = new TranslationManager($lang);
        foreach ($dict as $key => $value) {
            $manager->translationLanguage[$key] = $value;
        }
        $manager->saveTranslationFile();
        session()->flash('success', 'Translation saved.');
        return redirect()->route('l18n.show', ['lang' => $lang]);
    }

    public function addString()
    {
        $availableLanguages = config('l18n-translator.available_languages', []);
        $mainLanguage = config('l18n-translator.main_language', 'en');
        $isNew = true;
        return view('l18n-translator::editstring', compact('availableLanguages', 'mainLanguage', 'isNew'));
    }

    public function appendToTranslations(Request $request)
    {
        $key = $request->input('key');
        $languages = $request->input('languages', []);
        foreach ($languages as $iso => $string) {
            if ($string !== '' && $string !== null) {
                $manager = new TranslationManager($iso);
                $manager->appendString($key, $string);
                $manager->saveTranslationFile();
            }
        }
        session()->flash('success', "Key '{$key}' added to all translation files.");
        return redirect()->back();
    }

    public function editStrings(string $key)
    {
        $manager = new TranslationManager(config('l18n-translator.main_language', 'en'));
        $availableLanguages = $manager->availableLanguages;
        $mainLanguage = $manager->mainLanguageIso;
        $translations = $manager->getAllForKey($key);
        $isNew = false;
        return view('l18n-translator::editstring', compact('key', 'translations', 'availableLanguages', 'mainLanguage', 'isNew'));
    }

    public function updateAllTranslations(Request $request)
    {
        $key = $request->input('key');
        $languages = $request->input('languages', []);
        foreach ($languages as $iso => $string) {
            $manager = new TranslationManager($iso);
            $manager->appendString($key, $string ?? '');
            $manager->saveTranslationFile();
        }
        session()->flash('success', "Key '{$key}' updated across all languages.");
        return redirect()->route('l18n.editstrings', ['key' => $key]);
    }

    public function tmx(string $lang)
    {
        $manager = new TranslationManager($lang);
        $translations = $manager->mergeTranslations($manager->mainLanguage, $manager->translationLanguage);
        $mainLanguageIso = $manager->mainLanguageIso;
        $filename = $mainLanguageIso . '-' . $lang . '.tmx';
        $content = view('l18n-translator::tmx', compact('translations', 'lang', 'mainLanguageIso'));
        return response($content, 200, [
            'Content-Type'        => 'application/xml',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
