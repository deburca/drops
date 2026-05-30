<?php

declare(strict_types=1);

namespace Drops\Config;

final class ApplicationConfig
{
    /**
     * @param array<string, bool> $steps
     * @param array<string, array<string, mixed>> $stepConfig
     * @param array<string, mixed> $importOptions
     * @param ?string $uri Drupal site URI for multi-site installs (e.g. "site-a.example.com").
     *                     Takes precedence over the environment-level URI.
     */
    public function __construct(
        public readonly string $id,
        public readonly array $steps,
        public readonly array $stepConfig = [],
        public readonly ?string $label = null,
        public readonly array $importOptions = [],
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
        return new self(
            id: $data['id'],
            steps: $data['steps'] ?? [],
            stepConfig: $data['step_config'] ?? [],
            label: $data['label'] ?? null,
            importOptions: $data['import_options'] ?? [],
            uri: $data['uri'] ?? null,
        );
    }

    /**
     * Whether a given step is enabled.
     *
     * Steps not listed in the configuration are enabled by default.
     * To disable a step, set it to false explicitly in the config.
     */
    public function isStepEnabled(string $stepId): bool
    {
        return (bool) ($this->steps[$stepId] ?? true);
    }

    /**
     * Get configuration for a specific step.
     *
     * @return array<string, mixed>
     */
    public function getStepConfig(string $stepId): array
    {
        return $this->stepConfig[$stepId] ?? [];
    }

    /**
     * Get all enabled step IDs.
     *
     * @return string[]
     */
    public function getEnabledSteps(): array
    {
        return array_keys(array_filter($this->steps));
    }

    public function shouldCreateRollbackPackage(): bool
    {
        return (bool) ($this->importOptions['create_rollback_package'] ?? false);
    }

    public function getRollbackPackageDir(): ?string
    {
        return $this->importOptions['rollback_package_dir'] ?? null;
    }
}
