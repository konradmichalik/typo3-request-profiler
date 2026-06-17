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

namespace KonradMichalik\Typo3RequestProfiler;

use KonradMichalik\Typo3RequestProfiler\Profiling\Instrumentation\Doctrine\ProfilingDriverMiddleware;
use KonradMichalik\Typo3RequestProfiler\Profiling\Instrumentation\Log\ProfilingLogWriter;
use Psr\Log\LogLevel;
use TYPO3\CMS\Core\Utility\ArrayUtility;

use function is_array;

/**
 * Configuration.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class Configuration
{
    final public const EXT_KEY = 'typo3_request_profiler';
    final public const EXT_NAME = 'Typo3RequestProfiler';

    /**
     * Register the profiling Doctrine driver middleware. The sortable array form
     * is required on v13/v14 (the v12 string-switch API is not used).
     */
    public static function registerProfilingDriverMiddleware(): void
    {
        self::setConfVarsValue(
            ['DB', 'Connections', 'Default', 'driverMiddlewares', self::EXT_KEY.'/profiling'],
            [
                'target' => ProfilingDriverMiddleware::class,
                'after' => [
                    'typo3/core/custom-platform-driver-middleware',
                    'typo3/core/custom-pdo-driver-result-middleware',
                ],
            ],
        );
    }

    /**
     * Capture log activity per request (level + component only, never the
     * message body). Registered at the lowest level so every record is seen;
     * additive to the core writer configuration.
     */
    public static function registerProfilingLogWriter(): void
    {
        self::setConfVarsValue(
            ['LOG', 'writerConfiguration', LogLevel::DEBUG, ProfilingLogWriter::class],
            [],
        );
    }

    /**
     * Write a value into the nested $GLOBALS['TYPO3_CONF_VARS'] array. The array
     * path form keeps keys that contain a slash (e.g. the driver middleware
     * identifier) intact instead of splitting them on the default delimiter.
     *
     * @param list<string> $path
     */
    private static function setConfVarsValue(array $path, mixed $value): void
    {
        $confVars = $GLOBALS['TYPO3_CONF_VARS'] ?? null;
        if (!is_array($confVars)) {
            return;
        }

        $GLOBALS['TYPO3_CONF_VARS'] = ArrayUtility::setValueByPath($confVars, $path, $value);
    }
}
