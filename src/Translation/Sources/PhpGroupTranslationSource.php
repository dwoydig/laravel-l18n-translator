<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Translation\Sources;

use Dwoydig\L18nTranslator\Contracts\TranslationSource;
use Dwoydig\L18nTranslator\Translation\PhpArrayExporter;
use Dwoydig\L18nTranslator\Translation\TranslationKey;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

/**
 * PHP group files laid out as `{path}/{locale}/{group}.php` — used for the app's
 * `lang/{locale}/*.php` files as well as for `lang/vendor/{package}/{locale}/*.php` overrides.
 * Nested arrays are flattened to dot notation (`auth.throttle` → group "auth", key "throttle").
 */
final class PhpGroupTranslationSource implements TranslationSource
{
    /**
     * @param  list<string>  $excludedDirectories  Directories below $path that are not locales (e.g. "vendor").
     */
    public function __construct(
        private readonly string $path,
        private readonly string $origin,
        private readonly PhpArrayExporter $exporter,
        private readonly array $excludedDirectories = [],
    ) {
    }

    public function origin(): string
    {
        return $this->origin;
    }

    public function locales(): array
    {
        if (!File::isDirectory($this->path)) {
            return [];
        }
        $locales = [];
        foreach (File::directories($this->path) as $directory) {
            $locale = basename($directory);
            if (!in_array($locale, $this->excludedDirectories, true) && $this->groups($locale) !== []) {
                $locales[] = $locale;
            }
        }
        return $locales;
    }

    public function groups(string $locale): array
    {
        return array_map(
            fn(string $file): string => pathinfo($file, PATHINFO_FILENAME),
            File::glob("{$this->path}/{$locale}/*.php"),
        );
    }

    public function accepts(TranslationKey $key): bool
    {
        return $key->origin === $this->origin && !$key->isJson();
    }

    public function load(string $locale): array
    {
        $entries = [];
        foreach ($this->groups($locale) as $group) {
            foreach (Arr::dot($this->read($locale, $group)) as $key => $value) {
                if (is_string($value)) {
                    $entries[(new TranslationKey($this->origin, $group, (string) $key))->id()] = $value;
                }
            }
        }
        return $entries;
    }

    public function write(string $locale, array $changes): void
    {
        $byGroup = [];
        foreach ($changes as $id => $value) {
            $key = TranslationKey::fromId($id);
            $byGroup[$key->group][$key->key] = $value;
        }

        foreach ($byGroup as $group => $groupChanges) {
            $data = $this->read($locale, $group);
            foreach ($groupChanges as $key => $value) {
                if ($value === null) {
                    Arr::forget($data, $key);
                } else {
                    Arr::set($data, $key, $value);
                }
            }
            $this->put($this->file($locale, $group), $data);
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private function read(string $locale, string $group): array
    {
        $file = $this->file($locale, $group);
        if (!File::exists($file)) {
            return [];
        }
        $data = File::getRequire($file);
        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function put(string $file, array $data): void
    {
        File::ensureDirectoryExists(dirname($file));
        File::put($file, $this->exporter->export($data));
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
    }

    private function file(string $locale, string $group): string
    {
        return "{$this->path}/{$locale}/{$group}.php";
    }
}
