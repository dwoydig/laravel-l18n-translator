<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Contracts;

interface TranslatorContract
{
    /**
     * Translate a string to the target language.
     * Laravel :placeholder tokens must be preserved through translation.
     *
     * @param  string       $text        Text to translate.
     * @param  string       $targetLang  BCP-47 target locale (e.g. "DE", "FR").
     * @param  string|null  $sourceLang  BCP-47 source locale; defaults to l18n-translator.main_language.
     * @throws \RuntimeException when the adapter is not configured or the API call fails.
     */
    public function translate(string $text, string $targetLang, ?string $sourceLang = null): string;

    /** Whether this adapter is configured and ready to use. */
    public function isEnabled(): bool;

    /** Whether this adapter can report usage/quota information. */
    public function supportsUsage(): bool;

    /**
     * Return current usage statistics (e.g. character count and limit).
     * Returns an empty array when supportsUsage() is false.
     *
     * @return array<string, int>
     */
    public function usage(): array;
}
