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
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * ProfileWriterTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 */
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
}
