<?php

declare(strict_types=1);

namespace Drops\Tests\Unit\Package;

use Drops\Package\Manifest;
use PHPUnit\Framework\TestCase;

final class ManifestTest extends TestCase
{
    public function testToArrayAndFromArray(): void
    {
        $manifest = new Manifest(
            toolVersion: '1.0.0',
            createdAt: new \DateTimeImmutable('2025-05-03T14:15:00Z'),
            applicationId: 'acme-corp',
            applicationLabel: 'ACME Corp Website',
            sourceEnvironmentId: 'production',
            sourceEnvironmentLabel: 'Production Server',
            sourceAccessType: 'ssh',
            sourceHost: 'prod.example.com',
            stepsIncluded: ['database_export', 'config_export'],
            checksums: ['database/dump.sql.gz' => 'sha256:abc123'],
            label: 'Test deployment',
        );

        $array = $manifest->toArray();

        $this->assertSame('drops', $array['tool']);
        $this->assertSame('1.0.0', $array['tool_version']);
        $this->assertSame(1, $array['schema_version']);
        $this->assertSame('acme-corp', $array['application']['id']);
        $this->assertSame('production', $array['source_environment']['id']);
        $this->assertSame('Test deployment', $array['label']);

        // Round-trip
        $restored = Manifest::fromArray($array);
        $this->assertSame('acme-corp', $restored->applicationId);
        $this->assertSame('production', $restored->sourceEnvironmentId);
        $this->assertSame(['database_export', 'config_export'], $restored->stepsIncluded);
    }
}
