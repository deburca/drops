<?php

declare(strict_types=1);

namespace Drops\Output;

use Drops\Pipeline\StepStatus;
use Symfony\Component\Console\Output\OutputInterface;

final class StepProgressRenderer
{
    private int $total = 0;
    private int $current = 0;

    public function __construct(
        private readonly OutputInterface $output,
    ) {
    }

    public function start(int $total): void
    {
        $this->total = $total;
        $this->current = 0;
    }

    /**
     * Display a status line without advancing the step counter.
     *
     * Use this to show a transient status (e.g. RUNNING) before a step
     * completes. The counter stays at the current value.
     */
    public function status(string $label, StepStatus $status): void
    {
        $this->renderLine($label, $status, $this->current + 1);
    }

    /**
     * Advance the step counter and display the result status.
     */
    public function advance(string $label, StepStatus $status): void
    {
        $this->current++;
        $this->renderLine($label, $status, $this->current);
    }

    private function renderLine(string $label, StepStatus $status, int $step): void
    {
        $statusIcon = match ($status) {
            StepStatus::RUNNING => '<comment>⏳</comment>',
            StepStatus::COMPLETE => '<info>✓</info>',
            StepStatus::FAILED => '<error>✗</error>',
            StepStatus::SKIPPED => '<comment>⊘</comment>',
            StepStatus::PENDING => '<comment>…</comment>',
        };

        $this->output->writeln(sprintf(
            '  %s [%d/%d] %s',
            $statusIcon,
            $step,
            $this->total,
            $label,
        ));
    }

    public function finish(): void
    {
        $this->output->writeln('');
    }
}
