<?php

declare(strict_types=1);

namespace Contempt\Config\Provider;

use Contempt\Core\Environment;

enum DotEnvLoadingPolicy
{
    /** Load in development and test, but never implicitly in production. */
    case Automatic;

    /** Load explicitly in every environment, including production. */
    case Enabled;

    /** Never inspect a dotenv file. */
    case Disabled;

    #[\NoDiscard]
    public static function fromFlag(?string $raw): self
    {
        if ($raw === null) {
            return self::Automatic;
        }

        return match (strtolower(trim($raw))) {
            'auto', 'automatic' => self::Automatic,
            '1', 'true', 'yes', 'on', 'enabled' => self::Enabled,
            '0', 'false', 'no', 'off', 'disabled' => self::Disabled,
            default => throw new \InvalidArgumentException(\sprintf(
                'Invalid dotenv loading flag "%s". Expected automatic, enabled, or disabled.',
                $raw,
            )),
        };
    }

    public function shouldLoad(Environment $environment): bool
    {
        return match ($this) {
            self::Automatic => $environment->allowsImplicitDotEnv(),
            self::Enabled => true,
            self::Disabled => false,
        };
    }
}
