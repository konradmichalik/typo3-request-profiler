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

namespace KonradMichalik\Typo3RequestProfiler\Profiling\Instrumentation;

use KonradMichalik\Typo3RequestProfiler\Configuration;
use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\EventCollector;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * ProfilingEventDispatcher.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class ProfilingEventDispatcher implements EventDispatcherInterface
{
    private bool $enabled;

    private EventCollector $collector;

    public function __construct(
        private EventDispatcherInterface $inner,
    ) {
        // Follow the same activation gate as the rest of the profiler
        // (Development, or Force outside it) so events are captured wherever
        // profiling is active — including staging with _FORCE=1.
        $this->enabled = Configuration::isProfilingActive() && Configuration::isEnvFlagEnabled('TYPO3_REQUEST_PROFILER_EVENTS');
        // Shared via makeInstance (like QueryCollector) so the writer reads the
        // very same request-scoped instance this dispatcher records into.
        $this->collector = GeneralUtility::makeInstance(EventCollector::class);
    }

    public function dispatch(object $event): object
    {
        if (!$this->enabled) {
            return $this->inner->dispatch($event);
        }

        $start = microtime(true);
        try {
            return $this->inner->dispatch($event);
        } finally {
            try {
                $this->collector->record($event::class, (microtime(true) - $start) * 1000);
            } catch (Throwable) { // @codeCoverageIgnore
                // Fail-safe: profiling must never affect event dispatching.
            }
        }
    }
}
