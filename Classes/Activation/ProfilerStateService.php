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
use RuntimeException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function dirname;
use function file_exists;
use function getmypid;
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
     *
     * @throws RuntimeException if the state file could not be written — a
     *                          caller must never report "activated" for a
     *                          toggle that silently failed to persist
     */
    public function activate(Duration $duration): int
    {
        $expiresAt = time() + $duration->seconds();

        $json = json_encode(['expiresAt' => $expiresAt], \JSON_THROW_ON_ERROR);
        if (!$this->writeAtomically(self::filePath(), $json)) {
            throw new RuntimeException('Failed to write the profiler activation state file.', 8329651047);
        }

        return $expiresAt;
    }

    /**
     * @return bool whether the state file is now absent — true if it never
     *              existed or was just removed; false only if it still
     *              exists because removal failed
     */
    public function deactivate(): bool
    {
        $path = self::filePath();
        if (!file_exists($path)) {
            return true;
        }

        return @unlink($path);
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
     * The temp name is unique per process: unlike
     * {@see \KonradMichalik\Typo3RequestProfiler\Profiling\ProfileWriter},
     * this is a single shared state file rather than one file per request, so
     * two concurrent activate() calls would otherwise race on the same
     * fixed ".tmp" path.
     */
    private function writeAtomically(string $target, string $contents): bool
    {
        GeneralUtility::mkdir_deep(dirname($target));

        $temp = $target.'.'.getmypid().'.tmp';
        if (false === @file_put_contents($temp, $contents)) {
            return false;
        }

        GeneralUtility::fixPermissions($temp);
        if (!@rename($temp, $target)) {
            @unlink($temp);

            return false;
        }

        return true;
    }
}
