<?php

declare(strict_types=1);

namespace Drops\Tests\Unit;

use Composer\InstalledVersions;
use Drops\Application;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    public function testVersionMatchesComposerVersion(): void
    {
        $app = new Application();

        $expected = InstalledVersions::getPrettyVersion('drops/drops');
        $this->assertNotNull($expected, 'Composer must know the installed version of drops/drops');
        $this->assertSame($expected, $app->getVersion());
    }

    public function testVersionConstantIsFallback(): void
    {
        $this->assertSame('dev', Application::VERSION);
    }
}
