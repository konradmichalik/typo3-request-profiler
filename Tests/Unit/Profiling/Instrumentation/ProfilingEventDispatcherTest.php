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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Unit\Profiling\Instrumentation;

use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\EventCollector;
use KonradMichalik\Typo3RequestProfiler\Profiling\Instrumentation\ProfilingEventDispatcher;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use stdClass;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\BaseTestCase;

/**
 * ProfilingEventDispatcherTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ProfilingEventDispatcherTest extends BaseTestCase
{
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
        putenv('TYPO3_REQUEST_PROFILER_FORCE');
        parent::tearDown();
    }

    #[Test]
    public function recordsEventsWithEventsFlagAloneWithoutForce(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_EVENTS=1');
        putenv('TYPO3_REQUEST_PROFILER_FORCE');
        $event = new stdClass();

        $result = (new ProfilingEventDispatcher($this->innerReturning($event)))->dispatch($event);

        self::assertSame($event, $result);
        self::assertSame(1, $this->collector->getGroups()[stdClass::class]['count']);
    }

    #[Test]
    public function passesThroughWithoutRecordingWhenFlagIsOff(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_EVENTS');
        $event = new stdClass();

        $result = (new ProfilingEventDispatcher($this->innerReturning($event)))->dispatch($event);

        self::assertSame($event, $result);
        self::assertSame([], $this->collector->getGroups());
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
