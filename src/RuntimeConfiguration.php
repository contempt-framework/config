<?php

declare(strict_types=1);

namespace Contempt\Config;

use Contempt\Core\Environment;

/** Runtime configuration boundary shared by HTTP, console and workers. */
final readonly class RuntimeConfiguration
{
    public function __construct(
        public Environment $environment,
        public ConfigurationProvider $provider,
    ) {}
}
