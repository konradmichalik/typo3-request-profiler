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

namespace KonradMichalik\Typo3RequestProfiler\Profiling\Doctrine;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use KonradMichalik\Typo3RequestProfiler\Profiling\QueryCollector;

/**
 * Times direct query()/exec() calls and wraps prepared statements.
 *
 * Cross-version note (verified against DBAL 3.x/v13 and DBAL 4.x/v14):
 * - query(): Result and prepare(): Statement are identical on both majors.
 * - exec() returns int on DBAL 3 and int|string on DBAL 4. Declaring int here is
 *   covariant (a narrower subtype) against both parents; the explicit (int) cast
 *   guarantees the return value matches and never throws a TypeError.
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
        $start = microtime(true);
        try {
            return parent::query($sql);
        } finally {
            $this->collector->addQuery($sql, (microtime(true) - $start) * 1000);
        }
    }

    public function exec(string $sql): int
    {
        $start = microtime(true);
        try {
            return (int)parent::exec($sql);
        } finally {
            $this->collector->addQuery($sql, (microtime(true) - $start) * 1000);
        }
    }
}
