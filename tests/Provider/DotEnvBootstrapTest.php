<?php

declare(strict_types=1);

namespace Contempt\Config\Tests\Provider;

use Contempt\Config\Provider\DotEnvBootstrap;
use Contempt\Config\Provider\DotEnvLoadingPolicy;
use Contempt\Core\Environment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DotEnvBootstrap::class)]
#[CoversClass(DotEnvLoadingPolicy::class)]
final class DotEnvBootstrapTest extends TestCase
{
    private string $directory;

    /** @var array<array-key, mixed> */
    private array $savedEnv;

    /** @var array<array-key, mixed> */
    private array $savedServer;

    protected function setUp(): void
    {
        $this->savedEnv = $_ENV;
        $this->savedServer = $_SERVER;
        $this->directory = sys_get_temp_dir() . '/contempt-dotenv-' . bin2hex(random_bytes(8));

        if (!mkdir($this->directory, 0o700)) {
            self::fail('Could not create the dotenv test directory.');
        }

        unset($_ENV['APP_ENV'], $_SERVER['APP_ENV'], $_ENV['APP_NAME'], $_SERVER['APP_NAME']);
        unset($_ENV['SYMFONY_DOTENV_PATH'], $_SERVER['SYMFONY_DOTENV_PATH']);
        unset($_ENV['SYMFONY_DOTENV_VARS'], $_SERVER['SYMFONY_DOTENV_VARS']);
    }

    protected function tearDown(): void
    {
        $_ENV = $this->savedEnv;
        $_SERVER = $this->savedServer;
        $files = glob($this->directory . '/.env*');

        if (\is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        rmdir($this->directory);
    }

    public function testAutomaticPolicyNeverReadsDotEnvInProduction(): void
    {
        $this->write('.env', "this is deliberately invalid\n");
        $_ENV['APP_NAME'] = 'process';

        $variables = new DotEnvBootstrap()->boot(
            $this->directory . '/.env',
            Environment::Production,
            DotEnvLoadingPolicy::Automatic,
        );

        self::assertSame(Environment::Production, $variables->environment);
        self::assertSame('process', $variables->values['APP_NAME'] ?? null);
        self::assertArrayNotHasKey('SYMFONY_DOTENV_VARS', $variables->values);
    }

    public function testExplicitProductionLoadingUsesSymfonyPrecedenceWithoutOverridingProcessValues(): void
    {
        $this->write('.env', "APP_ENV=prod\nAPP_NAME=base\nCONTEMPT_DOTENV_THREAD_TEST=base\n");
        $this->write('.env.local', "APP_NAME=local\nCONTEMPT_DOTENV_THREAD_TEST=local\n");
        $this->write('.env.prod', "APP_NAME=environment\nCONTEMPT_DOTENV_THREAD_TEST=environment\n");
        $this->write('.env.prod.local', "APP_NAME=machine\nCONTEMPT_DOTENV_THREAD_TEST=machine\n");
        $_ENV['APP_NAME'] = 'process';

        $variables = new DotEnvBootstrap()->boot(
            $this->directory . '/.env',
            Environment::Production,
            DotEnvLoadingPolicy::Enabled,
        );

        self::assertSame(Environment::Production, $variables->environment);
        self::assertSame('process', $variables->values['APP_NAME'] ?? null);
        self::assertSame('machine', $variables->values['CONTEMPT_DOTENV_THREAD_TEST'] ?? null);
        self::assertFalse(
            getenv('CONTEMPT_DOTENV_THREAD_TEST'),
            'Dotenv must not use the process-wide, thread-unsafe putenv API.',
        );
    }

    public function testTestEnvironmentIgnoresMachineLocalFile(): void
    {
        $this->write('.env', "APP_ENV=test\nAPP_NAME=base\n");
        $this->write('.env.local', "APP_NAME=must-not-load\n");
        $this->write('.env.test', "APP_NAME=test\n");
        $this->write('.env.test.local', "APP_NAME=test-machine\n");

        $variables = new DotEnvBootstrap()->boot(
            $this->directory . '/.env',
            Environment::Test,
            DotEnvLoadingPolicy::Automatic,
        );

        self::assertSame(Environment::Test, $variables->environment);
        self::assertSame('test-machine', $variables->values['APP_NAME'] ?? null);
    }

    public function testEnvironmentSelectedByDotEnvMustBeRecognised(): void
    {
        $this->write('.env', "APP_ENV=staging\n");

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown environment');

        (void) new DotEnvBootstrap()->boot(
            $this->directory . '/.env',
            Environment::Development,
            DotEnvLoadingPolicy::Enabled,
        );
    }

    public function testDisabledPolicyDoesNotRequireAnExistingFile(): void
    {
        $variables = new DotEnvBootstrap()->boot(
            $this->directory . '/missing',
            Environment::Development,
            DotEnvLoadingPolicy::Disabled,
        );

        self::assertSame(Environment::Development, $variables->environment);
        self::assertArrayNotHasKey('SYMFONY_DOTENV_VARS', $variables->values);
    }

    public function testProcessEnvironmentCannotDisagreeWithBootstrapEnvironment(): void
    {
        $_SERVER['APP_ENV'] = 'prod';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Process APP_ENV');

        (void) new DotEnvBootstrap()->boot(
            $this->directory . '/missing',
            Environment::Development,
            DotEnvLoadingPolicy::Disabled,
        );
    }

    public function testSelectedEnvironmentDefaultsSafelyToProduction(): void
    {
        self::assertSame(Environment::Production, DotEnvBootstrap::selectedEnvironment());

        $_SERVER['APP_ENV'] = 'TEST';

        self::assertSame(Environment::Test, DotEnvBootstrap::selectedEnvironment());
    }

    public function testLoadingPolicyFlagIsStrictlyParsed(): void
    {
        self::assertSame(DotEnvLoadingPolicy::Automatic, DotEnvLoadingPolicy::fromFlag(null));
        self::assertSame(DotEnvLoadingPolicy::Enabled, DotEnvLoadingPolicy::fromFlag('yes'));
        self::assertSame(DotEnvLoadingPolicy::Disabled, DotEnvLoadingPolicy::fromFlag('0'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('dotenv loading flag');

        (void) DotEnvLoadingPolicy::fromFlag('sometimes');
    }

    private function write(string $name, string $contents): void
    {
        if (file_put_contents($this->directory . '/' . $name, $contents) === false) {
            self::fail('Could not create a dotenv fixture.');
        }
    }
}
