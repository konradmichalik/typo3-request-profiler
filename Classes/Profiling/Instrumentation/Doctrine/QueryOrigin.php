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

namespace KonradMichalik\Typo3RequestProfiler\Profiling\Instrumentation\Doctrine;

/**
 * QueryOrigin.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class QueryOrigin
{
    /**
     * Namespaces that belong to the DB plumbing and are skipped when looking for
     * the first meaningful application caller.
     *
     * @var list<string>
     */
    private const PLUMBING = [
        'Doctrine\\',
        'KonradMichalik\\Typo3RequestProfiler\\Profiling\\Instrumentation\\Doctrine\\',
        'TYPO3\\CMS\\Core\\Database\\',
    ];

    public static function isEnabled(): bool
    {
        $flag = getenv('TYPO3_REQUEST_PROFILER_TRACE');

        return false !== $flag && '' !== $flag && '0' !== $flag;
    }

    public static function capture(): ?string
    {
        if (!self::isEnabled()) {
            return null;
        }

        $callSite = null;
        foreach (debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 50) as $frame) {
            $class = $frame['class'] ?? '';
            if ('' !== $class && self::isPlumbing($class)) {
                // Remember the plumbing frame: its file/line points at the
                // application line that called into the DB layer.
                $callSite = self::location($frame) ?? $callSite;
                continue;
            }

            $callable = '' !== $class ? $class.($frame['type'] ?? '::').$frame['function'] : $frame['function'];

            return null !== $callSite ? $callable.' ('.$callSite.')' : $callable;
        }

        return null; // @codeCoverageIgnore
    }

    /**
     * @param array{file?: string, line?: int} $frame
     */
    private static function location(array $frame): ?string
    {
        if (!isset($frame['file'], $frame['line'])) {
            return null;
        }

        return basename($frame['file']).':'.$frame['line'];
    }

    private static function isPlumbing(string $class): bool
    {
        foreach (self::PLUMBING as $prefix) {
            if (str_starts_with($class, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
