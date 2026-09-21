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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Unit\Profiling\Instrumentation\Http;

use GuzzleHttp\Promise\{Create, PromiseInterface};
use GuzzleHttp\Psr7\{Request, Response};
use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\HttpCollector;
use KonradMichalik\Typo3RequestProfiler\Profiling\Instrumentation\Http\ProfilingHttpMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * ProfilingHttpMiddlewareTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ProfilingHttpMiddlewareTest extends TestCase
{
    private HttpCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new HttpCollector();
        GeneralUtility::setSingletonInstance(HttpCollector::class, $this->collector);
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
    }

    #[Test]
    public function recordsASuccessfulRequestWithMaskedQueryValues(): void
    {
        $handler = ProfilingHttpMiddleware::wrap(
            static fn (RequestInterface $request, array $options): PromiseInterface => Create::promiseFor(new Response(200)),
        );

        $handler(new Request('GET', 'https://api.example.org/v1/products?token=secret'), [])->wait();

        $requests = $this->collector->getRequests();
        self::assertCount(1, $requests);
        self::assertSame('GET', $requests[0]['method']);
        self::assertSame('https://api.example.org/v1/products?token=?', $requests[0]['url']);
        self::assertSame(200, $requests[0]['status']);
        self::assertGreaterThanOrEqual(0.0, $requests[0]['ms']);
    }

    #[Test]
    public function recordsARejectedRequestWithNoStatusAndStillPropagatesTheRejection(): void
    {
        $handler = ProfilingHttpMiddleware::wrap(
            static fn (RequestInterface $request, array $options): PromiseInterface => Create::rejectionFor(new RuntimeException('connect timeout')),
        );

        try {
            $handler(new Request('GET', 'https://api.example.org/'), [])->wait();
            self::fail('Expected exception was not thrown.');
        } catch (RuntimeException $exception) {
            self::assertSame('connect timeout', $exception->getMessage());
        }

        $requests = $this->collector->getRequests();
        self::assertCount(1, $requests);
        self::assertNull($requests[0]['status']);
    }
}
