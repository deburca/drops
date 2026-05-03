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

    public function advance(string $label, StepStatus $status): void
    {
        $this->current++;
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
            $this->current,
            $this->total,
            $label,
        ));
    }

    public function finish(): void
    {
        $this->output->writeln('');
    }
}
