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

namespace KonradMichalik\Typo3RequestProfiler\Profiling;

use InvalidArgumentException;
use JsonException;

use function is_array;
use function is_int;
use function sprintf;

/**
 * ProfileResolver.
 *
 * Turns a command line reference, a profile token or a profile file path, into
 * a decoded profile.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class ProfileResolver
{
    /**
     * An existing file wins over the token reading. The token itself can only
     * ever name a file inside the profile directory, see ProfileReader::byToken().
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException when the reference does not lead to a profile
     */
    public static function resolve(string $reference): array
    {
        if (is_file($reference)) {
            return self::readFile($reference);
        }

        $profile = (new ProfileReader(ProfileWriter::defaultDirectory()))->byToken($reference);
        if (null === $profile) {
            throw new InvalidArgumentException(sprintf('No profile found for "%s" (neither a known token nor an existing file).', $reference), 2012333444);
        }

        return self::assertProfile($profile, $reference);
    }

    /**
     * @return array<string, mixed>
     */
    private static function readFile(string $file): array
    {
        $json = is_readable($file) ? file_get_contents($file) : false;
        if (false === $json) {
            throw new InvalidArgumentException(sprintf('File "%s" is not readable.', $file), 1266480993);
        }

        try {
            $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException(sprintf('File "%s" is not valid JSON.', $file), 3626286599);
        }

        if (!is_array($decoded)) {
            throw new InvalidArgumentException(sprintf('File "%s" is not a profile.', $file), 2340660918);
        }

        $profile = [];
        foreach ($decoded as $key => $value) {
            $profile[(string) $key] = $value;
        }

        return self::assertProfile($profile, $file);
    }

    /**
     * @param array<string, mixed> $profile
     *
     * @return array<string, mixed>
     */
    private static function assertProfile(array $profile, string $reference): array
    {
        if (!is_int($profile['schemaVersion'] ?? null)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a profile (no schemaVersion).', $reference), 1584437864);
        }

        return $profile;
    }
}
