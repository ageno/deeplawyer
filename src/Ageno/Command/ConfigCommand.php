<?php

namespace Ageno\Command;

use Ageno\Helper\OpenSSL;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

use Moccalotto\Ssh\Auth;
use Moccalotto\Ssh\Session;
use Moccalotto\Ssh\Connect;
use Symfony\Component\Console\Helper\Table;

class ConfigCommand extends Command
{
    const DEFAULT_PORT = 22;

    protected function configure()
    {
        $this->setName('configure')
            ->setDescription('Deployment configuration');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /**
         * Get hostname, username and password of vps server
         */
        $helper = $this->getHelper('question');

        // Hostname
        $question = new Question('Hostname (ip): ');
        $hostname = $helper->ask($input, $output, $question);

        // Auth Method
        /*
        $question = new ChoiceQuestion(
            'Authentication method: ',
            array('password', 'ssh-key'),
            0
        );
        $question->setErrorMessage('Auth method %s is invalid.');
        $authMethod = $helper->ask($input, $output, $question);
        */

        // Username
        $question = new Question('User: ');
        $username = $helper->ask($input, $output, $question);

        // Password
        $question = new Question('Password: ');
        $question->setHidden(true);
        $question->setHiddenFallback(false);

        $password = $helper->ask($input, $output, $question);

        // Worktree
        $question = new Question('Worktree directory: ');
        $worktree = $helper->ask($input, $output, $question);

        // Repository Name
        $question = new Question('Repository name: ');
        $repository = $helper->ask($input, $output, $question);

        // Branch Name
        $question = new Question('Default branch name: ', 'master');
        $branch = $helper->ask($input, $output, $question);

        list($publicKey, $privateKey) = OpenSSL::generateRSA();

        /**
         * Create an SSH session
         */
        $port = self::DEFAULT_PORT;

        if (strpos($hostname, ':') !== false) {
            list($hostname, $port) = explode(":", $hostname, 2);
        }

        $ssh = new Session(
            Connect::to($hostname, $port),
            Auth::viaPassword($username, $password)
        );

        /**
         * Open a shell on the remote server
         */
        $shell_output = $ssh->shell(
            function ($shell) use ($publicKey, $repository, $worktree, $branch) {

                $hook = <<<EOF
#!/bin/sh

read OLDREV NEWREV REFNAME
WORKTREE=`git config core.worktree`

# GITDIR=`pwd`
# checkout current commit

echo "git checkout {$branch} --force"
git checkout {$branch} --force
EOF;

                $captured_output = $shell
                    ->writeline("mkdir -p ~/.ssh && echo \"{$publicKey}\" >> ~/.ssh/authorized_keys")
                    ->writeline("git init --bare {$repository}")
                    ->writeline("cd {$repository}")
                    ->writeline("bash -c \"cat >> hooks/post-receive\" <<EOL\n{$hook}\nEOL")
                    ->writeline("chmod +x hooks/post-receive")
                    ->writeline("git config core.worktree {$worktree}")
                    ->writeline("git config core.bare false")
                    ->writeline("git config receive.denyCurrentBranch ignore")
                    ->writeline("cat config")
                    ->writeline("mkdir -p {$worktree}")
                    ->writeline("ls -l {$worktree}")
                    ->writeline('logout')
                    ->wait(0.3)// give the shell time to execute the commands.
                    ->readToEnd();

                return $captured_output;
            }
        );

        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $output->writeln($shell_output);
        }

        /**
         * Summary
         */
        $table = new Table($output);
        $table
            ->setHeaders(['GitLab CI Variable', 'Value'])
            ->setRows(
                [
                    ['DEPLOY_CREDENTIALS', "{$username}@{$hostname}"],
                    ['DEPLOY_PATH', $repository],
                ]
            );
        $table->render();

        $output->writeln('SSH_PRIVATE_KEY:');
        $output->writeln($privateKey);
    }
}
