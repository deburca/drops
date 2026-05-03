<?php

declare(strict_types=1);

namespace Drops\Environment;

use Drops\Config\EnvironmentConfig;
use phpseclib3\Net\SSH2;
use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;
use RuntimeException;

final class SshEnvironment implements EnvironmentInterface
{
    private ?SSH2 $ssh = null;
    private ?SFTP $sftp = null;

    public function __construct(
        private readonly EnvironmentConfig $config,
    ) {
    }

    public function execute(string $command, array $envVars = []): CommandResult
    {
        $ssh = $this->getSshConnection();
        $allEnvVars = array_merge($this->config->envVars, $envVars);

        // Build env prefix for the command
        $envPrefix = '';
        foreach ($allEnvVars as $key => $value) {
            $envPrefix .= sprintf('export %s=%s; ', $key, escapeshellarg($value));
        }

        $fullCommand = sprintf('cd %s && %s%s', escapeshellarg($this->config->webroot), $envPrefix, $command);

        // phpseclib exec returns output directly; stderr is captured separately
        $stdout = $ssh->exec($fullCommand);
        $stderr = $ssh->getStdError();
        $exitCode = $ssh->getExitStatus();

        if ($stdout === false) {
            $stdout = '';
        }

        return new CommandResult(
            exitCode: is_int($exitCode) ? $exitCode : 1,
            stdout: $stdout,
            stderr: $stderr,
        );
    }

    public function upload(string $localPath, string $remotePath): void
    {
        $sftp = $this->getSftpConnection();

        if (is_dir($localPath)) {
            $this->uploadDirectory($sftp, $localPath, $remotePath);
        } else {
            $sftp->put($remotePath, $localPath, SFTP::SOURCE_LOCAL_FILE);
        }
    }

    public function download(string $remotePath, string $localPath): void
    {
        $sftp = $this->getSftpConnection();

        $stat = $sftp->stat($remotePath);
        if ($stat !== false && ($stat['type'] ?? 0) === 2) {
            // Directory
            $this->downloadDirectory($sftp, $remotePath, $localPath);
        } else {
            $dir = dirname($localPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $sftp->get($remotePath, $localPath);
        }
    }

    public function exists(string $path): bool
    {
        $sftp = $this->getSftpConnection();

        return $sftp->stat($path) !== false;
    }

    public function getConfig(): EnvironmentConfig
    {
        return $this->config;
    }

    private function getSshConnection(): SSH2
    {
        if ($this->ssh !== null && $this->ssh->isConnected()) {
            return $this->ssh;
        }

        $host = $this->config->host;
        if ($host === null) {
            throw new RuntimeException('SSH host is not configured');
        }

        $this->ssh = new SSH2($host, $this->config->port);
        $this->authenticate($this->ssh);

        return $this->ssh;
    }

    private function getSftpConnection(): SFTP
    {
        if ($this->sftp !== null && $this->sftp->isConnected()) {
            return $this->sftp;
        }

        $host = $this->config->host;
        if ($host === null) {
            throw new RuntimeException('SSH host is not configured');
        }

        $this->sftp = new SFTP($host, $this->config->port);
        $this->authenticate($this->sftp);

        return $this->sftp;
    }

    private function authenticate(SSH2 $connection): void
    {
        $user = $this->config->user;
        if ($user === null) {
            throw new RuntimeException('SSH user is not configured');
        }

        if ($this->config->identityFile !== null) {
            $keyPath = $this->config->identityFile;
            if (str_starts_with($keyPath, '~/')) {
                $keyPath = ($_SERVER['HOME'] ?? getenv('HOME')) . substr($keyPath, 1);
            }

            if (!file_exists($keyPath)) {
                throw new RuntimeException(sprintf('SSH identity file not found: %s', $keyPath));
            }

            $key = PublicKeyLoader::load(file_get_contents($keyPath));
            if (!$connection->login($user, $key)) {
                throw new RuntimeException(sprintf('SSH key authentication failed for %s@%s', $user, $this->config->host));
            }
        } else {
            // Attempt agent-based authentication
            if (!$connection->login($user)) {
                throw new RuntimeException(sprintf('SSH agent authentication failed for %s@%s', $user, $this->config->host));
            }
        }
    }

    private function uploadDirectory(SFTP $sftp, string $localPath, string $remotePath): void
    {
        $sftp->mkdir($remotePath, 0755, true);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($localPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($localPath));
            $targetPath = $remotePath . $relativePath;

            if ($item->isDir()) {
                $sftp->mkdir($targetPath, 0755, true);
            } else {
                $sftp->put($targetPath, $item->getPathname(), SFTP::SOURCE_LOCAL_FILE);
            }
        }
    }

    private function downloadDirectory(SFTP $sftp, string $remotePath, string $localPath): void
    {
        if (!is_dir($localPath)) {
            mkdir($localPath, 0755, true);
        }

        $list = $sftp->nlist($remotePath);
        if ($list === false) {
            return;
        }

        foreach ($list as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $remoteItem = $remotePath . '/' . $entry;
            $localItem = $localPath . '/' . $entry;

            $stat = $sftp->stat($remoteItem);
            if ($stat !== false && ($stat['type'] ?? 0) === 2) {
                $this->downloadDirectory($sftp, $remoteItem, $localItem);
            } else {
                $sftp->get($remoteItem, $localItem);
            }
        }
    }

    public function __destruct()
    {
        if ($this->sftp !== null && $this->sftp->isConnected()) {
            $this->sftp->disconnect();
        }
        if ($this->ssh !== null && $this->ssh->isConnected()) {
            $this->ssh->disconnect();
        }
    }
}
