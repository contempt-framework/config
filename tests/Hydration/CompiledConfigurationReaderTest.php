<?php

declare(strict_types=1);

namespace Contempt\Config\Tests\Hydration;

use Contempt\Config\ConfigurationValues;
use Contempt\Config\Exception\ConfigurationHydrationFailed;
use Contempt\Config\Hydration\CompiledConfigurationReader;
use Contempt\Core\Secret\SecretAccess;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompiledConfigurationReader::class)]
final class CompiledConfigurationReaderTest extends TestCase
{
    /** @return iterable<string, array{mixed}> */
    public static function invalidIntegers(): iterable
    {
        yield 'fraction' => ['1.5'];
        yield 'leading zero' => ['01'];
        yield 'positive sign' => ['+1'];
        yield 'overflow' => [(string) PHP_INT_MAX . '0'];
        yield 'boolean' => [true];
    }

    #[DataProvider('invalidIntegers')]
    public function testIntegerConversionRejectsLossAndOverflow(mixed $value): void
    {
        $reader = $this->reader(['workers' => $value], ['workers']);

        $this->expectException(ConfigurationHydrationFailed::class);
        $this->expectExceptionMessage('app.workers');

        $reader->integer($reader->required('workers'), 'workers');
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidFloatingPoints(): iterable
    {
        yield 'nan' => ['NAN'];
        yield 'positive infinity' => ['INF'];
        yield 'negative infinity' => ['-INF'];
        yield 'leading whitespace' => [' 1.5'];
        yield 'trailing whitespace' => ['1.5 '];
        yield 'leading zero' => ['01.5'];
        yield 'boolean' => [false];
    }

    #[DataProvider('invalidFloatingPoints')]
    public function testFloatConversionRejectsNonFiniteOrUnrelatedValues(mixed $value): void
    {
        $reader = $this->reader(['ratio' => $value], ['ratio']);

        $this->expectException(ConfigurationHydrationFailed::class);
        $this->expectExceptionMessage('app.ratio');

        $reader->floatingPoint($reader->required('ratio'), 'ratio');
    }

    public function testUnknownKeysAreSortedAndReportedWithFullPaths(): void
    {
        $this->expectException(ConfigurationHydrationFailed::class);
        $this->expectExceptionMessage('app.alpha, app.zeta');

        $this->reader(['zeta' => 1, 'alpha' => 2], []);
    }

    public function testBackedEnumRejectsUnknownCasesAndAcceptsAnExistingInstance(): void
    {
        $reader = $this->reader([], []);

        self::assertSame(CompiledMode::Safe, $reader->backedEnum(CompiledMode::Safe, CompiledMode::class, 'mode'));

        $this->expectException(ConfigurationHydrationFailed::class);
        $this->expectExceptionMessage('app.mode');
        $reader->backedEnum('unknown', CompiledMode::class, 'mode');
    }

    public function testSecretUsesConfigurationComponentAsAccessScope(): void
    {
        $reader = $this->reader(['token' => 'classified'], ['token']);
        $secret = $reader->secret($reader->required('token'), 'token');

        self::assertSame('[REDACTED]', (string) $secret);
        self::assertSame('classified', $secret->reveal(SecretAccess::for('app', 'verify generated configuration')));
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string> $allowed
     */
    private function reader(array $values, array $allowed): CompiledConfigurationReader
    {
        return new CompiledConfigurationReader(new ConfigurationValues($values), 'app', $allowed);
    }
}

enum CompiledMode: string
{
    case Safe = 'safe';
}
