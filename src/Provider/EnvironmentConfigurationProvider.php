<?php

declare(strict_types=1);

namespace Contempt\Config\Provider;

use Contempt\Config\ConfigurationProvider;
use Contempt\Config\ConfigurationRequest;
use Contempt\Config\ConfigurationValues;
use Contempt\Config\Internal\ConfigurationPath;

final readonly class EnvironmentConfigurationProvider implements ConfigurationProvider
{
    /** @var array<string, mixed> */
    private array $environment;

    /** @var array<string, non-empty-string> */
    private array $mapping;

    /**
     * Environment input is injected so builds and tests never depend on global
     * process state. A bootstrap may pass `$_ENV` explicitly.
     *
     * @param array<string, mixed> $environment
     * @param array<string, non-empty-string> $mapping environment name => configuration path
     */
    public function __construct(array $environment, array $mapping)
    {
        $targets = [];

        foreach ($mapping as $variable => $target) {
            if ($variable === '' || trim($variable) !== $variable) {
                throw new \InvalidArgumentException('Environment variable names must not be blank.');
            }

            ConfigurationPath::segments($target);

            if (isset($targets[$target])) {
                throw new \InvalidArgumentException(\sprintf('Configuration target "%s" has multiple environment mappings.', $target));
            }

            $targets[$target] = true;
        }

        $this->environment = $environment;
        $this->mapping = $mapping;
    }

    #[\Override]
    public function load(ConfigurationRequest $request): ConfigurationValues
    {
        $flat = [];

        foreach ($this->mapping as $variable => $target) {
            $value = $this->environment[$variable] ?? null;

            if (!\is_string($value)) {
                continue;
            }

            $flat[$target] = $value;
        }

        return ConfigurationValues::fromFlat($flat)->forRequest($request);
    }
}
