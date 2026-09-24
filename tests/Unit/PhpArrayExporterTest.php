<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Tests\Unit;

use Dwoydig\L18nTranslator\Translation\PhpArrayExporter;
use PHPUnit\Framework\TestCase;

final class PhpArrayExporterTest extends TestCase
{
    public function test_exports_nested_arrays_with_short_syntax(): void
    {
        $code = (new PhpArrayExporter())->export([
            'failed'   => 'Nope.',
            'throttle' => ['minutes' => 'Wait :minutes.'],
            'empty'    => [],
        ]);

        $expected = <<<'PHP'
            <?php

            return [
                'failed' => 'Nope.',
                'throttle' => [
                    'minutes' => 'Wait :minutes.',
                ],
                'empty' => [],
            ];

            PHP;

        $this->assertSame($expected, $code);
    }

    public function test_exported_code_evaluates_to_the_original_array(): void
    {
        $data = [
            'quote'   => "It's \"quoted\" \\ with backslash",
            'unicode' => 'Zurück — ✓',
            'dollar'  => '$notAVariable {$either}',
            'nested'  => ['list' => ['a', 'b'], 'int' => 3, 'bool' => true],
        ];

        $file = tempnam(sys_get_temp_dir(), 'l18n-export-');
        file_put_contents($file, (new PhpArrayExporter())->export($data));

        try {
            $this->assertSame($data, require $file);
        } finally {
            unlink($file);
        }
    }
}
