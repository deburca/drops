<?php

declare(strict_types=1);

namespace Drops\Step;

use Drops\Pipeline\DeployContext;
use Drops\Pipeline\StepResult;

final class MaintenanceOffStep implements StepInterface
{
    public function getId(): string
    {
        return 'maintenance_off';
    }

    public function getLabel(): string
    {
        return 'Disable maintenance mode';
    }

    public function getPhase(): Phase
    {
        return Phase::IMPORT;
    }

    public function run(DeployContext $context): StepResult
    {
        $command = $context->drushCommand('state:set system.maintenance_mode 0 --input-format=integer');
        $result = $context->environment->execute($command);

        if (!$result->isSuccessful()) {
            return StepResult::failed(
                sprintf('Failed to disable maintenance mode (exit code %d)', $result->exitCode),
                [$result->getErrorOutput()],
            );
        }

        return StepResult::success(['Maintenance mode disabled']);
    }
}
