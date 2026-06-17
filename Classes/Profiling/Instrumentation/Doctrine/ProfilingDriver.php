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

namespace KonradMichalik\Typo3RequestProfiler\Profiling\Instrumentation\Doctrine;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\QueryCollector;
use SensitiveParameter;

/**
 * ProfilingDriver.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ProfilingDriver extends AbstractDriverMiddleware
{
    public function __construct(
        DriverInterface $wrappedDriver,
        private readonly QueryCollector $collector,
    ) {
        parent::__construct($wrappedDriver);
    }

    public function connect(
        #[SensitiveParameter]
        array $params,
    ): Connection {
        return new ProfilingConnection(parent::connect($params), $this->collector);
    }
}
