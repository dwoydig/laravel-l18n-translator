<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Services\Adapters;

use RuntimeException;

class AwsTranslateAdapter extends AbstractTranslatorAdapter
{
    public function isEnabled(): bool
    {
        $cfg = config('l18n-translator.aws');
        return !empty($cfg['key']) && !empty($cfg['secret']) && !empty($cfg['region']);
    }

    /**
     * @throws RuntimeException when AWS Translate is not configured, the SDK is missing, or the API call fails.
     */
    public function translate(string $text, string $targetLang, ?string $sourceLang = null): string
    {
        // AWS Translate uses ISO 639-1 primary subtags (e.g. "en", "de", "pt").
        $sourcePrimary = strtolower(explode('-', $sourceLang ?? config('l18n-translator.main_language', 'en'))[0]);
        $targetPrimary = strtolower(explode('-', $targetLang)[0]);

        if ($sourcePrimary === $targetPrimary) {
            return $text;
        }

        $cfg = $this->cfg();

        $this->ensureSdkAvailable();

        [$encoded, $placeholders] = $this->encodePlaceholders($text);

        /** @var \Aws\Translate\TranslateClient $client */
        $client = new \Aws\Translate\TranslateClient([
            'version'     => 'latest',
            'region'      => $cfg['region'],
            'credentials' => [
                'key'    => $cfg['key'],
                'secret' => $cfg['secret'],
            ],
        ]);

        try {
            $result = $client->translateText([
                'Text'               => $encoded,
                'SourceLanguageCode' => $sourcePrimary,
                'TargetLanguageCode' => $targetPrimary,
            ]);
        } catch (\Aws\Exception\AwsException $e) {
            throw new RuntimeException('AWS Translate request failed: ' . $e->getAwsErrorMessage());
        }

        $translated = $result->get('TranslatedText');
        if ($translated === null) {
            throw new RuntimeException('Missing translation in AWS Translate response');
        }

        return $this->decodePlaceholders($translated, $placeholders);
    }

    /** @throws RuntimeException when AWS Translate is not configured. */
    private function cfg(): array
    {
        if (!$this->isEnabled()) {
            throw new RuntimeException(
                'AWS Translate is not configured. Set AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, ' .
                'and AWS_DEFAULT_REGION in your .env.'
            );
        }

        return config('l18n-translator.aws');
    }

    private function ensureSdkAvailable(): void
    {
        if (!class_exists(\Aws\Translate\TranslateClient::class)) {
            throw new RuntimeException(
                'The aws/aws-sdk-php package is required for AWS Translate. ' .
                'Run: composer require aws/aws-sdk-php'
            );
        }
    }
}
