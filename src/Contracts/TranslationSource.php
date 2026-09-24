<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Contracts;

use Dwoydig\L18nTranslator\Translation\TranslationKey;

/**
 * A storage location for translations, e.g. the app's JSON files or a set of PHP group files.
 */
interface TranslationSource
{
    /**
     * Returns "app" or the vendor package name this source belongs to.
     */
    public function origin(): string;

    /**
     * Returns all locales this source holds translations for.
     *
     * @return list<string>
     */
    public function locales(): array;

    /**
     * Returns the groups available for the given locale ("*" for JSON sources).
     *
     * @return list<string>
     */
    public function groups(string $locale): array;

    /**
     * Returns true when the given key is stored in this source.
     */
    public function accepts(TranslationKey $key): bool;

    /**
     * Loads all string translations of a locale as a flat id → value map.
     *
     * @return array<string, string>
     */
    public function load(string $locale): array;

    /**
     * Applies changes to the locale's files. A null value removes the key.
     *
     * @param  array<string, string|null>  $changes  Id → new value; all ids are accepted by this source.
     */
    public function write(string $locale, array $changes): void;
}
