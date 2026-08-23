<?php

declare(strict_types=1);

namespace Contempt\Config\Internal;

use Contempt\Config\Exception\InvalidConfigurationPath;

/** @internal */
final class ConfigurationPath
{
    private function __construct() {}

    /** @return non-empty-list<non-empty-string> */
    public static function segments(string $path): array
    {
        if ($path === '' || trim($path) !== $path) {
            throw new InvalidConfigurationPath(\sprintf('Invalid configuration path "%s".', $path));
        }

        $segments = explode('.', $path);

        foreach ($segments as $segment) {
            if (preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/D', $segment) !== 1) {
                throw new InvalidConfigurationPath(\sprintf('Invalid configuration path "%s".', $path));
            }
        }

        return $segments;
    }

    public static function segment(string $segment): void
    {
        if (str_contains($segment, '.') || preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/D', $segment) !== 1) {
            throw new InvalidConfigurationPath(\sprintf('Invalid configuration key "%s".', $segment));
        }
    }
}
