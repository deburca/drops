<?php

declare(strict_types=1);

namespace Drops\Tests\Unit\Pipeline;

use Drops\Config\ApplicationConfig;
use Drops\Config\EnvironmentConfig;
use Drops\Environment\EnvironmentInterface;
use Drops\Pipeline\DeployContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;

final class DeployContextTest extends TestCase
{
    public function testDrushCommandWithoutUri(): void
    {
        $context = $this->createContext(uri: null, drush: '/usr/bin/drush');

        $this->assertSame('/usr/bin/drush cache:rebuild', $context->drushCommand('cache:rebuild'));
    }

    public function testDrushCommandWithUri(): void
    {
        $context = $this->createContext(uri: 'site-a.example.com', drush: '/usr/bin/drush');

        $expected = "/usr/bin/drush cache:rebuild --uri='site-a.example.com'";
        $this->assertSame($expected, $context->drushCommand('cache:rebuild'));
    }

    public function testDrushCommandWithUriAndDefaultDrush(): void
    {
        $context = $this->createContext(uri: 'intranet.local', drush: null);

        $expected = "drush updatedb --yes --uri='intranet.local'";
        $this->assertSame($expected, $context->drushCommand('updatedb --yes'));
    }

    public function testIsFreshInstallDefaultsToFalse(): void
    {
        $context = $this->createContext(uri: null, drush: null);

        $this->assertFalse($context->isFreshInstall);
    }

    public function testIsFreshInstallCanBeSetToTrue(): void
    {
        $context = $this->createContext(uri: null, drush: null, isFreshInstall: true);

        $this->assertTrue($context->isFreshInstall);
    }

    private function createContext(?string $uri, ?string $drush, bool $isFreshInstall = false): DeployContext
    {
        $envConfig = new EnvironmentConfig(
            id: 'test-env',
            accessType: 'local',
            webroot: '/var/www/html',
            drush: $drush,
            uri: $uri,
        );

        $appConfig = new ApplicationConfig(
            id: 'test-app',
            steps: [],
        );

        $environment = $this->createMock(EnvironmentInterface::class);

        return new DeployContext(
            appConfig: $appConfig,
            envConfig: $envConfig,
            environment: $environment,
            output: new NullOutput(),
            isFreshInstall: $isFreshInstall,
        );
    }
}
