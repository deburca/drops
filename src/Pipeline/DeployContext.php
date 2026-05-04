<?php

declare(strict_types=1);

namespace Drops\Pipeline;

use Drops\Config\ApplicationConfig;
use Drops\Config\EnvironmentConfig;
use Drops\Environment\EnvironmentInterface;
use Drops\Package\PackageBuilder;
use Drops\Package\PackageReader;
use Symfony\Component\Console\Output\OutputInterface;

final class DeployContext
{
    /** @var array<string, mixed> */
    private array $bag = [];

    public function __construct(
        public readonly ApplicationConfig $appConfig,
        public readonly EnvironmentConfig $envConfig,
        public readonly EnvironmentInterface $environment,
        public readonly OutputInterface $output,
        public readonly bool $dryRun = false,
        public readonly ?PackageBuilder $packageBuilder = null,
        public readonly ?PackageReader $packageReader = null,
    ) {
    }

    /**
     * Get the Drush command prefix for this environment.
     *
     * When the environment has a URI configured (multi-site), --uri is
     * appended so Drush targets the correct site.
     */
    public function drushCommand(string $subCommand): string
    {
        $command = sprintf('%s %s', $this->envConfig->getDrushPath(), $subCommand);

        if ($this->envConfig->uri !== null) {
            $command .= ' --uri=' . escapeshellarg($this->envConfig->uri);
        }

        return $command;
    }

    /**
     * Get step-specific configuration.
     *
     * @return array<string, mixed>
     */
    public function getStepConfig(string $stepId): array
    {
        return $this->appConfig->getStepConfig($stepId);
    }

    /**
     * Whether a specific step is enabled.
     */
    public function isStepEnabled(string $stepId): bool
    {
        return $this->appConfig->isStepEnabled($stepId);
    }

    /**
     * Store arbitrary data in the context bag (for inter-step communication).
     */
    public function set(string $key, mixed $value): void
    {
        $this->bag[$key] = $value;
    }

    /**
     * Retrieve data from the context bag.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->bag[$key] ?? $default;
    }
}
