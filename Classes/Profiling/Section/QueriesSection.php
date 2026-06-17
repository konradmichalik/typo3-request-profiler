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

namespace KonradMichalik\Typo3RequestProfiler\Profiling\Section;

use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\QueryCollector;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * QueriesSection.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class QueriesSection implements ProfileSection
{
    public function __construct(
        private QueryAggregator $aggregator,
    ) {}

    public function name(): string
    {
        return 'queries';
    }

    public function priority(): int
    {
        return 60;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * @return array{count: int, total_ms: float}
     */
    public function collect(ProfileContext $context): array
    {
        $queries = $this->aggregator->applicationQueries(GeneralUtility::makeInstance(QueryCollector::class));
        $stats = $this->aggregator->aggregate($queries);

        return ['count' => $stats['count'], 'total_ms' => $stats['total_ms']];
    }
}
