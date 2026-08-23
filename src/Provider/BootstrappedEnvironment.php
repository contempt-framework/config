<?php

declare(strict_types=1);

namespace Contempt\Config\Provider;

use Contempt\Core\Environment;

final readonly class BootstrappedEnvironment
{
    /**
     * @param array<string, mixed> $values immutable snapshot of $_SERVER overlaid by $_ENV
     */
    public function __construct(
        public Environment $environment,
        public array $values,
    ) {}
}
