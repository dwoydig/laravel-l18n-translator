<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Translation;

use Dwoydig\L18nTranslator\TranslationManager;

/**
 * Creates TranslationManager instances for a given locale, sharing one TranslationRepository.
 */
final class TranslationManagerFactory
{
    public function __construct(private readonly TranslationRepository $repository)
    {
    }

    /**
     * @param  string       $languageIso      Locale to load as the translation target.
     * @param  string|null  $mainLanguageIso  Override for the source language.
     */
    public function make(string $languageIso, ?string $mainLanguageIso = null): TranslationManager
    {
        return new TranslationManager($this->repository, $languageIso, $mainLanguageIso);
    }

    /**
     * Returns a manager for the configured main language.
     */
    public function main(): TranslationManager
    {
        return $this->make($this->mainLanguageIso());
    }

    /**
     * Returns the configured source language (e.g. "en").
     */
    public function mainLanguageIso(): string
    {
        return config('l18n-translator.main_language', 'en');
    }
}
