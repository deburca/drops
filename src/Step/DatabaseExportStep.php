<?php

declare(strict_types=1);

namespace Drops\Step;

use Drops\Pipeline\DeployContext;
use Drops\Pipeline\StepResult;

final class DatabaseExportStep implements StepInterface
{
    public function getId(): string
    {
        return 'database_export';
    }

    public function getLabel(): string
    {
        return 'Export database';
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

        $config = $context->getStepConfig('database_export');
        $skipDataTables = $config['skip_data_tables'] ?? [];

        $log = [];
        $dumpFile = $context->packageBuilder->getDatabaseDir() . '/dump.sql.gz';

        // Build the drush sql:dump command — dump to stdout so it works
        // in both direct and containerised (DDEV/Lando) environments
        $command = $context->drushCommand('sql:dump');

        // Add structure-only tables
        foreach ($skipDataTables as $table) {
            $command .= ' --structure-tables-list=' . escapeshellarg($table);
        }

        // Pipe through gzip; output goes to stdout
        $command .= ' | gzip';

        $log[] = 'Running database export...';
        $result = $context->environment->execute($command);

        if (!$result->isSuccessful()) {
            return StepResult::failed(
                sprintf('Database export failed (exit code %d)', $result->exitCode),
                array_merge($log, [$result->getErrorOutput()]),
            );
        }

        // Write the captured stdout (gzipped SQL) to the package directory on the host
        if (file_put_contents($dumpFile, $result->getOutput()) === false) {
            return StepResult::failed('Failed to write database dump to package');
        }

        $context->packageBuilder->addChecksum('database/dump.sql.gz', $dumpFile);
        $context->packageBuilder->recordStep($this->getId());
        $log[] = sprintf('Database dumped to dump.sql.gz (%s)', $this->formatBytes(filesize($dumpFile) ?: 0));

        return StepResult::success($log);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return sprintf('%.1f MB', $bytes / 1048576);
        }
        if ($bytes >= 1024) {
            return sprintf('%.1f KB', $bytes / 1024);
        }
        return sprintf('%d bytes', $bytes);
    }
}
