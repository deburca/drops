<?php

declare(strict_types=1);

namespace Drops\Step;

use Drops\Pipeline\DeployContext;
use Drops\Pipeline\StepResult;

final class DatabaseUpdateStep implements StepInterface
{
    public function getId(): string
    {
        return 'database_update';
    }

    public function getLabel(): string
    {
        return 'Run database updates';
    }

    public function getPhase(): Phase
    {
        return Phase::IMPORT;
    }

    public function run(DeployContext $context): StepResult
    {
        $command = $context->drushCommand('updatedb --yes');
        $result = $context->environment->execute($command);

        if (!$result->isSuccessful()) {
            return StepResult::failed(
                sprintf('Database update failed (exit code %d)', $result->exitCode),
                [$result->getErrorOutput()],
            );
        }

        $log = ['Database updates applied'];
        if ($result->getOutput() !== '') {
            $log[] = $result->getOutput();
        }

        return StepResult::success($log);
    }
}
