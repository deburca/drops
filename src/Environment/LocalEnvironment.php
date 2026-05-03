<?php

declare(strict_types=1);

namespace Drops\Environment;

use Drops\Config\EnvironmentConfig;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use RuntimeException;

final class LocalEnvironment implements EnvironmentInterface
{
    private Filesystem $filesystem;

    public function __construct(
        private readonly EnvironmentConfig $config,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function execute(string $command, array $envVars = []): CommandResult
    {
        $allEnvVars = array_merge($this->config->envVars, $envVars);

        $process = Process::fromShellCommandline($command, $this->config->webroot, $allEnvVars);
        $process->setTimeout(null);
        $process->run();

        return new CommandResult(
            exitCode: $process->getExitCode() ?? 1,
            stdout: $process->getOutput(),
            stderr: $process->getErrorOutput(),
        );
    }

    public function upload(string $localPath, string $remotePath): void
    {
        // For local environments, "upload" is a copy operation.
        if (is_dir($localPath)) {
            $this->filesystem->mirror($localPath, $remotePath);
        } else {
            $this->filesystem->copy($localPath, $remotePath, true);
        }
    }

    public function download(string $remotePath, string $localPath): void
    {
        // For local environments, "download" is a copy operation.
        if (is_dir($remotePath)) {
            $this->filesystem->mirror($remotePath, $localPath);
        } else {
            $this->filesystem->copy($remotePath, $localPath, true);
        }
    }

    public function exists(string $path): bool
    {
        return $this->filesystem->exists($path);
    }

    public function getConfig(): EnvironmentConfig
    {
        return $this->config;
    }
}
