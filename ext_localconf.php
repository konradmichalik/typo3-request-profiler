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

use KonradMichalik\Typo3RequestProfiler\Profiling\Doctrine\ProfilingDriverMiddleware;
use TYPO3\CMS\Core\Core\Environment;

defined('TYPO3') || exit;

// Dev-only: register the profiling Doctrine driver middleware. The sortable
// array form is required on v13/v14 (the v12 string-switch API is not used).
if (Environment::getContext()->isDevelopment()) {
    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['driverMiddlewares']['typo3_request_profiler/profiling'] = [
        'target' => ProfilingDriverMiddleware::class,
        'after' => [
            'typo3/core/custom-platform-driver-middleware',
            'typo3/core/custom-pdo-driver-result-middleware',
        ],
    ];
}
