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

namespace KonradMichalik\Typo3RequestProfiler\Middleware;

use KonradMichalik\Typo3RequestProfiler\Profiling\ProfileWriter;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use Throwable;
use TYPO3\CMS\Core\Core\{Environment, RequestId};

/**
 * PerformanceProfilerMiddleware.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class PerformanceProfilerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RequestId $requestId,
        private ProfileWriter $profileWriter,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!Environment::getContext()->isDevelopment() || '0' === getenv('TYPO3_REQUEST_PROFILER')) {
            return $handler->handle($request);
        }

        $start = microtime(true);
        $response = $handler->handle($request);
        $totalMs = (microtime(true) - $start) * 1000;

        // Optional sampling: only persist requests at/above a minimum wall-clock
        // time (TYPO3_REQUEST_PROFILER_MIN_MS), to focus on slow pages and keep
        // the profile directory small. Default 0 = profile every request.
        if ($totalMs < (float) getenv('TYPO3_REQUEST_PROFILER_MIN_MS')) {
            return $response;
        }

        try {
            $this->profileWriter->write(
                $request,
                $response,
                (string) $this->requestId,
                $totalMs,
            );
        } catch (Throwable) {
            // Fail-safe: profiling must never break the response.
        }

        return $response;
    }
}
