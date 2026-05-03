<?php

declare(strict_types=1);

namespace Drops\Package;

use DateTimeImmutable;

final class Manifest
{
    public const SCHEMA_VERSION = 1;
    public const TOOL_NAME = 'drops';

    /**
     * @param string[] $stepsIncluded
     * @param array<string, string> $checksums
     */
    public function __construct(
        public readonly string $toolVersion,
        public readonly DateTimeImmutable $createdAt,
        public readonly string $applicationId,
        public readonly string $applicationLabel,
        public readonly string $sourceEnvironmentId,
        public readonly string $sourceEnvironmentLabel,
        public readonly string $sourceAccessType,
        public readonly ?string $sourceHost,
        public readonly array $stepsIncluded,
        public readonly array $checksums = [],
        public readonly ?string $label = null,
    ) {
    }

    /**
     * Create from decoded manifest.json data.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $app = $data['application'] ?? [];
        $env = $data['source_environment'] ?? [];

        return new self(
            toolVersion: $data['tool_version'] ?? '0.0.0',
            createdAt: new DateTimeImmutable($data['created_at'] ?? 'now'),
            applicationId: $app['id'] ?? '',
            applicationLabel: $app['label'] ?? '',
            sourceEnvironmentId: $env['id'] ?? '',
            sourceEnvironmentLabel: $env['label'] ?? '',
            sourceAccessType: $env['access_type'] ?? '',
            sourceHost: $env['host'] ?? null,
            stepsIncluded: $data['steps_included'] ?? [],
            checksums: $data['checksums'] ?? [],
            label: $data['label'] ?? null,
        );
    }

    /**
     * Serialise to an array suitable for JSON encoding.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'tool' => self::TOOL_NAME,
            'tool_version' => $this->toolVersion,
            'schema_version' => self::SCHEMA_VERSION,
            'created_at' => $this->createdAt->format('Y-m-d\TH:i:s\Z'),
            'application' => [
                'id' => $this->applicationId,
                'label' => $this->applicationLabel,
            ],
            'source_environment' => [
                'id' => $this->sourceEnvironmentId,
                'label' => $this->sourceEnvironmentLabel,
                'access_type' => $this->sourceAccessType,
            ],
            'steps_included' => $this->stepsIncluded,
            'checksums' => $this->checksums,
        ];

        if ($this->label !== null) {
            $data['label'] = $this->label;
        }

        if ($this->sourceHost !== null) {
            $data['source_environment']['host'] = $this->sourceHost;
        }

        return $data;
    }
}
