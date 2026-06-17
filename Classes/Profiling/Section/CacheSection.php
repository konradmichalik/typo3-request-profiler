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
use TYPO3\CMS\Frontend\Cache\CacheInstruction;

use function is_string;

/**
 * CacheSection.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class CacheSection implements ProfileSection
{
    public function name(): string
    {
        return 'cache';
    }

    public function priority(): int
    {
        return 20;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * @return array{hit: bool, cacheable: bool, disabled_reasons?: list<string>}
     */
    public function collect(ProfileContext $context): array
    {
        $cacheable = true;
        $reasons = [];
        $instruction = $context->request->getAttribute('frontend.cache.instruction');
        if ($instruction instanceof CacheInstruction) {
            $cacheable = $instruction->isCachingAllowed();
            // Why caching is disabled (no_cache, USER_INT cObj, …) — the #1 cause
            // of slow pages. Borrowed from EXT:adminpanel's GeneralInformation
            // module; getDisabledCacheReasons() exists on v13 and v14.
            $reasons = array_values(array_filter(
                $instruction->getDisabledCacheReasons(),
                is_string(...),
            ));
        }

        // A page is only a cache hit if it is cacheable AND was not (re)generated
        // this request. Gating on cacheable resolves the no_cache caveat: such
        // pages never fire AfterCachedPageIsPersistedEvent and would otherwise be
        // misreported as a hit.
        $cache = [
            'hit' => $cacheable && !GeneralUtility::makeInstance(QueryCollector::class)->isPageGenerated(),
            'cacheable' => $cacheable,
        ];
        if ([] !== $reasons) {
            $cache['disabled_reasons'] = $reasons;
        }

        return $cache;
    }
}
