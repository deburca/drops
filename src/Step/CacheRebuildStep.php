<?php

declare(strict_types=1);

namespace Drops\Step;

use Drops\Pipeline\DeployContext;
use Drops\Pipeline\StepResult;

final class CacheRebuildStep implements StepInterface
{
    public function getId(): string
    {
        return 'cache_rebuild';
    }

    public function getLabel(): string
    {
        return 'Rebuild cache';
    }

    public function getPhase(): Phase
    {
        return Phase::IMPORT;
    }

    public function run(DeployContext $context): StepResult
    {
        $command = $context->drushCommand('cache:rebuild');
        $result = $context->environment->execute($command);

        if (!$result->isSuccessful()) {
            return StepResult::failed(
                sprintf('Cache rebuild failed (exit code %d)', $result->exitCode),
                [$result->getErrorOutput()],
            );
        }

        return StepResult::success(['Cache rebuilt successfully']);
    }
}
