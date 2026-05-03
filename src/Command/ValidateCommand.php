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

        if ($validateAll) {
            // Validate all environments
            foreach ($loader->loadAllEnvironments() as $envConfig) {
                $output->write(sprintf('Environment "%s": ', $envConfig->id));
                $result = $validator->validateEnvironment($this->loadRawConfig($loader->getConfigDir() . '/environments/' . $envConfig->id . '.yml'));
                if ($result->isValid()) {
                    $output->writeln('<info>valid</info>');
                } else {
                    $output->writeln('<error>invalid</error>');
                    foreach ($result->errors as $error) {
                        $output->writeln(sprintf('  - %s', $error));
                    }
                    $hasErrors = true;
                }
            }

            // Validate all applications
            foreach ($loader->loadAllApplications() as $appConfig) {
                $output->write(sprintf('Application "%s": ', $appConfig->id));
                $result = $validator->validateApplication($this->loadRawConfig($loader->getConfigDir() . '/applications/' . $appConfig->id . '.yml'));
                if ($result->isValid()) {
                    $output->writeln('<info>valid</info>');
                } else {
                    $output->writeln('<error>invalid</error>');
                    foreach ($result->errors as $error) {
                        $output->writeln(sprintf('  - %s', $error));
                    }
                    $hasErrors = true;
                }
            }
        } else {
            if ($envId !== null) {
                $output->write(sprintf('Environment "%s": ', $envId));
                $result = $validator->validateEnvironment($this->loadRawConfig($loader->getConfigDir() . '/environments/' . $envId . '.yml'));
                if ($result->isValid()) {
                    $output->writeln('<info>valid</info>');
                } else {
                    $output->writeln('<error>invalid</error>');
                    foreach ($result->errors as $error) {
                        $output->writeln(sprintf('  - %s', $error));
                    }
                    $hasErrors = true;
                }
            }

            if ($appId !== null) {
                $output->write(sprintf('Application "%s": ', $appId));
                $result = $validator->validateApplication($this->loadRawConfig($loader->getConfigDir() . '/applications/' . $appId . '.yml'));
                if ($result->isValid()) {
                    $output->writeln('<info>valid</info>');
                } else {
                    $output->writeln('<error>invalid</error>');
                    foreach ($result->errors as $error) {
                        $output->writeln(sprintf('  - %s', $error));
                    }
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
