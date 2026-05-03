<?php

declare(strict_types=1);

namespace Drops\Environment;

use Drops\Config\EnvironmentConfig;
use RuntimeException;

final class EnvironmentFactory
{
    public static function create(EnvironmentConfig $config): EnvironmentInterface
    {
        return match ($config->accessType) {
            'local' => new LocalEnvironment($config),
            'ssh' => new SshEnvironment($config),
            default => throw new RuntimeException(
                sprintf('Unsupported access type: %s', $config->accessType)
            ),
        };
    }
}
