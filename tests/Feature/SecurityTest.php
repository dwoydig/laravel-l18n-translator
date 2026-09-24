<?php

declare(strict_types=1);

namespace Dwoydig\L18nTranslator\Tests\Feature;

use Dwoydig\L18nTranslator\Tests\TestCase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Manipulated requests must never write files outside the lang directory.
 */
final class SecurityTest extends TestCase
{
    private const PREFIX = '/admin/translations';

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function maliciousRequests(): array
    {
        return [
            'locale traversal' => ['/storedictionary', ['lang' => '../evil', 'dict' => ['app|*|x' => 'y']]],
            'group traversal'  => ['/storedictionary', ['lang' => 'de', 'dict' => ['app|../../evil|x' => 'y']]],
            'unknown origin'   => ['/storedictionary', ['lang' => 'de', 'dict' => ['evil|messages|x' => 'y']]],
            'target traversal' => ['/appendtotranslation', ['target' => 'app|x/../../evil', 'key' => 'a', 'languages' => ['de' => 'b']]],
            'new locale traversal' => ['/store', ['targetLanguage' => '../evil']],
            'missing traversal' => ['/missing', ['dict' => ['../evil' => ['app|*|x' => 'y']]]],
            'orphan traversal' => ['/orphans/remove', ['lang' => '../evil', 'keys' => ['app|*|x']]],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('maliciousRequests')]
    public function test_malicious_request_is_rejected_without_writing(string $path, array $payload): void
    {
        $before = $this->snapshot();

        $response = $this->post(self::PREFIX . $path, $payload);

        $this->assertRejected($response);
        $this->assertSame($before, $this->snapshot(), 'Lang directory must stay unchanged.');
        $this->assertSame(['lang'], array_values(array_diff(scandir($this->sandboxPath), ['.', '..'])), 'Nothing may be written next to the lang directory.');
    }

    private function assertRejected(TestResponse $response): void
    {
        $status = $response->getStatusCode();
        $rejected = $status >= 400 || ($status === 302 && session()->has('errors'));
        $this->assertTrue($rejected, "Expected the request to be rejected, got HTTP {$status}.");
    }

    /**
     * @return array<string, string>  Relative path → md5 of every file below the lang directory.
     */
    private function snapshot(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->langPath, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $files[substr($file->getPathname(), strlen($this->langPath))] = md5_file($file->getPathname());
        }
        ksort($files);
        return $files;
    }
}
