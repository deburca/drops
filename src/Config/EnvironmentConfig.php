<?php

declare(strict_types=1);

namespace Drops\Config;

final class EnvironmentConfig
{
    /**
     * @param array<string, string> $envVars
     * @param ?string $exec Command prefix for containerised environments (e.g. "ddev exec -p myproject")
     * @param ?string $uri Drupal site URI for multi-site installs (e.g. "site-a.example.com")
     */
    public function __construct(
        public readonly string $id,
        public readonly string $accessType,
        public readonly string $webroot,
        public readonly ?string $label = null,
        public readonly ?string $host = null,
        public readonly int $port = 22,
        public readonly ?string $user = null,
        public readonly ?string $identityFile = null,
        public readonly ?string $exec = null,
        public readonly ?string $drush = null,
        public readonly ?string $php = null,
        public readonly ?string $temp = null,
        public readonly ?string $privateFiles = null,
        public readonly array $envVars = [],
        public readonly ?string $uri = null,
    ) {
    }

    /**
     * Create from a parsed YAML array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $access = $data['access'] ?? [];
        $paths = $data['paths'] ?? [];

        return new self(
            id: $data['id'],
            accessType: $access['type'] ?? 'local',
            webroot: $paths['webroot'] ?? '',
            label: $data['label'] ?? null,
            host: $access['host'] ?? null,
            port: (int) ($access['port'] ?? 22),
            user: $access['user'] ?? null,
            identityFile: $access['identity_file'] ?? null,
            exec: $access['exec'] ?? null,
            drush: $paths['drush'] ?? null,
            php: $paths['php'] ?? null,
            temp: $paths['temp'] ?? null,
            privateFiles: $paths['private_files'] ?? null,
            envVars: $data['env_vars'] ?? [],
            uri: $data['uri'] ?? null,
        );
    }

    public function isLocal(): bool
    {
        return $this->accessType === 'local';
    }

    public function isSsh(): bool
    {
        return $this->accessType === 'ssh';
    }

    public function getDrushPath(): string
    {
        return $this->drush ?? 'drush';
    }

    public function getPhpPath(): string
    {
        return $this->php ?? 'php';
    }

    public function getTempDir(): string
    {
        return $this->temp ?? sys_get_temp_dir() . '/drops';
    }

    /**
     * Get the absolute path to the private file system, or null if not configured.
     */
    public function getPrivateFilesPath(): ?string
    {
        return $this->privateFiles;
    }

    /**
     * Get the sites/ subdirectory name for this environment.
     *
     * In a Drupal multi-site install, each site lives under sites/<dir>/.
     * Drupal resolves the directory from the request URI (via sites.php or
     * directory naming conventions). For DROPS, we use the URI value
     * directly as the directory name — this matches Drupal's default
     * convention where sites/example.com/ maps to the URI example.com.
     *
     * Returns 'default' when no URI is configured (standard single-site).
     */
    public function getSiteDir(): string
    {
        return $this->uri ?? 'default';
    }
}
