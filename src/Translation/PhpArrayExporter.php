<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Translation;

/**
 * Renders a nested array as a PHP language file (`<?php return [...];`) using short array syntax.
 */
final class PhpArrayExporter
{
    private const INDENT = '    ';

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function export(array $data): string
    {
        return "<?php\n\nreturn " . $this->exportArray($data, 0) . ";\n";
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function exportArray(array $data, int $depth): string
    {
        if ($data === []) {
            return '[]';
        }

        $indent = str_repeat(self::INDENT, $depth + 1);
        $lines  = [];
        foreach ($data as $key => $value) {
            $exported = is_array($value) ? $this->exportArray($value, $depth + 1) : var_export($value, true);
            $lines[]  = $indent . var_export($key, true) . ' => ' . $exported . ',';
        }

        return "[\n" . implode("\n", $lines) . "\n" . str_repeat(self::INDENT, $depth) . ']';
    }
}
