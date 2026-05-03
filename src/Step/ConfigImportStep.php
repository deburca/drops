<?php

declare(strict_types=1);

namespace Drops\Step;

use Drops\Pipeline\DeployContext;
use Drops\Pipeline\StepResult;

final class ConfigImportStep implements StepInterface
{
    public function getId(): string
    {
        return 'config_import';
    }

    public function getLabel(): string
    {
        return 'Import configuration';
    }

    public function getPhase(): Phase
    {
        return Phase::IMPORT;
    }

    public function run(DeployContext $context): StepResult
    {
        if ($context->packageReader === null) {
            return StepResult::failed('No package reader available');
        }

        $config = $context->getStepConfig('config_import');
        $syncDir = $config['sync_dir'] ?? '../config/sync';
        $log = [];

        // Copy config from package to the target's config sync directory
        $packageConfigDir = $context->packageReader->getConfigDir();
        $targetConfigDir = $context->envConfig->webroot . '/' . $syncDir;

        $rsyncCmd = sprintf(
            'rsync -a --delete %s %s',
            escapeshellarg($packageConfigDir . '/'),
            escapeshellarg($targetConfigDir . '/'),
        );

        $log[] = 'Copying config files to sync directory...';
        $copyResult = $context->environment->execute($rsyncCmd);

        if (!$copyResult->isSuccessful()) {
            return StepResult::failed(
                sprintf('Failed to copy config files (exit code %d)', $copyResult->exitCode),
                array_merge($log, [$copyResult->getErrorOutput()]),
            );
        }

        // Run drush config:import
        $command = $context->drushCommand('config:import --yes');
        $log[] = 'Running drush config:import...';
        $result = $context->environment->execute($command);

        if (!$result->isSuccessful()) {
            return StepResult::failed(
                sprintf('Config import failed (exit code %d)', $result->exitCode),
                array_merge($log, [$result->getErrorOutput()]),
            );
        }

        return StepResult::success($log);
    }
}
