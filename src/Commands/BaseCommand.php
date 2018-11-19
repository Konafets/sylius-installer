<?php namespace Konafets\Installer\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class BaseCommand extends Command
{

    protected function initialize(InputInterface $input, OutputInterface $output) : void
    {
        $this->printWelcomeMessage($output);
        $output->writeln('');
    }

    /**
     * @param OutputInterface $output
     */
    protected function printWelcomeMessage(OutputInterface $output) : void
    {
        $output->writeln('<bg=green>                                        </>');
        $output->writeln('<bg=green;fg=black>  Welcome to Sylius Plugin Kickstarter  </>');
        $output->writeln('<bg=green>                                        </>');
    }

    /**
     * @param string $directory
     * @return void
     */
    protected function verifyProjectFolderDoesNotExist(string $directory) : void
    {
        if ((is_dir($directory) || is_file($directory)) && $directory != getcwd()) {
            throw new \RuntimeException('Directory already exists');
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param $commands
     * @param $directory
     */
    protected function executeProcess(InputInterface $input, OutputInterface $output, $commands, ?string $directory) : void
    {
        $commands = is_array($commands) ? $commands : [$commands];

        $commands = $this->passOptionsToCommand($input, $commands);

        $process = new Process(implode(' && ', $commands), $directory, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            $process->setTty(true);
        }

        $process->run(function ($line) use ($output) {
            $output->write($line);
        });
    }


    /**
     * @param InputInterface $input
     * @param $commands
     * @return array
     */
    protected function passOptionsToCommand(InputInterface $input, $commands) : array
    {
        if ($input->getOption('no-ansi')) {
            $commands = array_map(function ($value) {
                return $value . ' --no-ansi';
            }, $commands);
        }

        if ($input->getOption('quiet')) {
            $commands = array_map(function ($value) {
                return $value . ' --quiet';
            }, $commands);
        }

        return $commands;
    }

    /**
     * @return string
     */
    protected function findComposer() : string
    {
        if (file_exists(getcwd() . '/composer.phar')) {
            return '"' . PHP_BINARY . '" composer.phar';
        }

        return 'composer';
    }
}
