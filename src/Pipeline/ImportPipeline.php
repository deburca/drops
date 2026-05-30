<?php

declare(strict_types=1);

namespace Drops\Pipeline;

use Drops\Output\StepProgressRenderer;
use Drops\Output\SummaryRenderer;
use Drops\Step\StepInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ImportPipeline
{
    /** @var StepInterface[] */
    private array $steps;

    /**
     * @param StepInterface[] $steps Steps in execution order (already filtered for import phase)
     */
    public function __construct(array $steps)
    {
        $this->steps = $steps;
    }

    /**
     * Run the import pipeline.
     *
     * @return array<string, StepResult> Keyed by step ID
     */
    public function run(DeployContext $context, bool $continueOnError = false): array
    {
        $results = [];
        $progress = new StepProgressRenderer($context->output);
        $summary = new SummaryRenderer($context->output);

        // Verify package checksums before starting
        if ($context->packageReader !== null) {
            $checksumFailures = $context->packageReader->verifyChecksums();
            if (!empty($checksumFailures)) {
                $context->output->writeln('<error>Package checksum verification failed:</error>');
                foreach ($checksumFailures as $failure) {
                    $context->output->writeln(sprintf('  - %s', $failure));
                }
                return ['_checksum_verification' => StepResult::failed('Checksum verification failed')];
            }
        }

        $progress->start(count($this->steps));

        // Register a shutdown handler to catch fatal errors that bypass
        // normal exception handling (e.g. OOM, segfaults with display_errors=Off).
        $lastStepId = null;
        $pipelineOutput = $context->output;
        register_shutdown_function(static function () use (&$lastStepId, $pipelineOutput): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
                $pipelineOutput->writeln('');
                $pipelineOutput->writeln(sprintf(
                    '<error>Fatal error after step "%s": %s in %s on line %d</error>',
                    $lastStepId ?? '(unknown)',
                    $error['message'],
                    $error['file'],
                    $error['line'],
                ));
            }
        });

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

            try {
                $result = $step->run($context);
            } catch (\Throwable $e) {
                $context->output->writeln(sprintf(
                    '<error>Uncaught %s in step "%s": %s</error>',
                    get_class($e),
                    $stepId,
                    $e->getMessage(),
                ));
                $context->output->writeln(sprintf('  in %s:%d', $e->getFile(), $e->getLine()));
                $result = StepResult::failed($e->getMessage());
            }

            $results[$stepId] = $result;
            $lastStepId = $stepId;

            $progress->advance($step->getLabel(), $result->status);

            if ($result->isFailed() && !$continueOnError) {
                break;
            }
        }

        $progress->finish();
        $summary->render($results, 'Import');

        return $results;
    }
}
