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
        self::assertSame([], $this->subject->getRecords());
    }

    #[Test]
    public function recordStoresLevelAndComponent(): void
    {
        $this->subject->record('warning', 'TYPO3.CMS.Core.Foo');
        $this->subject->record('error', 'App.Service.Bar');

        $records = $this->subject->getRecords();

        self::assertCount(2, $records);
        self::assertSame(['level' => 'warning', 'component' => 'TYPO3.CMS.Core.Foo'], $records[0]);
        self::assertSame(['level' => 'error', 'component' => 'App.Service.Bar'], $records[1]);
    }
}
