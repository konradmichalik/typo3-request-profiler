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

namespace KonradMichalik\Typo3RequestProfiler\Profiling\Section;

/**
 * ExceptionSection.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ExceptionSection implements ProfileSection
{
    public function name(): string
    {
        return 'exception';
    }

    public function priority(): int
    {
        return 1;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * Never the exception message: it regularly carries user input, record
     * data or absolute paths, matching the same privacy stance as the log
     * section (level + component only) and query tracing (call site only).
     *
     * @return array{class: string, file: string, line: int, code?: int|string}|null
     */
    public function collect(ProfileContext $context): ?array
    {
        $exception = $context->exception;
        if (null === $exception) {
            return null;
        }

        $payload = [
            'class' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        $code = $exception->getCode();
        if (0 !== $code) {
            $payload['code'] = $code;
        }

        return $payload;
    }
}
