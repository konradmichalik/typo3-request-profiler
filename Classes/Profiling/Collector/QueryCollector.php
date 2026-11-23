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

namespace KonradMichalik\Typo3RequestProfiler\Profiling\Collector;

use TYPO3\CMS\Core\SingletonInterface;

use function count;

/**
 * QueryCollector.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class QueryCollector implements SingletonInterface
{
    /**
     * Long-running processes (CLI imports, workers) never reach the writer, so
     * the collector must not grow unbounded. Beyond the cap only count and time
     * are kept.
     */
    public const MAX_ENTRIES = 10000;

    /**
     * @var list<array{sql: string, ms: float, origin: string|null}>
     */
    private array $queries = [];

    private int $omittedCount = 0;
    private float $omittedMs = 0.0;
    private bool $pageGenerated = false;

    public function addQuery(string $sql, float $ms, ?string $origin = null): void
    {
        if (count($this->queries) >= self::MAX_ENTRIES) {
            ++$this->omittedCount;
            $this->omittedMs += $ms;

            return;
        }

        $this->queries[] = ['sql' => $sql, 'ms' => $ms, 'origin' => $origin];
    }

    /**
     * @return list<array{sql: string, ms: float, origin: string|null}>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    public function getOmittedCount(): int
    {
        return $this->omittedCount;
    }

    public function getOmittedMs(): float
    {
        return $this->omittedMs;
    }

    public function isTruncated(): bool
    {
        return $this->omittedCount > 0;
    }

    public function markPageGenerated(): void
    {
        $this->pageGenerated = true;
    }

    public function isPageGenerated(): bool
    {
        return $this->pageGenerated;
    }
}
