<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Services\Adapters;

use Dwoydig\L18nTranslator\Contracts\TranslatorContract;

abstract class AbstractTranslatorAdapter implements TranslatorContract
{
    public function supportsUsage(): bool
    {
        return false;
    }

    public function usage(): array
    {
        return [];
    }

    /**
     * Replace Laravel :placeholder tokens with XML-safe tokens the translation API won't touch.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    protected function encodePlaceholders(string $text): array
    {
        $placeholders = [];
        $encoded = preg_replace_callback(
            '/(?<!\w):([a-zA-Z_][a-zA-Z0-9_]*)/',
            function ($m) use (&$placeholders) {
                $token = '__PH_' . count($placeholders) . '__';
                $placeholders[$token] = ':' . $m[1];
                return "{{$token}}";
            },
            $text
        );
        return [$encoded, $placeholders];
    }

    /**
     * Restore original :placeholder tokens after translation.
     * Handles minor formatting variations APIs may introduce around the token.
     *
     * @param array<string, string> $placeholders
     */
    protected function decodePlaceholders(string $text, array $placeholders): string
    {
        foreach ($placeholders as $token => $original) {
            $core    = preg_replace('/^__|__$/', '', $token);
            $pattern = '/\{+\s*(?:__)?' . preg_quote($core, '/') . '(?:__)?\s*\}+/u';
            $text    = preg_replace($pattern, $original, $text);
        }
        return $text;
    }
}
