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

use KonradMichalik\Typo3RequestProfiler\Middleware\PerformanceProfilerMiddleware;

return [
    'frontend' => [
        'typo3-request-profiler/performance-profiler' => [
            'target' => PerformanceProfilerMiddleware::class,
            'after' => [
                'typo3/cms-frontend/timetracker',
            ],
        ],
    ],
];
