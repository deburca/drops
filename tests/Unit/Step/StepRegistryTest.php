<?php

declare(strict_types=1);

namespace Drops\Tests\Unit\Step;

use Drops\Step\StepRegistry;
use PHPUnit\Framework\TestCase;

final class StepRegistryTest extends TestCase
{
    private StepRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new StepRegistry();
    }

    public function testAllBuiltinStepsAreRegistered(): void
    {
        $ids = $this->registry->getStepIds();

        $this->assertContains('pre_hooks', $ids);
        $this->assertContains('maintenance_on', $ids);
        $this->assertContains('database_export', $ids);
        $this->assertContains('files_export', $ids);
        $this->assertContains('config_export', $ids);
        $this->assertContains('config_import', $ids);
        $this->assertContains('files_import', $ids);
        $this->assertContains('database_import', $ids);
        $this->assertContains('database_update', $ids);
        $this->assertContains('cache_rebuild', $ids);
        $this->assertContains('maintenance_off', $ids);
        $this->assertContains('post_hooks', $ids);
        $this->assertCount(12, $ids);
    }

    public function testExportStepsReturnCorrectPhaseSteps(): void
    {
        $exportSteps = $this->registry->getExportSteps();
        $ids = array_map(fn($s) => $s->getId(), $exportSteps);

        $this->assertContains('pre_hooks', $ids);
        $this->assertContains('database_export', $ids);
        $this->assertContains('files_export', $ids);
        $this->assertContains('config_export', $ids);
        $this->assertContains('post_hooks', $ids);
        // Import-only steps should not appear
        $this->assertNotContains('maintenance_on', $ids);
        $this->assertNotContains('database_update', $ids);
    }

    public function testImportStepsReturnCorrectPhaseSteps(): void
    {
        $importSteps = $this->registry->getImportSteps();
        $ids = array_map(fn($s) => $s->getId(), $importSteps);

        $this->assertContains('pre_hooks', $ids);
        $this->assertContains('maintenance_on', $ids);
        $this->assertContains('config_import', $ids);
        $this->assertContains('database_import', $ids);
        $this->assertContains('database_update', $ids);
        $this->assertContains('cache_rebuild', $ids);
        $this->assertContains('maintenance_off', $ids);
        $this->assertContains('post_hooks', $ids);
        // Export-only steps should not appear
        $this->assertNotContains('database_export', $ids);
        $this->assertNotContains('files_export', $ids);
        $this->assertNotContains('config_export', $ids);
    }

    public function testGetStepById(): void
    {
        $step = $this->registry->get('cache_rebuild');
        $this->assertNotNull($step);
        $this->assertSame('cache_rebuild', $step->getId());

        $this->assertNull($this->registry->get('nonexistent'));
    }
}
