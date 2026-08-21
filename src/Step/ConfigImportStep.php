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

        // PackageReader extracts the package on the machine running DROPS,
        // which is a different filesystem than the target environment for
        // SSH deployments. Stage the config files there first so the rsync
        // below (which runs *on* the environment) can find them.
        $packageConfigDir = $context->packageReader->getConfigDir();
        $targetConfigDir = $context->envConfig->webroot . '/' . $syncDir;
        $stagingDir = rtrim($context->envConfig->getTempDir(), '/') . '/drops-config-import-' . date('YmdHis');

        $stageResult = $context->environment->execute(sprintf('mkdir -p %s', escapeshellarg($stagingDir)));
        if (!$stageResult->isSuccessful()) {
            return StepResult::failed(
                sprintf('Failed to create staging directory (exit code %d)', $stageResult->exitCode),
                [$stageResult->getErrorOutput()],
            );
        }
        $context->environment->upload($packageConfigDir, $stagingDir);
        $log[] = sprintf('Staged config on target: %s', $stagingDir);

        try {
            $rsyncCmd = sprintf(
                'rsync -a --delete %s %s',
                escapeshellarg($stagingDir . '/'),
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
        } finally {
            $context->environment->execute(sprintf('rm -rf %s', escapeshellarg($stagingDir)));
        }
    }
}
