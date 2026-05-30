<?php

declare(strict_types=1);

namespace Drops\Package;

use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

final class PackageReader
{
    private string $extractedDir;
    private Manifest $manifest;
    private Filesystem $filesystem;

    public function __construct(private readonly string $archivePath)
    {
        $this->filesystem = new Filesystem();
    }

    /**
     * Extract the package and load the manifest.
     */
    public function open(string $extractTo): void
    {
        if (!file_exists($this->archivePath)) {
            throw new RuntimeException(sprintf('Package not found: %s', $this->archivePath));
        }

        $this->filesystem->mkdir($extractTo);

        $phar = new \PharData($this->archivePath);
        $phar->extractTo($extractTo, null, true);

        // Find the extracted directory (should be the only top-level entry)
        $entries = array_diff(scandir($extractTo) ?: [], ['.', '..']);
        if (count($entries) === 1) {
            $this->extractedDir = $extractTo . '/' . reset($entries);
        } else {
            $this->extractedDir = $extractTo;
        }

        $this->loadManifest();
    }

    public function getManifest(): Manifest
    {
        return $this->manifest;
    }

    public function getExtractedDir(): string
    {
        return $this->extractedDir;
    }

    public function getDatabaseDir(): string
    {
        return $this->extractedDir . '/database';
    }

    public function getConfigDir(): string
    {
        return $this->extractedDir . '/config';
    }

    public function getFilesDir(): string
    {
        return $this->extractedDir . '/files';
    }

    public function getPrivateFilesDir(): string
    {
        return $this->extractedDir . '/files-private';
    }

    public function getHooksDir(): string
    {
        return $this->extractedDir . '/hooks';
    }

    /**
     * Verify all checksums in the manifest against actual files.
     *
     * @return string[] List of files with checksum mismatches
     */
    public function verifyChecksums(): array
    {
        $failures = [];

        foreach ($this->manifest->checksums as $relativePath => $expectedChecksum) {
            $filePath = $this->extractedDir . '/' . $relativePath;

            if (!file_exists($filePath)) {
                $failures[] = sprintf('%s: file missing', $relativePath);
                continue;
            }

            $actualHash = hash_file('sha256', $filePath);
            $expected = str_replace('sha256:', '', $expectedChecksum);

            if ($actualHash !== $expected) {
                $failures[] = sprintf('%s: checksum mismatch', $relativePath);
            }
        }

        return $failures;
    }

    /**
     * Clean up the extracted package directory.
     */
    public function cleanup(): void
    {
        if (isset($this->extractedDir) && is_dir($this->extractedDir)) {
            $this->filesystem->remove($this->extractedDir);
        }
    }

    private function loadManifest(): void
    {
        $manifestPath = $this->extractedDir . '/manifest.json';

        if (!file_exists($manifestPath)) {
            throw new RuntimeException(sprintf('Manifest not found in package: %s', $manifestPath));
        }

        $content = file_get_contents($manifestPath);
        if ($content === false) {
            throw new RuntimeException('Failed to read manifest.json');
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid manifest.json format');
        }

        $this->manifest = Manifest::fromArray($data);
    }
}
