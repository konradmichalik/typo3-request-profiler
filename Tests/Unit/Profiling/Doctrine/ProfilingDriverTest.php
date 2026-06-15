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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Unit\Profiling\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use KonradMichalik\Typo3RequestProfiler\Profiling\Doctrine\{ProfilingConnection, ProfilingDriver, ProfilingDriverMiddleware};
use KonradMichalik\Typo3RequestProfiler\Profiling\QueryCollector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;


/**
 * ProfilingDriverTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 */

final class ProfilingDriverTest extends TestCase
{
    #[Test]
    public function connectWrapsTheConnectionInAProfilingConnection(): void
    {
        $wrappedDriver = $this->createMock(Driver::class);
        $wrappedDriver->method('connect')->willReturn($this->createMock(Connection::class));

        $driver = new ProfilingDriver($wrappedDriver, new QueryCollector());

        self::assertInstanceOf(ProfilingConnection::class, $driver->connect([]));
    }

    #[Test]
    public function middlewareWrapsTheDriverInAProfilingDriver(): void
    {
        GeneralUtility::setSingletonInstance(QueryCollector::class, new QueryCollector());

        $wrapped = (new ProfilingDriverMiddleware())->wrap($this->createMock(Driver::class));

        self::assertInstanceOf(ProfilingDriver::class, $wrapped);

        GeneralUtility::purgeInstances();
    }
}
