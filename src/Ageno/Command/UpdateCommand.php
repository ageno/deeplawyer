<?php

namespace Ageno\Command;

use Humbug\SelfUpdate\Updater;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends Command
{
    protected function configure()
    {
        $this->setName('self-update');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $githubPagesUrl = 'https://ageno.github.io/deeplawyer/';

        $updater = new Updater();
        $updater->getStrategy()->setPharUrl($githubPagesUrl . 'deeplawyer.phar');
        $updater->getStrategy()->setVersionUrl($githubPagesUrl. 'deeplawyer.phar.version');

        try {
            $result = $updater->update();

            if (! $result) {
                // No update needed!
                exit(0);
            }

            $new = $updater->getNewVersion();
            $old = $updater->getOldVersion();
            printf('Updated from %s to %s', $old, $new);

            exit(0);
        } catch (\Exception $e) {
            // Report an error!
            exit(1);
        }
    }
}
