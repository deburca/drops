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

        // Build the drush sql:dump command
        $dumpFile = $context->packageBuilder->getDatabaseDir() . '/dump.sql';
        $command = $context->drushCommand('sql:dump --result-file=' . escapeshellarg($dumpFile));

        // Add structure-only tables
        foreach ($skipDataTables as $table) {
            $command .= ' --structure-tables-list=' . escapeshellarg($table);
        }

        $command .= ' --gzip';

        $log[] = 'Running database export...';
        $result = $context->environment->execute($command);

        if (!$result->isSuccessful()) {
            return StepResult::failed(
                sprintf('Database export failed (exit code %d)', $result->exitCode),
                array_merge($log, [$result->getErrorOutput()]),
            );
        }

        // The dump file will be dump.sql.gz after --gzip
        $gzipPath = $dumpFile . '.gz';
        if (file_exists($gzipPath)) {
            $context->packageBuilder->addChecksum('database/dump.sql.gz', $gzipPath);
            $log[] = sprintf('Database dumped to dump.sql.gz');
        } elseif (file_exists($dumpFile)) {
            $context->packageBuilder->addChecksum('database/dump.sql', $dumpFile);
            $log[] = sprintf('Database dumped to dump.sql');
        }

        $context->packageBuilder->recordStep($this->getId());

        return StepResult::success($log);
    }
}
