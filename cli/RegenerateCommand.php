<?php

namespace Grav\Plugin\Console;

use Grav\Console\ConsoleCommand;
use Grav\Plugin\EmbedFontawesome\Fontawesome;

class RegenerateCommand extends ConsoleCommand
{
    protected function configure()
    {
        $this
            ->setName('regenerate')
            ->setDescription('Download and extract the latest fontawesome version');
    }

    protected function serve()
    {
        $fontawesome = new Fontawesome();
        $fontawesome->regenerate();

        $this->output->writeln('Installed: ' . $fontawesome->versionInstalled());
    }
}
