<?php

declare(strict_types=1);

namespace Drops\Tests\Unit\Command;

use Drops\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests that commands produce clear error messages for missing or invalid parameters.
 *
 * These tests deliberately omit required options to verify early validation,
 * so they do not need a real config directory or YAML files.
 */
final class ParameterValidationTest extends TestCase
{
    private Application $application;

    protected function setUp(): void
    {
        $this->application = new Application();
        $this->application->setAutoExit(false);
        $this->application->setCatchExceptions(false);
    }

    // ── ExportCommand ────────────────────────────────────────────────────────

    public function testExportRequiresOutputOption(): void
    {
        $tester = new CommandTester($this->application->find('export'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "--output" option is required');

        $tester->execute([]);
    }

    public function testExportRequiresAppOption(): void
    {
        $dir = sys_get_temp_dir();
        $tester = new CommandTester($this->application->find('export'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "--app" option is required');

        $tester->execute(['--output' => $dir . '/test.tar.gz']);
    }

    public function testExportRequiresEnvOption(): void
    {
        $configDir = $this->createTempConfigDir();
        $this->createAppConfig($configDir, 'testapp');
        $dir = sys_get_temp_dir();
        $tester = new CommandTester($this->application->find('export'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "--env" option is required');

        $tester->execute([
            '--output' => $dir . '/test.tar.gz',
            '--app' => 'testapp',
            '--config-dir' => $configDir,
        ]);
    }

    public function testExportRejectsOutputWithNonexistentDirectory(): void
    {
        $tester = new CommandTester($this->application->find('export'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The directory for "--output" does not exist');

        $tester->execute(['--output' => '/nonexistent/path/package.tar.gz']);
    }

    // ── ImportCommand ────────────────────────────────────────────────────────

    public function testImportRequiresPackageOption(): void
    {
        $tester = new CommandTester($this->application->find('import'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "--package" option is required');

        $tester->execute([]);
    }

    public function testImportRejectsNonexistentPackage(): void
    {
        $tester = new CommandTester($this->application->find('import'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The file specified by "--package" does not exist');

        $tester->execute(['--package' => '/nonexistent/package.tar.gz']);
    }

    public function testImportRequiresAppOption(): void
    {
        $packagePath = $this->createTempFile('package.tar.gz');
        $configDir = $this->createTempConfigDir();
        $tester = new CommandTester($this->application->find('import'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "--app" option is required');

        $tester->execute([
            '--package' => $packagePath,
            '--config-dir' => $configDir,
        ]);
    }

    // ── PingCommand ──────────────────────────────────────────────────────────

    public function testPingRequiresEnvOption(): void
    {
        $tester = new CommandTester($this->application->find('ping'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "--env" option is required');

        $tester->execute([]);
    }

    // ── ValidateCommand ───────────────────────────────────────────────────────

    public function testValidateRequiresAppOrEnvOrAll(): void
    {
        $configDir = $this->createTempConfigDir();
        $tester = new CommandTester($this->application->find('validate'));

        $tester->execute(['--config-dir' => $configDir]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Specify --app, --env, or --all', $tester->getDisplay());
    }

    // ── Config directory ──────────────────────────────────────────────────────

    public function testNonexistentConfigDirGivesClearError(): void
    {
        $tester = new CommandTester($this->application->find('ping'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Configuration directory does not exist');

        $tester->execute([
            '--env' => 'production',
            '--config-dir' => '/nonexistent/config/path',
        ]);
    }

    // ── Step override mutual exclusivity ──────────────────────────────────────

    public function testStepsAndSkipStepsAreMutuallyExclusive(): void
    {
        $configDir = $this->createTempConfigDir();
        $this->createAppConfig($configDir, 'testapp');
        $this->createEnvConfig($configDir, 'testenv');
        $dir = sys_get_temp_dir();

        $tester = new CommandTester($this->application->find('export'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('mutually exclusive');

        $tester->execute([
            '--app' => 'testapp',
            '--env' => 'testenv',
            '--output' => $dir . '/test.tar.gz',
            '--steps' => 'database_export',
            '--skip-steps' => 'cache_rebuild',
            '--config-dir' => $configDir,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function createTempConfigDir(): string
    {
        $dir = sys_get_temp_dir() . '/drops-test-' . uniqid();
        mkdir($dir, 0755, true);
        mkdir($dir . '/applications', 0755, true);
        mkdir($dir . '/environments', 0755, true);

        return $dir;
    }

    private function createTempFile(string $name): string
    {
        $path = sys_get_temp_dir() . '/drops-test-' . uniqid() . '/' . $name;
        mkdir(dirname($path), 0755, true);
        file_put_contents($path, '');

        return $path;
    }

    private function createAppConfig(string $configDir, string $id): void
    {
        $content = "id: {$id}\nsteps:\n  database_export: true\n  cache_rebuild: true\n";
        file_put_contents($configDir . '/applications/' . $id . '.yml', $content);
    }

    private function createEnvConfig(string $configDir, string $id): void
    {
        $content = "id: {$id}\naccess:\n  type: local\npaths:\n  webroot: /var/www\n";
        file_put_contents($configDir . '/environments/' . $id . '.yml', $content);
    }
}
