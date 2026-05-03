<?php

declare(strict_types=1);

namespace Drops\Step;

use Drops\Pipeline\DeployContext;
use Drops\Pipeline\StepResult;

final class ConfigExportStep implements StepInterface
{
    public function getId(): string
    {
        return 'config_export';
    }

    public function getLabel(): string
    {
        return 'Export configuration';
    }

    public function getPhase(): Phase
    {
        return Phase::EXPORT;
    }

    public function run(DeployContext $context): StepResult
    {
        if ($context->packageBuilder === null) {
            return StepResult::failed('No package builder available');
        }

        $config = $context->getStepConfig('config_export');
        $syncDir = $config['sync_dir'] ?? '../config/sync';

        $log = [];

        // Run drush config:export
        $command = $context->drushCommand('config:export --yes');
        $log[] = 'Running drush config:export...';
        $result = $context->environment->execute($command);

        if (!$result->isSuccessful()) {
            return StepResult::failed(
                sprintf('Config export failed (exit code %d)', $result->exitCode),
                array_merge($log, [$result->getErrorOutput()]),
            );
        }

        // Copy exported config into the package
        $configSourcePath = $context->envConfig->webroot . '/' . $syncDir;
        $packageConfigDir = $context->packageBuilder->getConfigDir();

        $rsyncCmd = sprintf(
            'rsync -a %s %s',
            escapeshellarg($configSourcePath . '/'),
            escapeshellarg($packageConfigDir . '/'),
        );

        $log[] = 'Copying config files to package...';
        $copyResult = $context->environment->execute($rsyncCmd);

        if (!$copyResult->isSuccessful()) {
            return StepResult::failed(
                sprintf('Failed to copy config files (exit code %d)', $copyResult->exitCode),
                array_merge($log, [$copyResult->getErrorOutput()]),
            );
        }

        $context->packageBuilder->addChecksumForDirectory('config');
        $context->packageBuilder->recordStep($this->getId());

        return StepResult::success($log);
    }
}
