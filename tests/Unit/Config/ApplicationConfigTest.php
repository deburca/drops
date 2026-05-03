<?php

declare(strict_types=1);

namespace Drops\Tests\Unit\Config;

use Drops\Config\ApplicationConfig;
use PHPUnit\Framework\TestCase;

final class ApplicationConfigTest extends TestCase
{
    public function testFromArray(): void
    {
        $data = [
            'id' => 'acme-corp',
            'label' => 'ACME Corp Website',
            'steps' => [
                'database_export' => true,
                'config_export' => true,
                'config_import' => true,
                'cache_rebuild' => true,
                'files_export' => false,
            ],
            'step_config' => [
                'database_export' => ['skip_data_tables' => ['cache', 'watchdog']],
            ],
            'import_options' => [
                'create_rollback_package' => true,
                'rollback_package_dir' => '/var/backups/drops/',
            ],
        ];

        $config = ApplicationConfig::fromArray($data);

        $this->assertSame('acme-corp', $config->id);
        $this->assertSame('ACME Corp Website', $config->label);
        $this->assertTrue($config->isStepEnabled('database_export'));
        $this->assertTrue($config->isStepEnabled('config_export'));
        $this->assertFalse($config->isStepEnabled('files_export'));
        $this->assertFalse($config->isStepEnabled('nonexistent'));
    }

    public function testGetEnabledSteps(): void
    {
        $config = new ApplicationConfig(
            id: 'test',
            steps: [
                'database_update' => true,
                'cache_rebuild' => true,
                'files_export' => false,
            ],
        );

        $enabled = $config->getEnabledSteps();
        $this->assertSame(['database_update', 'cache_rebuild'], $enabled);
    }

    public function testGetStepConfig(): void
    {
        $config = new ApplicationConfig(
            id: 'test',
            steps: [],
            stepConfig: [
                'database_export' => ['skip_data_tables' => ['cache']],
            ],
        );

        $this->assertSame(['skip_data_tables' => ['cache']], $config->getStepConfig('database_export'));
        $this->assertSame([], $config->getStepConfig('nonexistent'));
    }

    public function testRollbackOptions(): void
    {
        $config = new ApplicationConfig(
            id: 'test',
            steps: [],
            importOptions: [
                'create_rollback_package' => true,
                'rollback_package_dir' => '/backups/',
            ],
        );

        $this->assertTrue($config->shouldCreateRollbackPackage());
        $this->assertSame('/backups/', $config->getRollbackPackageDir());
    }
}
