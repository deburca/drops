<?php

declare(strict_types=1);

namespace Drops\Step;

use Drops\Pipeline\DeployContext;
use Drops\Pipeline\StepResult;

final class FilesExportStep implements StepInterface
{
    public function getId(): string
    {
        return 'files_export';
    }

    public function getLabel(): string
    {
        return 'Export files';
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

        $config = $context->getStepConfig('files_export');
        $directories = $config['directories'] ?? ['files/public'];
        $excludes = $config['exclude'] ?? [];

        $log = [];
        $webroot = $context->envConfig->webroot;
        $targetDir = $context->packageBuilder->getFilesDir();

        foreach ($directories as $dir) {
            $sourcePath = $webroot . '/sites/' . $context->envConfig->getSiteDir() . '/' . $dir;
            $destPath = $targetDir . '/' . $dir;

            // Build rsync command
            $rsyncCmd = sprintf(
                'rsync -a %s %s',
                escapeshellarg($sourcePath . '/'),
                escapeshellarg($destPath . '/'),
            );

            foreach ($excludes as $exclude) {
                $rsyncCmd .= ' --exclude=' . escapeshellarg($exclude);
            }

            $log[] = sprintf('Syncing: %s', $dir);
            $result = $context->environment->execute($rsyncCmd);

            if (!$result->isSuccessful()) {
                return StepResult::failed(
                    sprintf('File export failed for %s (exit code %d)', $dir, $result->exitCode),
                    array_merge($log, [$result->getErrorOutput()]),
                );
            }
        }

        $context->packageBuilder->addChecksumForDirectory('files');

        // Export private files if configured
        $privateFilesPath = $context->envConfig->getPrivateFilesPath();
        if ($privateFilesPath !== null) {
            $privateTargetDir = $context->packageBuilder->getPrivateFilesDir();

            $rsyncCmd = sprintf(
                'rsync -a %s %s',
                escapeshellarg(rtrim($privateFilesPath, '/') . '/'),
                escapeshellarg($privateTargetDir . '/'),
            );

            foreach ($excludes as $exclude) {
                $rsyncCmd .= ' --exclude=' . escapeshellarg($exclude);
            }

            $log[] = 'Syncing: private files';
            $result = $context->environment->execute($rsyncCmd);

            if (!$result->isSuccessful()) {
                return StepResult::failed(
                    sprintf('Private file export failed (exit code %d)', $result->exitCode),
                    array_merge($log, [$result->getErrorOutput()]),
                );
            }

            $context->packageBuilder->addChecksumForDirectory('files-private');
        }

        $context->packageBuilder->recordStep($this->getId());

        return StepResult::success($log);
    }
}
