<?php

declare(strict_types=1);

namespace Drops\Output;

use Drops\Pipeline\StepResult;
use Symfony\Component\Console\Output\OutputInterface;

final class SummaryRenderer
{
    public function __construct(
        private readonly OutputInterface $output,
    ) {
    }

    /**
     * Render a summary of step results.
     *
     * @param array<string, StepResult> $results
     */
    public function render(array $results, string $phase): void
    {
        $completed = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($results as $result) {
            if ($result->isSuccess()) {
                $completed++;
            } elseif ($result->isFailed()) {
                $failed++;
            } elseif ($result->isSkipped()) {
                $skipped++;
            }
        }

        $this->output->writeln(sprintf(
            '<info>%s summary:</info> %d completed, %d skipped, %d failed',
            $phase,
            $completed,
            $skipped,
            $failed,
        ));

        if ($failed > 0) {
            $this->output->writeln('');
            $this->output->writeln('<error>Failures:</error>');
            foreach ($results as $stepId => $result) {
                if ($result->isFailed()) {
                    $this->output->writeln(sprintf('  <error>%s:</error> %s', $stepId, $result->errorMessage));
                }
            }
        }
    }
}
