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
            throw new InvalidArgumentException('Invalid duration "'.$value.'". Use a plain second count or a suffix of s, m, or h (e.g. "15m").');
        }

        $amount = (int) $matches[1];
        $seconds = match ($matches[2]) {
            'h' => $amount * 3600,
            'm' => $amount * 60,
            default => $amount,
        };

        if ($seconds <= 0) {
            throw new InvalidArgumentException('Duration must be greater than zero.');
        }

        return new self($seconds);
    }

    public function seconds(): int
    {
        return $this->seconds;
    }
}
