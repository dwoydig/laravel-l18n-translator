<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Translation;

use InvalidArgumentException;

/**
 * Identifies a single translation entry across all sources.
 *
 * The serialized id has the form `{origin}|{group}|{key}`, where `origin` is "app" or a
 * vendor package name and `group` is the PHP group file name, or "*" for JSON files.
 * Origin and group never contain "|", so the key itself may contain any character.
 */
final class TranslationKey
{
    public const APP_ORIGIN = 'app';
    public const JSON_GROUP = '*';

    private const SEGMENT_PATTERN = '/^[A-Za-z0-9_.-]+$/';
    private const GROUP_PATTERN   = '/^([A-Za-z0-9_-]+|\*)$/';

    public function __construct(
        public readonly string $origin,
        public readonly string $group,
        public readonly string $key,
    ) {
        if (!preg_match(self::SEGMENT_PATTERN, $origin) || str_contains($origin, '..')) {
            throw new InvalidArgumentException("Invalid translation origin '{$origin}'.");
        }
        if (!preg_match(self::GROUP_PATTERN, $group)) {
            throw new InvalidArgumentException("Invalid translation group '{$group}'.");
        }
        if ($key === '') {
            throw new InvalidArgumentException('Translation key must not be empty.');
        }
    }

    /**
     * Parses a serialized `{origin}|{group}|{key}` id.
     *
     * @throws InvalidArgumentException  When the id is malformed.
     */
    public static function fromId(string $id): self
    {
        $parts = explode('|', $id, 3);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException("Invalid translation id '{$id}'.");
        }
        return new self(...$parts);
    }

    /**
     * Returns the serialized id used in forms, URLs and in-memory maps.
     */
    public function id(): string
    {
        return "{$this->origin}|{$this->group}|{$this->key}";
    }

    /**
     * Returns the key as it is referenced in code, e.g. `Welcome`, `auth.failed` or `package::group.key`.
     */
    public function label(): string
    {
        if ($this->isJson()) {
            return $this->key;
        }
        $grouped = "{$this->group}.{$this->key}";
        return $this->isApp() ? $grouped : "{$this->origin}::{$grouped}";
    }

    public function isJson(): bool
    {
        return $this->group === self::JSON_GROUP;
    }

    public function isApp(): bool
    {
        return $this->origin === self::APP_ORIGIN;
    }
}
