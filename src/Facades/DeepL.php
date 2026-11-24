<?php

namespace Dwoydig\L18nTranslator\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * DeepL-specific facade for backward compatibility.
 * For provider-agnostic code, inject TranslatorContract instead.
 *
 * @method static string translate(string $text, string $targetLang, ?string $sourceLang = null)
 * @method static array{character_count: int, character_limit: int} usage()
 * @method static bool isEnabled()
 *
 * @see \Dwoydig\L18nTranslator\Services\DeeplService
 * @see \Dwoydig\L18nTranslator\Contracts\TranslatorContract
 */
class DeepL extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Dwoydig\L18nTranslator\Services\DeeplService::class;
    }
}
