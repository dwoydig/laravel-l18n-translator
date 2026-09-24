<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Tests\Unit;

use Dwoydig\L18nTranslator\Translation\TranslationKey;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TranslationKeyTest extends TestCase
{
    public function test_id_round_trips_including_separators_in_the_key(): void
    {
        $key = new TranslationKey('app', '*', 'Price | total :: net');

        $parsed = TranslationKey::fromId($key->id());

        $this->assertSame('app|*|Price | total :: net', $key->id());
        $this->assertSame('app', $parsed->origin);
        $this->assertSame('*', $parsed->group);
        $this->assertSame('Price | total :: net', $parsed->key);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function labels(): array
    {
        return [
            'app json'      => ['app|*|Welcome back.', 'Welcome back.'],
            'app group'     => ['app|auth|throttle.minutes', 'auth.throttle.minutes'],
            'vendor group'  => ['cashier|messages|paid', 'cashier::messages.paid'],
        ];
    }

    #[DataProvider('labels')]
    public function test_label_uses_laravel_notation(string $id, string $expected): void
    {
        $this->assertSame($expected, TranslationKey::fromId($id)->label());
    }

    public function test_type_helpers(): void
    {
        $json   = TranslationKey::fromId('app|*|Hello');
        $vendor = TranslationKey::fromId('cashier|messages|paid');

        $this->assertTrue($json->isJson());
        $this->assertTrue($json->isApp());
        $this->assertFalse($vendor->isJson());
        $this->assertFalse($vendor->isApp());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidIds(): array
    {
        return [
            'missing parts'        => ['app|Hello'],
            'traversal in group'   => ['app|../../evil|x'],
            'slash in group'       => ['app|a/b|x'],
            'traversal in origin'  => ['..|messages|x'],
            'slash in origin'      => ['a/b|messages|x'],
            'empty key'            => ['app|auth|'],
            'empty origin'         => ['|auth|x'],
        ];
    }

    #[DataProvider('invalidIds')]
    public function test_rejects_invalid_ids(string $id): void
    {
        $this->expectException(InvalidArgumentException::class);

        TranslationKey::fromId($id);
    }
}
