<?php

declare(strict_types=1);

namespace Drops\Config;

use Symfony\Component\Yaml\Yaml;
use RuntimeException;

final class ConfigLoader
{
    private string $configDir;

    public function __construct(string $configDir)
    {
        $this->configDir = rtrim($configDir, '/');
    }

    public function getConfigDir(): string
    {
        return $this->configDir;
    }

    /**
     * Load a single environment configuration by ID.
     */
    public function loadEnvironment(string $id): EnvironmentConfig
    {
        $path = $this->configDir . '/environments/' . $id . '.yml';
        $data = $this->loadYamlFile($path);

        return EnvironmentConfig::fromArray($data);
    }

    /**
     * Load a single application configuration by ID.
     */
    public function loadApplication(string $id): ApplicationConfig
    {
        $path = $this->configDir . '/applications/' . $id . '.yml';
        $data = $this->loadYamlFile($path);

        return ApplicationConfig::fromArray($data);
    }

    /**
     * Load all environment configurations.
     *
     * @return EnvironmentConfig[]
     */
    public function loadAllEnvironments(): array
    {
        $dir = $this->configDir . '/environments';
        if (!is_dir($dir)) {
            return [];
        }

        $configs = [];
        foreach ($this->findYamlFiles($dir) as $path) {
            $data = $this->loadYamlFile($path);
            $configs[] = EnvironmentConfig::fromArray($data);
        }

        return $configs;
    }

    /**
     * Load all application configurations.
     *
     * @return ApplicationConfig[]
     */
    public function loadAllApplications(): array
    {
        $dir = $this->configDir . '/applications';
        if (!is_dir($dir)) {
            return [];
        }

        $configs = [];
        foreach ($this->findYamlFiles($dir) as $path) {
            $data = $this->loadYamlFile($path);
            $configs[] = ApplicationConfig::fromArray($data);
        }

        return $configs;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadYamlFile(string $path): array
    {
        if (!file_exists($path)) {
            throw new RuntimeException(sprintf('Configuration file not found: %s', $path));
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException(sprintf('Unable to read configuration file: %s', $path));
        }

        $data = Yaml::parse($content);
        if (!is_array($data)) {
            throw new RuntimeException(sprintf('Configuration file must contain a YAML mapping: %s', $path));
        }

        return $data;
    }

    /**
     * Find all .yml files in a directory (non-recursive).
     *
     * @return string[]
     */
    private function findYamlFiles(string $dir): array
    {
        $files = glob($dir . '/*.yml');
        if ($files === false) {
            return [];
        }

        sort($files);

        return $files;
    }
}
