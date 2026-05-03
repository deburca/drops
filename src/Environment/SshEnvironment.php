<?php

declare(strict_types=1);

namespace Drops\Environment;

use Drops\Config\EnvironmentConfig;
use Symfony\Component\Process\Process;
use RuntimeException;

/**
 * SSH environment using the native system SSH client via Symfony Process.
 *
 * This approach respects ~/.ssh/config, the macOS Keychain SSH agent,
 * host key verification, ProxyJump, and all other SSH features the user
 * has configured on their system.
 */
final class SshEnvironment implements EnvironmentInterface
{
    public function __construct(
        private readonly EnvironmentConfig $config,
    ) {
    }

    public function execute(string $command, array $envVars = []): CommandResult
    {
        $allEnvVars = array_merge($this->config->envVars, $envVars);

        // Build env prefix for the remote command
        $envPrefix = '';
        foreach ($allEnvVars as $key => $value) {
            $envPrefix .= sprintf('export %s=%s; ', $key, escapeshellarg($value));
        }

        $remoteCommand = sprintf('cd %s && %s%s', escapeshellarg($this->config->webroot), $envPrefix, $command);

        $sshArgs = $this->buildSshArgs();
        $sshArgs[] = $remoteCommand;

        $process = new Process($sshArgs);
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
        $target = sprintf('%s@%s:%s', $this->config->user, $this->config->host, $remotePath);
        $args = $this->buildScpBaseArgs();

        if (is_dir($localPath)) {
            // Use rsync for directories
            $rsyncArgs = $this->buildRsyncArgs($localPath . '/', $target . '/');
            $this->runProcess($rsyncArgs, 'Upload directory failed');
        } else {
            $args[] = $localPath;
            $args[] = $target;
            $this->runProcess($args, 'Upload file failed');
        }
    }

    public function download(string $remotePath, string $localPath): void
    {
        $source = sprintf('%s@%s:%s', $this->config->user, $this->config->host, $remotePath);
        $args = $this->buildScpBaseArgs();

        $dir = dirname($localPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Check if remote path is a directory
        $checkResult = $this->execute(sprintf('test -d %s && echo DIR || echo FILE', escapeshellarg($remotePath)));
        if (trim($checkResult->getOutput()) === 'DIR') {
            if (!is_dir($localPath)) {
                mkdir($localPath, 0755, true);
            }
            $rsyncArgs = $this->buildRsyncArgs($source . '/', $localPath . '/');
            $this->runProcess($rsyncArgs, 'Download directory failed');
        } else {
            $args[] = $source;
            $args[] = $localPath;
            $this->runProcess($args, 'Download file failed');
        }
    }

    public function exists(string $path): bool
    {
        $result = $this->execute(sprintf('test -e %s && echo EXISTS || echo MISSING', escapeshellarg($path)));

        return trim($result->getOutput()) === 'EXISTS';
    }

    public function getConfig(): EnvironmentConfig
    {
        return $this->config;
    }

    /**
     * Build the base SSH command arguments.
     *
     * @return string[]
     */
    private function buildSshArgs(): array
    {
        $args = ['ssh'];
        $args[] = '-o';
        $args[] = 'BatchMode=yes';

        if ($this->config->port !== 22) {
            $args[] = '-p';
            $args[] = (string) $this->config->port;
        }

        if ($this->config->identityFile !== null) {
            $keyPath = $this->resolveKeyPath($this->config->identityFile);
            $args[] = '-i';
            $args[] = $keyPath;
        }

        $args[] = sprintf('%s@%s', $this->config->user, $this->config->host);

        return $args;
    }

    /**
     * Build base scp arguments with port and identity file.
     *
     * @return string[]
     */
    private function buildScpBaseArgs(): array
    {
        $args = ['scp', '-o', 'BatchMode=yes'];

        if ($this->config->port !== 22) {
            $args[] = '-P';
            $args[] = (string) $this->config->port;
        }

        if ($this->config->identityFile !== null) {
            $keyPath = $this->resolveKeyPath($this->config->identityFile);
            $args[] = '-i';
            $args[] = $keyPath;
        }

        return $args;
    }

    /**
     * Build rsync-over-SSH arguments for directory transfers.
     *
     * @return string[]
     */
    private function buildRsyncArgs(string $source, string $dest): array
    {
        $sshCmd = 'ssh -o BatchMode=yes';
        if ($this->config->port !== 22) {
            $sshCmd .= sprintf(' -p %d', $this->config->port);
        }
        if ($this->config->identityFile !== null) {
            $sshCmd .= sprintf(' -i %s', escapeshellarg($this->resolveKeyPath($this->config->identityFile)));
        }

        return ['rsync', '-az', '-e', $sshCmd, $source, $dest];
    }

    private function resolveKeyPath(string $keyPath): string
    {
        if (str_starts_with($keyPath, '~/')) {
            $keyPath = ($_SERVER['HOME'] ?? getenv('HOME')) . substr($keyPath, 1);
        }

        return $keyPath;
    }

    /**
     * @param string[] $args
     */
    private function runProcess(array $args, string $errorMessage): void
    {
        $process = new Process($args);
        $process->setTimeout(null);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                '%s: %s',
                $errorMessage,
                $process->getErrorOutput() ?: $process->getOutput(),
            ));
        }
    }
}
