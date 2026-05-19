<?php

declare(strict_types=1);

namespace Drops\Command;

use Drops\Config\ApplicationConfig;
use Drops\Config\ConfigLoader;
use Drops\Config\ConfigValidator;
use Drops\Config\EnvironmentConfig;
use Drops\Environment\EnvironmentFactory;
use Drops\Environment\EnvironmentInterface;
use Drops\Step\StepRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

abstract class DropsCommand extends Command
{
    protected ?ConfigLoader $configLoader = null;
    protected ?ConfigValidator $configValidator = null;
    protected ?StepRegistry $stepRegistry = null;

    protected function configure(): void
    {
        $this->addOption(
            'config-dir',
            null,
            InputOption::VALUE_REQUIRED,
            'Path to config directory',
            $this->getDefaultConfigDir(),
        );
    }

    /**
     * Get a required option value, throwing a clear error if it is missing.
     *
     * Symfony Console options marked as VALUE_REQUIRED only require a value
     * when the flag is present — they do not enforce that the flag itself is
     * provided. This helper fills that gap for options that are logically
     * mandatory for a command to run.
     */
    protected function requireOption(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);

        if ($value === null || $value === '') {
            throw new \InvalidArgumentException(sprintf(
                'The "--%s" option is required. See "%s --help" for usage.',
                $name,
                $this->getName(),
            ));
        }

        return (string) $value;
    }

    /**
     * Validate that a file path exists and is readable.
     */
    protected function requireFileExists(string $path, string $optionName): void
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException(sprintf(
                'The file specified by "--%s" does not exist: %s',
                $optionName,
                $path,
            ));
        }

        if (!is_readable($path)) {
            throw new \InvalidArgumentException(sprintf(
                'The file specified by "--%s" is not readable: %s',
                $optionName,
                $path,
            ));
        }
    }

    /**
     * Validate that the parent directory of a path exists (for output files).
     */
    protected function requireDirectoryExists(string $path, string $optionName): void
    {
        $dir = dirname($path);

        if (!is_dir($dir)) {
            throw new \InvalidArgumentException(sprintf(
                'The directory for "--%s" does not exist: %s',
                $optionName,
                $dir,
            ));
        }

        if (!is_writable($dir)) {
            throw new \InvalidArgumentException(sprintf(
                'The directory for "--%s" is not writable: %s',
                $optionName,
                $dir,
            ));
        }
    }

    protected function getConfigLoader(InputInterface $input): ConfigLoader
    {
        if ($this->configLoader === null) {
            $configDir = $input->getOption('config-dir');

            if (!is_dir($configDir)) {
                throw new \InvalidArgumentException(sprintf(
                    'Configuration directory does not exist: %s'
                    . "\nCreate it or specify a different path with --config-dir.",
                    $configDir,
                ));
            }

            $this->configLoader = new ConfigLoader($configDir);
        }

        return $this->configLoader;
    }

    protected function getConfigValidator(): ConfigValidator
    {
        if ($this->configValidator === null) {
            $schemaDir = dirname(__DIR__, 2) . '/config/schema';
            $this->configValidator = new ConfigValidator($schemaDir);
        }

        return $this->configValidator;
    }

    protected function getStepRegistry(InputInterface $input): StepRegistry
    {
        if ($this->stepRegistry === null) {
            $this->stepRegistry = new StepRegistry();

            // Load custom steps if the file exists
            $configDir = $input->getOption('config-dir');
            $this->stepRegistry->loadCustomSteps($configDir . '/steps.php');
        }

        return $this->stepRegistry;
    }

    protected function resolveApplication(InputInterface $input): ApplicationConfig
    {
        $appId = $this->requireOption($input, 'app');

        return $this->getConfigLoader($input)->loadApplication($appId);
    }

    protected function resolveEnvironment(InputInterface $input): EnvironmentConfig
    {
        $envId = $this->requireOption($input, 'env');

        return $this->getConfigLoader($input)->loadEnvironment($envId);
    }

    protected function createEnvironment(EnvironmentConfig $config): EnvironmentInterface
    {
        return EnvironmentFactory::create($config);
    }

    /**
     * Parse --steps and --skip-steps options to determine which steps to enable.
     *
     * @param string[] $enabledSteps The application's enabled steps
     * @return string[]|null Filtered step list, or null if no overrides
     */
    protected function resolveStepOverrides(InputInterface $input, array $enabledSteps): ?array
    {
        $stepsOption = $input->getOption('steps') ?? null;
        $skipStepsOption = $input->getOption('skip-steps') ?? null;

        if ($stepsOption !== null && $skipStepsOption !== null) {
            throw new \InvalidArgumentException(
                'The "--steps" and "--skip-steps" options are mutually exclusive. Use one or the other.',
            );
        }

        if ($stepsOption !== null) {
            return array_map('trim', explode(',', $stepsOption));
        }

        if ($skipStepsOption !== null) {
            $skipSteps = array_map('trim', explode(',', $skipStepsOption));
            return array_diff($enabledSteps, $skipSteps);
        }

        return null;
    }

    private function getDefaultConfigDir(): string
    {
        $home = $_SERVER['HOME'] ?? getenv('HOME') ?: '/tmp';

        return $home . '/.drops';
    }
}
