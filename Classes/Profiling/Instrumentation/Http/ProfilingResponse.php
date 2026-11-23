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
use KonradMichalik\Typo3RequestProfiler\Profiling\UrlSanitizer;
use Symfony\Contracts\HttpClient\ResponseInterface;
use TYPO3\CMS\Core\Http\Uri;

use function is_int;
use function is_numeric;
use function is_string;

/**
 * ProfilingResponse.
 *
 * Responses are lazy, so nothing is recorded when the request is made. The
 * call is recorded once, when the body was read, the response was canceled or,
 * as the fallback for a response nobody read to the end, when it is destroyed.
 * Reading only the status or the headers does not count as completion, because
 * "total_time" would still be incomplete then.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ProfilingResponse implements ResponseInterface
{
    private bool $recorded = false;

    public function __construct(
        private readonly ResponseInterface $inner,
        private readonly HttpCollector $collector,
        private readonly string $method,
        private readonly string $url,
    ) {}

    public function __destruct()
    {
        $this->record();
    }

    public function getStatusCode(): int
    {
        return $this->inner->getStatusCode();
    }

    public function getHeaders(bool $throw = true): array
    {
        return $this->inner->getHeaders($throw);
    }

    public function getContent(bool $throw = true): string
    {
        try {
            return $this->inner->getContent($throw);
        } finally {
            $this->record();
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(bool $throw = true): array
    {
        try {
            return $this->inner->toArray($throw);
        } finally {
            $this->record();
        }
    }

    public function cancel(): void
    {
        $this->inner->cancel();
        $this->record();
    }

    public function getInfo(?string $type = null): mixed
    {
        return $this->inner->getInfo($type);
    }

    public function unwrap(): ResponseInterface
    {
        return $this->inner;
    }

    private function record(): void
    {
        if ($this->recorded) {
            return;
        }
        $this->recorded = true;

        // The resolved URL covers relative request URLs of clients with a
        // base_uri; "http_code" is 0 when no response arrived.
        $url = $this->inner->getInfo('url');
        $status = $this->inner->getInfo('http_code');
        $seconds = $this->inner->getInfo('total_time');

        $this->collector->addRequest(
            $this->method,
            UrlSanitizer::maskQueryValues(new Uri(is_string($url) && '' !== $url ? $url : $this->url)),
            is_numeric($seconds) ? (float) $seconds * 1000 : 0.0,
            is_int($status) && $status > 0 ? $status : null,
        );
    }
}
