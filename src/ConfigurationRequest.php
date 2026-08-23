<?php

declare(strict_types=1);

namespace Contempt\Config;

use Contempt\Config\Internal\ConfigurationPath;

final readonly class ConfigurationRequest
{
    public function __construct(public ?string $prefix = null)
    {
        if ($prefix !== null) {
            ConfigurationPath::segments($prefix);
        }
    }

    #[\NoDiscard]
    public static function all(): self
    {
        return new self();
    }
}
