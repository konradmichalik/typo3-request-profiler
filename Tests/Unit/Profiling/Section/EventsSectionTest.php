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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Unit\Profiling\Section;

use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\EventCollector;
use KonradMichalik\Typo3RequestProfiler\Profiling\Section\{EventsSection, ProfileContext};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Http\{Response, ServerRequest};
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * EventsSectionTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class EventsSectionTest extends TestCase
{
    private EventsSection $subject;

    private EventCollector $collector;

    protected function setUp(): void
    {
        $this->subject = new EventsSection();
        $this->collector = new EventCollector();
        GeneralUtility::setSingletonInstance(EventCollector::class, $this->collector);
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        putenv('TYPO3_REQUEST_PROFILER_EVENTS');
    }

    #[Test]
    public function isNamedEventsWithLowestPriority(): void
    {
        self::assertSame('events', $this->subject->name());
        self::assertSame(100, $this->subject->priority());
    }

    #[Test]
    public function isDisabledByDefaultAndEnabledViaEnvironmentFlag(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_EVENTS');
        self::assertFalse($this->subject->isEnabled());

        putenv('TYPO3_REQUEST_PROFILER_EVENTS=0');
        self::assertFalse($this->subject->isEnabled());

        putenv('TYPO3_REQUEST_PROFILER_EVENTS=1');
        self::assertTrue($this->subject->isEnabled());
    }

    #[Test]
    public function collectReturnsNullWhenNoEventsWereCollected(): void
    {
        self::assertNull($this->subject->collect($this->context()));
    }

    #[Test]
    public function collectSumsCountAndTimePerEventClass(): void
    {
        for ($i = 0; $i < 100; ++$i) {
            $this->collector->record('Core\\Cache\\Event\\Persist', 0.1);
        }
        $this->collector->record('Core\\Routing\\Event\\Match', 2.0);

        $result = $this->subject->collect($this->context());

        self::assertNotNull($result);
        self::assertSame(101, $result['count']);
        self::assertEqualsWithDelta(12.0, $result['total_ms'], 0.001);
        self::assertCount(2, $result['top']);
        self::assertSame('Core\\Cache\\Event\\Persist', $result['top'][0]['event']);
        self::assertSame(100, $result['top'][0]['count']);
        self::assertEqualsWithDelta(10.0, $result['top'][0]['total_ms'], 0.001);
    }

    #[Test]
    public function collectSortsTopByTotalTimeDescending(): void
    {
        $this->collector->record('A', 1.0);
        $this->collector->record('B', 5.0);
        $this->collector->record('C', 3.0);

        $result = $this->subject->collect($this->context());

        self::assertNotNull($result);
        self::assertSame(['B', 'C', 'A'], array_column($result['top'], 'event'));
    }

    #[Test]
    public function collectCapsTopAtTwenty(): void
    {
        for ($i = 0; $i < 30; ++$i) {
            $this->collector->record('Event'.$i, (float) $i);
        }

        $result = $this->subject->collect($this->context());

        self::assertNotNull($result);
        self::assertSame(30, $result['count']);
        self::assertCount(20, $result['top']);
        self::assertSame('Event29', $result['top'][0]['event']);
    }

    private function context(): ProfileContext
    {
        return new ProfileContext(new ServerRequest('https://example.com/', 'GET'), new Response(), 'tok', 1.0);
    }
}
