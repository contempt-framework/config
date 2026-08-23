<?php

declare(strict_types=1);

namespace Contempt\Config\Tests;

use Contempt\Config\ConfigurationRequest;
use Contempt\Config\ConfigurationValues;
use Contempt\Config\Exception\InvalidConfigurationPath;
use Contempt\Config\Exception\MissingConfigurationValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigurationRequest::class)]
#[CoversClass(ConfigurationValues::class)]
final class ConfigurationValuesTest extends TestCase
{
    public function testRootMustBeAnAssociativeMap(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ConfigurationValues(['first', 'second']);
    }

    public function testKeysCannotContainEmptyOrTraversalSegments(): void
    {
        $this->expectException(InvalidConfigurationPath::class);

        new ConfigurationValues(['database..password' => 'secret']);
    }

    public function testUnsupportedRuntimeValuesAreRejectedAtTheBoundary(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('resource');

        $stream = fopen('php://memory', 'rb');
        self::assertIsResource($stream);

        try {
            new ConfigurationValues(['stream' => $stream]);
        } finally {
            fclose($stream);
        }
    }

    public function testMissingValueDoesNotSilentlyBecomeNull(): void
    {
        $values = new ConfigurationValues(['database' => ['host' => 'localhost']]);

        $this->expectException(MissingConfigurationValue::class);
        $this->expectExceptionMessage('database.port');

        $values->require('database.port');
    }

    public function testNullIsDistinctFromAnAbsentValue(): void
    {
        $values = new ConfigurationValues(['feature' => null]);

        self::assertTrue($values->has('feature'));
        self::assertNull($values->require('feature'));
    }

    public function testHigherPrecedenceMapsMergeRecursivelyAndListsReplaceAtomically(): void
    {
        $base = new ConfigurationValues([
            'database' => ['host' => 'db', 'ports' => [5432, 5433]],
            'logging' => ['level' => 'info'],
        ]);
        $override = new ConfigurationValues([
            'database' => ['ports' => [6432]],
            'logging' => ['json' => true],
        ]);

        $merged = $base->overlaidBy($override);

        self::assertSame([6432], $merged->require('database.ports'));
        self::assertSame('db', $merged->require('database.host'));
        self::assertSame(['level' => 'info'], $base->require('logging'), 'operands remain immutable');
        self::assertSame(['level' => 'info', 'json' => true], $merged->require('logging'));
    }

    public function testScalarAndMapShapeConflictIsRejectedInsteadOfLastWriteWins(): void
    {
        $base = new ConfigurationValues(['database' => ['host' => 'db']]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('database');

        $unexpected = $base->overlaidBy(new ConfigurationValues(['database' => 'sqlite::memory:']));
        self::fail('Expected a shape conflict, received ' . json_encode($unexpected->all(), JSON_THROW_ON_ERROR));
    }

    public function testRequestRejectsAnInvalidPrefix(): void
    {
        $this->expectException(InvalidConfigurationPath::class);

        new ConfigurationRequest('../secrets');
    }

    public function testRequestCanSelectOnlyItsPrefix(): void
    {
        $values = new ConfigurationValues([
            'database' => ['host' => 'db'],
            'mail' => ['host' => 'smtp'],
        ]);

        self::assertSame(
            ['host' => 'db'],
            $values->forRequest(new ConfigurationRequest('database'))->all(),
        );
    }

    public function testAbsentRequestedPrefixProducesAnEmptyOverlay(): void
    {
        $values = new ConfigurationValues(['mail' => ['host' => 'smtp']]);

        self::assertSame([], $values->forRequest(new ConfigurationRequest('database'))->all());
    }
}
