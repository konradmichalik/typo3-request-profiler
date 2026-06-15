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

namespace KonradMichalik\Typo3RequestProfiler\Profiling;

use JsonException;
use TYPO3\CMS\Core\Core\Environment;

use function array_slice;
use function is_array;

/**
 * ProfileReader.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 */
final readonly class ProfileReader
{
    public function __construct(private ?string $directory = null) {}

    /**
     * Newest profiles first.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->load($this->sortedFiles());
    }

    /**
     * The $limit newest profiles, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function latest(int $limit = 10): array
    {
        if ($limit < 1) {
            return [];
        }

        return $this->load(array_slice($this->sortedFiles(), 0, $limit));
    }

    /**
     * A single profile by its token (= request id), or null if unknown.
     *
     * @return array<string, mixed>|null
     */
    public function byToken(string $token): ?array
    {
        // The token becomes a file name, so reject anything but the safe
        // characters a RequestId produces to prevent path traversal.
        if ('' === $token || 1 !== preg_match('/^[A-Za-z0-9_-]+$/', $token)) {
            return null;
        }

        $file = $this->directory().'/'.$token.'.json';

        return is_file($file) ? $this->decode($file) : null;
    }

    private function directory(): string
    {
        return $this->directory ?? Environment::getVarPath().'/log/profiles';
    }

    /**
     * @return list<string>
     */
    private function sortedFiles(): array
    {
        $files = glob($this->directory().'/*.json') ?: [];
        usort($files, static fn (string $a, string $b): int => (int) filemtime($b) <=> (int) filemtime($a));

        return $files;
    }

    /**
     * @param list<string> $files
     *
     * @return list<array<string, mixed>>
     */
    private function load(array $files): array
    {
        $profiles = [];
        foreach ($files as $file) {
            $decoded = $this->decode($file);
            if (null !== $decoded) {
                $profiles[] = $decoded;
            }
        }

        return $profiles;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(string $file): ?array
    {
        $json = @file_get_contents($file);
        if (false === $json) {
            return null;
        }

        try {
            $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        // Re-key to string offsets so the static type matches the JSON object
        // shape (avoids an inline @var that the code-style fixer strips).
        $profile = [];
        foreach ($decoded as $key => $value) {
            $profile[(string) $key] = $value;
        }

        return $profile;
    }
}
