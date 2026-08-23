<?php

declare(strict_types=1);

namespace Contempt\Config\Exception;

use Contempt\Core\Exception\ConfigurationException;

final class MissingConfigurationValue extends ConfigurationException
{
    public function __construct(string $path)
    {
        parent::__construct(\sprintf('Required configuration value "%s" is missing.', $path));
    }
}
