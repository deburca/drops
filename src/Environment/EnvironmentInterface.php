<?php

declare(strict_types=1);

namespace Drops\Environment;

use Drops\Config\EnvironmentConfig;

interface EnvironmentInterface
{
    /**
     * Execute a command on this environment and return its output.
     *
     * @param string $command The command to execute
     * @param array<string, string> $envVars Additional environment variables
     * @return CommandResult
     */
    public function execute(string $command, array $envVars = []): CommandResult;

    /**
     * Upload a local file or directory to the environment.
     *
     * @param string $localPath Absolute path on the local machine
     * @param string $remotePath Absolute path on the environment
     */
    public function upload(string $localPath, string $remotePath): void;

    /**
     * Download a file or directory from the environment to the local machine.
     *
     * @param string $remotePath Absolute path on the environment
     * @param string $localPath Absolute path on the local machine
     */
    public function download(string $remotePath, string $localPath): void;

    /**
     * Check if a file or directory exists on the environment.
     */
    public function exists(string $path): bool;

    /**
     * Get the environment configuration.
     */
    public function getConfig(): EnvironmentConfig;
}
