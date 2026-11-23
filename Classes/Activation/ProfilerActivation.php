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

namespace KonradMichalik\Typo3RequestProfiler\Activation;

use KonradMichalik\Typo3RequestProfiler\Configuration;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\LogManager;

use function getenv;
use function hash_equals;
use function sprintf;
use function strlen;

/**
 * ProfilerActivation.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ProfilerActivation
{
    private const HEADER_NAME = 'Typo3-Profiler';

    private const SECRET_ENV_VAR = 'TYPO3_REQUEST_PROFILER_SECRET';

    private const MIN_SECRET_LENGTH = 32;

    private bool $weakSecretWarned = false;

    public function __construct(
        private readonly ProfilerStateService $stateService, private readonly LogManager $logManager,
    ) {}

    public function decide(ServerRequestInterface $request): ActivationMode
    {
        if ('0' === getenv('TYPO3_REQUEST_PROFILER')) {
            return ActivationMode::None;
        }

        if ($this->stateService->isActive()) {
            return ActivationMode::StateFile;
        }

        if (Configuration::isProfilingActive()) {
            return ActivationMode::Context;
        }

        // Checked last: the most expensive check (secret lookup + constant-time
        // comparison), reached only when nothing cheaper already activated.
        if ($this->isHeaderTriggered($request)) {
            return ActivationMode::Header;
        }

        return ActivationMode::None;
    }

    /**
     * Whether a valid trigger header was sent — checked independently of
     * {@see decide()}'s result. A request already profiled via the state file
     * or context can still carry a valid header: the caller wants exactly
     * this request's artifact correlated (see the middleware), which matters
     * regardless of why profiling happened to be active.
     *
     * An invalid or missing token must be indistinguishable from "no header
     * sent" — no exception, no early return with a different code path that
     * could leak timing or observable behavior. A wrong/missing secret is
     * therefore just another false condition here, not a rejected request.
     */
    public function isHeaderTriggered(ServerRequestInterface $request): bool
    {
        $header = $request->getHeaderLine(self::HEADER_NAME);
        if ('' === $header) {
            return false;
        }

        if (Environment::getContext()->isDevelopment()) {
            // Header is already confirmed non-empty above; only "0" counts as off,
            // matching the same on/off convention as the env-var flags.
            return '0' !== $header;
        }

        $secret = getenv(self::SECRET_ENV_VAR);
        if (false === $secret || '' === $secret) {
            return false;
        }

        // A short secret can be guessed online (no rate limit), so it counts as unset.
        if (strlen($secret) < self::MIN_SECRET_LENGTH) {
            $this->warnAboutWeakSecret();

            return false;
        }

        return hash_equals($secret, $header);
    }

    private function warnAboutWeakSecret(): void
    {
        if ($this->weakSecretWarned) {
            return;
        }
        $this->weakSecretWarned = true;

        $this->logManager
            ->getLogger(self::class)
            ->warning(sprintf(
                '%s is shorter than %d characters and is ignored, the header trigger stays disabled. '
                .'Use a longer random value, e.g. "openssl rand -hex 32".',
                self::SECRET_ENV_VAR,
                self::MIN_SECRET_LENGTH,
            ));
    }
}
