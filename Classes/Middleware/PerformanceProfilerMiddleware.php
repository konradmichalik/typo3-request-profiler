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

use KonradMichalik\Typo3RequestProfiler\Activation\{ActivationMode, ProfilerActivation};
use KonradMichalik\Typo3RequestProfiler\Profiling\ProfileWriter;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use Throwable;
use TYPO3\CMS\Core\Core\RequestId;

/**
 * PerformanceProfilerMiddleware.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class PerformanceProfilerMiddleware implements MiddlewareInterface
{
    private const HEADER_ARTIFACT = 'Typo3-Profiler-Artifact';

    public function __construct(
        private RequestId $requestId,
        private ProfileWriter $profileWriter,
        private ProfilerActivation $activation,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $activationMode = $this->activation->decide($request);
        if (ActivationMode::None === $activationMode) {
            return $handler->handle($request);
        }

        // Checked independently of $activationMode: a request already profiled
        // via the state file or context can still carry a valid trigger header
        // — the caller wants exactly this request's artifact correlated, which
        // matters regardless of why profiling happened to be active.
        $headerTriggered = $this->activation->isHeaderTriggered($request);

        $start = microtime(true);
        $response = $handler->handle($request);
        $totalMs = (microtime(true) - $start) * 1000;

        // Optional sampling: only persist requests at/above a minimum wall-clock
        // time (TYPO3_REQUEST_PROFILER_MIN_MS), to focus on slow pages and keep
        // the profile directory small. Default 0 = profile every request. An
        // explicit header trigger always gets its artifact: a caller that asked
        // for exactly this request's profile must be able to rely on it.
        if (!$headerTriggered && $totalMs < (float) getenv('TYPO3_REQUEST_PROFILER_MIN_MS')) {
            return $response;
        }

        $token = (string) $this->requestId;

        if ($headerTriggered) {
            // Deliberately no Vary on the trigger header: it would fragment
            // caches and disclose the feature to intermediaries.
            $response = $response
                ->withHeader(self::HEADER_ARTIFACT, $token)
                ->withHeader('Cache-Control', 'no-store');
        }

        try {
            $this->profileWriter->write(
                $request,
                $response,
                $token,
                $totalMs,
                $activationMode,
            );
        } catch (Throwable) {
            // Fail-safe: profiling must never break the response.
        }

        return $response;
    }
}
