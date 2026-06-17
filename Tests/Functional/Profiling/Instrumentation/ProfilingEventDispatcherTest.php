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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Functional\Profiling\Instrumentation;

use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\EventCollector;
use KonradMichalik\Typo3RequestProfiler\Profiling\Instrumentation\ProfilingEventDispatcher;
use KonradMichalik\Typo3RequestProfiler\Tests\Functional\DevelopmentContextTrait;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use stdClass;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * ProfilingEventDispatcherTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ProfilingEventDispatcherTest extends FunctionalTestCase
{
    use DevelopmentContextTrait;

    protected bool $initializeDatabase = false;

    private EventCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new EventCollector();
        GeneralUtility::setSingletonInstance(EventCollector::class, $this->collector);
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        putenv('TYPO3_REQUEST_PROFILER_EVENTS');
        parent::tearDown();
    }

    #[Test]
    public function recordsTimingAndDelegatesWhenEnabled(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_EVENTS=1');
        $event = new stdClass();
        $inner = $this->innerReturning($event);

        $this->inDevelopmentContext(static function () use ($inner, $event): void {
            $result = (new ProfilingEventDispatcher($inner))->dispatch($event);

            self::assertSame($event, $result);
        });

        $events = $this->collector->getEvents();
        self::assertCount(1, $events);
        self::assertSame(stdClass::class, $events[0]['event']);
    }

    #[Test]
    public function passesThroughWithoutRecordingWhenFlagDisabled(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_EVENTS=0');
        $event = new stdClass();
        $inner = $this->innerReturning($event);

        $this->inDevelopmentContext(static function () use ($inner, $event): void {
            $result = (new ProfilingEventDispatcher($inner))->dispatch($event);

            self::assertSame($event, $result);
        });

        self::assertSame([], $this->collector->getEvents());
    }

    private function innerReturning(object $event): EventDispatcherInterface
    {
        return new class($event) implements EventDispatcherInterface {
            public function __construct(private readonly object $event) {}

            public function dispatch(object $event): object
            {
                return $this->event;
            }
        };
    }
}
