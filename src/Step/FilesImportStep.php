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
        $directories = $config['directories'] ?? ['files'];
        $deleteRemoved = $config['delete_removed'] ?? false;

        $log = [];
        $webroot = $context->envConfig->webroot;
        $packageFilesDir = $context->packageReader->getFilesDir();

        foreach ($directories as $dir) {
            $sourcePath = $packageFilesDir . '/' . $dir;
            $destPath = $webroot . '/sites/' . $context->envConfig->getSiteDir() . '/' . $dir;

            // Ensure target directory exists (may not on a new environment)
            $mkdirCmd = sprintf('mkdir -p %s', escapeshellarg($destPath));
            $mkdirResult = $context->environment->execute($mkdirCmd);

            if (!$mkdirResult->isSuccessful()) {
                return StepResult::failed(
                    sprintf('Failed to create directory %s (exit code %d)', $dir, $mkdirResult->exitCode),
                    array_merge($log, [$mkdirResult->getErrorOutput()]),
                );
            }

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

        // Import private files if the package contains them
        $privateFilesDir = $context->packageReader->getPrivateFilesDir();
        if (is_dir($privateFilesDir) && !$this->isEmptyDir($privateFilesDir)) {
            $privateFilesPath = $context->envConfig->getPrivateFilesPath();

            if ($privateFilesPath === null) {
                $log[] = 'WARNING: Package contains private files but target has no'
                    . ' paths.private_files configured — skipping';
            } else {
                // Ensure target directory exists
                $mkdirCmd = sprintf('mkdir -p %s', escapeshellarg($privateFilesPath));
                $mkdirResult = $context->environment->execute($mkdirCmd);

                if (!$mkdirResult->isSuccessful()) {
                    return StepResult::failed(
                        sprintf('Failed to create private files directory (exit code %d)', $mkdirResult->exitCode),
                        array_merge($log, [$mkdirResult->getErrorOutput()]),
                    );
                }

                $rsyncCmd = sprintf(
                    'rsync -a %s %s',
                    escapeshellarg($privateFilesDir . '/'),
                    escapeshellarg(rtrim($privateFilesPath, '/') . '/'),
                );

                if ($deleteRemoved) {
                    $rsyncCmd .= ' --delete';
                }

                $log[] = 'Syncing: private files';
                $result = $context->environment->execute($rsyncCmd);

                if (!$result->isSuccessful()) {
                    return StepResult::failed(
                        sprintf('Private file import failed (exit code %d)', $result->exitCode),
                        array_merge($log, [$result->getErrorOutput()]),
                    );
                }
            }
        }

        return StepResult::success($log);
    }

    private function isEmptyDir(string $path): bool
    {
        return count(array_diff(scandir($path) ?: [], ['.', '..'])) === 0;
    }
}
