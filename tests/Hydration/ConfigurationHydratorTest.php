<?php

declare(strict_types=1);

namespace Contempt\Config\Tests\Hydration;

use Contempt\Config\ConfigurationValues;
use Contempt\Config\Exception\ConfigurationHydrationFailed;
use Contempt\Config\Hydration\ConfigurationHydrator;
use Contempt\Core\Secret\Secret;
use Contempt\Core\Secret\SecretAccess;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigurationHydrator::class)]
final class ConfigurationHydratorTest extends TestCase
{
    public function testUnknownKeysAreRejected(): void
    {
        $this->expectException(ConfigurationHydrationFailed::class);
        $this->expectExceptionMessage('database.extra');

        self::assertInstanceOf(
            DatabaseConfiguration::class,
            new ConfigurationHydrator()->hydrate(DatabaseConfiguration::class, new ConfigurationValues([
                'dsn' => 'sqlite::memory:',
                'password' => 'secret',
                'extra' => true,
            ]), 'database'),
        );
    }

    public function testMissingRequiredValuesAreReportedWithTheirFullPath(): void
    {
        $this->expectException(ConfigurationHydrationFailed::class);
        $this->expectExceptionMessage('database.password');

        self::assertInstanceOf(
            DatabaseConfiguration::class,
            new ConfigurationHydrator()->hydrate(DatabaseConfiguration::class, new ConfigurationValues([
                'dsn' => 'sqlite::memory:',
            ]), 'database'),
        );
    }

    public function testLossyScalarCoercionIsForbidden(): void
    {
        $this->expectException(ConfigurationHydrationFailed::class);
        $this->expectExceptionMessage('database.poolSize');

        self::assertInstanceOf(
            DatabaseConfiguration::class,
            new ConfigurationHydrator()->hydrate(DatabaseConfiguration::class, new ConfigurationValues([
                'dsn' => 'sqlite::memory:',
                'password' => 'secret',
                'poolSize' => '10 workers',
            ]), 'database'),
        );
    }

    public function testClassWithoutAPublicConstructorCannotBeHydrated(): void
    {
        $this->expectException(ConfigurationHydrationFailed::class);

        self::assertInstanceOf(
            ClosedConfiguration::class,
            new ConfigurationHydrator()->hydrate(ClosedConfiguration::class, ConfigurationValues::empty()),
        );
    }

    public function testTypedObjectIsHydratedAndSecretsStayExplicit(): void
    {
        $configuration = new ConfigurationHydrator()->hydrate(DatabaseConfiguration::class, new ConfigurationValues([
            'dsn' => 'sqlite::memory:',
            'password' => 'very-secret',
            'poolSize' => '12',
            'mode' => 'read-only',
        ]), 'database');

        self::assertSame('sqlite::memory:', $configuration->dsn);
        self::assertSame(12, $configuration->poolSize);
        self::assertSame(DatabaseMode::ReadOnly, $configuration->mode);
        self::assertSame('[REDACTED]', (string) $configuration->password);
        self::assertSame('very-secret', $configuration->password->reveal(SecretAccess::for('database', 'verify hydration')));
    }
}

enum DatabaseMode: string
{
    case ReadWrite = 'read-write';
    case ReadOnly = 'read-only';
}

final readonly class DatabaseConfiguration
{
    public function __construct(
        public string $dsn,
        public Secret $password,
        public int $poolSize = 10,
        public DatabaseMode $mode = DatabaseMode::ReadWrite,
    ) {}
}

final class ClosedConfiguration
{
    private function __construct() {}
}
