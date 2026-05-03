<?php

declare(strict_types=1);

namespace Drops\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'list:applications', description: 'List all configured applications with their enabled steps')]
final class ListApplicationsCommand extends DropsCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $loader = $this->getConfigLoader($input);
        $applications = $loader->loadAllApplications();

        if (empty($applications)) {
            $output->writeln('<comment>No applications configured.</comment>');
            $output->writeln(sprintf('Add YAML files to: %s/applications/', $loader->getConfigDir()));
            return self::SUCCESS;
        }

        $output->writeln('<info>Configured applications:</info>');
        $output->writeln('');

        foreach ($applications as $app) {
            $label = $app->label !== null ? sprintf(' (%s)', $app->label) : '';
            $output->writeln(sprintf('  <info>%s</info>%s', $app->id, $label));

            $enabledSteps = $app->getEnabledSteps();
            if (!empty($enabledSteps)) {
                $output->writeln(sprintf('    Steps: %s', implode(', ', $enabledSteps)));
            } else {
                $output->writeln('    Steps: <comment>none</comment>');
            }
            $output->writeln('');
        }

        return self::SUCCESS;
    }
}
