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
        self::assertSame([], $this->subject->getEvents());
    }

    #[Test]
    public function recordStoresEventClassAndTiming(): void
    {
        $this->subject->record('App\\Event\\Foo', 1.5);
        $this->subject->record('App\\Event\\Bar', 2.0);

        $events = $this->subject->getEvents();

        self::assertCount(2, $events);
        self::assertSame(['event' => 'App\\Event\\Foo', 'ms' => 1.5], $events[0]);
        self::assertSame(['event' => 'App\\Event\\Bar', 'ms' => 2.0], $events[1]);
    }
}
