<?php

declare(strict_types=1);

namespace Drops\Pipeline;

enum StepStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case COMPLETE = 'complete';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';
}
