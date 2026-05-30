<?php

declare(strict_types=1);

namespace Drops\Pipeline;

use Drops\Output\StepProgressRenderer;
use Drops\Output\SummaryRenderer;
use Drops\Step\StepInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ExportPipeline
{
    /** @var StepInterface[] */
    private array $steps;

    /**
     * @param StepInterface[] $steps Steps in execution order (already filtered for export phase)
     */
    public function __construct(array $steps)
    {
        $this->steps = $steps;
    }

    /**
     * Run the export pipeline.
     *
     * @return array<string, StepResult> Keyed by step ID
     */
    public function run(DeployContext $context, bool $continueOnError = false): array
    {
        $results = [];
        $progress = new StepProgressRenderer($context->output);
        $summary = new SummaryRenderer($context->output);

        $progress->start(count($this->steps));

        foreach ($this->steps as $step) {
            $stepId = $step->getId();

            if (!$context->isStepEnabled($stepId)) {
                $results[$stepId] = StepResult::skipped('Step disabled in configuration');
                $progress->advance($step->getLabel(), StepStatus::SKIPPED);
                continue;
            }

            $progress->status($step->getLabel(), StepStatus::RUNNING);

            if ($context->dryRun) {
                $results[$stepId] = StepResult::skipped('Dry run');
                $progress->advance($step->getLabel(), StepStatus::SKIPPED);
                continue;
            }

            $result = $step->run($context);
            $results[$stepId] = $result;

            $progress->advance($step->getLabel(), $result->status);

            if ($result->isFailed() && !$continueOnError) {
                break;
            }
        }

        $progress->finish();
        $summary->render($results, 'Export');

        return $results;
    }
}
