<?php

declare(strict_types=1);

namespace Contempt\Config;

use Contempt\Config\Exception\MissingConfigurationValue;
use Contempt\Config\Internal\ConfigurationPath;
use Contempt\Core\Secret\Secret;

/**
 * Immutable hierarchical raw configuration at the provider boundary.
 *
 * Values deliberately remain raw here. Type conversion happens exactly once,
 * while hydrating a declared configuration class.
 */
final readonly class ConfigurationValues
{
    /** @var array<string, mixed> */
    private array $values;

    /**
     * @param array<array-key, mixed> $values
     */
    public function __construct(array $values)
    {
        if ($values !== [] && array_is_list($values)) {
            throw new \InvalidArgumentException('Configuration root must be an associative map.');
        }

        self::validateMap($values, '');

        /** @var array<string, mixed> $values */
        $this->values = $values;
    }

    #[\NoDiscard]
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param array<string, mixed> $flat
     */
    #[\NoDiscard]
    public static function fromFlat(array $flat): self
    {
        $nested = [];

        foreach ($flat as $path => $value) {
            $segments = ConfigurationPath::segments($path);
            $cursor = &$nested;

            foreach ($segments as $index => $segment) {
                if ($index === array_key_last($segments)) {
                    if (\array_key_exists($segment, $cursor)) {
                        throw new \InvalidArgumentException(\sprintf('Configuration path "%s" is defined more than once.', $path));
                    }

                    $cursor[$segment] = $value;

                    continue;
                }

                if (isset($cursor[$segment]) && !\is_array($cursor[$segment])) {
                    throw new \InvalidArgumentException(\sprintf('Configuration path "%s" conflicts with a scalar value.', $path));
                }

                $cursor[$segment] ??= [];
                $cursor = &$cursor[$segment];
            }

            unset($cursor);
        }

        return new self($nested);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->values;
    }

    public function has(string $path): bool
    {
        [$found] = $this->find($path);

        return $found;
    }

    public function require(string $path): mixed
    {
        [$found, $value] = $this->find($path);

        if (!$found) {
            throw new MissingConfigurationValue($path);
        }

        return $value;
    }

    #[\NoDiscard]
    public function forRequest(ConfigurationRequest $request): self
    {
        if ($request->prefix === null) {
            return $this;
        }

        [$found, $value] = $this->find($request->prefix);

        if (!$found) {
            return self::empty();
        }

        if (!\is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new \InvalidArgumentException(\sprintf(
                'Configuration prefix "%s" must select an associative map.',
                $request->prefix,
            ));
        }

        return new self($value);
    }

    #[\NoDiscard]
    public function overlaidBy(self $higherPrecedence): self
    {
        return new self(self::merge($this->values, $higherPrecedence->values));
    }

    /** @return array{bool, mixed} */
    private function find(string $path): array
    {
        $segments = ConfigurationPath::segments($path);
        $cursor = $this->values;

        foreach ($segments as $index => $segment) {
            if (!\array_key_exists($segment, $cursor)) {
                return [false, null];
            }

            $value = $cursor[$segment];

            if ($index === array_key_last($segments)) {
                return [true, $value];
            }

            if (!\is_array($value) || ($value !== [] && array_is_list($value))) {
                return [false, null];
            }

            $cursor = $value;
        }

        return [false, null];
    }

    /**
     * @param array<array-key, mixed> $lower
     * @param array<array-key, mixed> $higher
     * @return array<array-key, mixed>
     */
    private static function merge(array $lower, array $higher, string $prefix = ''): array
    {
        foreach ($higher as $key => $higherValue) {
            if (!\is_string($key)) {
                throw new \LogicException('Configuration maps cannot contain integer keys.');
            }

            if (!\array_key_exists($key, $lower)) {
                $lower[$key] = $higherValue;

                continue;
            }

            $path = $prefix === '' ? $key : $prefix . '.' . $key;
            $lowerValue = $lower[$key];
            if (\is_array($lowerValue) !== \is_array($higherValue)) {
                throw new \InvalidArgumentException(\sprintf('Configuration shape conflict at "%s".', $path));
            }

            if (!\is_array($lowerValue) || !\is_array($higherValue)) {
                $lower[$key] = $higherValue;

                continue;
            }

            $lowerIsList = array_is_list($lowerValue);
            $higherIsList = array_is_list($higherValue);

            if ($lowerIsList !== $higherIsList) {
                throw new \InvalidArgumentException(\sprintf('Configuration shape conflict at "%s".', $path));
            }

            $lower[$key] = $lowerIsList
                ? $higherValue
                : self::merge($lowerValue, $higherValue, $path);
        }

        return $lower;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function validateMap(array $values, string $prefix): void
    {
        foreach ($values as $key => $value) {
            if (!\is_string($key)) {
                throw new \InvalidArgumentException('Configuration map keys must be strings.');
            }

            ConfigurationPath::segment($key);
            $path = $prefix === '' ? $key : $prefix . '.' . $key;
            self::validateValue($value, $path);
        }
    }

    private static function validateValue(mixed $value, string $path): void
    {
        if ($value === null || \is_scalar($value) || $value instanceof Secret) {
            return;
        }

        if (!\is_array($value)) {
            throw new \InvalidArgumentException(\sprintf(
                'Unsupported configuration value of type %s at "%s".',
                get_debug_type($value),
                $path,
            ));
        }

        if (array_is_list($value)) {
            foreach ($value as $index => $element) {
                self::validateValue($element, $path . '.' . $index);
            }

            return;
        }

        self::validateMap($value, $path);
    }
}
