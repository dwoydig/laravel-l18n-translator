<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Tests\Feature;

use Dwoydig\L18nTranslator\Tests\TestCase;

final class SaveTranslationsTest extends TestCase
{
    private const PREFIX = '/admin/translations';

    public function test_editor_save_writes_each_source_and_skips_unchanged_files(): void
    {
        $this->post(self::PREFIX . '/storedictionary', [
            'lang' => 'de',
            'dict' => [
                'app|*|Welcome back.'       => 'Willkommen zurück.', // unchanged
                'app|*|Log out'             => '',                   // empty and absent
                'app|auth|throttle.minutes' => 'Versuche es in :minutes Minuten.',
                'cashier|messages|failed'   => 'Zahlung fehlgeschlagen.',
                'cashier|messages|paid'     => '',                   // cleared
            ],
        ])->assertRedirect(self::PREFIX . '/de');

        $this->assertSame($this->fixture('de.json'), file_get_contents($this->langFile('de.json')));
        $this->assertSame([
            'failed'   => 'Diese Zugangsdaten sind ungültig.',
            'throttle' => ['minutes' => 'Versuche es in :minutes Minuten.'],
        ], $this->readPhp('de/auth.php'));
        $this->assertSame(['failed' => 'Zahlung fehlgeschlagen.'], $this->readPhp('vendor/cashier/de/messages.php'));
    }

    public function test_written_files_are_readable_by_laravel(): void
    {
        $this->post(self::PREFIX . '/storedictionary', [
            'lang' => 'de',
            'dict' => ['app|auth|throttle.minutes' => 'Versuche es in :minutes Minuten.'],
        ]);

        $this->app->useLangPath($this->langPath);
        $this->app->forgetInstance('translator');
        $this->app->forgetInstance('translation.loader');

        $this->assertSame('Versuche es in 5 Minuten.', trans('auth.throttle.minutes', ['minutes' => 5], 'de'));
    }

    public function test_creating_a_language_creates_its_json_file(): void
    {
        $this->post(self::PREFIX . '/store', ['targetLanguage' => 'it'])
            ->assertRedirect(self::PREFIX . '/it');

        $this->assertSame(
            ['Welcome back.' => '', 'Log out' => '', 'auth.failed' => '', 'Hello :name' => ''],
            $this->readJson('it.json'),
        );
    }

    public function test_adding_a_string_to_a_php_group(): void
    {
        $this->from(self::PREFIX . '/addstring')->post(self::PREFIX . '/appendtotranslation', [
            'target'    => 'app|auth',
            'key'       => 'reset.link',
            'languages' => ['en' => 'Reset link', 'de' => 'Link zurücksetzen', 'fr' => ''],
        ])->assertRedirect(self::PREFIX . '/addstring')->assertSessionHas('success');

        $this->assertSame(['link' => 'Reset link'], $this->readPhp('en/auth.php')['reset']);
        $this->assertSame(['link' => 'Link zurücksetzen'], $this->readPhp('de/auth.php')['reset']);
        $this->assertFileDoesNotExist($this->langFile('fr/auth.php'));
    }

    public function test_adding_a_string_to_a_vendor_file(): void
    {
        $this->post(self::PREFIX . '/appendtotranslation', [
            'target'    => 'cashier|messages',
            'key'       => 'refunded',
            'languages' => ['en' => 'Refunded', 'fr' => 'Remboursé'],
        ])->assertRedirect();

        $this->assertSame('Refunded', $this->readPhp('vendor/cashier/en/messages.php')['refunded']);
        $this->assertSame('Remboursé', $this->readPhp('vendor/cashier/fr/messages.php')['refunded']);
    }

    public function test_adding_a_string_requires_a_target_and_key(): void
    {
        $this->post(self::PREFIX . '/appendtotranslation', ['languages' => ['en' => 'x']])
            ->assertSessionHasErrors(['target', 'key']);
    }

    public function test_updating_a_key_across_languages_removes_emptied_values(): void
    {
        $this->post(self::PREFIX . '/updatealltranslations', [
            'key'       => 'cashier|messages|paid',
            'languages' => ['en' => 'Paid!', 'de' => 'Bezahlt!', 'fr' => ''],
        ])->assertRedirect(self::PREFIX . '/editstrings?key=' . urlencode('cashier|messages|paid'));

        $this->assertSame('Paid!', $this->readPhp('vendor/cashier/en/messages.php')['paid']);
        $this->assertSame(['paid' => 'Bezahlt!'], $this->readPhp('vendor/cashier/de/messages.php'));
        $this->assertSame([], $this->readPhp('vendor/cashier/fr/messages.php'));
    }

    public function test_missing_page_save_ignores_empty_values(): void
    {
        $this->post(self::PREFIX . '/missing', [
            'dict' => [
                'fr' => ['app|auth|failed' => 'Identifiants invalides.', 'app|*|Log out' => ''],
                'de' => ['cashier|messages|failed' => 'Zahlung fehlgeschlagen.'],
            ],
        ])->assertRedirect(self::PREFIX . '/missing')->assertSessionHas('success', ['2 translation(s) saved.']);

        $this->assertSame(['failed' => 'Identifiants invalides.'], $this->readPhp('fr/auth.php'));
        $this->assertFileDoesNotExist($this->langFile('fr.json'));
        $this->assertSame('Zahlung fehlgeschlagen.', $this->readPhp('vendor/cashier/de/messages.php')['failed']);
    }

    public function test_removing_orphans(): void
    {
        $this->post(self::PREFIX . '/orphans/remove', ['lang' => 'de', 'keys' => ['app|*|Orphan']])
            ->assertRedirect(self::PREFIX . '/de');

        $this->assertSame(['Welcome back.' => 'Willkommen zurück.'], $this->readJson('de.json'));
    }

    public function test_adopting_orphans_adds_them_to_the_main_language(): void
    {
        $this->post(self::PREFIX . '/orphans/adopt', ['lang' => 'de', 'keys' => ['app|*|Orphan']])
            ->assertRedirect(self::PREFIX . '/de');

        $this->assertSame('', $this->readJson('en.json')['Orphan']);
    }
}
