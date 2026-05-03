<?php

declare(strict_types=1);

namespace Drops\Tests\Unit\Pipeline;

use Drops\Pipeline\StepResult;
use Drops\Pipeline\StepStatus;
use PHPUnit\Framework\TestCase;

final class StepResultTest extends TestCase
{
    public function testSuccess(): void
    {
        $result = StepResult::success(['Step completed']);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isFailed());
        $this->assertFalse($result->isSkipped());
        $this->assertSame(StepStatus::COMPLETE, $result->status);
        $this->assertSame(['Step completed'], $result->log);
        $this->assertNull($result->errorMessage);
    }

    public function testFailed(): void
    {
        $result = StepResult::failed('Something went wrong', ['Attempted step']);

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isFailed());
        $this->assertFalse($result->isSkipped());
        $this->assertSame(StepStatus::FAILED, $result->status);
        $this->assertSame('Something went wrong', $result->errorMessage);
    }

    public function testSkipped(): void
    {
        $result = StepResult::skipped('Not needed');

        $this->assertFalse($result->isSuccess());
        $this->assertFalse($result->isFailed());
        $this->assertTrue($result->isSkipped());
        $this->assertSame(StepStatus::SKIPPED, $result->status);
    }
}
