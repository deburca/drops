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
        $localDumpFile = null;
        if (file_exists($databaseDir . '/dump.sql.gz')) {
            $localDumpFile = $databaseDir . '/dump.sql.gz';
        } elseif (file_exists($databaseDir . '/dump.sql')) {
            $localDumpFile = $databaseDir . '/dump.sql';
        }

        if ($localDumpFile === null) {
            return StepResult::failed('No database dump found in package');
        }

        // PackageReader extracts the package on the machine running DROPS,
        // which is a different filesystem than the target environment for
        // SSH deployments. Stage the dump there first so the commands below
        // (which run *on* the environment) can find it.
        $stagingDir = rtrim($context->envConfig->getTempDir(), '/') . '/drops-db-import-' . date('YmdHis');
        $stageResult = $context->environment->execute(sprintf('mkdir -p %s', escapeshellarg($stagingDir)));
        if (!$stageResult->isSuccessful()) {
            return StepResult::failed(
                sprintf('Failed to create staging directory (exit code %d)', $stageResult->exitCode),
                [$stageResult->getErrorOutput()],
            );
        }
        $dumpFile = $stagingDir . '/' . basename($localDumpFile);
        $context->environment->upload($localDumpFile, $dumpFile);
        $log[] = sprintf('Staged dump on target: %s', $dumpFile);

        try {
            // Drop all existing tables using Drush's built-in command.
            $dropCommand = $context->drushCommand('sql:drop --yes');

            $log[] = 'Dropping existing database tables...';
            $dropResult = $context->environment->execute($dropCommand);

            if (!$dropResult->isSuccessful()) {
                $errorDetail = trim($dropResult->getErrorOutput() ?: $dropResult->getOutput());
                return StepResult::failed(
                    sprintf("Failed to drop database tables (exit code %d): %s", $dropResult->exitCode, $errorDetail),
                    array_merge($log, [$errorDetail]),
                );
            }

            // Get the raw MySQL connection command via drush sql:connect.
            // Piping large dumps through drush sql:cli is unreliable; piping
            // directly to the MySQL client avoids Drush's stdin handling issues.
            $log[] = 'Resolving database connection...';
            $connectResult = $context->environment->execute($context->drushCommand('sql:connect'));

            if (!$connectResult->isSuccessful()) {
                $errorDetail = trim($connectResult->getErrorOutput() ?: $connectResult->getOutput());
                return StepResult::failed(
                    sprintf(
                        "Failed to resolve database connection (exit code %d): %s",
                        $connectResult->exitCode,
                        $errorDetail,
                    ),
                    array_merge($log, [$errorDetail]),
                );
            }

            // Append --binary-mode to disable backslash interpretation, which
            // is required for reliably importing SQL dump files.
            $mysqlCommand = trim($connectResult->getOutput()) . ' --binary-mode';

            // Decompress the dump if needed, then import via file redirection
            // rather than piping, for maximum compatibility.
            $sqlFile = $dumpFile;
            if (str_ends_with($dumpFile, '.gz')) {
                $sqlFile = substr($dumpFile, 0, -3);
                $log[] = 'Decompressing dump file...';
                $decompressResult = $context->environment->execute(
                    sprintf('gunzip -f %s', escapeshellarg($dumpFile)),
                );
                if (!$decompressResult->isSuccessful()) {
                    $errorDetail = trim($decompressResult->getErrorOutput());
                    return StepResult::failed(
                        sprintf("Failed to decompress dump file: %s", $errorDetail),
                        array_merge($log, [$errorDetail]),
                    );
                }
            }

            // Strip all MariaDB 10.11+ sandbox mode directives.
            // Newer mariadb-dump versions emit /*M!999999\- enable the sandbox mode */
            // lines throughout the dump, which older clients choke on due to \-.
            $context->environment->execute(
                sprintf("sed -i '/\/\*M!999999/d' %s", escapeshellarg($sqlFile)),
            );

            $command = sprintf('%s < %s', $mysqlCommand, escapeshellarg($sqlFile));

            $log[] = sprintf('MySQL command: %s', $mysqlCommand);
            $log[] = 'Importing database dump...';
            $result = $context->environment->execute($command);

            if (!$result->isSuccessful()) {
                $errorDetail = trim($result->getErrorOutput() ?: $result->getOutput());
                return StepResult::failed(
                    sprintf("Database import failed (exit code %d): %s", $result->exitCode, $errorDetail),
                    array_merge($log, [$errorDetail]),
                );
            }

            $log[] = 'Database imported successfully';

            return StepResult::success($log);
        } finally {
            $context->environment->execute(sprintf('rm -rf %s', escapeshellarg($stagingDir)));
        }
    }
}
