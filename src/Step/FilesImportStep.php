<?php

declare(strict_types=1);

namespace Drops\Step;

use Drops\Pipeline\DeployContext;
use Drops\Pipeline\StepResult;

final class FilesImportStep implements StepInterface
{
    public function getId(): string
    {
        return 'files_import';
    }

    public function getLabel(): string
    {
        return 'Import files';
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

        $config = $context->getStepConfig('files_import');
        $directories = $config['directories'] ?? ['files/public'];
        $deleteRemoved = $config['delete_removed'] ?? false;

        $log = [];
        $webroot = $context->envConfig->webroot;
        $packageFilesDir = $context->packageReader->getFilesDir();

        foreach ($directories as $dir) {
            $sourcePath = $packageFilesDir . '/' . $dir;
            $destPath = $webroot . '/sites/default/' . $dir;

            $rsyncCmd = sprintf(
                'rsync -a %s %s',
                escapeshellarg($sourcePath . '/'),
                escapeshellarg($destPath . '/'),
            );

            if ($deleteRemoved) {
                $rsyncCmd .= ' --delete';
            }

            $log[] = sprintf('Syncing: %s', $dir);
            $result = $context->environment->execute($rsyncCmd);

            if (!$result->isSuccessful()) {
                return StepResult::failed(
                    sprintf('File import failed for %s (exit code %d)', $dir, $result->exitCode),
                    array_merge($log, [$result->getErrorOutput()]),
                );
            }
        }

        return StepResult::success($log);
    }
}
