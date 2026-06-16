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
 * EventCollector.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 */
final class EventCollector implements SingletonInterface
{
    /**
     * @var list<array{event: string, ms: float}>
     */
    private array $events = [];

    public function record(string $eventClass, float $ms): void
    {
        $this->events[] = ['event' => $eventClass, 'ms' => $ms];
    }

    /**
     * @return list<array{event: string, ms: float}>
     */
    public function getEvents(): array
    {
        return $this->events;
    }
}
