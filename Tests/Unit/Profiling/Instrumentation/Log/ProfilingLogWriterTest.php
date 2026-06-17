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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Unit\Profiling\Instrumentation\Log;

use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\LogCollector;
use KonradMichalik\Typo3RequestProfiler\Profiling\Instrumentation\Log\ProfilingLogWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogRecord;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * ProfilingLogWriterTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ProfilingLogWriterTest extends TestCase
{
    protected function setUp(): void
    {
        GeneralUtility::setSingletonInstance(LogCollector::class, new LogCollector());
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
    }

    #[Test]
    public function writeLogRecordsLevelAndComponentIntoSharedCollector(): void
    {
        $writer = new ProfilingLogWriter();

        $writer->writeLog(new LogRecord('App.Service.Foo', LogLevel::WARNING, 'something happened'));

        $records = GeneralUtility::makeInstance(LogCollector::class)->getRecords();

        self::assertSame([['level' => LogLevel::WARNING, 'component' => 'App.Service.Foo']], $records);
    }

    #[Test]
    public function writeLogReturnsItselfForChaining(): void
    {
        $writer = new ProfilingLogWriter();

        $result = $writer->writeLog(new LogRecord('App.Foo', LogLevel::INFO, 'msg'));

        self::assertSame($writer, $result);
    }
}
