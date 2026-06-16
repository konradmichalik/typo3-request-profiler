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

use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * ProfilingEventDispatcher.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 */
final readonly class ProfilingEventDispatcher implements EventDispatcherInterface
{
    private bool $enabled;

    private EventCollector $collector;

    public function __construct(
        private EventDispatcherInterface $inner,
    ) {
        $this->enabled = Environment::getContext()->isDevelopment() && self::isProfilingEnabled();
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
            } catch (Throwable) {
                // Fail-safe: profiling must never affect event dispatching.
            }
        }
    }

    private static function isProfilingEnabled(): bool
    {
        $flag = getenv('TYPO3_REQUEST_PROFILER_EVENTS');

        return false !== $flag && '' !== $flag && '0' !== $flag;
    }
}
