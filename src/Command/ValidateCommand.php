<?php

declare(strict_types=1);

namespace Drops\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'validate', description: 'Validate configuration files')]
final class ValidateCommand extends DropsCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('app', null, InputOption::VALUE_OPTIONAL, 'Application identifier')
            ->addOption('env', null, InputOption::VALUE_OPTIONAL, 'Environment identifier')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Validate all configurations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $validator = $this->getConfigValidator();
        $loader = $this->getConfigLoader($input);
        $hasErrors = false;

        $validateAll = $input->getOption('all');
        $appId = $input->getOption('app');
        $envId = $input->getOption('env');

        $configDir = $loader->getConfigDir();

        if ($validateAll) {
            foreach ($loader->loadAllEnvironments() as $envConfig) {
                $output->write(sprintf('Environment "%s": ', $envConfig->id));
                $path = $configDir . '/environments/' . $envConfig->id . '.yml';
                $result = $validator->validateEnvironment($this->loadRawConfig($path));
                if ($this->reportResult($result, $output)) {
                    $hasErrors = true;
                }
            }

            foreach ($loader->loadAllApplications() as $appConfig) {
                $output->write(sprintf('Application "%s": ', $appConfig->id));
                $path = $configDir . '/applications/' . $appConfig->id . '.yml';
                $result = $validator->validateApplication($this->loadRawConfig($path));
                if ($this->reportResult($result, $output)) {
                    $hasErrors = true;
                }
            }
        } else {
            if ($envId !== null) {
                $output->write(sprintf('Environment "%s": ', $envId));
                $path = $configDir . '/environments/' . $envId . '.yml';
                $result = $validator->validateEnvironment($this->loadRawConfig($path));
                if ($this->reportResult($result, $output)) {
                    $hasErrors = true;
                }
            }

            if ($appId !== null) {
                $output->write(sprintf('Application "%s": ', $appId));
                $path = $configDir . '/applications/' . $appId . '.yml';
                $result = $validator->validateApplication($this->loadRawConfig($path));
                if ($this->reportResult($result, $output)) {
                    $hasErrors = true;
                }
            }

            if ($envId === null && $appId === null) {
                $output->writeln('<comment>Specify --app, --env, or --all</comment>');
                return self::FAILURE;
            }
        }

        return $hasErrors ? self::FAILURE : self::SUCCESS;
    }

    private function reportResult(
        \Drops\Config\ValidationResult $result,
        OutputInterface $output,
    ): bool {
        if ($result->isValid()) {
            $output->writeln('<info>valid</info>');
            return false;
        }

        $output->writeln('<error>invalid</error>');
        foreach ($result->errors as $error) {
            $output->writeln(sprintf('  - %s', $error));
        }
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadRawConfig(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return [];
        }

        $data = Yaml::parse($content);

        return is_array($data) ? $data : [];
    }
}
