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

use JsonException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function dirname;
use function is_array;
use function is_int;
use function json_decode;
use function json_encode;
use function time;

/**
 * ProfilerStateService.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ProfilerStateService
{
    public static function filePath(): string
    {
        return Environment::getVarPath().'/log/profiler-activation-state.json';
    }

    /**
     * @return int the unix timestamp the activation expires at
     */
    public function activate(Duration $duration): int
    {
        $expiresAt = time() + $duration->seconds();

        $json = json_encode(['expiresAt' => $expiresAt], \JSON_THROW_ON_ERROR);
        $this->writeAtomically(self::filePath(), $json);

        return $expiresAt;
    }

    public function deactivate(): void
    {
        @unlink(self::filePath());
    }

    public function isActive(): bool
    {
        $expiresAt = $this->readExpiry();

        return null !== $expiresAt && $expiresAt > time();
    }

    private function readExpiry(): ?int
    {
        $contents = @file_get_contents(self::filePath());
        if (false === $contents) {
            return null;
        }

        try {
            $data = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($data) || !is_int($data['expiresAt'] ?? null)) {
            return null;
        }

        return $data['expiresAt'];
    }

    /**
     * Write to a sibling temp file and atomically rename it into place, so a
     * concurrent {@see isActive()} check never observes a half-written file.
     */
    private function writeAtomically(string $target, string $contents): void
    {
        GeneralUtility::mkdir_deep(dirname($target));

        $temp = $target.'.tmp';
        if (false === @file_put_contents($temp, $contents)) {
            return;
        }

        GeneralUtility::fixPermissions($temp);
        if (!@rename($temp, $target)) {
            @unlink($temp);
        }
    }
}
