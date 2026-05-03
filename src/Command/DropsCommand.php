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
        $this->addOption('config-dir', null, InputOption::VALUE_REQUIRED, 'Path to config directory', $this->getDefaultConfigDir());
    }

    protected function getConfigLoader(InputInterface $input): ConfigLoader
    {
        if ($this->configLoader === null) {
            $configDir = $input->getOption('config-dir');
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
        $appId = $input->getOption('app');

        return $this->getConfigLoader($input)->loadApplication($appId);
    }

    protected function resolveEnvironment(InputInterface $input): EnvironmentConfig
    {
        $envId = $input->getOption('env');

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
