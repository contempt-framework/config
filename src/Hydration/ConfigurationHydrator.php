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

        $parameters = [];

        foreach ($constructor->getParameters() as $parameter) {
            $parameters[$parameter->getName()] = $parameter;
        }

        $reader = new CompiledConfigurationReader($values, $prefix, array_keys($parameters));

        $arguments = [];

        foreach ($parameters as $name => $parameter) {
            $value = $parameter->isDefaultValueAvailable()
                ? $reader->valueOr($name, $parameter->getDefaultValue())
                : $reader->required($name);
            $arguments[] = $this->convert($value, $parameter->getType(), $name, $prefix, $reader);
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

    private function convert(
        mixed $value,
        ?\ReflectionType $type,
        string $name,
        string $prefix,
        CompiledConfigurationReader $reader,
    ): mixed {
        $path = self::path($prefix, $name);

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

        $typeName = $type->getName();

        if ($type->isBuiltin()) {
            return match ($typeName) {
                'string' => $reader->string($value, $name),
                'int' => $reader->integer($value, $name),
                'float' => $reader->floatingPoint($value, $name),
                'bool' => $reader->boolean($value, $name),
                'array' => $reader->array($value, $name),
                default => throw new ConfigurationHydrationFailed(\sprintf(
                    'Configuration value "%s" uses unsupported built-in type %s.',
                    $path,
                    $typeName,
                )),
            };
        }

        $class = self::className($typeName, $path);

        if ($value instanceof $class) {
            return $value;
        }

        if ($class === Secret::class) {
            return $reader->secret($value, $name);
        }

        if (is_subclass_of($class, \BackedEnum::class)) {
            return $reader->backedEnum($value, $class, $name);
        }

        if (\is_array($value) && ($value === [] || !array_is_list($value))) {
            return $this->hydrate($class, new ConfigurationValues($value), $path);
        }

        throw self::typeFailure($path, $class);
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
