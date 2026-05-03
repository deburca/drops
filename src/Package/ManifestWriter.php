<?php

declare(strict_types=1);

namespace Drops\Package;

use RuntimeException;

final class ManifestWriter
{
    /**
     * Write a Manifest to a manifest.json file in the given directory.
     */
    public function write(Manifest $manifest, string $packageDir): void
    {
        $path = rtrim($packageDir, '/') . '/manifest.json';
        $json = json_encode($manifest->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (file_put_contents($path, $json . "\n") === false) {
            throw new RuntimeException(sprintf('Failed to write manifest to: %s', $path));
        }
    }
}
