<?php

declare(strict_types=1);

namespace Drops\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'ping', description: 'Test connectivity to an environment and verify PHP and Drush are reachable')]
final class PingCommand extends DropsCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this->addOption('env', null, InputOption::VALUE_REQUIRED, 'Environment identifier');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $envConfig = $this->resolveEnvironment($input);
        $environment = $this->createEnvironment($envConfig);

        $output->writeln(sprintf(
            '<info>Pinging %s</info> (%s)',
            $envConfig->label ?? $envConfig->id,
            $envConfig->accessType,
        ));
        $output->writeln('');

        // Test PHP
        $output->write('  PHP: ');
        $phpResult = $environment->execute($envConfig->getPhpPath() . ' --version');
        if ($phpResult->isSuccessful()) {
            $lines = explode("\n", $phpResult->getOutput());
            $version = trim($lines[0]);
            $output->writeln(sprintf('<info>%s</info>', $version));
        } else {
            $output->writeln('<error>not reachable</error>');
            return self::FAILURE;
        }

        // Test Drush
        $output->write('  Drush: ');
        $drushResult = $environment->execute($envConfig->getDrushPath() . ' --version');
        if ($drushResult->isSuccessful()) {
            $version = trim($drushResult->getOutput());
            $output->writeln(sprintf('<info>%s</info>', $version));
        } else {
            $output->writeln('<error>not reachable</error>');
            return self::FAILURE;
        }

        // Test webroot exists
        $output->write('  Webroot: ');
        if ($environment->exists($envConfig->webroot)) {
            $output->writeln(sprintf('<info>%s</info>', $envConfig->webroot));
        } else {
            $output->writeln(sprintf('<error>%s (not found)</error>', $envConfig->webroot));
            return self::FAILURE;
        }

        $output->writeln('');
        $output->writeln('<info>All checks passed.</info>');

        return self::SUCCESS;
    }
}
