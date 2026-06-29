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

namespace KonradMichalik\Typo3RequestProfiler\Profiling\Instrumentation\Log;

use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\LogCollector;
use Throwable;
use TYPO3\CMS\Core\Log\LogRecord;
use TYPO3\CMS\Core\Log\Writer\{AbstractWriter, WriterInterface};
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * ProfilingLogWriter.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ProfilingLogWriter extends AbstractWriter
{
    public function writeLog(LogRecord $record): WriterInterface
    {
        try {
            // Shared via makeInstance (like QueryCollector) so the ProfileWriter
            // reads the very same request-scoped instance this writer records into.
            GeneralUtility::makeInstance(LogCollector::class)->record(
                $record->getLevel(),
                $record->getComponent(),
            );
        } catch (Throwable) { // @codeCoverageIgnore
            // Fail-safe: profiling must never break logging.
        }

        return $this;
    }
}
