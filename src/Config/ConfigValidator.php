<?php

declare(strict_types=1);

namespace Drops\Config;

use JsonSchema\Validator;
use RuntimeException;

final class ConfigValidator
{
    private string $schemaDir;

    public function __construct(string $schemaDir)
    {
        $this->schemaDir = rtrim($schemaDir, '/');
    }

    /**
     * Validate environment config data against the JSON Schema.
     *
     * @param array<string, mixed> $data
     * @return ValidationResult
     */
    public function validateEnvironment(array $data): ValidationResult
    {
        return $this->validate($data, $this->schemaDir . '/environment.schema.json');
    }

    /**
     * Validate application config data against the JSON Schema.
     *
     * @param array<string, mixed> $data
     * @return ValidationResult
     */
    public function validateApplication(array $data): ValidationResult
    {
        return $this->validate($data, $this->schemaDir . '/application.schema.json');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validate(array $data, string $schemaPath): ValidationResult
    {
        if (!file_exists($schemaPath)) {
            throw new RuntimeException(sprintf('Schema file not found: %s', $schemaPath));
        }

        $schemaContent = file_get_contents($schemaPath);
        if ($schemaContent === false) {
            throw new RuntimeException(sprintf('Unable to read schema file: %s', $schemaPath));
        }

        $schema = json_decode($schemaContent);
        $dataObject = json_decode(json_encode($data, JSON_THROW_ON_ERROR));

        $validator = new Validator();
        $validator->validate($dataObject, $schema);

        $errors = [];
        if (!$validator->isValid()) {
            foreach ($validator->getErrors() as $error) {
                $path = $error['property'] !== '' ? $error['property'] . ': ' : '';
                $errors[] = $path . $error['message'];
            }
        }

        return new ValidationResult($errors);
    }
}
