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

namespace KonradMichalik\Typo3RequestProfiler\Profiling\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use KonradMichalik\Typo3RequestProfiler\Profiling\QueryCollector;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * ProfilingDriverMiddleware.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ProfilingDriverMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new ProfilingDriver($driver, GeneralUtility::makeInstance(QueryCollector::class));
    }
}
