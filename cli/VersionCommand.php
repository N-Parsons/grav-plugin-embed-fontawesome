<?php

namespace Grav\Plugin\Console;

use Grav\Console\ConsoleCommand;
use Grav\Plugin\EmbedFontawesome\Fontawesome;

class VersionCommand extends ConsoleCommand
{
    protected function configure()
    {
        $this
            ->setName('version')
            ->setDescription('Analyze the current fontawesome version installed and the latest available version');
    }

    protected function serve()
    {
        $fontawesome = new Fontawesome();
        $this->output->writeln('Latest: ' . $fontawesome->versionLatest());
        $this->output->writeln('Installed: ' . $fontawesome->versionInstalled());
    }
}
