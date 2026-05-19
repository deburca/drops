<?php

declare(strict_types=1);

namespace Drops\Command;

use Drops\Config\ApplicationConfig;
use Drops\Package\PackageReader;
use Drops\Pipeline\DeployContext;
use Drops\Pipeline\ImportPipeline;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'import', description: 'Import a deployment package to a target environment')]
final class ImportCommand extends DropsCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('app', null, InputOption::VALUE_REQUIRED, 'Application identifier')
            ->addOption('env', null, InputOption::VALUE_REQUIRED, 'Environment identifier')
            ->addOption('package', null, InputOption::VALUE_REQUIRED, 'Path to the .tar.gz package')
            ->addOption('steps', null, InputOption::VALUE_OPTIONAL, 'Override which steps run (comma-separated)')
            ->addOption('skip-steps', null, InputOption::VALUE_OPTIONAL, 'Steps to skip')
            ->addOption('no-maintenance', null, InputOption::VALUE_NONE, 'Skip maintenance mode')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate without executing')
            ->addOption('continue-on-error', null, InputOption::VALUE_NONE, 'Continue on step failure');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $packagePath = $this->requireOption($input, 'package');
        $this->requireFileExists($packagePath, 'package');

        $appConfig = $this->resolveApplication($input);
        $envConfig = $this->resolveEnvironment($input);
        $environment = $this->createEnvironment($envConfig);
        $dryRun = $input->getOption('dry-run');
        $continueOnError = $input->getOption('continue-on-error');
        $noMaintenance = $input->getOption('no-maintenance');

        $output->writeln(sprintf(
            '<info>DROPS Import</info> — %s to %s',
            $appConfig->label ?? $appConfig->id,
            $envConfig->label ?? $envConfig->id,
        ));
        $output->writeln('');

        // Open and extract the package
        $reader = new PackageReader($packagePath);
        $extractDir = $envConfig->getTempDir() . '/drops-import-' . date('YmdHis');
        $reader->open($extractDir);

        $manifest = $reader->getManifest();
        $output->writeln(sprintf(
            'Package from: %s (%s)',
            $manifest->sourceEnvironmentId,
            $manifest->createdAt->format('Y-m-d H:i:s'),
        ));
        $output->writeln('');

        // Apply step overrides
        $stepOverrides = $this->resolveStepOverrides($input, $appConfig->getEnabledSteps());
        if ($stepOverrides !== null) {
            $steps = [];
            foreach ($appConfig->steps as $id => $enabled) {
                $steps[$id] = in_array($id, $stepOverrides, true);
            }
            $appConfig = new ApplicationConfig(
                id: $appConfig->id,
                steps: $steps,
                stepConfig: $appConfig->stepConfig,
                label: $appConfig->label,
                importOptions: $appConfig->importOptions,
            );
        }

        // Handle --no-maintenance
        if ($noMaintenance) {
            $steps = $appConfig->steps;
            $steps['maintenance_on'] = false;
            $steps['maintenance_off'] = false;
            $appConfig = new ApplicationConfig(
                id: $appConfig->id,
                steps: $steps,
                stepConfig: $appConfig->stepConfig,
                label: $appConfig->label,
                importOptions: $appConfig->importOptions,
            );
        }

        $registry = $this->getStepRegistry($input);
        $pipeline = new ImportPipeline($registry->getImportSteps());

        $context = new DeployContext(
            appConfig: $appConfig,
            envConfig: $envConfig,
            environment: $environment,
            output: $output,
            dryRun: $dryRun,
            packageReader: $reader,
        );

        $results = $pipeline->run($context, $continueOnError);

        // Cleanup extracted package
        $reader->cleanup();

        $hasFailures = false;
        foreach ($results as $result) {
            if ($result->isFailed()) {
                $hasFailures = true;
                break;
            }
        }

        return $hasFailures ? self::FAILURE : self::SUCCESS;
    }
}
