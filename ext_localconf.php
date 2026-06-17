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
use TYPO3\CMS\Core\Core\Environment;

defined('TYPO3') || exit;

// Dev-only: profiling instrumentation must never touch production requests.
if (Environment::getContext()->isDevelopment()) {
    Configuration::registerProfilingDriverMiddleware();
    Configuration::registerProfilingLogWriter();
}
