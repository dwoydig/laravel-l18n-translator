<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Tests\Unit;

use Dwoydig\L18nTranslator\Tests\TestCase;
use Dwoydig\L18nTranslator\Translation\PhpArrayExporter;
use Dwoydig\L18nTranslator\Translation\Sources\PhpGroupTranslationSource;
use Dwoydig\L18nTranslator\Translation\TranslationKey;

final class PhpGroupTranslationSourceTest extends TestCase
{
    private function appSource(): PhpGroupTranslationSource
    {
        return new PhpGroupTranslationSource($this->langPath, 'app', new PhpArrayExporter(), ['vendor']);
    }

    private function vendorSource(): PhpGroupTranslationSource
    {
        return new PhpGroupTranslationSource($this->langFile('vendor/cashier'), 'cashier', new PhpArrayExporter());
    }

    public function test_lists_locales_excluding_the_vendor_directory(): void
    {
        $locales = $this->appSource()->locales();
        sort($locales);

        $this->assertSame(['de', 'en'], $locales);
    }

    public function test_lists_vendor_locales(): void
    {
        $locales = $this->vendorSource()->locales();
        sort($locales);

        $this->assertSame(['de', 'en', 'fr'], $locales);
    }

    public function test_missing_directory_has_no_locales(): void
    {
        $source = new PhpGroupTranslationSource($this->langFile('does-not-exist'), 'app', new PhpArrayExporter());

        $this->assertSame([], $source->locales());
    }

    public function test_lists_groups(): void
    {
        $this->assertSame(['auth'], $this->appSource()->groups('en'));
        $this->assertSame([], $this->appSource()->groups('it'));
    }

    public function test_load_flattens_nested_keys_and_skips_non_strings(): void
    {
        $this->assertSame([
            'app|auth|failed'           => 'These credentials do not match our records.',
            'app|auth|throttle.minutes' => 'Try again in :minutes minutes.',
            'app|auth|throttle.seconds' => 'Try again in :seconds seconds.',
        ], $this->appSource()->load('en'));
    }

    public function test_accepts_only_group_keys_of_its_origin(): void
    {
        $this->assertTrue($this->vendorSource()->accepts(TranslationKey::fromId('cashier|messages|paid')));
        $this->assertFalse($this->vendorSource()->accepts(TranslationKey::fromId('app|messages|paid')));
        $this->assertFalse($this->appSource()->accepts(TranslationKey::fromId('app|*|Hello')));
    }

    public function test_write_updates_nested_keys_and_preserves_non_string_values(): void
    {
        $this->appSource()->write('en', [
            'app|auth|throttle.minutes' => 'Wait :minutes minutes.',
            'app|auth|throttle.seconds' => null,
            'app|auth|reset.link'       => 'Reset link',
        ]);

        $this->assertSame([
            'failed'   => 'These credentials do not match our records.',
            'throttle' => ['minutes' => 'Wait :minutes minutes.'],
            'meta'     => ['count' => 3],
            'reset'    => ['link' => 'Reset link'],
        ], $this->readPhp('en/auth.php'));
    }

    public function test_write_creates_missing_locale_directory_and_group(): void
    {
        $this->vendorSource()->write('it', ['cashier|messages|paid' => 'Pagamento ricevuto.']);

        $this->assertSame(['paid' => 'Pagamento ricevuto.'], $this->readPhp('vendor/cashier/it/messages.php'));
    }

    public function test_write_only_touches_groups_with_changes(): void
    {
        file_put_contents($this->langFile('en/validation.php'), "<?php\n// keep me\nreturn ['required' => 'Required.'];\n");

        $this->appSource()->write('en', ['app|auth|failed' => 'Changed.']);

        $this->assertStringContainsString('// keep me', file_get_contents($this->langFile('en/validation.php')));
    }
}
