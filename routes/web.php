<?php

use Dwoydig\L18nTranslator\Http\Controllers\DeeplController;
use Dwoydig\L18nTranslator\Http\Controllers\TranslationController;
use Illuminate\Support\Facades\Route;

// Static routes must come before /{lang} to avoid being swallowed by the wildcard.
Route::get('/',                  [TranslationController::class, 'index'])->name('index');
Route::get('/create',            [TranslationController::class, 'create'])->name('create');
Route::get('/addstring',         [TranslationController::class, 'addString'])->name('addstring');
Route::get('/editstrings/{key}', [TranslationController::class, 'editStrings'])->name('editstrings');

Route::post('/store',                [TranslationController::class, 'store'])->name('store');
Route::post('/storedictionary',      [TranslationController::class, 'storeDictionary'])->name('storedictionary');
Route::post('/appendtotranslation',  [TranslationController::class, 'appendToTranslations'])->name('appendtotranslation');
Route::post('/updatealltranslations',[TranslationController::class, 'updateAllTranslations'])->name('updatealltranslations');
Route::post('/deepl',                [DeeplController::class, 'translate'])->name('deepl');

Route::get('/tmx/{lang}', [TranslationController::class, 'tmx'])->name('tmx');
Route::get('/{lang}',     [TranslationController::class, 'show'])->name('show');
