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

use GuzzleHttp\Promise\{Create, PromiseInterface};
use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\HttpCollector;
use KonradMichalik\Typo3RequestProfiler\Profiling\UrlSanitizer;
use Psr\Http\Message\{RequestInterface, ResponseInterface};
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * ProfilingHttpMiddleware.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ProfilingHttpMiddleware
{
    /**
     * Guzzle middleware factory, registered as a plain static callable via
     * {@see \KonradMichalik\Typo3RequestProfiler\Configuration::registerProfilingHttpMiddleware()}.
     * HandlerStack::push() uses the array value directly and offers no
     * constructor injection, the same constraint {@see \KonradMichalik\Typo3RequestProfiler\Profiling\Instrumentation\Doctrine\ProfilingDriverMiddleware}
     * already works under, so the collector is resolved here rather than injected.
     */
    public static function wrap(callable $handler): callable
    {
        return static function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            $start = microtime(true);

            return $handler($request, $options)->then(
                static function (ResponseInterface $response) use ($request, $start): ResponseInterface {
                    self::record($request, $start, $response->getStatusCode());

                    return $response;
                },
                static function (mixed $reason) use ($request, $start): PromiseInterface {
                    // No response exists on a rejection (e.g. a connect
                    // timeout), so there is no status code to report.
                    self::record($request, $start, null);

                    return Create::rejectionFor($reason);
                },
            );
        };
    }

    private static function record(RequestInterface $request, float $start, ?int $status): void
    {
        GeneralUtility::makeInstance(HttpCollector::class)->addRequest(
            $request->getMethod(),
            UrlSanitizer::maskQueryValues($request->getUri()),
            (microtime(true) - $start) * 1000,
            $status,
        );
    }
}
