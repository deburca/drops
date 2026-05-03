<?php

declare(strict_types=1);

namespace Drops\Pipeline;

final class StepResult
{
    /**
     * @param string[] $log
     */
    private function __construct(
        public readonly StepStatus $status,
        public readonly array $log = [],
        public readonly ?string $errorMessage = null,
    ) {
    }

    /**
     * @param string[] $log
     */
    public static function success(array $log = []): self
    {
        return new self(StepStatus::COMPLETE, $log);
    }

    /**
     * @param string[] $log
     */
    public static function failed(string $errorMessage, array $log = []): self
    {
        return new self(StepStatus::FAILED, $log, $errorMessage);
    }

    /**
     * @param string[] $log
     */
    public static function skipped(string $reason = '', array $log = []): self
    {
        return new self(StepStatus::SKIPPED, $log, $reason);
    }

    public function isSuccess(): bool
    {
        return $this->status === StepStatus::COMPLETE;
    }

    public function isFailed(): bool
    {
        return $this->status === StepStatus::FAILED;
    }

    public function isSkipped(): bool
    {
        return $this->status === StepStatus::SKIPPED;
    }
}
