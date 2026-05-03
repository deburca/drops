<?php

declare(strict_types=1);

namespace Drops\Config;

final class ValidationResult
{
    /**
     * @param string[] $errors
     */
    public function __construct(
        public readonly array $errors = [],
    ) {
    }

    public function isValid(): bool
    {
        return count($this->errors) === 0;
    }
}
