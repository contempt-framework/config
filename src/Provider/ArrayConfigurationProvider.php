<?php

declare(strict_types=1);

namespace Contempt\Config\Provider;

use Contempt\Config\ConfigurationProvider;
use Contempt\Config\ConfigurationRequest;
use Contempt\Config\ConfigurationValues;

final readonly class ArrayConfigurationProvider implements ConfigurationProvider
{
    private ConfigurationValues $values;

    /** @param array<array-key, mixed> $values */
    public function __construct(array $values)
    {
        $this->values = new ConfigurationValues($values);
    }

    #[\Override]
    public function load(ConfigurationRequest $request): ConfigurationValues
    {
        return $this->values->forRequest($request);
    }
}
