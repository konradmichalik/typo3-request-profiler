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

use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\HttpCollector;
use KonradMichalik\Typo3RequestProfiler\Profiling\Instrumentation\Http\ProfilingHttpClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

use function count;

/**
 * ProfilingHttpClientTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ProfilingHttpClientTest extends TestCase
{
    private HttpCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new HttpCollector();
    }

    #[Test]
    public function recordsMethodMaskedUrlStatusAndDurationOnceTheContentIsRead(): void
    {
        $response = $this->client(new StubResponse(['http_code' => 200, 'total_time' => 0.25]))
            ->request('GET', 'https://api.example.org/v1/products?token=secret');

        $response->getContent();

        $requests = $this->collector->getRequests();
        self::assertCount(1, $requests);
        self::assertSame('GET', $requests[0]['method']);
        self::assertSame('https://api.example.org/v1/products?token=?', $requests[0]['url']);
        self::assertSame(200, $requests[0]['status']);
        self::assertEqualsWithDelta(250.0, $requests[0]['ms'], 0.001);
    }

    #[Test]
    public function recordsNothingBeforeTheResponseCompleted(): void
    {
        $response = $this->client(new StubResponse(['http_code' => 200, 'total_time' => 0.1]))
            ->request('GET', 'https://api.example.org/');

        $response->getStatusCode();
        $response->getHeaders();

        self::assertSame([], $this->collector->getRequests());
    }

    #[Test]
    public function recordsACompletedResponseOnlyOnce(): void
    {
        $response = $this->client(new StubResponse(['http_code' => 200, 'total_time' => 0.1]))
            ->request('GET', 'https://api.example.org/');

        $response->getContent();
        $response->toArray();
        unset($response);

        self::assertCount(1, $this->collector->getRequests());
    }

    #[Test]
    public function recordsAResponseThatWasNeverReadWhenItIsDestroyed(): void
    {
        $response = $this->client(new StubResponse(['http_code' => 204, 'total_time' => 0.1]))
            ->request('DELETE', 'https://api.example.org/items/1');

        unset($response);

        $requests = $this->collector->getRequests();
        self::assertCount(1, $requests);
        self::assertSame('DELETE', $requests[0]['method']);
        self::assertSame(204, $requests[0]['status']);
    }

    #[Test]
    public function recordsATransportFailureWithoutStatusAndRethrowsIt(): void
    {
        $response = $this->client(new StubResponse(['http_code' => 0, 'total_time' => 0.0], new class('connect timeout') extends RuntimeException implements TransportExceptionInterface {}))
            ->request('GET', 'https://api.example.org/');

        try {
            $response->getContent();
            self::fail('Expected exception was not thrown.');
        } catch (TransportExceptionInterface $exception) {
            self::assertSame('connect timeout', $exception->getMessage());
        }

        $requests = $this->collector->getRequests();
        self::assertCount(1, $requests);
        self::assertNull($requests[0]['status']);
    }

    #[Test]
    public function recordsAnErrorStatusEvenWhenTheContentThrows(): void
    {
        $response = $this->client(new StubResponse(['http_code' => 503, 'total_time' => 0.1], new RuntimeException('503')))
            ->request('GET', 'https://api.example.org/');

        try {
            $response->getContent();
        } catch (RuntimeException) {
        }

        self::assertSame(503, $this->collector->getRequests()[0]['status']);
    }

    #[Test]
    public function recordsACanceledResponseAndCancelsTheInnerOne(): void
    {
        $inner = new StubResponse(['http_code' => 0, 'total_time' => 0.05]);
        $response = $this->client($inner)->request('GET', 'https://api.example.org/');

        $response->cancel();

        self::assertTrue($inner->canceled);
        self::assertCount(1, $this->collector->getRequests());
        self::assertNull($this->collector->getRequests()[0]['status']);
    }

    #[Test]
    public function prefersTheResolvedUrlOfTheResponseOverARelativeRequestUrl(): void
    {
        $response = $this->client(new StubResponse(['http_code' => 200, 'total_time' => 0.1, 'url' => 'https://api.example.org/v1/items?id=7']))
            ->request('GET', '/v1/items?id=7');

        $response->getContent();

        self::assertSame('https://api.example.org/v1/items?id=?', $this->collector->getRequests()[0]['url']);
    }

    #[Test]
    public function streamsTheDecoratedResponsesAndRecordsThemOnceTheyAreDone(): void
    {
        $client = $this->client(new StubResponse(['http_code' => 200, 'total_time' => 0.3]));
        $response = $client->request('GET', 'https://api.example.org/');
        $seen = [];

        foreach ($client->stream($response) as $streamed => $chunk) {
            $seen[] = $streamed;
            self::assertTrue($chunk->isLast());
        }

        self::assertSame([$response], $seen);
        self::assertSame(0, $this->recordedCount());

        unset($response, $seen, $streamed);

        self::assertSame(1, $this->recordedCount());
    }

    #[Test]
    public function keepsDecoratingAfterWithOptions(): void
    {
        $inner = new StubHttpClient(new StubResponse(['http_code' => 200, 'total_time' => 0.1]));
        $scoped = (new ProfilingHttpClient($inner, $this->collector))->withOptions(['base_uri' => 'https://api.example.org']);

        $scoped->request('GET', 'https://api.example.org/')->getContent();

        self::assertCount(1, $this->collector->getRequests());
    }

    #[Test]
    public function respectsTheCollectorCap(): void
    {
        for ($i = 0; $i < HttpCollector::MAX_ENTRIES; ++$i) {
            $this->collector->addRequest('GET', 'https://api.example.org/', 1.0, 200);
        }

        $this->client(new StubResponse(['http_code' => 200, 'total_time' => 0.1]))
            ->request('GET', 'https://api.example.org/')
            ->getContent();

        self::assertCount(HttpCollector::MAX_ENTRIES, $this->collector->getRequests());
        self::assertSame(1, $this->collector->getOmittedCount());
    }

    private function recordedCount(): int
    {
        return count($this->collector->getRequests());
    }

    private function client(StubResponse $response): ProfilingHttpClient
    {
        return new ProfilingHttpClient(new StubHttpClient($response), $this->collector);
    }
}
