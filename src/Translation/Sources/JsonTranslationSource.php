<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Translation\Sources;

use Dwoydig\L18nTranslator\Contracts\TranslationSource;
use Dwoydig\L18nTranslator\Translation\TranslationKey;
use Illuminate\Support\Facades\File;

/**
 * The app's `{lang_path}/{locale}.json` files.
 */
final class JsonTranslationSource implements TranslationSource
{
    public function __construct(private readonly string $path)
    {
    }

    public function origin(): string
    {
        return TranslationKey::APP_ORIGIN;
    }

    public function locales(): array
    {
        return array_map(
            fn(string $file): string => pathinfo($file, PATHINFO_FILENAME),
            File::glob($this->path . '/*.json'),
        );
    }

    public function groups(string $locale): array
    {
        return [TranslationKey::JSON_GROUP];
    }

    public function accepts(TranslationKey $key): bool
    {
        return $key->isApp() && $key->isJson();
    }

    public function load(string $locale): array
    {
        $entries = [];
        foreach ($this->read($locale) as $key => $value) {
            if (is_string($value) && $key !== '') {
                $entries[$this->keyFor((string) $key)->id()] = $value;
            }
        }
        return $entries;
    }

    public function write(string $locale, array $changes): void
    {
        $data = $this->read($locale);
        foreach ($changes as $id => $value) {
            $key = TranslationKey::fromId($id)->key;
            if ($value === null) {
                unset($data[$key]);
            } else {
                $data[$key] = $value;
            }
        }
        File::ensureDirectoryExists($this->path);
        File::put($this->file($locale), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function read(string $locale): array
    {
        $file = $this->file($locale);
        if (!File::exists($file)) {
            return [];
        }
        $decoded = json_decode(File::get($file), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function file(string $locale): string
    {
        return "{$this->path}/{$locale}.json";
    }

    private function keyFor(string $key): TranslationKey
    {
        return new TranslationKey(TranslationKey::APP_ORIGIN, TranslationKey::JSON_GROUP, $key);
    }
}
