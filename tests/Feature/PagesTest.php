<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Tests\Feature;

use Dwoydig\L18nTranslator\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PagesTest extends TestCase
{
    private const PREFIX = '/admin/translations';

    /**
     * @return array<string, array{0: string}>
     */
    public static function pages(): array
    {
        return [
            'index'       => [''],
            'show de'     => ['/de'],
            'show main'   => ['/en'],
            'show vendor-only locale' => ['/fr'],
            'show new locale' => ['/it'],
            'coverage'    => ['/coverage'],
            'missing'     => ['/missing'],
            'add string'  => ['/addstring'],
            'edit string' => ['/editstrings?key=cashier%7Cmessages%7Cfailed'],
            'keys'        => ['/keys'],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_renders(string $path): void
    {
        $this->get(self::PREFIX . $path)->assertOk();
    }

    public function test_index_lists_locales_from_all_sources(): void
    {
        $this->get(self::PREFIX)
            ->assertOk()
            ->assertSee('German')
            ->assertSee('French'); // only present as vendor/cashier/fr
    }

    public function test_editor_shows_origin_column_and_laravel_labels(): void
    {
        $this->get(self::PREFIX . '/de')
            ->assertOk()
            ->assertSee('Origin')
            ->assertSee('title="Vendor package: cashier"', false)
            ->assertSee('cashier::messages.failed')
            ->assertSee('auth.throttle.minutes')
            ->assertSee('name="dict[cashier|messages|failed]"', false)
            ->assertSee('data-origin="cashier"', false);
    }

    public function test_editor_lists_orphaned_keys(): void
    {
        $this->get(self::PREFIX . '/de')
            ->assertSee('1 orphaned key')
            ->assertSee('value="app|*|Orphan"', false);
    }

    public function test_missing_page_lists_missing_keys_per_language(): void
    {
        $this->get(self::PREFIX . '/missing')
            ->assertOk()
            ->assertSee('name="dict[de][cashier|messages|failed]"', false)
            ->assertSee('name="dict[fr][app|auth|failed]"', false)
            ->assertDontSee('name="dict[de][app|*|Welcome back.]"', false);
    }

    public function test_coverage_counts_all_sources(): void
    {
        // en has 4 JSON + 3 auth + 2 cashier = 9 keys; de translates 1 + 1 + 1 = 3.
        $this->get(self::PREFIX . '/coverage')
            ->assertOk()
            ->assertSeeInOrder(['German', '33%']);
    }

    public function test_add_string_offers_every_target_file(): void
    {
        $this->get(self::PREFIX . '/addstring')
            ->assertOk()
            ->assertSee('<option value="app|*">app · en.json</option>', false)
            ->assertSee('<option value="app|auth">app · auth.php</option>', false)
            ->assertSee('<option value="cashier|messages">cashier · messages.php</option>', false);
    }

    public function test_edit_string_shows_origin_and_values(): void
    {
        $this->get(self::PREFIX . '/editstrings?key=cashier%7Cmessages%7Cpaid')
            ->assertOk()
            ->assertSee('Edit: cashier::messages.paid')
            ->assertSee('<input type="hidden" name="key" value="cashier|messages|paid">', false)
            ->assertSee('Zahlung erhalten.')
            ->assertSee('Paiement reçu.');
    }

    public function test_edit_string_without_key_is_not_found(): void
    {
        $this->get(self::PREFIX . '/editstrings')->assertNotFound();
    }

    public function test_keys_endpoint_returns_described_main_keys(): void
    {
        $this->getJson(self::PREFIX . '/keys')
            ->assertOk()
            ->assertJsonCount(9)
            ->assertJsonFragment(['id' => 'cashier|messages|paid', 'label' => 'cashier::messages.paid', 'origin' => 'cashier'])
            ->assertJsonFragment(['id' => 'app|auth|throttle.minutes', 'label' => 'auth.throttle.minutes', 'origin' => 'app']);
    }

    public function test_deepl_controls_are_hidden_when_disabled(): void
    {
        $this->get(self::PREFIX . '/de')->assertDontSee('busy ? cancelTranslation() : translateSelected()', false);
    }

    public function test_deepl_controls_are_shown_when_enabled(): void
    {
        $this->enableDeepl();

        $this->get(self::PREFIX . '/de')->assertSee('busy ? cancelTranslation() : translateSelected()', false);
    }
}
