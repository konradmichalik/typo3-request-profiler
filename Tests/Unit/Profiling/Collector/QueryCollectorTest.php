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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Unit\Profiling\Collector;

use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\QueryCollector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * QueryCollectorTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class QueryCollectorTest extends TestCase
{
    private QueryCollector $subject;

    protected function setUp(): void
    {
        $this->subject = new QueryCollector();
    }

    #[Test]
    public function startsEmptyAndNotGenerated(): void
    {
        self::assertSame([], $this->subject->getQueries());
        self::assertFalse($this->subject->isPageGenerated());
    }

    #[Test]
    public function addQueryStoresSqlTimingAndOrigin(): void
    {
        $this->subject->addQuery('SELECT 1', 1.5);
        $this->subject->addQuery('SELECT 2', 2.0, 'App\\Demo::run (Demo.php:10)');

        $queries = $this->subject->getQueries();

        self::assertCount(2, $queries);
        self::assertSame(['sql' => 'SELECT 1', 'ms' => 1.5, 'origin' => null], $queries[0]);
        self::assertSame(['sql' => 'SELECT 2', 'ms' => 2.0, 'origin' => 'App\\Demo::run (Demo.php:10)'], $queries[1]);
    }

    #[Test]
    public function markPageGeneratedFlipsTheFlag(): void
    {
        $this->subject->markPageGenerated();

        self::assertTrue($this->subject->isPageGenerated());
    }

    #[Test]
    public function aboveTheCapOnlyCountAndTotalTimeAreKept(): void
    {
        $cap = QueryCollector::MAX_ENTRIES;
        for ($i = 0; $i < $cap + 5; ++$i) {
            $this->subject->addQuery('SELECT '.$i, 2.0);
        }

        self::assertCount($cap, $this->subject->getQueries());
        self::assertTrue($this->subject->isTruncated());
        self::assertSame(5, $this->subject->getOmittedCount());
        self::assertEqualsWithDelta(10.0, $this->subject->getOmittedMs(), 0.001);
    }

    #[Test]
    public function isNotTruncatedAtExactlyTheCap(): void
    {
        for ($i = 0; $i < QueryCollector::MAX_ENTRIES; ++$i) {
            $this->subject->addQuery('SELECT 1', 1.0);
        }

        self::assertFalse($this->subject->isTruncated());
        self::assertSame(0, $this->subject->getOmittedCount());
    }
}
