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

namespace KonradMichalik\Typo3RequestProfiler\Profiling;

use function array_key_exists;
use function is_array;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;

/**
 * ProfileComparer.
 *
 * Diffs two persisted profiles. It only looks at what the artifact stores:
 * aggregates and top lists, never full query lists.
 *
 * @phpstan-type Metric array{base: int|float, head: int|float, delta: int|float, delta_pct: float|null}
 * @phpstan-type ChangedEntry array{sql: string, base: int|float, head: int|float, delta: int|float}
 * @phpstan-type TopListDiff array{added: list<array<string, int|float|string>>, left_top_list: list<array<string, int|float|string>>, changed: list<ChangedEntry>}
 * @phpstan-type Comparison array{schemaVersion: int, base: string, head: string, comparable: bool, warnings: list<string>, metrics: array<string, Metric>, duplicate_queries: TopListDiff, slow_queries: TopListDiff}
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class ProfileComparer
{
    public const SCHEMA_VERSION = 1;

    private const METRICS = [
        ['timing', 'total_ms'],
        ['queries', 'count'],
        ['queries', 'total_ms'],
        ['memory', 'peak_mb'],
        ['php', 'included_files'],
        ['http', 'count'],
        ['http', 'total_ms'],
        ['log', 'count'],
        ['events', 'count'],
        ['events', 'total_ms'],
    ];

    private const SECTIONS = [
        'page', 'cache', 'timing', 'memory', 'php', 'queries', 'slow_queries', 'duplicate_queries',
        'http', 'slow_http', 'log', 'events', 'exception',
    ];

    private const TRUNCATABLE = ['queries', 'http'];

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $head
     *
     * @return Comparison
     */
    public function compare(array $base, array $head, string $baseLabel, string $headLabel): array
    {
        $mismatches = $this->mismatches($base, $head);

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'base' => $baseLabel,
            'head' => $headLabel,
            'comparable' => [] === $mismatches,
            'warnings' => [...$mismatches, ...$this->sectionWarnings($base, $head), ...$this->truncationWarnings($base, $head)],
            'metrics' => $this->metrics($base, $head),
            'duplicate_queries' => $this->diffTopList($base, $head, 'duplicate_queries', 'count'),
            'slow_queries' => $this->diffTopList($base, $head, 'slow_queries', 'ms'),
        ];
    }

    /**
     * Differences that make the numbers meaningless to compare.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $head
     *
     * @return list<string>
     */
    private function mismatches(array $base, array $head): array
    {
        $pairs = [
            'schemaVersion' => [$base['schemaVersion'] ?? null, $head['schemaVersion'] ?? null],
            'url' => [$base['url'] ?? null, $head['url'] ?? null],
            'page.id' => [$this->nested($base, 'page', 'id'), $this->nested($head, 'page', 'id')],
            'cache.hit' => [$this->nested($base, 'cache', 'hit'), $this->nested($head, 'cache', 'hit')],
        ];

        $warnings = [];
        foreach ($pairs as $field => [$baseValue, $headValue]) {
            if ($baseValue !== $headValue) {
                $warnings[] = sprintf('%s differs (base: %s, head: %s), the numbers are not comparable', $field, $this->scalar($baseValue), $this->scalar($headValue));
            }
        }

        return $warnings;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $head
     *
     * @return list<string>
     */
    private function sectionWarnings(array $base, array $head): array
    {
        $warnings = [];
        foreach (self::SECTIONS as $section) {
            $inBase = array_key_exists($section, $base);
            if ($inBase !== array_key_exists($section, $head)) {
                $warnings[] = sprintf("section '%s' is only present in the %s profile", $section, $inBase ? 'base' : 'head');
            }
        }

        return $warnings;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $head
     *
     * @return list<string>
     */
    private function truncationWarnings(array $base, array $head): array
    {
        $warnings = [];
        foreach (['base' => $base, 'head' => $head] as $side => $profile) {
            foreach (self::TRUNCATABLE as $section) {
                if (true === $this->nested($profile, $section, 'truncated')) {
                    $warnings[] = sprintf("'%s' was truncated in the %s profile, its top lists cover only the first recorded entries", $section, $side);
                }
            }
        }

        return $warnings;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $head
     *
     * @return array<string, Metric>
     */
    private function metrics(array $base, array $head): array
    {
        $metrics = [];
        foreach (self::METRICS as [$section, $field]) {
            $metric = $this->metric($this->nested($base, $section, $field), $this->nested($head, $section, $field));
            if (null !== $metric) {
                $metrics["{$section}.{$field}"] = $metric;
            }
        }

        return $metrics;
    }

    /**
     * @return Metric|null
     */
    private function metric(mixed $base, mixed $head): ?array
    {
        if (!$this->isNumber($base) || !$this->isNumber($head)) {
            return null;
        }

        $delta = $this->delta($base, $head);

        return [
            'base' => $base,
            'head' => $head,
            'delta' => $delta,
            'delta_pct' => 0 === $base || 0.0 === $base ? null : round($delta / $base * 100, 1),
        ];
    }

    private function delta(int|float $base, int|float $head): int|float
    {
        return is_int($base) && is_int($head) ? $head - $base : round($head - $base, 2);
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $head
     *
     * @return TopListDiff
     */
    private function diffTopList(array $base, array $head, string $section, string $valueKey): array
    {
        $diff = ['added' => [], 'left_top_list' => [], 'changed' => []];
        // A list that exists on one side only is already a section warning, and
        // diffing it against nothing would report every entry as added or gone.
        if (!is_array($base[$section] ?? null) || !is_array($head[$section] ?? null)) {
            return $diff;
        }

        $baseList = $this->topList($base, $section, $valueKey);
        $headList = $this->topList($head, $section, $valueKey);

        foreach (array_diff_key($headList, $baseList) as $sql => $value) {
            $diff['added'][] = ['sql' => $sql, $valueKey => $value];
        }
        foreach (array_diff_key($baseList, $headList) as $sql => $value) {
            $diff['left_top_list'][] = ['sql' => $sql, $valueKey => $value];
        }
        foreach (array_intersect_key($headList, $baseList) as $sql => $value) {
            if ($baseList[$sql] !== $value) {
                $diff['changed'][] = ['sql' => $sql, 'base' => $baseList[$sql], 'head' => $value, 'delta' => $this->delta($baseList[$sql], $value)];
            }
        }

        return $diff;
    }

    /**
     * Entries keyed by their normalized sql. Malformed entries are skipped:
     * a profile read from an arbitrary file must not make the diff fail.
     *
     * @param array<string, mixed> $profile
     *
     * @return array<string, int|float>
     */
    private function topList(array $profile, string $section, string $valueKey): array
    {
        $entries = $profile[$section] ?? null;
        if (!is_array($entries)) {
            return [];
        }

        $list = [];
        foreach ($entries as $entry) {
            if (!is_array($entry) || !is_string($entry['sql'] ?? null) || !$this->isNumber($entry[$valueKey] ?? null)) {
                continue;
            }
            $list[$entry['sql']] ??= $entry[$valueKey];
        }

        return $list;
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function nested(array $profile, string $section, string $field): mixed
    {
        $data = $profile[$section] ?? null;

        return is_array($data) ? ($data[$field] ?? null) : null;
    }

    /**
     * @phpstan-assert-if-true int|float $value
     */
    private function isNumber(mixed $value): bool
    {
        return is_int($value) || is_float($value);
    }

    private function scalar(mixed $value): string
    {
        return json_encode($value, \JSON_THROW_ON_ERROR);
    }
}
