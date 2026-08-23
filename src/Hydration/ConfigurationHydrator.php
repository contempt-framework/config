<?php

declare(strict_types=1);

namespace Contempt\Config\Hydration;

use Contempt\Config\ConfigurationValues;
use Contempt\Config\Exception\ConfigurationHydrationFailed;
use Contempt\Core\Secret\Secret;

/**
 * Build/startup hydrator for immutable typed configuration objects.
 *
 * Reflection is intentional at this boundary. The compiler emits equivalent
 * direct constructor calls for the optimized runtime.
 */
final class ConfigurationHydrator
{
    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    #[\NoDiscard]
    public function hydrate(string $class, ConfigurationValues $values, string $prefix = ''): object
    {
        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null || !$constructor->isPublic()) {
            throw new ConfigurationHydrationFailed(\sprintf('Configuration class %s requires a public constructor.', $class));
        }

        $raw = $values->all();
        $parameters = [];

        foreach ($constructor->getParameters() as $parameter) {
            $parameters[$parameter->getName()] = $parameter;
        }

        foreach (array_keys($raw) as $key) {
            if (!isset($parameters[$key])) {
                throw new ConfigurationHydrationFailed(\sprintf(
                    'Unknown configuration value "%s".',
                    self::path($prefix, $key),
                ));
            }
        }

        $arguments = [];

        foreach ($parameters as $name => $parameter) {
            $path = self::path($prefix, $name);

            if (!\array_key_exists($name, $raw)) {
                if ($parameter->isDefaultValueAvailable()) {
                    $arguments[] = $parameter->getDefaultValue();

                    continue;
                }

                throw new ConfigurationHydrationFailed(\sprintf('Required configuration value "%s" is missing.', $path));
            }

            $arguments[] = $this->convert($raw[$name], $parameter->getType(), $path);
        }

        try {
            return $reflection->newInstanceArgs($arguments);
        } catch (ConfigurationHydrationFailed $error) {
            throw $error;
        } catch (\Throwable $error) {
            throw new ConfigurationHydrationFailed(\sprintf(
                'Configuration class %s rejected its values: %s',
                $class,
                $error->getMessage(),
            ), previous: $error);
        }
    }

    private function convert(mixed $value, ?\ReflectionType $type, string $path): mixed
    {
        if (!$type instanceof \ReflectionNamedType) {
            throw new ConfigurationHydrationFailed(\sprintf(
                'Configuration value "%s" requires one declared named type.',
                $path,
            ));
        }

        if ($value === null) {
            if ($type->allowsNull()) {
                return null;
            }

            throw self::typeFailure($path, $type->getName());
        }

        $name = $type->getName();

        if ($type->isBuiltin()) {
            return $this->convertBuiltin($value, $name, $path);
        }

        $class = self::className($name, $path);

        if ($value instanceof $class) {
            return $value;
        }

        if ($class === Secret::class && \is_string($value)) {
            $component = strstr($path, '.', true);

            return $component === false ? new Secret($value) : new Secret($value, $component);
        }

        if (is_subclass_of($class, \BackedEnum::class)) {
            if (!\is_string($value) && !\is_int($value)) {
                throw self::typeFailure($path, $class);
            }

            try {
                return $class::from($value);
            } catch (\ValueError $error) {
                throw new ConfigurationHydrationFailed(\sprintf(
                    'Configuration value "%s" is not a valid %s case.',
                    $path,
                    $class,
                ), previous: $error);
            }
        }

        if (\is_array($value) && ($value === [] || !array_is_list($value))) {
            return $this->hydrate($class, new ConfigurationValues($value), $path);
        }

        throw self::typeFailure($path, $class);
    }

    private function convertBuiltin(mixed $value, string $type, string $path): mixed
    {
        return match ($type) {
            'string' => \is_string($value) ? $value : throw self::typeFailure($path, $type),
            'int' => $this->integer($value, $path),
            'float' => $this->floatingPoint($value, $path),
            'bool' => $this->boolean($value, $path),
            'array' => \is_array($value) ? $value : throw self::typeFailure($path, $type),
            default => throw new ConfigurationHydrationFailed(\sprintf(
                'Configuration value "%s" uses unsupported built-in type %s.',
                $path,
                $type,
            )),
        };
    }

    private function integer(mixed $value, string $path): int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (!\is_string($value) || preg_match('/^-?(?:0|[1-9][0-9]*)$/D', $value) !== 1) {
            throw self::typeFailure($path, 'int');
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if ($integer === false) {
            throw self::typeFailure($path, 'int');
        }

        return $integer;
    }

    private function floatingPoint(mixed $value, string $path): float
    {
        if (\is_float($value) || \is_int($value)) {
            return (float) $value;
        }

        if (!\is_string($value) || !is_numeric($value)) {
            throw self::typeFailure($path, 'float');
        }

        $float = (float) $value;

        if (!is_finite($float)) {
            throw self::typeFailure($path, 'finite float');
        }

        return $float;
    }

    private function boolean(mixed $value, string $path): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_string($value)) {
            return match (strtolower($value)) {
                '1', 'true' => true,
                '0', 'false' => false,
                default => throw self::typeFailure($path, 'bool'),
            };
        }

        throw self::typeFailure($path, 'bool');
    }

    private static function path(string $prefix, string $name): string
    {
        return $prefix === '' ? $name : $prefix . '.' . $name;
    }

    private static function typeFailure(string $path, string $expected): ConfigurationHydrationFailed
    {
        return new ConfigurationHydrationFailed(\sprintf(
            'Configuration value "%s" cannot be converted losslessly to %s.',
            $path,
            $expected,
        ));
    }

    /** @return class-string<object> */
    private static function className(string $name, string $path): string
    {
        if (!class_exists($name) && !enum_exists($name)) {
            throw new ConfigurationHydrationFailed(\sprintf(
                'Configuration value "%s" references unknown type %s.',
                $path,
                $name,
            ));
        }

        return $name;
    }
}
