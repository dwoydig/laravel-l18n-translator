<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Translation;

use Dwoydig\L18nTranslator\Contracts\TranslationSource;
use Dwoydig\L18nTranslator\Translation\Sources\JsonTranslationSource;
use Dwoydig\L18nTranslator\Translation\Sources\PhpGroupTranslationSource;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Aggregates all translation sources (app JSON, app PHP groups, vendor overrides)
 * into one flat id → value map per locale and routes writes back to the owning source.
 */
final class TranslationRepository
{
    private const VENDOR_DIRECTORY = 'vendor';
    private const LOCALE_PATTERN   = '/^[A-Za-z0-9_-]+$/';

    /**
     * @param  list<TranslationSource>  $sources
     */
    public function __construct(private readonly array $sources)
    {
    }

    /**
     * Builds the repository from a Laravel lang directory:
     * `{locale}.json`, `{locale}/{group}.php` and `vendor/{package}/{locale}/{group}.php`.
     */
    public static function discover(string $langPath, PhpArrayExporter $exporter): self
    {
        $sources = [
            new JsonTranslationSource($langPath),
            new PhpGroupTranslationSource($langPath, TranslationKey::APP_ORIGIN, $exporter, [self::VENDOR_DIRECTORY]),
        ];

        $vendorPath = $langPath . '/' . self::VENDOR_DIRECTORY;
        $packages   = File::isDirectory($vendorPath) ? File::directories($vendorPath) : [];
        sort($packages);
        foreach ($packages as $packagePath) {
            $sources[] = new PhpGroupTranslationSource($packagePath, basename($packagePath), $exporter);
        }

        return new self($sources);
    }

    /**
     * Returns every locale present in at least one source.
     *
     * @return list<string>
     */
    public function locales(): array
    {
        $locales = [];
        foreach ($this->sources as $source) {
            $locales = [...$locales, ...$source->locales()];
        }
        $locales = array_values(array_unique(array_filter(
            $locales,
            fn(string $locale): bool => (bool) preg_match(self::LOCALE_PATTERN, $locale),
        )));
        sort($locales);
        return $locales;
    }

    /**
     * Returns the files new keys can be added to, based on the groups of the given locale.
     *
     * @return list<array{target: string, origin: string, label: string}>  `target` is the `{origin}|{group}` id prefix.
     */
    public function targets(string $locale): array
    {
        $this->assertValidLocale($locale);
        $targets = [];
        foreach ($this->sources as $source) {
            foreach ($source->groups($locale) as $group) {
                $targets[] = [
                    'target' => $source->origin() . '|' . $group,
                    'origin' => $source->origin(),
                    'label'  => $group === TranslationKey::JSON_GROUP ? "{$locale}.json" : "{$group}.php",
                ];
            }
        }
        return $targets;
    }

    /**
     * Loads all translations of a locale from every source.
     *
     * @return array<string, string>  Translation id → value.
     */
    public function load(string $locale): array
    {
        $this->assertValidLocale($locale);
        $entries = [];
        foreach ($this->sources as $source) {
            $entries += $source->load($locale);
        }
        return $entries;
    }

    /**
     * Persists changes for a locale; each change is written to the source that owns its key.
     *
     * @param  array<string, string|null>  $changes  Translation id → new value (null removes the key).
     * @throws InvalidArgumentException  When an id is malformed or belongs to no source.
     */
    public function write(string $locale, array $changes): void
    {
        $this->assertValidLocale($locale);
        $bySource = [];
        foreach ($changes as $id => $value) {
            $bySource[$this->sourceIndexFor(TranslationKey::fromId($id))][$id] = $value;
        }
        foreach ($bySource as $index => $sourceChanges) {
            $this->sources[$index]->write($locale, $sourceChanges);
        }
    }

    private function sourceIndexFor(TranslationKey $key): int
    {
        foreach ($this->sources as $index => $source) {
            if ($source->accepts($key)) {
                return $index;
            }
        }
        throw new InvalidArgumentException("No translation source for '{$key->id()}'.");
    }

    private function assertValidLocale(string $locale): void
    {
        if (!preg_match(self::LOCALE_PATTERN, $locale)) {
            throw new InvalidArgumentException("Invalid locale '{$locale}'.");
        }
    }
}
