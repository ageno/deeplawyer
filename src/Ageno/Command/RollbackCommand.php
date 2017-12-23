<?php

namespace Ageno\Command;

use Humbug\SelfUpdate\Updater;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RollbackCommand extends Command
{
    protected function configure()
    {
        $this->setName('rollback');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $githubPagesUrl = 'https://ageno.github.io/deeplawyer/';

        $updater = new Updater();
        $updater->getStrategy()->setPharUrl($githubPagesUrl . 'deeplawyer.phar');
        $updater->getStrategy()->setVersionUrl($githubPagesUrl . 'deeplawyer.phar.version');

        try {
            $result = $updater->rollback();

            if (! $result) {
                exit(1);
            }

            exit(0);
        } catch (\Exception $e) {
            exit(1);
        }
    }
}
