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
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\{ArrayUtility, GeneralUtility};

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
     * Deployment-level switch: profiling is always active in the Development
     * context, and in any other context only via the explicit
     * TYPO3_REQUEST_PROFILER_FORCE=1 opt-in. Everywhere else it stays off.
     */
    public static function isProfilingActive(): bool
    {
        if (Environment::getContext()->isDevelopment()) {
            return true;
        }

        return '1' === getenv('TYPO3_REQUEST_PROFILER_FORCE');
    }

    /**
     * Emit a warning when profiling is force-enabled outside the Development
     * context. Called from ext_localconf.php, which is cached, so this fires
     * only on cache (re)build instead of on every request.
     */
    public static function warnIfForcedOutsideDevelopment(): void
    {
        if (Environment::getContext()->isDevelopment()) {
            return;
        }

        if ('1' !== getenv('TYPO3_REQUEST_PROFILER_FORCE')) {
            return;
        }

        GeneralUtility::makeInstance(LogManager::class)
            ->getLogger(self::class)
            ->warning(
                'Request profiling is force-enabled outside the Development context '
                .'via TYPO3_REQUEST_PROFILER_FORCE. This is meant for staging only — '
                .'never enable it in real production.',
            );
    }

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
