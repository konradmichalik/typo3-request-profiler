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

use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\QueryCollector;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Event\AfterCachedPageIsPersistedEvent;

/**
 * CachePersistListener.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class CachePersistListener
{
    #[AsEventListener(identifier: 'typo3-request-profiler/cache-persist')]
    public function __invoke(AfterCachedPageIsPersistedEvent $event): void
    {
        GeneralUtility::makeInstance(QueryCollector::class)->markPageGenerated();
    }
}
