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

use KonradMichalik\Typo3RequestProfiler\Profiling\{ProfileWriter, QueryCollector};
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\{Response, ServerRequest};
use TYPO3\CMS\Frontend\Cache\CacheInstruction;
use TYPO3\CMS\Frontend\Page\PageInformation;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

use function count;


/**
 * ProfileWriterTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 */

final class ProfileWriterTest extends FunctionalTestCase
{
    protected bool $initializeDatabase = false;

    private ProfileWriter $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new ProfileWriter();
    }

    #[Test]
    public function writeCreatesCompactProfileAndFiltersInfrastructureQueries(): void
    {
        $pageInformation = new PageInformation();
        $pageInformation->setId(42);

        $request = (new ServerRequest('https://example.com/', 'GET'))
            ->withAttribute('frontend.page.information', $pageInformation)
            ->withAttribute('frontend.cache.instruction', new CacheInstruction());

        $collector = new QueryCollector();
        $collector->addQuery('SELECT title FROM pages WHERE uid = 1', 0.5);
        $collector->addQuery('SELECT title FROM pages WHERE uid = 2', 0.5);
        $collector->addQuery('SELECT DATABASE()', 0.1);

        $this->subject->write($request, new Response(), 'tok_main', $collector, 12.5);

        $profile = $this->readProfile('tok_main');
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
    }

    #[Test]
    public function writeRecordsDisabledCacheReasonsForUncacheablePage(): void
    {
        $instruction = new CacheInstruction();
        $instruction->disableCache('no_cache parameter was given');

        $request = (new ServerRequest('https://example.com/', 'GET'))
            ->withAttribute('frontend.cache.instruction', $instruction);

        $this->subject->write($request, new Response(), 'tok_uncached', new QueryCollector(), 5.0);

        $profile = $this->readProfile('tok_uncached');
        self::assertFalse($profile['cache']['cacheable']);
        self::assertFalse($profile['cache']['hit']);
        self::assertContains('no_cache parameter was given', $profile['cache']['disabled_reasons']);
    }

    #[Test]
    public function pruneRetainsOnlyTheConfiguredNumberOfProfiles(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_KEEP=3');
        try {
            for ($i = 1; $i <= 5; ++$i) {
                $this->subject->write(
                    new ServerRequest('https://example.com/', 'GET'),
                    new Response(),
                    'keep_'.$i,
                    new QueryCollector(),
                    1.0,
                );
            }
        } finally {
            putenv('TYPO3_REQUEST_PROFILER_KEEP');
        }

        $files = glob(Environment::getVarPath().'/log/profiles/*.json') ?: [];
        self::assertLessThanOrEqual(3, count($files));
    }

    private function readProfile(string $token): array
    {
        $file = Environment::getVarPath().'/log/profiles/'.$token.'.json';
        self::assertFileExists($file);

        return (array) json_decode((string) file_get_contents($file), true);
    }
}
