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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Unit\Activation;

use InvalidArgumentException;
use KonradMichalik\Typo3RequestProfiler\Activation\Duration;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * DurationTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class DurationTest extends TestCase
{
    #[Test]
    public function defaultIsFifteenMinutes(): void
    {
        self::assertSame(900, Duration::default()->seconds());
    }

    #[Test]
    #[DataProvider('validDurationProvider')]
    public function fromStringParsesSuffixedAndPlainValues(string $value, int $expectedSeconds): void
    {
        self::assertSame($expectedSeconds, Duration::fromString($value)->seconds());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function validDurationProvider(): iterable
    {
        yield 'seconds suffix' => ['30s', 30];
        yield 'minutes suffix' => ['15m', 900];
        yield 'hours suffix' => ['2h', 7200];
        yield 'plain seconds' => ['45', 45];
        yield 'padded value' => [' 5m ', 300];
        yield 'exactly the maximum' => ['604800', 604_800];
    }

    #[Test]
    #[DataProvider('invalidDurationProvider')]
    public function fromStringRejectsInvalidValues(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        Duration::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDurationProvider(): iterable
    {
        yield 'unit-only' => ['m'];
        yield 'unknown suffix' => ['15d'];
        yield 'negative' => ['-5m'];
        yield 'zero' => ['0m'];
        yield 'empty' => [''];
        yield 'one second above the maximum' => ['604801'];
        yield 'just above the maximum via hours' => ['169h']; // 608400s
        yield 'PHP_INT_MAX as plain seconds' => ['9223372036854775807'];
        yield 'overflows int multiplication via hours suffix' => ['99999999999999999999h'];
    }
}
