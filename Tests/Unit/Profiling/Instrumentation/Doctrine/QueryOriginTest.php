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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Unit\Profiling\Instrumentation\Doctrine;

use KonradMichalik\Typo3RequestProfiler\Profiling\Instrumentation\Doctrine\QueryOrigin;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * QueryOriginTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class QueryOriginTest extends TestCase
{
    protected function setUp(): void
    {
        QueryOrigin::reset();
    }

    protected function tearDown(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_TRACE');
        QueryOrigin::reset();
    }

    #[Test]
    #[DataProvider('enabledFlagProvider')]
    public function isEnabledReflectsTheEnvironmentFlag(string|false $flag, bool $expected): void
    {
        if (false === $flag) {
            putenv('TYPO3_REQUEST_PROFILER_TRACE');
        } else {
            putenv('TYPO3_REQUEST_PROFILER_TRACE='.$flag);
        }

        self::assertSame($expected, QueryOrigin::isEnabled());
    }

    #[Test]
    public function isEnabledMemoizesTheFlagUntilReset(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_TRACE=1');
        self::assertTrue(QueryOrigin::isEnabled());

        // A later environment change is not observed until the memo is reset.
        putenv('TYPO3_REQUEST_PROFILER_TRACE=0');
        self::assertTrue(QueryOrigin::isEnabled());

        QueryOrigin::reset();
        self::assertFalse(QueryOrigin::isEnabled());
    }

    /**
     * @return iterable<string, array{string|false, bool}>
     */
    public static function enabledFlagProvider(): iterable
    {
        yield 'unset' => [false, false];
        yield 'zero' => ['0', false];
        yield 'empty' => ['', false];
        yield 'one' => ['1', true];
        yield 'truthy' => ['yes', true];
    }

    #[Test]
    public function captureReturnsNullWhenDisabled(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_TRACE=0');

        self::assertNull(QueryOrigin::capture());
    }

    #[Test]
    public function captureReturnsCallingMethodWhenEnabled(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_TRACE=1');

        $origin = QueryOrigin::capture();

        self::assertNotNull($origin);
        self::assertStringContainsString('QueryOriginTest', $origin);
        self::assertStringContainsString('captureReturnsCallingMethodWhenEnabled', $origin);
    }

    #[Test]
    public function locationReturnsNullWhenFrameHasNoFileOrLine(): void
    {
        $location = new ReflectionMethod(QueryOrigin::class, 'location');

        self::assertNull($location->invoke(null, ['line' => 5]));
    }
}
