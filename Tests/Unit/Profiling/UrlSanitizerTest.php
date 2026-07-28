<?php

declare(strict_types=1);

/*
 * This file is part of the "typo3_request_profiler" TYPO3 CMS extension.
 *
 * (c) 2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KonradMichalik\Typo3RequestProfiler\Tests\Unit\Profiling;

use KonradMichalik\Typo3RequestProfiler\Profiling\UrlSanitizer;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Http\Uri;

/**
 * UrlSanitizerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class UrlSanitizerTest extends TestCase
{
    #[Test]
    #[DataProvider('urlProvider')]
    public function maskQueryValuesKeepsParameterNamesButMasksValues(string $url, string $expected): void
    {
        self::assertSame($expected, UrlSanitizer::maskQueryValues(new Uri($url)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function urlProvider(): iterable
    {
        yield 'no query string' => [
            'https://example.com/path/to/page',
            'https://example.com/path/to/page',
        ];

        yield 'single parameter' => [
            'https://example.com/?q=secret',
            'https://example.com/?q=?',
        ];

        yield 'multiple parameters' => [
            'https://example.com/search?q=john%40example.com&page=2',
            'https://example.com/search?q=?&page=?',
        ];

        yield 'extbase array parameter' => [
            'https://example.com/?tx_felogin_login%5Bhash%5D=abc123&tx_felogin_login%5Baction%5D=show',
            'https://example.com/?tx_felogin_login%5Bhash%5D=?&tx_felogin_login%5Baction%5D=?',
        ];

        yield 'valueless parameter is kept as-is' => [
            'https://example.com/?debug',
            'https://example.com/?debug',
        ];

        yield 'empty value is masked' => [
            'https://example.com/?token=',
            'https://example.com/?token=?',
        ];

        yield 'value containing equals sign' => [
            'https://example.com/?data=a=b',
            'https://example.com/?data=?',
        ];

        yield 'path and port are preserved' => [
            'https://example.com:8443/de/news?cHash=d41d8cd98f',
            'https://example.com:8443/de/news?cHash=?',
        ];
    }
}
