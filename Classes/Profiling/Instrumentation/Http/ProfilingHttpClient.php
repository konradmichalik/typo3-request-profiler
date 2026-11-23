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

namespace KonradMichalik\Typo3RequestProfiler\Profiling\Instrumentation\Http;

use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\HttpCollector;
use Symfony\Contracts\HttpClient\{HttpClientInterface, ResponseInterface, ResponseStreamInterface};

use function spl_object_id;

/**
 * ProfilingHttpClient.
 *
 * Decorates any Symfony HttpClient so its calls show up in the "http" and
 * "slow_http" sections, like calls made through TYPO3's Guzzle client factory.
 * Needs symfony/http-client-contracts, which is why it is only registered in
 * Configuration/Services.php when that package is installed.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class ProfilingHttpClient implements HttpClientInterface
{
    public function __construct(
        private HttpClientInterface $client,
        private HttpCollector $collector,
    ) {}

    /**
     * @param array<array-key, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        return new ProfilingResponse($this->client->request($method, $url, $options), $this->collector, $method, $url);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        $decorated = [];
        $inner = [];
        foreach ($responses instanceof ResponseInterface ? [$responses] : $responses as $response) {
            $unwrapped = $response instanceof ProfilingResponse ? $response->unwrap() : $response;
            $inner[] = $unwrapped;
            $decorated[spl_object_id($unwrapped)] = $response;
        }

        return new ProfilingResponseStream($this->client->stream($inner, $timeout), $decorated);
    }

    /**
     * @param array<array-key, mixed> $options
     */
    public function withOptions(array $options): static
    {
        return new static($this->client->withOptions($options), $this->collector);
    }
}
