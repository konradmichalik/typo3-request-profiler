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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Unit\Profiling\Section;

use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\QueryCollector;
use KonradMichalik\Typo3RequestProfiler\Profiling\Section\QueryAggregator;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * QueryAggregatorTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class QueryAggregatorTest extends TestCase
{
    private QueryAggregator $subject;

    protected function setUp(): void
    {
        $this->subject = new QueryAggregator();
    }

    #[Test]
    public function applicationQueriesDropsInfrastructureIntrospection(): void
    {
        $collector = new QueryCollector();
        $collector->addQuery('SELECT title FROM pages WHERE uid = 1', 0.5);
        $collector->addQuery('SELECT DATABASE()', 0.1);

        $queries = $this->subject->applicationQueries($collector);

        self::assertCount(1, $queries);
        self::assertSame('SELECT title FROM pages WHERE uid = 1', $queries[0]['sql']);
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
        yield 'apostrophe in line comment does not shift quote matching' => [
            "SELECT * FROM pages -- don't\nWHERE slug = 'secret'",
            'SELECT * FROM pages WHERE slug = ?',
        ];
        yield 'apostrophe in block comment does not shift quote matching' => [
            "SELECT /* it's */ * FROM pages WHERE slug = 'secret'",
            'SELECT * FROM pages WHERE slug = ?',
        ];
        yield 'comment marker inside string literal is not a comment' => [
            "SELECT * FROM pages WHERE a = '-- x' AND b = 'y'",
            'SELECT * FROM pages WHERE a = ? AND b = ?',
        ];
        yield 'comment marker inside quoted identifier is kept' => [
            'SELECT * FROM `a--b` WHERE x = 5',
            'SELECT * FROM `a--b` WHERE x = ?',
        ];
        yield 'hex literal' => [
            'SELECT * FROM pages WHERE hash = 0xDEADBEEF',
            'SELECT * FROM pages WHERE hash = ?',
        ];
        yield 'named dcValue placeholders group with different list lengths' => [
            'SELECT * FROM pages WHERE uid IN (:dcValue1, :dcValue2, :dcValue3)',
            'SELECT * FROM pages WHERE uid IN (?)',
        ];
        yield 'double-quoted value stays as is (identifier quotes on PostgreSQL and SQLite)' => [
            'SELECT * FROM "pages" WHERE email = "max@example.org"',
            'SELECT * FROM "pages" WHERE email = "max@example.org"',
        ];
    }

    #[Test]
    public function normalizeSqlGroupsDcValueInListsOfDifferentLength(): void
    {
        self::assertSame(
            $this->subject->normalizeSql('SELECT 1 FROM t WHERE uid IN (:dcValue1, :dcValue2)'),
            $this->subject->normalizeSql('SELECT 1 FROM t WHERE uid IN (:dcValue1, :dcValue2, :dcValue3)'),
        );
    }

    #[Test]
    public function normalizeSqlCapsBulkInsertWithElisionMarker(): void
    {
        $sql = 'INSERT INTO t (a, b) VALUES '.implode(', ', array_fill(0, 500, '(?, ?)'));

        $normalized = $this->subject->normalizeSql($sql);

        self::assertSame(1000, mb_strlen($normalized));
        self::assertStringEndsWith('...', $normalized);
        self::assertStringStartsWith('INSERT INTO t (a, b) VALUES (?, ?)', $normalized);
    }

    #[Test]
    public function normalizeSqlKeepsStatementsBelowCapUntouched(): void
    {
        self::assertSame('SELECT * FROM pages', $this->subject->normalizeSql('SELECT * FROM pages'));
    }

    #[Test]
    public function aggregateAndSlowestQueriesNeverExceedTheCap(): void
    {
        $sql = 'INSERT INTO t (a) VALUES '.implode(', ', array_fill(0, 500, '(1)'));
        $queries = [['sql' => $sql, 'ms' => 1.0], ['sql' => $sql, 'ms' => 2.0]];

        $duplicates = $this->subject->aggregate($queries)['duplicates'];
        $slowest = $this->subject->slowestQueries($queries);

        self::assertLessThanOrEqual(1000, mb_strlen($duplicates[0]['sql']));
        self::assertLessThanOrEqual(1000, mb_strlen($slowest[0]['sql']));
    }

    #[Test]
    public function aggregateCountsAllQueriesAndDetectsNplusOne(): void
    {
        $queries = [];
        for ($i = 1; $i <= 100; ++$i) {
            $queries[] = ['sql' => 'SELECT * FROM tt_content WHERE pid = '.$i, 'ms' => 0.5];
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

    #[Test]
    #[DataProvider('infrastructureQueryProvider')]
    public function isInfrastructureQueryDetectsDevOnlyIntrospection(string $sql, bool $expected): void
    {
        self::assertSame($expected, $this->subject->isInfrastructureQuery($sql));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function infrastructureQueryProvider(): iterable
    {
        yield 'SELECT DATABASE()' => ['SELECT DATABASE()', true];
        yield 'information_schema columns' => ['SELECT * FROM information_schema.COLUMNS c WHERE x = 1', true];
        yield 'SHOW statement' => ['SHOW FULL TABLES', true];
        yield 'SET statement' => ['SET NAMES utf8mb4', true];
        yield 'server variable' => ['SELECT @@version', true];
        yield 'application select' => ['SELECT title FROM pages WHERE uid = 1', false];
    }

    #[Test]
    public function slowestQueriesReturnsTopFiveNormalisedAndSortedByTime(): void
    {
        $queries = [
            ['sql' => 'SELECT 1 FROM a WHERE id = 1', 'ms' => 0.5],
            ['sql' => 'SELECT 1 FROM b WHERE id = 7', 'ms' => 9.0],
            ['sql' => 'SELECT 1 FROM c WHERE id = 3', 'ms' => 2.0],
            ['sql' => 'SELECT 1 FROM d WHERE id = 4', 'ms' => 4.0],
            ['sql' => 'SELECT 1 FROM e WHERE id = 5', 'ms' => 1.0],
            ['sql' => 'SELECT 1 FROM f WHERE id = 6', 'ms' => 6.0],
        ];

        $result = $this->subject->slowestQueries($queries);

        self::assertCount(5, $result);
        self::assertSame('SELECT ? FROM b WHERE id = ?', $result[0]['sql']);
        self::assertSame(9.0, $result[0]['ms']);
        self::assertSame(6.0, $result[1]['ms']);
        self::assertSame(1.0, $result[4]['ms']);
        self::assertArrayNotHasKey('origin', $result[0]);
    }

    #[Test]
    public function aggregateKeepsFirstKnownOriginPerGroup(): void
    {
        $queries = [
            ['sql' => 'SELECT title FROM pages WHERE uid = 1', 'ms' => 0.5, 'origin' => 'App\\Demo::render (Demo.php:36)'],
            ['sql' => 'SELECT title FROM pages WHERE uid = 2', 'ms' => 0.5, 'origin' => 'App\\Demo::render (Demo.php:36)'],
        ];

        $result = $this->subject->aggregate($queries);

        self::assertCount(1, $result['duplicates']);
        self::assertSame('App\\Demo::render (Demo.php:36)', $result['duplicates'][0]['origin'] ?? null);
    }

    #[Test]
    public function originIsOmittedWhenTracingDisabled(): void
    {
        $queries = [
            ['sql' => 'SELECT a FROM x WHERE id = 1', 'ms' => 1.0, 'origin' => null],
            ['sql' => 'SELECT a FROM x WHERE id = 2', 'ms' => 1.0, 'origin' => null],
        ];

        $aggregated = $this->subject->aggregate($queries);
        $slowest = $this->subject->slowestQueries($queries);

        self::assertArrayNotHasKey('origin', $aggregated['duplicates'][0]);
        self::assertArrayNotHasKey('origin', $slowest[0]);
    }

    #[Test]
    public function slowestQueriesIncludesOriginWhenPresent(): void
    {
        $result = $this->subject->slowestQueries([
            ['sql' => 'SELECT * FROM big', 'ms' => 12.0, 'origin' => 'App\\Repo::find (Repo.php:88)'],
        ]);

        self::assertSame('App\\Repo::find (Repo.php:88)', $result[0]['origin'] ?? null);
    }

    #[Test]
    public function statsForAggregatesTheApplicationQueriesOfTheCollector(): void
    {
        $collector = new QueryCollector();
        $collector->addQuery('SELECT title FROM pages WHERE uid = 1', 0.5);
        $collector->addQuery('SELECT title FROM pages WHERE uid = 2', 0.5);
        $collector->addQuery('SELECT DATABASE()', 0.1);

        $stats = $this->subject->statsFor($collector);

        self::assertSame(2, $stats['count']);
        self::assertCount(1, $stats['duplicates']);
        self::assertSame('SELECT title FROM pages WHERE uid = ?', $stats['duplicates'][0]['sql']);
    }

    #[Test]
    public function statsForIsRecomputedWhenTheCollectorReceivesNewQueries(): void
    {
        $collector = new QueryCollector();
        $collector->addQuery('SELECT a FROM x WHERE id = 1', 1.0);

        self::assertSame(1, $this->subject->statsFor($collector)['count']);

        $collector->addQuery('SELECT a FROM x WHERE id = 2', 1.0);

        $stats = $this->subject->statsFor($collector);
        self::assertSame(2, $stats['count']);
        self::assertCount(1, $stats['duplicates']);
    }

    #[Test]
    public function statsForDistinguishesCollectorInstances(): void
    {
        $first = new QueryCollector();
        $first->addQuery('SELECT a FROM x WHERE id = 1', 1.0);
        $second = new QueryCollector();
        $second->addQuery('SELECT b FROM y WHERE id = 1', 1.0);
        $second->addQuery('SELECT b FROM y WHERE id = 2', 1.0);

        self::assertSame(1, $this->subject->statsFor($first)['count']);
        self::assertSame(2, $this->subject->statsFor($second)['count']);
        self::assertSame(1, $this->subject->statsFor($first)['count']);
    }

    #[Test]
    public function statsForKeepsCountAndTotalExactBeyondTheCap(): void
    {
        $collector = new QueryCollector();
        $total = QueryCollector::MAX_ENTRIES + 10;
        for ($i = 0; $i < $total; ++$i) {
            $collector->addQuery('SELECT title FROM pages WHERE uid = '.$i, 0.5);
        }

        $stats = $this->subject->statsFor($collector);

        self::assertSame($total, $stats['count']);
        self::assertSame(round($total * 0.5, 2), $stats['total_ms']);
        self::assertSame(QueryCollector::MAX_ENTRIES, $stats['duplicates'][0]['count']);
    }

    #[Test]
    public function statsForIsRecomputedWhenOnlyOverflowQueriesWereAdded(): void
    {
        $collector = new QueryCollector();
        for ($i = 0; $i < QueryCollector::MAX_ENTRIES; ++$i) {
            $collector->addQuery('SELECT 1', 1.0);
        }
        $before = $this->subject->statsFor($collector)['count'];

        $collector->addQuery('SELECT 1', 1.0);

        self::assertSame($before + 1, $this->subject->statsFor($collector)['count']);
    }

    #[Test]
    public function doubleDashWithoutFollowingWhitespaceIsNotAComment(): void
    {
        self::assertSame('SELECT ?--?', $this->subject->normalizeSql('SELECT 5--3'));
        self::assertSame('SELECT ? FROM t', $this->subject->normalizeSql("SELECT 5 -- note\nFROM t"));
    }

    #[Test]
    public function longStatementsDifferingOnlyAfterTheCapStayInSeparateGroups(): void
    {
        $columns = implode(', ', array_map(static fn (int $i): string => 'column_'.$i, range(1, 200)));
        $queries = [
            ['sql' => 'SELECT '.$columns.' FROM table_a WHERE uid = 1', 'ms' => 1.0],
            ['sql' => 'SELECT '.$columns.' FROM table_a WHERE uid = 2', 'ms' => 1.0],
            ['sql' => 'SELECT '.$columns.' FROM table_b WHERE uid = 1', 'ms' => 1.0],
            ['sql' => 'SELECT '.$columns.' FROM table_b WHERE uid = 2', 'ms' => 1.0],
        ];

        $duplicates = $this->subject->aggregate($queries)['duplicates'];

        self::assertCount(2, $duplicates);
        self::assertSame([2, 2], array_column($duplicates, 'count'));
    }
}
