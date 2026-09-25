<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Tests\Unit;

use Dwoydig\L18nTranslator\Services\Adapters\AwsTranslateAdapter;
use Dwoydig\L18nTranslator\Tests\TestCase;
use RuntimeException;

final class AwsTranslateAdapterTest extends TestCase
{
    private AwsTranslateAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = $this->app->make(AwsTranslateAdapter::class);
    }

    public function test_is_enabled_with_all_credentials(): void
    {
        $this->enableAws();

        $this->assertTrue($this->adapter->isEnabled());
    }

    public function test_is_not_enabled_without_key(): void
    {
        config()->set('l18n-translator.aws.key', null);
        config()->set('l18n-translator.aws.secret', 'secret');
        config()->set('l18n-translator.aws.region', 'eu-west-1');

        $this->assertFalse($this->adapter->isEnabled());
    }

    public function test_is_not_enabled_without_secret(): void
    {
        config()->set('l18n-translator.aws.key', 'key');
        config()->set('l18n-translator.aws.secret', null);
        config()->set('l18n-translator.aws.region', 'eu-west-1');

        $this->assertFalse($this->adapter->isEnabled());
    }

    public function test_is_not_enabled_without_region(): void
    {
        config()->set('l18n-translator.aws.key', 'key');
        config()->set('l18n-translator.aws.secret', 'secret');
        config()->set('l18n-translator.aws.region', null);

        $this->assertFalse($this->adapter->isEnabled());
    }

    public function test_does_not_support_usage(): void
    {
        $this->assertFalse($this->adapter->supportsUsage());
        $this->assertSame([], $this->adapter->usage());
    }

    public function test_translate_throws_when_not_configured(): void
    {
        // No credentials set — isEnabled() returns false, so cfg() throws before the SDK check.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/AWS_ACCESS_KEY_ID/');

        $this->adapter->translate('Hello', 'de');
    }

    public function test_translate_throws_when_sdk_not_installed(): void
    {
        // aws/aws-sdk-php is a suggested dependency and is NOT installed in the test environment.
        // ensureSdkAvailable() should throw before any network call is attempted.
        $this->enableAws();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/aws\/aws-sdk-php/');

        $this->adapter->translate('Hello', 'de');
    }

    public function test_translate_skips_api_for_same_primary_language(): void
    {
        // Same source and target primary subtag — returns early before SDK check.
        $this->enableAws();
        config()->set('l18n-translator.main_language', 'de');

        $this->assertSame('Hello', $this->adapter->translate('Hello', 'de-AT'));
    }
}
