<?php

declare(strict_types=1);

namespace Drops\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'list:environments', description: 'List all configured environments')]
final class ListEnvironmentsCommand extends DropsCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $loader = $this->getConfigLoader($input);
        $environments = $loader->loadAllEnvironments();

        if (empty($environments)) {
            $output->writeln('<comment>No environments configured.</comment>');
            $output->writeln(sprintf('Add YAML files to: %s/environments/', $loader->getConfigDir()));
            return self::SUCCESS;
        }

        $output->writeln('<info>Configured environments:</info>');
        $output->writeln('');

        foreach ($environments as $env) {
            $label = $env->label !== null ? sprintf(' (%s)', $env->label) : '';
            $access = $env->isSsh()
                ? sprintf('ssh://%s@%s:%d', $env->user, $env->host, $env->port)
                : 'local';

            $output->writeln(sprintf('  <info>%s</info>%s', $env->id, $label));
            $output->writeln(sprintf('    Access:  %s', $access));
            $output->writeln(sprintf('    Webroot: %s', $env->webroot));
            $output->writeln('');
        }

        return self::SUCCESS;
    }
}
