<?php

declare(strict_types=1);

namespace Drops;

use Composer\InstalledVersions;
use Drops\Command\ExportCommand;
use Drops\Command\ImportCommand;
use Drops\Command\ListApplicationsCommand;
use Drops\Command\ListEnvironmentsCommand;
use Drops\Command\PingCommand;
use Drops\Command\ValidateCommand;
use Symfony\Component\Console\Application as ConsoleApplication;

final class Application extends ConsoleApplication
{
    public const VERSION = 'dev';

    public function __construct()
    {
        $version = InstalledVersions::getPrettyVersion('drops/drops') ?? self::VERSION;
        parent::__construct('DROPS — Drupal Remote Operations and Pipeline System', $version);

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
