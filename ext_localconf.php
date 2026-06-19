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

// Development always, every other context only via explicit opt-in. Evaluated
// here (and therefore cached), so toggling FORCE requires a cache flush.
if (Configuration::isProfilingActive()) {
    Configuration::warnIfForcedOutsideDevelopment();
    Configuration::registerProfilingDriverMiddleware();
    Configuration::registerProfilingLogWriter();
}
