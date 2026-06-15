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

namespace KonradMichalik\Typo3RequestProfiler\Profiling;

use TYPO3\CMS\Core\SingletonInterface;

/**
 * Request-scoped sink for collected SQL queries and the cache-generation signal.
 *
 * Registered as a TYPO3 singleton and always resolved via
 * GeneralUtility::makeInstance() so that the Doctrine driver chain (instantiated
 * by Doctrine, outside the DI container) and the DI-managed listener/middleware
 * share the exact same instance. This is why it is excluded from autowiring in
 * Services.yaml.
 */
final class QueryCollector implements SingletonInterface
{
    /**
     * @var list<array{sql: string, ms: float}>
     */
    private array $queries = [];

    private bool $pageGenerated = false;

    public function addQuery(string $sql, float $ms): void
    {
        $this->queries[] = ['sql' => $sql, 'ms' => $ms];
    }

    /**
     * @return list<array{sql: string, ms: float}>
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
