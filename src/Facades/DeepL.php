<?php

namespace Dwoydig\L18nTranslator\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string translate(string $text, string $targetLang, ?string $sourceLang = null)
 * @method static array{character_count: int, character_limit: int} usage()
 *
 * @see \Dwoydig\L18nTranslator\Services\DeeplService
 */
class DeepL extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Dwoydig\L18nTranslator\Services\DeeplService::class;
    }
}
