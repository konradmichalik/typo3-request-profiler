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

use KonradMichalik\Typo3RequestProfiler\Configuration;

defined('TYPO3') || exit;

// Registered unconditionally (query/log instrumentation is a cheap in-memory
// append) so the state-file toggle and activation decision, both evaluated
// per request, can turn on full profiling without needing a cache flush.
// Whether a profile actually gets persisted is decided per request by
// ProfilerActivation, not here.
Configuration::warnIfForcedOutsideDevelopment();
Configuration::registerProfilingDriverMiddleware();
Configuration::registerProfilingLogWriter();
