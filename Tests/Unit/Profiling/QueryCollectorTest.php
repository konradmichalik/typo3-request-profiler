<?php

declare(strict_types=1);

/*
 * This file is part of the "typo3_request_profiler" TYPO3 CMS extension.
 *
 * (c) 2026 Konrad Michalik <km@move-elevator.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KonradMichalik\Typo3RequestProfiler\Tests\Unit\Profiling;

use KonradMichalik\Typo3RequestProfiler\Profiling\QueryCollector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * QueryCollectorTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
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
}
