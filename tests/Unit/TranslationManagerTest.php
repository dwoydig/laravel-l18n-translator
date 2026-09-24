<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Tests\Unit;

use Dwoydig\L18nTranslator\Tests\TestCase;
use Dwoydig\L18nTranslator\Translation\TranslationManagerFactory;
use Dwoydig\L18nTranslator\TranslationManager;

final class TranslationManagerTest extends TestCase
{
    private function manager(string $lang): TranslationManager
    {
        return $this->app->make(TranslationManagerFactory::class)->make($lang);
    }

    public function test_factory_main_uses_configured_main_language(): void
    {
        $manager = $this->app->make(TranslationManagerFactory::class)->main();

        $this->assertSame('en', $manager->getMainLanguageIso());
    }

    public function test_save_only_rewrites_files_with_changes(): void
    {
        $manager = $this->manager('de');
        $manager->setTranslation('app|*|Welcome back.', 'Willkommen zurück.'); // unchanged
        $manager->setTranslation('cashier|messages|failed', 'Zahlung fehlgeschlagen.');
        $manager->saveTranslationFile();

        $this->assertSame($this->fixture('de.json'), file_get_contents($this->langFile('de.json')));
        $this->assertSame($this->fixture('de/auth.php'), file_get_contents($this->langFile('de/auth.php')));
        $this->assertSame(
            ['paid' => 'Zahlung erhalten.', 'failed' => 'Zahlung fehlgeschlagen.'],
            $this->readPhp('vendor/cashier/de/messages.php'),
        );
    }

    public function test_apply_translation_with_empty_value_removes_the_key(): void
    {
        $manager = $this->manager('de');
        $manager->applyTranslation('cashier|messages|paid', '');
        $manager->applyTranslation('app|*|Log out', null); // absent: nothing to remove
        $manager->saveTranslationFile();

        $this->assertSame([], $this->readPhp('vendor/cashier/de/messages.php'));
        $this->assertArrayNotHasKey('Log out', $this->readJson('de.json'));
    }

    public function test_create_empty_translation_file_prepopulates_json_keys_only(): void
    {
        $manager = $this->manager('it');
        $manager->createEmptyTranslationFile();
        $manager->saveTranslationFile();

        $this->assertSame(
            ['Welcome back.' => '', 'Log out' => '', 'auth.failed' => '', 'Hello :name' => ''],
            $this->readJson('it.json'),
        );
        $this->assertDirectoryDoesNotExist($this->langFile('it'));
        $this->assertDirectoryDoesNotExist($this->langFile('vendor/cashier/it'));
    }

    public function test_merge_translations_describes_every_main_key(): void
    {
        $manager = $this->manager('de');
        $rows = collect(TranslationManager::mergeTranslations($manager->getMainLanguage(), $manager->getTranslationLanguage()))
            ->keyBy('id');

        $this->assertSame([
            'id'          => 'cashier|messages|failed',
            'label'       => 'cashier::messages.failed',
            'origin'      => 'cashier',
            'original'    => 'Payment failed.',
            'translation' => null,
        ], $rows['cashier|messages|failed']);
        $this->assertSame('Willkommen zurück.', $rows['app|*|Welcome back.']['translation']);
    }

    public function test_orphaned_translations(): void
    {
        $this->assertSame(
            [['id' => 'app|*|Orphan', 'label' => 'Orphan', 'origin' => 'app', 'value' => 'Verwaist']],
            $this->manager('de')->orphanedTranslations(),
        );
    }

    public function test_get_all_for_key_returns_every_language(): void
    {
        $this->assertSame(
            ['en' => 'Payment received.', 'de' => 'Zahlung erhalten.', 'fr' => 'Paiement reçu.'],
            $this->manager('en')->getAllForKey('cashier|messages|paid'),
        );
    }

    public function test_language_files_put_the_main_language_first(): void
    {
        $files = $this->manager('de')->getLanguageFiles()->values();

        $this->assertSame(['en', 'de', 'fr'], $files->pluck('filename')->all());
        $this->assertSame('German', $files[1]->name);
        $this->assertSame('🇩🇪', $files[1]->flag);
        $this->assertFalse($files[1]->rtl);
    }

    public function test_locale_helpers(): void
    {
        $this->assertSame('🇦🇺', TranslationManager::localeToFlag('en-AU'));
        $this->assertSame('🇬🇧', TranslationManager::localeToFlag('en'));
        $this->assertTrue(TranslationManager::isRtl('ar'));
        $this->assertTrue(TranslationManager::isRtl('he'));
        $this->assertFalse(TranslationManager::isRtl('de'));
    }
}
