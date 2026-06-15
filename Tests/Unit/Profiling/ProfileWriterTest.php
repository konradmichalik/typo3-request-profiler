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

use KonradMichalik\Typo3RequestProfiler\Profiling\ProfileWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProfileWriterTest extends TestCase
{
    private ProfileWriter $subject;

    protected function setUp(): void
    {
        $this->subject = new ProfileWriter();
    }

    #[Test]
    #[DataProvider('normalizationProvider')]
    public function normalizeSqlReplacesLiteralsAndCollapsesInLists(string $input, string $expected): void
    {
        self::assertSame($expected, $this->subject->normalizeSql($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function normalizationProvider(): iterable
    {
        yield 'numeric literal' => [
            'SELECT * FROM tt_content WHERE uid = 5',
            'SELECT * FROM tt_content WHERE uid = ?',
        ];
        yield 'string literal' => [
            "SELECT * FROM pages WHERE slug = 'home'",
            'SELECT * FROM pages WHERE slug = ?',
        ];
        yield 'collapses whitespace' => [
            "SELECT   *\n  FROM\tpages",
            'SELECT * FROM pages',
        ];
        yield 'collapses IN list' => [
            'SELECT * FROM pages WHERE uid IN (1, 2, 3, 4)',
            'SELECT * FROM pages WHERE uid IN (?)',
        ];
        yield 'collapses already parameterised IN list' => [
            'SELECT * FROM pages WHERE uid IN (?, ?, ?)',
            'SELECT * FROM pages WHERE uid IN (?)',
        ];
    }

    #[Test]
    public function aggregateCountsAllQueriesAndDetectsNplusOne(): void
    {
        $queries = [];
        for ($i = 1; $i <= 100; ++$i) {
            $queries[] = ['sql' => 'SELECT * FROM tt_content WHERE pid = ' . $i, 'ms' => 0.5];
        }
        $queries[] = ['sql' => 'SELECT * FROM pages WHERE uid = 1', 'ms' => 1.0];

        $result = $this->subject->aggregate($queries);

        self::assertSame(101, $result['count']);
        self::assertCount(1, $result['duplicates']);
        self::assertSame('SELECT * FROM tt_content WHERE pid = ?', $result['duplicates'][0]['sql']);
        self::assertSame(100, $result['duplicates'][0]['count']);
        self::assertEqualsWithDelta(50.0, $result['duplicates'][0]['total_ms'], 0.001);
    }

    #[Test]
    public function aggregateExcludesSingleExecutionsAndSortsByCountDescending(): void
    {
        $queries = [
            ['sql' => 'SELECT a FROM x WHERE id = 1', 'ms' => 1.0],
            ['sql' => 'SELECT a FROM x WHERE id = 2', 'ms' => 1.0],
            ['sql' => 'SELECT b FROM y WHERE id = 1', 'ms' => 1.0],
            ['sql' => 'SELECT b FROM y WHERE id = 2', 'ms' => 1.0],
            ['sql' => 'SELECT b FROM y WHERE id = 3', 'ms' => 1.0],
            ['sql' => 'SELECT c FROM z WHERE id = 1', 'ms' => 1.0],
        ];

        $result = $this->subject->aggregate($queries);

        self::assertCount(2, $result['duplicates']);
        self::assertSame('SELECT b FROM y WHERE id = ?', $result['duplicates'][0]['sql']);
        self::assertSame(3, $result['duplicates'][0]['count']);
        self::assertSame(2, $result['duplicates'][1]['count']);
    }

    #[Test]
    public function aggregateHandlesEmptyQueryList(): void
    {
        $result = $this->subject->aggregate([]);

        self::assertSame(0, $result['count']);
        self::assertSame(0.0, $result['total_ms']);
        self::assertSame([], $result['duplicates']);
    }
}
