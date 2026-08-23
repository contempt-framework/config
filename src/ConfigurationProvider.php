<?php

declare(strict_types=1);

namespace Contempt\Config;

interface ConfigurationProvider
{
    #[\NoDiscard]
    public function load(ConfigurationRequest $request): ConfigurationValues;
}
