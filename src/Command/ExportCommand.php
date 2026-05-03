<?php

declare(strict_types=1);

namespace Drops\Command;

use Drops\Config\ApplicationConfig;
use Drops\Package\PackageBuilder;
use Drops\Pipeline\DeployContext;
use Drops\Pipeline\ExportPipeline;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'export', description: 'Export a deployment package from a source environment')]
final class ExportCommand extends DropsCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('app', null, InputOption::VALUE_REQUIRED, 'Application identifier')
            ->addOption('env', null, InputOption::VALUE_REQUIRED, 'Environment identifier')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output package path (.tar.gz)')
            ->addOption('label', null, InputOption::VALUE_OPTIONAL, 'Human-readable label for this deployment')
            ->addOption('steps', null, InputOption::VALUE_OPTIONAL, 'Override which steps run (comma-separated)')
            ->addOption('skip-steps', null, InputOption::VALUE_OPTIONAL, 'Steps to skip')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate without executing')
            ->addOption('continue-on-error', null, InputOption::VALUE_NONE, 'Continue on step failure');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $appConfig = $this->resolveApplication($input);
        $envConfig = $this->resolveEnvironment($input);
        $environment = $this->createEnvironment($envConfig);
        $outputPath = $input->getOption('output');
        $dryRun = $input->getOption('dry-run');
        $continueOnError = $input->getOption('continue-on-error');

        $output->writeln(sprintf(
            '<info>DROPS Export</info> — %s from %s',
            $appConfig->label ?? $appConfig->id,
            $envConfig->label ?? $envConfig->id,
        ));
        $output->writeln('');

        // Apply step overrides if provided
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

        // Create staging directory for the package
        $tempDir = $envConfig->getTempDir() . '/drops-export-' . date('YmdHis');
        $packageBuilder = new PackageBuilder($tempDir);
        $packageBuilder->init();

        $registry = $this->getStepRegistry($input);
        $pipeline = new ExportPipeline($registry->getExportSteps());

        $context = new DeployContext(
            appConfig: $appConfig,
            envConfig: $envConfig,
            environment: $environment,
            output: $output,
            dryRun: $dryRun,
            packageBuilder: $packageBuilder,
        );

        $results = $pipeline->run($context, $continueOnError);

        // Finalise the package
        if (!$dryRun) {
            $hasFailed = false;
            foreach ($results as $result) {
                if ($result->isFailed()) {
                    $hasFailed = true;
                    break;
                }
            }

            if (!$hasFailed) {
                $packageBuilder->finalise(
                    $appConfig,
                    $envConfig,
                    $outputPath,
                    $input->getOption('label'),
                );
                $output->writeln(sprintf('<info>Package written to:</info> %s', $outputPath));
            }
        }

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
