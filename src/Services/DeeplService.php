<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Services;

use Dwoydig\L18nTranslator\Services\Adapters\DeeplAdapter;

/**
 * Kept for backward compatibility. Use DeeplAdapter or TranslatorContract instead.
 *
 * @see \Dwoydig\L18nTranslator\Services\Adapters\DeeplAdapter
 * @see \Dwoydig\L18nTranslator\Contracts\TranslatorContract
 */
class DeeplService extends DeeplAdapter
{
}
