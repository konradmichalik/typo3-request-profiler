<?php

declare(strict_types=1);

/*
 * This file is part of the "typo3_request_profiler" TYPO3 CMS extension.
 *
 * (c) 2026 Konrad Michalik <km@move-elevator.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KonradMichalik\Typo3RequestProfiler\Tests\Functional\Profiling;

use KonradMichalik\Typo3RequestProfiler\Profiling\{CachePersistListener, QueryCollector};
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Event\AfterCachedPageIsPersistedEvent;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;


/**
 * CachePersistListenerTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 */

final class CachePersistListenerTest extends FunctionalTestCase
{
    protected bool $initializeDatabase = false;

    #[Test]
    public function markPageGeneratedOnCachedPagePersistedEvent(): void
    {
        $collector = new QueryCollector();
        GeneralUtility::setSingletonInstance(QueryCollector::class, $collector);

        $event = new AfterCachedPageIsPersistedEvent(
            new ServerRequest('https://example.com/', 'GET'),
            'cache-identifier',
            [],
            3600,
        );

        (new CachePersistListener())($event);

        self::assertTrue($collector->isPageGenerated());
    }
}
