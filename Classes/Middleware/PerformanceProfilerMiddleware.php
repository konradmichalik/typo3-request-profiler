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

namespace KonradMichalik\Typo3RequestProfiler\Middleware;

use KonradMichalik\Typo3RequestProfiler\Profiling\ProfileWriter;
use KonradMichalik\Typo3RequestProfiler\Profiling\QueryCollector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\RequestId;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Wraps the frontend request with a wall-clock timer and persists a profile.
 *
 * Dev-only and opt-out via TYPO3_REQUEST_PROFILER=0. Writing is fail-safe: any
 * error during profiling must never affect page delivery.
 */
final class PerformanceProfilerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RequestId $requestId,
        private readonly ProfileWriter $profileWriter,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!Environment::getContext()->isDevelopment() || getenv('TYPO3_REQUEST_PROFILER') === '0') {
            return $handler->handle($request);
        }

        $start = microtime(true);
        $response = $handler->handle($request);
        $totalMs = (microtime(true) - $start) * 1000;

        try {
            $this->profileWriter->write(
                $request,
                $response,
                (string)$this->requestId,
                GeneralUtility::makeInstance(QueryCollector::class),
                $totalMs,
            );
        } catch (\Throwable) {
            // Fail-safe: profiling must never break the response.
        }

        return $response;
    }
}
