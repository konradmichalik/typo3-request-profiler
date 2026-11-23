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

/**
 * EventCollector.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class EventCollector implements SingletonInterface
{
    /**
     * @var array<string, array{count: int, total_ms: float}>
     */
    private array $groups = [];

    public function record(string $eventClass, float $ms): void
    {
        $this->groups[$eventClass] ??= ['count' => 0, 'total_ms' => 0.0];
        ++$this->groups[$eventClass]['count'];
        $this->groups[$eventClass]['total_ms'] += $ms;
    }

    /**
     * @return array<string, array{count: int, total_ms: float}> keyed by event class
     */
    public function getGroups(): array
    {
        return $this->groups;
    }
}
