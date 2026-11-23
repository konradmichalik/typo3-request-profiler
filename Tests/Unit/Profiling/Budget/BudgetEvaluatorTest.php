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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Unit\Profiling\Budget;

use KonradMichalik\Typo3RequestProfiler\Profiling\Budget\BudgetEvaluator;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * BudgetEvaluatorTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class BudgetEvaluatorTest extends TestCase
{
    private BudgetEvaluator $subject;

    protected function setUp(): void
    {
        $this->subject = new BudgetEvaluator();
    }

    #[Test]
    #[DataProvider('budgetKeys')]
    public function everyKeyReadsItsPersistedField(string $key, int|float $actual): void
    {
        $result = $this->subject->evaluate('ref', $this->profile(), $this->budgets([$key => $actual]));

        self::assertSame('ok', $result['status']);
        self::assertSame([['key' => $key, 'actual' => $actual, 'limit' => $actual, 'status' => 'ok']], $result['checks']);
    }

    #[Test]
    #[DataProvider('budgetKeys')]
    public function everyKeyFailsAboveItsLimit(string $key, int|float $actual): void
    {
        $limit = $actual - 0.01;
        $result = $this->subject->evaluate('ref', $this->profile(), $this->budgets([$key => $limit]));

        self::assertSame('violation', $result['status']);
        self::assertSame('fail', $result['checks'][0]['status']);
        self::assertSame($actual, $result['checks'][0]['actual']);
    }

    /**
     * @return iterable<string, array{string, int|float}>
     */
    public static function budgetKeys(): iterable
    {
        yield 'timing.total_ms' => ['timing.total_ms', 142.5];
        yield 'queries.count' => ['queries.count', 101];
        yield 'queries.total_ms' => ['queries.total_ms', 38.2];
        yield 'duplicate_queries.max_count' => ['duplicate_queries.max_count', 100];
        yield 'http.count' => ['http.count', 3];
        yield 'http.total_ms' => ['http.total_ms', 812.4];
        yield 'memory.peak_mb' => ['memory.peak_mb', 16.1];
        yield 'memory.peak_pct' => ['memory.peak_pct', 3.1];
        yield 'log.by_level.error' => ['log.by_level.error', 2];
    }

    #[Test]
    public function aValueEqualToTheLimitIsWithinBudget(): void
    {
        $result = $this->subject->evaluate('ref', $this->profile(), $this->budgets(['queries.count' => 101]));

        self::assertSame('ok', $result['checks'][0]['status']);
    }

    #[Test]
    public function aDuplicateViolationCarriesTheOffendingSql(): void
    {
        $profile = $this->profile();
        $profile['duplicate_queries'] = [
            ['sql' => 'SELECT 1', 'count' => 3, 'total_ms' => 1.0],
            ['sql' => 'SELECT * FROM sys_category_record_mm WHERE uid_foreign = ?', 'count' => 24, 'total_ms' => 5.0],
        ];

        $result = $this->subject->evaluate('ref', $profile, $this->budgets(['duplicate_queries.max_count' => 5]));

        self::assertSame(24, $result['checks'][0]['actual']);
        self::assertSame('SELECT * FROM sys_category_record_mm WHERE uid_foreign = ?', $result['checks'][0]['sql'] ?? null);
    }

    #[Test]
    public function aPassingDuplicateCheckCarriesNoSql(): void
    {
        $result = $this->subject->evaluate('ref', $this->profile(), $this->budgets(['duplicate_queries.max_count' => 500]));

        self::assertArrayNotHasKey('sql', $result['checks'][0]);
    }

    #[Test]
    public function sectionsThatAreAbsentWhenEmptyCountAsZero(): void
    {
        $profile = ['schemaVersion' => 1, 'url' => 'https://example.org/', 'cache' => ['hit' => false]];
        $budgets = $this->budgets([
            'queries.count' => 0, 'queries.total_ms' => 0, 'duplicate_queries.max_count' => 0,
            'http.count' => 0, 'http.total_ms' => 0, 'log.by_level.error' => 0,
        ]);

        $result = $this->subject->evaluate('ref', $profile, $budgets);

        self::assertSame('ok', $result['status']);
        self::assertSame([0, 0, 0, 0, 0, 0], array_column($result['checks'], 'actual'));
    }

    #[Test]
    public function aMissingMeasurementIsReportedNotAvailableInsteadOfPassing(): void
    {
        $profile = $this->profile();
        $profile['memory'] = ['peak_mb' => 16.1, 'limit_mb' => null];
        unset($profile['timing']);

        $result = $this->subject->evaluate('ref', $profile, $this->budgets(['memory.peak_pct' => 50, 'timing.total_ms' => 300]));

        self::assertSame('ok', $result['status']);
        self::assertSame(['n/a', 'n/a'], array_column($result['checks'], 'status'));
        self::assertSame([null, null], array_column($result['checks'], 'actual'));
    }

    #[Test]
    public function aCacheHitProfileIsSkippedAndNeverChecked(): void
    {
        $profile = $this->profile();
        $profile['cache'] = ['hit' => true];

        $result = $this->subject->evaluate('ref', $profile, $this->budgets(['queries.count' => 1]));

        self::assertSame('skipped', $result['status']);
        self::assertSame([], $result['checks']);
        self::assertStringContainsString('page cache hit', $result['notices'][0]);
    }

    #[Test]
    public function aTruncatedQuerySectionAddsANoticeButIsStillEvaluated(): void
    {
        $profile = $this->profile();
        $profile['queries'] = ['count' => 101, 'total_ms' => 38.2, 'truncated' => true];

        $result = $this->subject->evaluate('ref', $profile, $this->budgets(['queries.count' => 1]));

        self::assertSame('violation', $result['status']);
        self::assertCount(1, $result['notices']);
        self::assertStringContainsString('truncated', $result['notices'][0]);
    }

    #[Test]
    public function pathAndTokenAreExposed(): void
    {
        $profile = $this->profile();
        $profile['url'] = 'https://example.org/news/?page=?';

        $result = $this->subject->evaluate('some-file.json', $profile, $this->budgets([]));

        self::assertSame('some-file.json', $result['reference']);
        self::assertSame('abc', $result['token']);
        self::assertSame('/news/', $result['path']);
    }

    #[Test]
    public function aPageOverrideBeatsTheDefaultByPath(): void
    {
        $profile = $this->profile();
        $profile['url'] = 'https://example.org/news?page=?';
        $budgets = [
            'defaults' => ['queries.count' => 100, 'timing.total_ms' => 300],
            'pages' => [['match' => ['path' => '/news'], 'budgets' => ['queries.count' => 140]]],
        ];

        $result = $this->subject->evaluate('ref', $profile, $budgets);

        self::assertSame('ok', $result['status']);
        self::assertSame(['queries.count' => 140, 'timing.total_ms' => 300], array_column($result['checks'], 'limit', 'key'));
    }

    #[Test]
    public function aPageOverrideBeatsTheDefaultByPageId(): void
    {
        $budgets = [
            'defaults' => ['queries.count' => 100],
            'pages' => [['match' => ['page_id' => 1], 'budgets' => ['queries.count' => 140]]],
        ];

        $result = $this->subject->evaluate('ref', $this->profile(), $budgets);

        self::assertSame(140, $result['checks'][0]['limit']);
    }

    #[Test]
    public function nonMatchingPagesFallBackToTheDefaults(): void
    {
        $budgets = [
            'defaults' => ['queries.count' => 100],
            'pages' => [
                ['match' => ['path' => '/other'], 'budgets' => ['queries.count' => 500]],
                ['match' => ['page_id' => 2], 'budgets' => ['queries.count' => 500]],
            ],
        ];

        $result = $this->subject->evaluate('ref', $this->profile(), $budgets);

        self::assertSame('violation', $result['status']);
        self::assertSame(100, $result['checks'][0]['limit']);
    }

    #[Test]
    public function aMatchWithPathAndPageIdNeedsBoth(): void
    {
        $budgets = [
            'defaults' => ['queries.count' => 100],
            'pages' => [['match' => ['path' => '/', 'page_id' => 2], 'budgets' => ['queries.count' => 500]]],
        ];

        $result = $this->subject->evaluate('ref', $this->profile(), $budgets);

        self::assertSame(100, $result['checks'][0]['limit']);
    }

    #[Test]
    public function aLaterMatchingPageEntryWins(): void
    {
        $budgets = [
            'defaults' => ['queries.count' => 100],
            'pages' => [
                ['match' => ['page_id' => 1], 'budgets' => ['queries.count' => 200]],
                ['match' => ['path' => '/'], 'budgets' => ['queries.count' => 300]],
            ],
        ];

        $result = $this->subject->evaluate('ref', $this->profile(), $budgets);

        self::assertSame(300, $result['checks'][0]['limit']);
    }

    #[Test]
    public function theRootPathIsUsedWhenTheUrlHasNone(): void
    {
        $profile = $this->profile();
        $profile['url'] = 'https://example.org';
        $budgets = ['defaults' => [], 'pages' => [['match' => ['path' => '/'], 'budgets' => ['queries.count' => 500]]]];

        $result = $this->subject->evaluate('ref', $profile, $budgets);

        self::assertSame('/', $result['path']);
        self::assertSame(500, $result['checks'][0]['limit']);
    }

    #[Test]
    public function aPageWithoutPageSectionNeverMatchesByPageId(): void
    {
        $profile = $this->profile();
        unset($profile['page']);
        $budgets = ['defaults' => [], 'pages' => [['match' => ['page_id' => 1], 'budgets' => ['queries.count' => 500]]]];

        self::assertSame([], $this->subject->evaluate('ref', $profile, $budgets)['checks']);
    }

    #[Test]
    public function theReportSummarizesViolationsAndSkippedProfiles(): void
    {
        $hit = $this->profile();
        $hit['cache'] = ['hit' => true];
        $over = $this->profile();
        $over['queries'] = ['count' => 500, 'total_ms' => 1.0];

        $report = $this->subject->report(['a' => $this->profile(), 'b' => $over, 'c' => $hit], $this->budgets(['queries.count' => 200]));

        self::assertSame(1, $report['schemaVersion']);
        self::assertSame(['profiles' => 3, 'within_budget' => 1, 'over_budget' => 1, 'skipped' => 1], $report['summary']);
        self::assertSame(['ok', 'violation', 'skipped'], array_column($report['profiles'], 'status'));
        self::assertSame(['a', 'b', 'c'], array_column($report['profiles'], 'reference'));
    }

    /**
     * @param array<string, int|float> $defaults
     *
     * @return array{defaults: array<string, int|float>, pages: list<never>}
     */
    private function budgets(array $defaults): array
    {
        return ['defaults' => $defaults, 'pages' => []];
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(): array
    {
        return [
            'schemaVersion' => 1,
            'token' => 'abc',
            'url' => 'https://example.ddev.site/',
            'page' => ['id' => 1, 'type' => 0],
            'cache' => ['hit' => false, 'cacheable' => false, 'disabled_reasons' => []],
            'timing' => ['total_ms' => 142.5],
            'memory' => ['peak_mb' => 16.1, 'limit_mb' => 512, 'peak_pct' => 3.1],
            'queries' => ['count' => 101, 'total_ms' => 38.2],
            'duplicate_queries' => [['sql' => 'SELECT COUNT(*) FROM tt_content WHERE pid = ?', 'count' => 100, 'total_ms' => 31.4]],
            'http' => ['count' => 3, 'total_ms' => 812.4],
            'log' => ['count' => 3, 'by_level' => ['error' => 2, 'notice' => 1], 'top_components' => []],
        ];
    }
}
