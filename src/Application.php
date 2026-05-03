<?php

declare(strict_types=1);

namespace Drops;

use Drops\Command\ExportCommand;
use Drops\Command\ImportCommand;
use Drops\Command\ListApplicationsCommand;
use Drops\Command\ListEnvironmentsCommand;
use Drops\Command\PingCommand;
use Drops\Command\ValidateCommand;
use Symfony\Component\Console\Application as ConsoleApplication;

final class Application extends ConsoleApplication
{
    public const VERSION = '1.0.0';

    public function __construct()
    {
        parent::__construct('DROPS — Drupal Remote Operations and Pipeline System', self::VERSION);

        $this->addCommands([
            new ExportCommand(),
            new ImportCommand(),
            new ValidateCommand(),
            new PingCommand(),
            new ListEnvironmentsCommand(),
            new ListApplicationsCommand(),
        ]);
    }
}
