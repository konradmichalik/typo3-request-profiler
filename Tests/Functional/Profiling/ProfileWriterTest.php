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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Functional\Profiling;

use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\{EventCollector, LogCollector, QueryCollector};
use KonradMichalik\Typo3RequestProfiler\Profiling\ProfileWriter;
use KonradMichalik\Typo3RequestProfiler\Profiling\Section\{CacheSection, DuplicateQueriesSection, EventsSection, LogSection, MemorySection, PageSection, PhpSection, QueriesSection, SlowQueriesSection, TimingSection};
use KonradMichalik\Typo3RequestProfiler\Profiling\Section\QueryAggregator;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\{Response, ServerRequest};
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;
use TYPO3\CMS\Frontend\Page\PageInformation;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

use function count;

/**
 * ProfileWriterTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ProfileWriterTest extends FunctionalTestCase
{
    protected bool $initializeDatabase = false;

    private ProfileWriter $subject;

    private QueryCollector $queryCollector;

    private LogCollector $logCollector;

    protected function setUp(): void
    {
        parent::setUp();

        $aggregator = new QueryAggregator();
        $this->subject = new ProfileWriter([
            new PageSection(),
            new CacheSection(),
            new TimingSection(),
            new MemorySection(),
            new PhpSection(),
            new QueriesSection($aggregator),
            new SlowQueriesSection($aggregator),
            new DuplicateQueriesSection($aggregator),
            new LogSection(),
            new EventsSection(),
        ]);

        $this->queryCollector = new QueryCollector();
        $this->logCollector = new LogCollector();
        GeneralUtility::setSingletonInstance(QueryCollector::class, $this->queryCollector);
        GeneralUtility::setSingletonInstance(LogCollector::class, $this->logCollector);
        GeneralUtility::setSingletonInstance(EventCollector::class, new EventCollector());
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    #[Test]
    public function writeCreatesCompactProfileAndFiltersInfrastructureQueries(): void
    {
        $pageInformation = new PageInformation();
        $pageInformation->setId(42);

        $request = (new ServerRequest('https://example.com/', 'GET'))
            ->withAttribute('frontend.page.information', $pageInformation)
            ->withAttribute('frontend.cache.instruction', new CacheInstruction());

        $this->queryCollector->addQuery('SELECT title FROM pages WHERE uid = 1', 0.5);
        $this->queryCollector->addQuery('SELECT title FROM pages WHERE uid = 2', 0.5);
        $this->queryCollector->addQuery('SELECT DATABASE()', 0.1);
        $this->logCollector->record('warning', 'App.Service.Foo');
        $this->logCollector->record('warning', 'App.Service.Foo');

        $this->subject->write($request, new Response(), 'tok_main', 12.5);

        $profile = $this->readProfile('tok_main');
        self::assertSame(ProfileWriter::SCHEMA_VERSION, $profile['schemaVersion']);
        self::assertSame('tok_main', $profile['token']);
        self::assertSame(42, $profile['page']['id']);
        self::assertSame(12.5, $profile['timing']['total_ms']);
        // The SELECT DATABASE() infrastructure query is filtered out.
        self::assertSame(2, $profile['queries']['count']);
        self::assertTrue($profile['cache']['cacheable']);
        self::assertCount(1, $profile['duplicate_queries']);
        self::assertSame(2, $profile['duplicate_queries'][0]['count']);
        self::assertSame('SELECT title FROM pages WHERE uid = ?', $profile['duplicate_queries'][0]['sql']);
        self::assertArrayHasKey('peak_mb', $profile['memory']);
        self::assertGreaterThan(0, $profile['php']['included_files']);
        self::assertSame(2, $profile['log']['count']);
        self::assertSame(['warning' => 2], $profile['log']['by_level']);
        self::assertSame('App.Service.Foo', $profile['log']['top_components'][0]['component']);
    }

    #[Test]
    public function writeKeepsSectionsInPriorityOrder(): void
    {
        $pageInformation = new PageInformation();
        $pageInformation->setId(1);

        $request = (new ServerRequest('https://example.com/', 'GET'))
            ->withAttribute('frontend.page.information', $pageInformation)
            ->withAttribute('frontend.cache.instruction', new CacheInstruction());
        $this->logCollector->record('notice', 'X');

        $this->subject->write($request, new Response(), 'tok_order', 1.0);

        self::assertSame(
            ['schemaVersion', 'token', 'time', 'method', 'url', 'status', 'page', 'cache', 'timing', 'memory', 'php', 'queries', 'slow_queries', 'duplicate_queries', 'log'],
            array_keys($this->readProfile('tok_order')),
        );
    }

    #[Test]
    public function writeRecordsDisabledCacheReasonsForUncacheablePage(): void
    {
        $instruction = new CacheInstruction();
        $instruction->disableCache('no_cache parameter was given');

        $request = (new ServerRequest('https://example.com/', 'GET'))
            ->withAttribute('frontend.cache.instruction', $instruction);

        $this->subject->write($request, new Response(), 'tok_uncached', 5.0);

        $profile = $this->readProfile('tok_uncached');
        self::assertFalse($profile['cache']['cacheable']);
        self::assertFalse($profile['cache']['hit']);
        self::assertContains('no_cache parameter was given', $profile['cache']['disabled_reasons']);
    }

    #[Test]
    public function writeOmitsLogSectionWhenNoRecordsWereCollected(): void
    {
        $request = (new ServerRequest('https://example.com/', 'GET'))
            ->withAttribute('frontend.cache.instruction', new CacheInstruction());

        $this->subject->write($request, new Response(), 'tok_nolog', 1.0);

        self::assertArrayNotHasKey('log', $this->readProfile('tok_nolog'));
    }

    #[Test]
    public function pruneRetainsOnlyTheConfiguredNumberOfProfiles(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_KEEP=3');
        try {
            for ($i = 1; $i <= 5; ++$i) {
                $this->subject->write(new ServerRequest('https://example.com/', 'GET'), new Response(), 'keep_'.$i, 1.0);
            }
        } finally {
            putenv('TYPO3_REQUEST_PROFILER_KEEP');
        }

        $files = glob(Environment::getVarPath().'/log/profiles/*.json') ?: [];
        self::assertLessThanOrEqual(3, count($files));
    }

    /**
     * @return array<string, mixed>
     */
    private function readProfile(string $token): array
    {
        $file = Environment::getVarPath().'/log/profiles/'.$token.'.json';
        self::assertFileExists($file);

        $profile = json_decode((string) file_get_contents($file), true);
        self::assertIsArray($profile);

        return $profile;
    }
}
