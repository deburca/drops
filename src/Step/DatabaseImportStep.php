<?php

declare(strict_types=1);

namespace Drops\Step;

use Drops\Pipeline\DeployContext;
use Drops\Pipeline\StepResult;

final class DatabaseImportStep implements StepInterface
{
    public function getId(): string
    {
        return 'database_import';
    }

    public function getLabel(): string
    {
        return 'Import database';
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

        $log = [];
        $databaseDir = $context->packageReader->getDatabaseDir();

        // Determine the dump file (could be .sql.gz or .sql)
        $dumpFile = null;
        if (file_exists($databaseDir . '/dump.sql.gz')) {
            $dumpFile = $databaseDir . '/dump.sql.gz';
        } elseif (file_exists($databaseDir . '/dump.sql')) {
            $dumpFile = $databaseDir . '/dump.sql';
        }

        if ($dumpFile === null) {
            return StepResult::failed('No database dump found in package');
        }

        // Drop existing tables first
        $dropCommand = $context->drushCommand('sql:drop --yes');
        $log[] = 'Dropping existing database tables...';
        $dropResult = $context->environment->execute($dropCommand);

        if (!$dropResult->isSuccessful()) {
            return StepResult::failed(
                sprintf('Failed to drop database tables (exit code %d)', $dropResult->exitCode),
                array_merge($log, [$dropResult->getErrorOutput()]),
            );
        }

        // Import the dump
        if (str_ends_with($dumpFile, '.gz')) {
            $command = sprintf(
                'gunzip -c %s | %s',
                escapeshellarg($dumpFile),
                $context->drushCommand('sql:cli'),
            );
        } else {
            $command = sprintf(
                '%s < %s',
                $context->drushCommand('sql:cli'),
                escapeshellarg($dumpFile),
            );
        }

        $log[] = 'Importing database dump...';
        $result = $context->environment->execute($command);

        if (!$result->isSuccessful()) {
            return StepResult::failed(
                sprintf('Database import failed (exit code %d)', $result->exitCode),
                array_merge($log, [$result->getErrorOutput()]),
            );
        }

        $log[] = 'Database imported successfully';

        return StepResult::success($log);
    }
}
