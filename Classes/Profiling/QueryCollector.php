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

use TYPO3\CMS\Core\SingletonInterface;

/**
 * QueryCollector.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class QueryCollector implements SingletonInterface
{
    /**
     * @var list<array{sql: string, ms: float, origin: string|null}>
     */
    private array $queries = [];

    private bool $pageGenerated = false;

    public function addQuery(string $sql, float $ms, ?string $origin = null): void
    {
        $this->queries[] = ['sql' => $sql, 'ms' => $ms, 'origin' => $origin];
    }

    /**
     * @return list<array{sql: string, ms: float, origin: string|null}>
     */
    public function getQueries(): array
    {
        return $this->queries;
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
