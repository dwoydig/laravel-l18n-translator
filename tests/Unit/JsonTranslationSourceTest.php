<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Tests\Unit;

use Dwoydig\L18nTranslator\Tests\TestCase;
use Dwoydig\L18nTranslator\Translation\Sources\JsonTranslationSource;
use Dwoydig\L18nTranslator\Translation\TranslationKey;

final class JsonTranslationSourceTest extends TestCase
{
    private function source(): JsonTranslationSource
    {
        return new JsonTranslationSource($this->langPath);
    }

    public function test_lists_json_locales(): void
    {
        $locales = $this->source()->locales();
        sort($locales);

        $this->assertSame(['de', 'en'], $locales);
    }

    public function test_loads_entries_keyed_by_id(): void
    {
        $this->assertSame([
            'app|*|Welcome back.' => 'Willkommen zurück.',
            'app|*|Orphan'        => 'Verwaist',
        ], $this->source()->load('de'));
    }

    public function test_missing_locale_loads_empty(): void
    {
        $this->assertSame([], $this->source()->load('it'));
    }

    public function test_skips_non_string_values(): void
    {
        file_put_contents($this->langFile('it.json'), '{"ok":"Sì","broken":{"nested":"x"},"number":1}');

        $this->assertSame(['app|*|ok' => 'Sì'], $this->source()->load('it'));
    }

    public function test_accepts_only_app_json_keys(): void
    {
        $source = $this->source();

        $this->assertTrue($source->accepts(TranslationKey::fromId('app|*|Hello')));
        $this->assertFalse($source->accepts(TranslationKey::fromId('app|auth|failed')));
        $this->assertFalse($source->accepts(TranslationKey::fromId('cashier|*|Hello')));
    }

    public function test_write_sets_and_removes_keys_and_keeps_others(): void
    {
        $this->source()->write('de', [
            'app|*|Log out' => 'Abmelden',
            'app|*|Orphan'  => null,
        ]);

        $this->assertSame([
            'Welcome back.' => 'Willkommen zurück.',
            'Log out'       => 'Abmelden',
        ], $this->readJson('de.json'));
    }

    public function test_write_creates_file_with_unescaped_unicode(): void
    {
        $this->source()->write('it', ['app|*|Back' => 'Già / indietro']);

        $contents = file_get_contents($this->langFile('it.json'));
        $this->assertStringContainsString('"Già / indietro"', $contents);
        $this->assertSame(['Back' => 'Già / indietro'], $this->readJson('it.json'));
    }
}
