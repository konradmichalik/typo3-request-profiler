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

use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\QueryCollector;
use KonradMichalik\Typo3RequestProfiler\Profiling\Instrumentation\CachePersistListener;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Event\AfterCachedPageIsPersistedEvent;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * CachePersistListenerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class CachePersistListenerTest extends FunctionalTestCase
{
    protected bool $initializeDatabase = false;

    #[Test]
    public function markPageGeneratedOnCachedPagePersistedEvent(): void
    {
        $collector = new QueryCollector();
        GeneralUtility::setSingletonInstance(QueryCollector::class, $collector);

        // The listener ignores the event payload, so build it without invoking
        // the constructor — its signature differs between TYPO3 v13 and v14.
        $event = (new ReflectionClass(AfterCachedPageIsPersistedEvent::class))->newInstanceWithoutConstructor();

        (new CachePersistListener())($event);

        self::assertTrue($collector->isPageGenerated());
    }
}
