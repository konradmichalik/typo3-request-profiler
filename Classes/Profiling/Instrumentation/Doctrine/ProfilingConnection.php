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

use Doctrine\DBAL\Driver\{Connection as ConnectionInterface, Result, Statement};
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\QueryCollector;

/**
 * ProfilingConnection.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ProfilingConnection extends AbstractConnectionMiddleware
{
    public function __construct(
        ConnectionInterface $wrappedConnection,
        private readonly QueryCollector $collector,
    ) {
        parent::__construct($wrappedConnection);
    }

    public function prepare(string $sql): Statement
    {
        return new ProfilingStatement(parent::prepare($sql), $this->collector, $sql);
    }

    public function query(string $sql): Result
    {
        $origin = QueryOrigin::capture();
        $start = microtime(true);
        try {
            return parent::query($sql);
        } finally {
            $this->collector->addQuery($sql, (microtime(true) - $start) * 1000, $origin);
        }
    }

    public function exec(string $sql): int
    {
        $origin = QueryOrigin::capture();
        $start = microtime(true);
        try {
            return (int) parent::exec($sql);
        } finally {
            $this->collector->addQuery($sql, (microtime(true) - $start) * 1000, $origin);
        }
    }
}
