<?php

declare(strict_types=1);

namespace Contempt\Config\Provider;

use Contempt\Core\Environment;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Loads Symfony dotenv files once, at the application boundary.
 *
 * Dotenv deliberately populates only PHP superglobals. `putenv()` remains
 * disabled because changing the process environment is not thread-safe.
 */
final readonly class DotEnvBootstrap
{
    #[\NoDiscard]
    public static function selectedEnvironment(Environment $default = Environment::Production): Environment
    {
        return self::environmentFrom(self::capture(), $default);
    }

    #[\NoDiscard]
    public function boot(
        string $path,
        Environment $environment,
        DotEnvLoadingPolicy $policy = DotEnvLoadingPolicy::Automatic,
    ): BootstrappedEnvironment {
        $before = self::capture();
        self::assertSelectedEnvironment($before, $environment);

        if (!$policy->shouldLoad($environment)) {
            $before['APP_ENV'] ??= $environment->value;

            return new BootstrappedEnvironment($environment, $before);
        }

        new Dotenv()->loadEnv(
            path: $path,
            envKey: 'APP_ENV',
            defaultEnv: $environment->value,
            testEnvs: [Environment::Test->value],
            overrideExistingVars: false,
        );

        $values = self::capture();
        $resolved = self::environmentFrom($values, $environment);

        if ($resolved !== $environment) {
            throw new \UnexpectedValueException(\sprintf(
                'Dotenv selected environment "%s", but bootstrap requires "%s".',
                $resolved->value,
                $environment->value,
            ));
        }

        return new BootstrappedEnvironment($resolved, $values);
    }

    /** @return array<string, mixed> */
    private static function capture(): array
    {
        $values = [];

        foreach ([$_SERVER, $_ENV] as $source) {
            foreach ($source as $name => $value) {
                if (\is_string($name)) {
                    $values[$name] = $value;
                }
            }
        }

        return $values;
    }

    /** @param array<string, mixed> $values */
    private static function assertSelectedEnvironment(array $values, Environment $environment): void
    {
        if (!\array_key_exists('APP_ENV', $values)) {
            return;
        }

        $selected = self::environmentFrom($values, $environment);

        if ($selected !== $environment) {
            throw new \InvalidArgumentException(\sprintf(
                'Process APP_ENV selects "%s", but bootstrap was configured for "%s".',
                $selected->value,
                $environment->value,
            ));
        }
    }

    /** @param array<string, mixed> $values */
    private static function environmentFrom(array $values, Environment $default): Environment
    {
        $raw = $values['APP_ENV'] ?? $default->value;

        if (!\is_string($raw)) {
            throw new \InvalidArgumentException('APP_ENV must be a string.');
        }

        return Environment::fromString($raw);
    }
}
