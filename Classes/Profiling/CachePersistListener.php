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

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Event\AfterCachedPageIsPersistedEvent;

/**
 * The AfterCachedPageIsPersistedEvent fires only when cacheable content is
 * actually written to the page cache, i.e. on a cache MISS. We use that as the
 * "page was generated" signal. It deliberately does NOT fire for cached or
 * no_cache pages — the cache.cacheable flag in the profile disambiguates those
 * (see ProfileWriter).
 *
 * Verified against TYPO3 v13/v14: namespace TYPO3\CMS\Frontend\Event.
 */
final class CachePersistListener
{
    #[AsEventListener(identifier: 'typo3-request-profiler/cache-persist')]
    public function __invoke(AfterCachedPageIsPersistedEvent $event): void
    {
        GeneralUtility::makeInstance(QueryCollector::class)->markPageGenerated();
    }
}
