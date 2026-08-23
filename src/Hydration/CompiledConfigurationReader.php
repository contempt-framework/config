<?php

declare(strict_types=1);

namespace Contempt\Config\Hydration;

use Contempt\Config\ConfigurationValues;
use Contempt\Config\Exception\ConfigurationHydrationFailed;
use Contempt\Core\Secret\Secret;

/** Reflection-free conversion primitives called by a generated container. */
final readonly class CompiledConfigurationReader
{
    /** @var array<string, mixed> */
    private array $values;

    /** @param list<string> $allowed */
    public function __construct(ConfigurationValues $values, private string $prefix, array $allowed)
    {
        $this->values = $values->all();
        $unknown = array_values(array_diff(array_keys($this->values), $allowed));

        if ($unknown !== []) {
            sort($unknown, SORT_STRING);

            throw new ConfigurationHydrationFailed(\sprintf(
                'Unknown configuration value(s): %s.',
                implode(', ', array_map($this->path(...), $unknown)),
            ));
        }
    }

    public function required(string $name): mixed
    {
        if (!\array_key_exists($name, $this->values)) {
            throw new ConfigurationHydrationFailed(\sprintf(
                'Required configuration value "%s" is missing.',
                $this->path($name),
            ));
        }

        return $this->values[$name];
    }

    public function valueOr(string $name, mixed $default): mixed
    {
        return \array_key_exists($name, $this->values) ? $this->values[$name] : $default;
    }

    public function string(mixed $value, string $name): string
    {
        return \is_string($value) ? $value : throw $this->typeFailure($name, 'string');
    }

    public function nullableString(mixed $value, string $name): ?string
    {
        return $value === null ? null : $this->string($value, $name);
    }

    public function integer(mixed $value, string $name): int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (!\is_string($value) || preg_match('/^-?(?:0|[1-9][0-9]*)$/D', $value) !== 1) {
            throw $this->typeFailure($name, 'int');
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return $integer !== false ? $integer : throw $this->typeFailure($name, 'int');
    }

    public function nullableInteger(mixed $value, string $name): ?int
    {
        return $value === null ? null : $this->integer($value, $name);
    }

    public function floatingPoint(mixed $value, string $name): float
    {
        if (\is_int($value) || \is_float($value)) {
            $float = (float) $value;
        } elseif (\is_string($value) && preg_match('/^[+-]?(?:(?:0|[1-9][0-9]*)(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?$/D', $value) === 1) {
            $float = (float) $value;
        } else {
            throw $this->typeFailure($name, 'float');
        }

        return is_finite($float) ? $float : throw $this->typeFailure($name, 'finite float');
    }

    public function nullableFloatingPoint(mixed $value, string $name): ?float
    {
        return $value === null ? null : $this->floatingPoint($value, $name);
    }

    public function boolean(mixed $value, string $name): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_string($value)) {
            return match (strtolower($value)) {
                '1', 'true' => true,
                '0', 'false' => false,
                default => throw $this->typeFailure($name, 'bool'),
            };
        }

        throw $this->typeFailure($name, 'bool');
    }

    public function nullableBoolean(mixed $value, string $name): ?bool
    {
        return $value === null ? null : $this->boolean($value, $name);
    }

    /** @return array<array-key, mixed> */
    public function array(mixed $value, string $name): array
    {
        return \is_array($value) ? $value : throw $this->typeFailure($name, 'array');
    }

    /** @return ?array<array-key, mixed> */
    public function nullableArray(mixed $value, string $name): ?array
    {
        return $value === null ? null : $this->array($value, $name);
    }

    public function secret(mixed $value, string $name): Secret
    {
        return $value instanceof Secret
            ? $value
            : (\is_string($value) ? new Secret($value, explode('.', $this->path($name), 2)[0]) : throw $this->typeFailure($name, Secret::class));
    }

    public function nullableSecret(mixed $value, string $name): ?Secret
    {
        return $value === null ? null : $this->secret($value, $name);
    }

    /**
     * @template T of \BackedEnum
     * @param class-string<T> $enum
     * @return T
     */
    public function backedEnum(mixed $value, string $enum, string $name): \BackedEnum
    {
        if ($value instanceof $enum) {
            return $value;
        }

        if (!\is_int($value) && !\is_string($value)) {
            throw $this->typeFailure($name, $enum);
        }

        try {
            return $enum::from($value);
        } catch (\ValueError $failure) {
            throw new ConfigurationHydrationFailed(\sprintf(
                'Configuration value "%s" is not a valid %s case.',
                $this->path($name),
                $enum,
            ), previous: $failure);
        }
    }

    /**
     * @template T of \BackedEnum
     * @param class-string<T> $enum
     * @return ?T
     */
    public function nullableBackedEnum(mixed $value, string $enum, string $name): ?\BackedEnum
    {
        return $value === null ? null : $this->backedEnum($value, $enum, $name);
    }

    private function path(string $name): string
    {
        return $this->prefix === '' ? $name : $this->prefix . '.' . $name;
    }

    private function typeFailure(string $name, string $expected): ConfigurationHydrationFailed
    {
        return new ConfigurationHydrationFailed(\sprintf(
            'Configuration value "%s" cannot be converted losslessly to %s.',
            $this->path($name),
            $expected,
        ));
    }
}
