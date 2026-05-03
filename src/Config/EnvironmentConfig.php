<?php

declare(strict_types=1);

namespace Drops\Config;

final class EnvironmentConfig
{
    /**
     * @param array<string, string> $envVars
     */
    /**
     * @param ?string $exec Command prefix for containerised environments (e.g. "ddev exec -p myproject")
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
        public readonly array $envVars = [],
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
            envVars: $data['env_vars'] ?? [],
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
}
