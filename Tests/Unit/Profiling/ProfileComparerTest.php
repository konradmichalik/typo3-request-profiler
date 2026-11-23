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

use KonradMichalik\Typo3RequestProfiler\Profiling\ProfileComparer;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * ProfileComparerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ProfileComparerTest extends TestCase
{
    private ProfileComparer $subject;

    protected function setUp(): void
    {
        $this->subject = new ProfileComparer();
    }

    #[Test]
    public function identicalProfilesYieldZeroDeltasAndNoWarnings(): void
    {
        $profile = $this->profile();

        $result = $this->subject->compare($profile, $profile, 'a', 'a');

        self::assertSame(1, $result['schemaVersion']);
        self::assertTrue($result['comparable']);
        self::assertSame([], $result['warnings']);
        self::assertCount(10, $result['metrics']);
        foreach ($result['metrics'] as $metric) {
            self::assertEquals(0, $metric['delta']);
            self::assertEquals(0.0, $metric['delta_pct']);
        }
        $emptyDiff = ['added' => [], 'left_top_list' => [], 'changed' => []];
        self::assertSame($emptyDiff, $result['duplicate_queries']);
        self::assertSame($emptyDiff, $result['slow_queries']);
    }

    /**
     * @return iterable<string, array{string, string, int|float, int|float, int|float, float|null}>
     */
    public static function metricProvider(): iterable
    {
        yield 'timing.total_ms' => ['timing', 'total_ms', 100.0, 150.5, 50.5, 50.5];
        yield 'queries.count' => ['queries', 'count', 101, 135, 34, 33.7];
        yield 'queries.total_ms' => ['queries', 'total_ms', 38.2, 52.9, 14.7, 38.5];
        yield 'memory.peak_mb' => ['memory', 'peak_mb', 16.0, 18.0, 2.0, 12.5];
        yield 'php.included_files' => ['php', 'included_files', 400, 410, 10, 2.5];
        yield 'http.count' => ['http', 'count', 2, 3, 1, 50.0];
        yield 'http.total_ms' => ['http', 'total_ms', 200.0, 100.0, -100.0, -50.0];
        yield 'log.count' => ['log', 'count', 4, 2, -2, -50.0];
        yield 'events.count' => ['events', 'count', 10, 20, 10, 100.0];
        yield 'events.total_ms' => ['events', 'total_ms', 1.0, 1.5, 0.5, 50.0];
    }

    #[Test]
    #[DataProvider('metricProvider')]
    public function comparesEveryAggregateField(string $section, string $field, int|float $base, int|float $head, int|float $delta, ?float $pct): void
    {
        $metric = $this->subject->compare(
            $this->profile([$section => [$field => $base]]),
            $this->profile([$section => [$field => $head]]),
            'a',
            'b',
        )['metrics']["{$section}.{$field}"];

        self::assertSame($base, $metric['base']);
        self::assertSame($head, $metric['head']);
        self::assertEquals($delta, $metric['delta']);
        self::assertSame($pct, $metric['delta_pct']);
    }

    #[Test]
    public function deltaPercentIsNullWhenTheBaseIsZero(): void
    {
        $metric = $this->subject->compare(
            $this->profile(['http' => ['count' => 0]]),
            $this->profile(['http' => ['count' => 3]]),
            'a',
            'b',
        )['metrics']['http.count'];

        self::assertSame(3, $metric['delta']);
        self::assertNull($metric['delta_pct']);
    }

    #[Test]
    public function reportsTheLabelsItWasGiven(): void
    {
        $result = $this->subject->compare($this->profile(), $this->profile(), 'tokenA', '/tmp/b.json');

        self::assertSame('tokenA', $result['base']);
        self::assertSame('/tmp/b.json', $result['head']);
    }

    #[Test]
    public function aNewNPlusOnePatternAppearsUnderAdded(): void
    {
        $head = $this->profile(['duplicate_queries' => [
            ['sql' => 'SELECT COUNT(*) FROM tt_content WHERE pid = ?', 'count' => 100, 'total_ms' => 31.4],
            ['sql' => 'SELECT * FROM sys_category_record_mm WHERE uid_foreign = ?', 'count' => 24, 'total_ms' => 5.0],
        ]]);

        $result = $this->subject->compare($this->profile(), $head, 'a', 'b');

        self::assertSame(
            [['sql' => 'SELECT * FROM sys_category_record_mm WHERE uid_foreign = ?', 'count' => 24]],
            $result['duplicate_queries']['added'],
        );
        self::assertSame([], $result['duplicate_queries']['left_top_list']);
        self::assertSame([], $result['duplicate_queries']['changed']);
    }

    #[Test]
    public function aDuplicatePatternMissingFromHeadLeftTheTopList(): void
    {
        $result = $this->subject->compare($this->profile(), $this->profile(['duplicate_queries' => []]), 'a', 'b');

        self::assertSame([['sql' => 'SELECT COUNT(*) FROM tt_content WHERE pid = ?', 'count' => 100]], $result['duplicate_queries']['left_top_list']);
    }

    #[Test]
    public function aChangedDuplicateCountIsReportedWithBothValues(): void
    {
        $head = $this->profile(['duplicate_queries' => [['sql' => 'SELECT COUNT(*) FROM tt_content WHERE pid = ?', 'count' => 120, 'total_ms' => 31.4]]]);

        $result = $this->subject->compare($this->profile(), $head, 'a', 'b');

        self::assertSame(
            [['sql' => 'SELECT COUNT(*) FROM tt_content WHERE pid = ?', 'base' => 100, 'head' => 120, 'delta' => 20]],
            $result['duplicate_queries']['changed'],
        );
        self::assertSame([], $result['duplicate_queries']['added']);
    }

    #[Test]
    public function slowQueriesAreDiffedByNormalizedSql(): void
    {
        $head = $this->profile(['slow_queries' => [
            ['sql' => 'SELECT * FROM pages WHERE slug = ?', 'ms' => 20.0],
            ['sql' => 'SELECT * FROM be_users', 'ms' => 9.0],
        ]]);

        $result = $this->subject->compare($this->profile(), $head, 'a', 'b');

        self::assertSame([['sql' => 'SELECT * FROM be_users', 'ms' => 9.0]], $result['slow_queries']['added']);
        self::assertSame([], $result['slow_queries']['left_top_list']);
        self::assertSame(
            [['sql' => 'SELECT * FROM pages WHERE slug = ?', 'base' => 12.4, 'head' => 20.0, 'delta' => 7.6]],
            $result['slow_queries']['changed'],
        );
    }

    #[Test]
    public function aSlowQueryMissingFromHeadLeftTheTopList(): void
    {
        $result = $this->subject->compare($this->profile(), $this->profile(['slow_queries' => []]), 'a', 'b');

        self::assertSame([['sql' => 'SELECT * FROM pages WHERE slug = ?', 'ms' => 12.4]], $result['slow_queries']['left_top_list']);
    }

    #[Test]
    public function cacheHitMismatchWarnsAndIsNotComparable(): void
    {
        $head = $this->profile(['cache' => ['hit' => true, 'cacheable' => false, 'disabled_reasons' => []]]);

        $result = $this->subject->compare($this->profile(), $head, 'a', 'b');

        self::assertFalse($result['comparable']);
        self::assertCount(1, $result['warnings']);
        self::assertStringContainsString('cache.hit', $result['warnings'][0]);
    }

    #[Test]
    public function differentUrlWarnsAndIsNotComparable(): void
    {
        $head = $this->profile(['url' => 'https://example.ddev.site/other']);

        $result = $this->subject->compare($this->profile(), $head, 'a', 'b');

        self::assertFalse($result['comparable']);
        self::assertStringContainsString('url', $result['warnings'][0]);
    }

    #[Test]
    public function differentPageIdWarnsAndIsNotComparable(): void
    {
        $head = $this->profile(['page' => ['id' => 2, 'type' => 0]]);

        $result = $this->subject->compare($this->profile(), $head, 'a', 'b');

        self::assertFalse($result['comparable']);
        self::assertStringContainsString('page.id', $result['warnings'][0]);
    }

    #[Test]
    public function differentSchemaVersionWarnsAndIsNotComparable(): void
    {
        $head = $this->profile(['schemaVersion' => 2]);

        $result = $this->subject->compare($this->profile(), $head, 'a', 'b');

        self::assertFalse($result['comparable']);
        self::assertStringContainsString('schemaVersion', $result['warnings'][0]);
    }

    #[Test]
    public function aSectionPresentInOnlyOneProfileIsAWarningAndStaysComparable(): void
    {
        $base = $this->profile();
        unset($base['events']);

        $result = $this->subject->compare($base, $this->profile(), 'a', 'b');

        self::assertTrue($result['comparable']);
        self::assertCount(1, $result['warnings']);
        self::assertStringContainsString('events', $result['warnings'][0]);
        self::assertStringContainsString('head', $result['warnings'][0]);
        self::assertArrayNotHasKey('events.count', $result['metrics']);
        self::assertArrayNotHasKey('events.total_ms', $result['metrics']);
        self::assertArrayHasKey('queries.count', $result['metrics']);
    }

    #[Test]
    public function missingTopListsAreTreatedAsWarningNotError(): void
    {
        $head = $this->profile();
        unset($head['slow_queries']);

        $result = $this->subject->compare($this->profile(), $head, 'a', 'b');

        self::assertStringContainsString('slow_queries', $result['warnings'][0]);
        self::assertSame(['added' => [], 'left_top_list' => [], 'changed' => []], $result['slow_queries']);
    }

    #[Test]
    public function truncatedCollectorsProduceAWarning(): void
    {
        $head = $this->profile(['queries' => ['count' => 101, 'total_ms' => 38.2, 'truncated' => true]]);

        $result = $this->subject->compare($this->profile(), $head, 'a', 'b');

        self::assertTrue($result['comparable']);
        self::assertCount(1, $result['warnings']);
        self::assertStringContainsString('queries', $result['warnings'][0]);
        self::assertStringContainsString('truncated', $result['warnings'][0]);
    }

    #[Test]
    public function malformedValuesAreIgnoredInsteadOfFailing(): void
    {
        $head = $this->profile([
            'queries' => ['count' => 'many'],
            'duplicate_queries' => ['garbage', ['count' => 3], ['sql' => 'SELECT 1', 'count' => 'x']],
        ]);

        $result = $this->subject->compare($this->profile(), $head, 'a', 'b');

        self::assertArrayNotHasKey('queries.count', $result['metrics']);
        self::assertSame([], $result['duplicate_queries']['added']);
    }

    /**
     * @param array<string, mixed> $overrides replaces whole top-level keys
     *
     * @return array<string, mixed>
     */
    private function profile(array $overrides = []): array
    {
        return array_replace([
            'schemaVersion' => 1,
            'token' => 'abc',
            'url' => 'https://example.ddev.site/',
            'page' => ['id' => 1, 'type' => 0],
            'cache' => ['hit' => false, 'cacheable' => false, 'disabled_reasons' => []],
            'timing' => ['total_ms' => 142.5],
            'memory' => ['peak_mb' => 16.1, 'limit_mb' => 512, 'peak_pct' => 3.1],
            'php' => ['included_files' => 432],
            'queries' => ['count' => 101, 'total_ms' => 38.2],
            'slow_queries' => [['sql' => 'SELECT * FROM pages WHERE slug = ?', 'ms' => 12.4]],
            'duplicate_queries' => [['sql' => 'SELECT COUNT(*) FROM tt_content WHERE pid = ?', 'count' => 100, 'total_ms' => 31.4]],
            'http' => ['count' => 3, 'total_ms' => 812.4],
            'log' => ['count' => 3, 'by_level' => ['warning' => 3], 'top_components' => []],
            'events' => ['count' => 142, 'total_ms' => 12.3, 'top' => []],
        ], $overrides);
    }
}
