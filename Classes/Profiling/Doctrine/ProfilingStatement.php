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

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use KonradMichalik\Typo3RequestProfiler\Profiling\QueryCollector;

/**
 * Times prepared-statement executions — the path that surfaces N+1 patterns.
 *
 * execute(): Result (no arguments) matches AbstractStatementMiddleware on both
 * DBAL 3.x (v13) and DBAL 4.x (v14).
 */
final class ProfilingStatement extends AbstractStatementMiddleware
{
    public function __construct(
        Statement $wrappedStatement,
        private readonly QueryCollector $collector,
        private readonly string $sql,
    ) {
        parent::__construct($wrappedStatement);
    }

    public function execute(): Result
    {
        $start = microtime(true);
        try {
            return parent::execute();
        } finally {
            $this->collector->addQuery($this->sql, (microtime(true) - $start) * 1000);
        }
    }
}
