<?php

declare(strict_types=1);

namespace Drops\Tests\Unit\Config;

use Drops\Config\EnvironmentConfig;
use PHPUnit\Framework\TestCase;

final class EnvironmentConfigTest extends TestCase
{
    public function testFromArrayWithSshConfig(): void
    {
        $data = [
            'id' => 'production',
            'label' => 'Production Server',
            'access' => [
                'type' => 'ssh',
                'host' => 'prod.example.com',
                'port' => 2222,
                'user' => 'deploy',
                'identity_file' => '~/.ssh/id_ed25519',
            ],
            'paths' => [
                'webroot' => '/var/www/drupal/web',
                'drush' => '/var/www/drupal/vendor/bin/drush',
                'php' => '/usr/bin/php8.2',
                'temp' => '/tmp/drops',
            ],
            'env_vars' => [
                'APP_ENV' => 'production',
            ],
        ];

        $config = EnvironmentConfig::fromArray($data);

        $this->assertSame('production', $config->id);
        $this->assertSame('Production Server', $config->label);
        $this->assertSame('ssh', $config->accessType);
        $this->assertSame('prod.example.com', $config->host);
        $this->assertSame(2222, $config->port);
        $this->assertSame('deploy', $config->user);
        $this->assertSame('/var/www/drupal/web', $config->webroot);
        $this->assertTrue($config->isSsh());
        $this->assertFalse($config->isLocal());
        $this->assertSame('/var/www/drupal/vendor/bin/drush', $config->getDrushPath());
        $this->assertSame('/usr/bin/php8.2', $config->getPhpPath());
        $this->assertSame('/tmp/drops', $config->getTempDir());
        $this->assertSame(['APP_ENV' => 'production'], $config->envVars);
    }

    public function testFromArrayWithLocalConfig(): void
    {
        $data = [
            'id' => 'local-dev',
            'access' => ['type' => 'local'],
            'paths' => ['webroot' => '/home/alice/projects/acme/web'],
        ];

        $config = EnvironmentConfig::fromArray($data);

        $this->assertSame('local-dev', $config->id);
        $this->assertTrue($config->isLocal());
        $this->assertFalse($config->isSsh());
        $this->assertSame('drush', $config->getDrushPath());
        $this->assertSame('php', $config->getPhpPath());
        $this->assertSame(22, $config->port);
    }

    public function testUriIsNullByDefault(): void
    {
        $config = EnvironmentConfig::fromArray([
            'id' => 'local',
            'access' => ['type' => 'local'],
            'paths' => ['webroot' => '/var/www/html'],
        ]);

        $this->assertNull($config->uri);
        $this->assertSame('default', $config->getSiteDir());
    }

    public function testFromArrayWithUri(): void
    {
        $config = EnvironmentConfig::fromArray([
            'id' => 'production-site-a',
            'access' => ['type' => 'ssh', 'host' => 'prod.example.com', 'user' => 'deploy'],
            'paths' => ['webroot' => '/var/www/drupal/web'],
            'uri' => 'site-a.example.com',
        ]);

        $this->assertSame('site-a.example.com', $config->uri);
        $this->assertSame('site-a.example.com', $config->getSiteDir());
    }

    public function testPrivateFilesIsNullByDefault(): void
    {
        $config = EnvironmentConfig::fromArray([
            'id' => 'local',
            'access' => ['type' => 'local'],
            'paths' => ['webroot' => '/var/www/html'],
        ]);

        $this->assertNull($config->privateFiles);
        $this->assertNull($config->getPrivateFilesPath());
    }

    public function testFromArrayWithPrivateFiles(): void
    {
        $config = EnvironmentConfig::fromArray([
            'id' => 'production',
            'access' => ['type' => 'ssh', 'host' => 'prod.example.com', 'user' => 'deploy'],
            'paths' => [
                'webroot' => '/var/www/drupal/web',
                'private_files' => '/var/private-files/drupal',
            ],
        ]);

        $this->assertSame('/var/private-files/drupal', $config->privateFiles);
        $this->assertSame('/var/private-files/drupal', $config->getPrivateFilesPath());
    }
}
