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

use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\EventCollector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * EventCollectorTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class EventCollectorTest extends TestCase
{
    private EventCollector $subject;

    protected function setUp(): void
    {
        $this->subject = new EventCollector();
    }

    #[Test]
    public function startsEmpty(): void
    {
        self::assertSame([], $this->subject->getGroups());
    }

    #[Test]
    public function recordAggregatesCountAndTimePerEventClass(): void
    {
        $this->subject->record('App\\Event\\Foo', 1.5);
        $this->subject->record('App\\Event\\Foo', 2.0);
        $this->subject->record('App\\Event\\Bar', 2.0);

        self::assertSame(
            [
                'App\\Event\\Foo' => ['count' => 2, 'total_ms' => 3.5],
                'App\\Event\\Bar' => ['count' => 1, 'total_ms' => 2.0],
            ],
            $this->subject->getGroups(),
        );
    }

    #[Test]
    public function memoryStaysFlatForRepeatedRecords(): void
    {
        $this->subject->record('A', 1.0);
        $before = memory_get_usage();
        for ($i = 0; $i < 100000; ++$i) {
            $this->subject->record('A', 1.0);
        }

        self::assertLessThan(65536, memory_get_usage() - $before);
        self::assertSame(100001, $this->subject->getGroups()['A']['count']);
    }
}
