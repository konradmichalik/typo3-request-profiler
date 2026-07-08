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
 * DuplicateQueriesSection.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class DuplicateQueriesSection implements ProfileSection
{
    public function __construct(
        private QueryAggregator $aggregator,
    ) {}

    public function name(): string
    {
        return 'duplicate_queries';
    }

    public function priority(): int
    {
        return 80;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * @return list<array{sql: string, count: int, total_ms: float, origin?: string}>
     */
    public function collect(ProfileContext $context): array
    {
        return $this->aggregator->statsFor(GeneralUtility::makeInstance(QueryCollector::class))['duplicates'];
    }
}
