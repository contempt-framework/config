<?php

declare(strict_types=1);

namespace Contempt\Config\Tests\Provider;

use Contempt\Config\ConfigurationRequest;
use Contempt\Config\Provider\ArrayConfigurationProvider;
use Contempt\Config\Provider\EnvironmentConfigurationProvider;
use Contempt\Config\Provider\ProviderChain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayConfigurationProvider::class)]
#[CoversClass(EnvironmentConfigurationProvider::class)]
#[CoversClass(ProviderChain::class)]
final class ProviderChainTest extends TestCase
{
    public function testDuplicateEnvironmentTargetsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('database.host');

        new EnvironmentConfigurationProvider(
            environment: ['DB_HOST' => 'first', 'DATABASE_HOST' => 'second'],
            mapping: ['DB_HOST' => 'database.host', 'DATABASE_HOST' => 'database.host'],
        );
    }

    public function testUnknownAndNonStringEnvironmentValuesAreIgnored(): void
    {
        $provider = new EnvironmentConfigurationProvider(
            environment: ['DB_HOST' => 'db', 'ARRAY_VALUE' => ['hostile'], 'UNMAPPED' => 'ignored'],
            mapping: ['DB_HOST' => 'database.host', 'ARRAY_VALUE' => 'database.invalid'],
        );

        self::assertSame(
            ['database' => ['host' => 'db']],
            $provider->load(ConfigurationRequest::all())->all(),
        );
    }

    public function testLaterProvidersHaveExplicitPrecedence(): void
    {
        $chain = new ProviderChain([
            new ArrayConfigurationProvider(['database' => ['host' => 'base', 'port' => 5432]]),
            new EnvironmentConfigurationProvider(
                environment: ['DB_HOST' => 'production'],
                mapping: ['DB_HOST' => 'database.host'],
            ),
            new ArrayConfigurationProvider(['database' => ['port' => 6432]]),
        ]);

        self::assertSame(
            ['host' => 'production', 'port' => 6432],
            $chain->load(new ConfigurationRequest('database'))->all(),
        );
    }

    public function testProviderWithoutRequestedPrefixDoesNotEraseLowerPrecedenceValues(): void
    {
        $chain = new ProviderChain([
            new ArrayConfigurationProvider(['application' => ['name' => 'contempt']]),
            new EnvironmentConfigurationProvider(
                environment: [],
                mapping: ['APP_NAME' => 'application.name'],
            ),
        ]);

        self::assertSame(
            ['name' => 'contempt'],
            $chain->load(new ConfigurationRequest('application'))->all(),
        );
    }

    public function testEmptyChainIsAValidEmptySource(): void
    {
        self::assertSame([], new ProviderChain()->load(ConfigurationRequest::all())->all());
    }
}
