<?php

declare(strict_types=1);

namespace Contempt\Config\Provider;

use Contempt\Config\ConfigurationProvider;
use Contempt\Config\ConfigurationRequest;
use Contempt\Config\ConfigurationValues;

final readonly class ProviderChain implements ConfigurationProvider
{
    /** @var list<ConfigurationProvider> */
    private array $providers;

    /** @param iterable<ConfigurationProvider> $providers lowest to highest precedence */
    public function __construct(iterable $providers = [])
    {
        $this->providers = array_values([...$providers]);
    }

    #[\Override]
    public function load(ConfigurationRequest $request): ConfigurationValues
    {
        $values = ConfigurationValues::empty();

        foreach ($this->providers as $provider) {
            $values = $values->overlaidBy($provider->load($request));
        }

        return $values;
    }
}
