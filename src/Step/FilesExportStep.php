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
        $directories = $config['directories'] ?? ['files'];
        $excludes = $config['exclude'] ?? ['styles', 'css', 'js', 'php'];

        $log = [];
        $webroot = $context->envConfig->webroot;
        $targetDir = $context->packageBuilder->getFilesDir();

        foreach ($directories as $dir) {
            $sourcePath = $webroot . '/sites/' . $context->envConfig->getSiteDir() . '/' . $dir;
            $destPath = $targetDir . '/' . $dir;

            $result = $this->exportDirectory($context, $sourcePath, $destPath, $excludes);

            $log[] = sprintf('Syncing: %s', $dir);

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

            $result = $this->exportDirectory(
                $context,
                rtrim($privateFilesPath, '/'),
                $privateTargetDir,
                $excludes,
            );

            $log[] = 'Syncing: private files';

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

    /**
     * Export a directory from the environment to a local destination.
     *
     * Uses tar streaming (like ConfigExportStep) so it works in both
     * direct and containerised (DDEV/Lando) environments — rsync
     * would fail when the destination path is on the host but the
     * command runs inside a container.
     *
     * @param string[] $excludes Directory names to exclude
     */
    private function exportDirectory(
        DeployContext $context,
        string $sourcePath,
        string $destPath,
        array $excludes,
    ): \Drops\Environment\CommandResult {
        // Build tar command with exclusions — runs inside the environment
        // Excludes must appear before the file list
        $tarCmd = 'tar';
        foreach ($excludes as $exclude) {
            $tarCmd .= ' --exclude=' . escapeshellarg($exclude);
        }
        $tarCmd .= sprintf(' -cf - -C %s .', escapeshellarg($sourcePath));

        $tarResult = $context->environment->execute($tarCmd);

        if (!$tarResult->isSuccessful()) {
            return $tarResult;
        }

        // Extract the tar stream into the destination directory on the host
        $filesystem = new \Symfony\Component\Filesystem\Filesystem();
        $filesystem->mkdir($destPath);

        $tarFile = sys_get_temp_dir() . '/drops-files-' . uniqid() . '.tar';
        file_put_contents($tarFile, $tarResult->getOutput());

        $extractProcess = new \Symfony\Component\Process\Process(
            ['tar', '-xf', $tarFile, '-C', $destPath],
        );
        $extractProcess->run();
        @unlink($tarFile);

        return new \Drops\Environment\CommandResult(
            exitCode: $extractProcess->isSuccessful() ? 0 : ($extractProcess->getExitCode() ?? 1),
            stdout: $extractProcess->getOutput(),
            stderr: $extractProcess->getErrorOutput(),
        );
    }
}
