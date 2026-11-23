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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Unit\Profiling\Collector;

use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\LogCollector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * LogCollectorTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class LogCollectorTest extends TestCase
{
    private LogCollector $subject;

    protected function setUp(): void
    {
        $this->subject = new LogCollector();
    }

    #[Test]
    public function startsEmpty(): void
    {
        self::assertSame([], $this->subject->getCounts());
    }

    #[Test]
    public function recordCountsPerLevelAndComponent(): void
    {
        $this->subject->record('warning', 'TYPO3.CMS.Core.Foo');
        $this->subject->record('warning', 'TYPO3.CMS.Core.Foo');
        $this->subject->record('error', 'App.Service.Bar');

        self::assertSame(
            ['warning' => ['TYPO3.CMS.Core.Foo' => 2], 'error' => ['App.Service.Bar' => 1]],
            $this->subject->getCounts(),
        );
    }

    #[Test]
    public function memoryStaysFlatForRepeatedRecords(): void
    {
        $this->subject->record('info', 'A');
        $before = memory_get_usage();
        for ($i = 0; $i < 100000; ++$i) {
            $this->subject->record('info', 'A');
        }

        self::assertLessThan(65536, memory_get_usage() - $before);
        self::assertSame(['info' => ['A' => 100001]], $this->subject->getCounts());
    }
}
