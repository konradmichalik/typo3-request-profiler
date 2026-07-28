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

use InvalidArgumentException;

use function preg_match;
use function trim;

/**
 * Duration.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class Duration
{
    private const DEFAULT_SECONDS = 900;

    /**
     * A week is already far beyond what a "temporary" toggle should ever need.
     * Rejecting anything above it also rules out the two ways an oversized
     * numeric string could otherwise misbehave: suffixed multiplication
     * (`h`/`m`) overflowing int into float, and a plain value near
     * PHP_INT_MAX later overflowing `time() + seconds` in
     * {@see ProfilerStateService::activate()}.
     */
    private const MAX_SECONDS = 604_800;

    private function __construct(
        private int $seconds,
    ) {}

    public static function default(): self
    {
        return new self(self::DEFAULT_SECONDS);
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^(\d+)([smh]?)$/', trim($value), $matches)) {
            throw new InvalidArgumentException('Invalid duration "'.$value.'". Use a plain second count or a suffix of s, m, or h (e.g. "15m").', 6415078150);
        }

        $amount = (int) $matches[1];
        $seconds = match ($matches[2]) {
            'h' => $amount * 3600,
            'm' => $amount * 60,
            default => $amount,
        };

        if ($seconds <= 0 || $seconds > self::MAX_SECONDS) {
            throw new InvalidArgumentException('Duration must be between 1 second and 7 days.', 2133962696);
        }

        return new self($seconds);
    }

    public function seconds(): int
    {
        return $this->seconds;
    }
}
