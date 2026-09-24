<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Tests\Unit;

use Dwoydig\L18nTranslator\Tests\TestCase;
use Dwoydig\L18nTranslator\Translation\TranslationRepository;
use InvalidArgumentException;

final class TranslationRepositoryTest extends TestCase
{
    private function repository(): TranslationRepository
    {
        return $this->app->make(TranslationRepository::class);
    }

    public function test_is_registered_as_singleton(): void
    {
        $this->assertSame($this->repository(), $this->repository());
    }

    public function test_locales_are_the_sorted_union_of_all_sources(): void
    {
        $this->assertSame(['de', 'en', 'fr'], $this->repository()->locales());
    }

    public function test_load_merges_json_groups_and_vendor_overrides(): void
    {
        $this->assertSame([
            'app|*|Welcome back.'      => 'Willkommen zurück.',
            'app|*|Orphan'             => 'Verwaist',
            'app|auth|failed'          => 'Diese Zugangsdaten sind ungültig.',
            'cashier|messages|paid'    => 'Zahlung erhalten.',
        ], $this->repository()->load('de'));
    }

    public function test_json_and_group_keys_with_the_same_label_do_not_collide(): void
    {
        $en = $this->repository()->load('en');

        $this->assertSame('JSON key that looks like a group key', $en['app|*|auth.failed']);
        $this->assertSame('These credentials do not match our records.', $en['app|auth|failed']);
    }

    public function test_write_routes_each_change_to_its_owning_file(): void
    {
        $this->repository()->write('fr', [
            'app|*|Log out'           => 'Se déconnecter',
            'app|auth|failed'         => 'Identifiants invalides.',
            'cashier|messages|failed' => 'Paiement échoué.',
        ]);

        $this->assertSame(['Log out' => 'Se déconnecter'], $this->readJson('fr.json'));
        $this->assertSame(['failed' => 'Identifiants invalides.'], $this->readPhp('fr/auth.php'));
        $this->assertSame(
            ['paid' => 'Paiement reçu.', 'failed' => 'Paiement échoué.'],
            $this->readPhp('vendor/cashier/fr/messages.php'),
        );
    }

    public function test_targets_list_every_file_of_the_locale(): void
    {
        $this->assertSame([
            ['target' => 'app|*', 'origin' => 'app', 'label' => 'en.json'],
            ['target' => 'app|auth', 'origin' => 'app', 'label' => 'auth.php'],
            ['target' => 'cashier|messages', 'origin' => 'cashier', 'label' => 'messages.php'],
        ], $this->repository()->targets('en'));
    }

    public function test_rejects_ids_without_a_source(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->repository()->write('de', ['unknown|messages|x' => 'y']);
    }

    public function test_rejects_vendor_json_ids(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->repository()->write('de', ['cashier|*|x' => 'y']);
    }

    public function test_rejects_path_traversal_in_locale(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->repository()->write('../evil', ['app|*|x' => 'y']);
    }

    public function test_rejects_path_traversal_in_locale_when_loading(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->repository()->load('../../etc');
    }
}
