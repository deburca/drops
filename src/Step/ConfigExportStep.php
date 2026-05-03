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

        // Copy exported config into the package.
        // Use tar to stream files — works for both direct and containerised environments
        // since the output is captured on the host side.
        $configSourcePath = $context->envConfig->webroot . '/' . $syncDir;
        $packageConfigDir = $context->packageBuilder->getConfigDir();

        $tarCmd = sprintf('tar -cf - -C %s .', escapeshellarg($configSourcePath));
        $log[] = 'Copying config files to package...';
        $tarResult = $context->environment->execute($tarCmd);

        if (!$tarResult->isSuccessful()) {
            return StepResult::failed(
                sprintf('Failed to read config files (exit code %d)', $tarResult->exitCode),
                array_merge($log, [$tarResult->getErrorOutput()]),
            );
        }

        // Extract the tar stream into the package config directory on the host
        $tarFile = sys_get_temp_dir() . '/drops-config-' . uniqid() . '.tar';
        file_put_contents($tarFile, $tarResult->getOutput());

        $extractProcess = new \Symfony\Component\Process\Process(
            ['tar', '-xf', $tarFile, '-C', $packageConfigDir]
        );
        $extractProcess->run();
        @unlink($tarFile);

        if (!$extractProcess->isSuccessful()) {
            return StepResult::failed(
                'Failed to extract config files to package',
                array_merge($log, [$extractProcess->getErrorOutput()]),
            );
        }

        $context->packageBuilder->addChecksumForDirectory('config');
        $context->packageBuilder->recordStep($this->getId());

        return StepResult::success($log);
    }
}
