<?php

declare(strict_types=1);

namespace Drops\Step;

use Drops\Pipeline\DeployContext;
use Drops\Pipeline\StepResult;

interface StepInterface
{
    /**
     * Unique identifier for this step (e.g. 'database_export', 'cache_rebuild').
     */
    public function getId(): string;

    /**
     * Human-readable label for display in progress output.
     */
    public function getLabel(): string;

    /**
     * Which phase(s) this step applies to.
     */
    public function getPhase(): Phase;

    /**
     * Execute the step within the given deployment context.
     */
    public function run(DeployContext $context): StepResult;
}
