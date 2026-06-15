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

use Doctrine\DBAL\Driver\{Connection, Result, Statement};
use KonradMichalik\Typo3RequestProfiler\Profiling\Doctrine\{ProfilingConnection, ProfilingStatement};
use KonradMichalik\Typo3RequestProfiler\Profiling\QueryCollector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;


/**
 * ProfilingConnectionTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 */

final class ProfilingConnectionTest extends TestCase
{
    private QueryCollector $collector;
    private Connection&MockObject $wrapped;
    private ProfilingConnection $subject;

    protected function setUp(): void
    {
        $this->collector = new QueryCollector();
        $this->wrapped = $this->createMock(Connection::class);
        $this->subject = new ProfilingConnection($this->wrapped, $this->collector);
    }

    protected function tearDown(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_TRACE');
    }

    #[Test]
    public function queryRecordsTheStatement(): void
    {
        $this->wrapped->method('query')->willReturn($this->createMock(Result::class));

        $this->subject->query('SELECT 1 FROM pages');

        $queries = $this->collector->getQueries();
        self::assertCount(1, $queries);
        self::assertSame('SELECT 1 FROM pages', $queries[0]['sql']);
        self::assertGreaterThanOrEqual(0.0, $queries[0]['ms']);
        self::assertNull($queries[0]['origin']);
    }

    #[Test]
    public function execRecordsTheStatementAndReturnsAffectedRows(): void
    {
        $this->wrapped->method('exec')->willReturn(3);

        $affected = $this->subject->exec('DELETE FROM pages WHERE uid = 5');

        self::assertSame(3, $affected);
        self::assertSame('DELETE FROM pages WHERE uid = 5', $this->collector->getQueries()[0]['sql']);
    }

    #[Test]
    public function prepareWrapsStatementAndExecuteRecordsIt(): void
    {
        $statement = $this->createMock(Statement::class);
        $statement->method('execute')->willReturn($this->createMock(Result::class));
        $this->wrapped->method('prepare')->willReturn($statement);

        $prepared = $this->subject->prepare('SELECT * FROM pages WHERE uid = ?');
        self::assertInstanceOf(ProfilingStatement::class, $prepared);

        $prepared->execute();

        $queries = $this->collector->getQueries();
        self::assertCount(1, $queries);
        self::assertSame('SELECT * FROM pages WHERE uid = ?', $queries[0]['sql']);
    }

    #[Test]
    public function capturesCallSiteOriginWhenTracingEnabled(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_TRACE=1');
        $this->wrapped->method('query')->willReturn($this->createMock(Result::class));

        $this->subject->query('SELECT 1');

        $origin = $this->collector->getQueries()[0]['origin'];
        self::assertNotNull($origin);
        self::assertStringContainsString('ProfilingConnectionTest', $origin);
    }
}
