<?php

declare(strict_types=1);

namespace Drops\Package;

use Drops\Config\ApplicationConfig;
use Drops\Config\EnvironmentConfig;
use DateTimeImmutable;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

final class PackageBuilder
{
    private string $packageDir;
    private Filesystem $filesystem;

    /** @var array<string, string> */
    private array $checksums = [];

    /** @var string[] */
    private array $stepsIncluded = [];

    public function __construct(string $packageDir)
    {
        $this->packageDir = rtrim($packageDir, '/');
        $this->filesystem = new Filesystem();
    }

    /**
     * Initialise the package directory structure.
     */
    public function init(): void
    {
        $this->filesystem->mkdir($this->packageDir);
        $this->filesystem->mkdir($this->packageDir . '/database');
        $this->filesystem->mkdir($this->packageDir . '/config');
        $this->filesystem->mkdir($this->packageDir . '/files');
        $this->filesystem->mkdir($this->packageDir . '/files-private');
        $this->filesystem->mkdir($this->packageDir . '/hooks');
    }

    public function getPackageDir(): string
    {
        return $this->packageDir;
    }

    public function getDatabaseDir(): string
    {
        return $this->packageDir . '/database';
    }

    public function getConfigDir(): string
    {
        return $this->packageDir . '/config';
    }

    public function getFilesDir(): string
    {
        return $this->packageDir . '/files';
    }

    public function getPrivateFilesDir(): string
    {
        return $this->packageDir . '/files-private';
    }

    public function getHooksDir(): string
    {
        return $this->packageDir . '/hooks';
    }

    /**
     * Record that a step was included in this package.
     */
    public function recordStep(string $stepId): void
    {
        $this->stepsIncluded[] = $stepId;
    }

    /**
     * Add a checksum for a file in the package.
     */
    public function addChecksum(string $relativePath, string $filePath): void
    {
        $hash = hash_file('sha256', $filePath);
        if ($hash === false) {
            throw new RuntimeException(sprintf('Failed to compute checksum for: %s', $filePath));
        }
        $this->checksums[$relativePath] = 'sha256:' . $hash;
    }

    /**
     * Compute checksums for all files in a subdirectory.
     */
    public function addChecksumForDirectory(string $subDir): void
    {
        $dir = $this->packageDir . '/' . $subDir;
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = $subDir . '/' . substr($file->getPathname(), strlen($dir) + 1);
                $this->addChecksum($relativePath, $file->getPathname());
            }
        }
    }

    /**
     * Write the manifest and create the final .tar.gz archive.
     */
    public function finalise(
        ApplicationConfig $appConfig,
        EnvironmentConfig $envConfig,
        string $outputPath,
        ?string $label = null,
        string $toolVersion = '1.0.0',
    ): void {
        $manifest = new Manifest(
            toolVersion: $toolVersion,
            createdAt: new DateTimeImmutable(),
            applicationId: $appConfig->id,
            applicationLabel: $appConfig->label ?? $appConfig->id,
            sourceEnvironmentId: $envConfig->id,
            sourceEnvironmentLabel: $envConfig->label ?? $envConfig->id,
            sourceAccessType: $envConfig->accessType,
            sourceHost: $envConfig->host,
            stepsIncluded: $this->stepsIncluded,
            checksums: $this->checksums,
            label: $label,
        );

        $writer = new ManifestWriter();
        $writer->write($manifest, $this->packageDir);

        $this->createArchive($outputPath);
    }

    private function createArchive(string $outputPath): void
    {
        $parentDir = dirname($this->packageDir);
        $baseName = basename($this->packageDir);

        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            $this->filesystem->mkdir($outputDir);
        }

        $phar = new \PharData($outputPath);
        $phar->buildFromDirectory($parentDir, '/^' . preg_quote($this->packageDir, '/') . '/');
        // The archive is already in .tar.gz format when the extension is .tar.gz
    }
}
