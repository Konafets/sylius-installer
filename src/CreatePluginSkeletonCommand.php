<?php namespace Konafets\Installer\Console;

use Exception;
use Nadar\PhpComposerReader\ComposerReader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;
use Underscore\Types\Strings;

class CreatePluginSkeletonCommand extends Command
{

    const ACME_NAMESPACE = 'Acme\SyliusExamplePlugin';
    const ACME_EXAMPLE = 'AcmeSyliusExample';
    const ACME_EXAMPLE_PLUGIN = 'AcmeSyliusExamplePlugin';

    /** @var string */
    protected $vendor = '';

    /** @var string */
    protected $name = '';

    /** @var Filesystem */
    protected $filesystem;

    /**
     * CreatePluginSkeletonCommand constructor.
     *
     * @param string $name
     */
    public function __construct(string $name = null) {
        parent::__construct($name);

        $this->filesystem = new Filesystem();
    }

    protected function configure()
    {
        $this
            ->setName('new:plugin')
            ->setDescription('Creates the plugin skeleton')
            ->setDefinition([
                new InputOption('name', null, InputOption::VALUE_REQUIRED, 'Name of the plugin'),
                new InputOption('description', 'd', InputOption::VALUE_REQUIRED, 'The description of your plugin'),
                //new InputOption('author', InputOption::VALUE_REQUIRED, 'Author name of the plugin'),
                //new InputOption('license', 'l', InputOption::VALUE_REQUIRED, 'License of package'),
                new InputOption('dev', null, InputOption::VALUE_NONE, 'Installs the latest "development" release'),
                new InputOption('force', 'f', InputOption::VALUE_NONE, 'Forces install even if the directory already exists'),
            ]);
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->printWelcomeMessage($output);
        $output->writeln('');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $directory = $this->getPath($input);

        if (! $input->getOption('force')) {
            $this->verifyPluginDoesntExist($directory);
        }

        $output->writeln('<info>Installing Plugin Skeleton...</info>');

        $composer = $this->findComposer();

        $commands = [
            $composer . ' create-project sylius/plugin-skeleton ' . $directory,
        ];

        if ($input->getOption('no-ansi')) {
            $commands = array_map(function ($value) {
                return $value.' --no-ansi';
            }, $commands);
        }
        if ($input->getOption('quiet')) {
            $commands = array_map(function ($value) {
                return $value.' --quiet';
            }, $commands);
        }

        $process = new Process(implode(' && ', $commands), null, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            $process->setTty(true);
        }

        $process->run(function ($line) use ($output) {
            $output->write($line);
        });

        $output->writeln('<info>Configure your plugin...</info>');

        $this->customizeSkeleton($input, $directory);

        $output->writeln('<info>Building assets...</info>');

        $commands = [
            $composer . ' dump-autoload --optimize',
            '(cd tests/Application && yarn install)',
            '(cd tests/Application && yarn build)',
            '(cd tests/Application && bin/console assets:install public -e test)',
        ];

        $process = new Process(implode(' && ', $commands), $directory, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            $process->setTty(true);
        }

        $process->run(function ($line) use ($output) {
            $output->write($line);
        });

        $output->writeln('<comment>Your plugin is ready in folder ' . $directory . '/</comment>');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<comment>We will guide you trough the installation.</comment>');
        $output->writeln('');

        /** @var QuestionHelper $questionHelper */
        $dialog = $this->getHelper('question');

        if (!$name = $input->getOption('name')) {
            $question = new Question('Package name (<vendor>/<name>): ');
            $question->setValidator(function ($answer) {
                if ( ! preg_match('{^[a-z0-9_.-]+/[a-z0-9_.-]+$}', $answer)) {
                    throw new \InvalidArgumentException('The package name ' . $answer . ' is invalid, it should be lowercase and have a vendor name, a forward slash, and a package name, matching: [a-z0-9_.-]+/[a-z0-9_.-]+');
                }

                return $answer;
            });
            $question->setMaxAttempts(2);
            $name = $dialog->ask($input, $output, $question);
        } else {
            if (!preg_match('{^[a-z0-9_.-]+/[a-z0-9_.-]+$}', $name)) {
                throw new \InvalidArgumentException(
                    'The package name '.$name.' is invalid, it should be lowercase and have a vendor name, a forward slash, and a package name, matching: [a-z0-9_.-]+/[a-z0-9_.-]+'
                );
            }
        }

        $input->setOption('name', $name);

        if (! $name = $input->getOption('description')) {
            $question = new Question('Description: ');
            $description = $dialog->ask($input, $output, $question);
            $input->setOption('description', $description);
        }
    }

    /**
     * @param string $directory
     * @return void
     */
    protected function verifyPluginDoesntExist(string $directory) : void
    {
        if ((is_dir($directory) || is_file($directory)) && $directory != getcwd()) {
            throw new \RuntimeException('Plugin already exists');
        }
    }

    /**
     * @param InputInterface $input
     * @return string
     */
    protected function getVersion(InputInterface $input) : string
    {
        if ($input->getOption('dev')) {
            return 'develop';
        }

        return 'master';
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

    /**
     * @param InputInterface $input
     * @param string $directory
     * @throws Exception
     */
    private function customizeSkeleton(InputInterface $input, string $directory)
    {
        $this->customizeComposerFile($input, $directory);
        $this->changeDummyPluginNameToCustomPluginName($directory);
        $this->renameClasses($directory);
    }

    /**
     * @param InputInterface $input
     * @return string
     */
    private function getPath(InputInterface $input) : string
    {
        $name = $input->getOption('name');
        list($vendor, $packageName) =  explode('/', $name);

        // TODO: Extract this to own function
        $this->vendor = $vendor;
        $this->name = $packageName;

        $directory = Strings::toPascalCase($vendor) . Strings::toPascalCase($packageName);

        return $directory;
    }

    /**
     * - sylius/plugin-skeleton -> iron-man/sylius-product-on-demand-plugin
     * - description: Acme example plugin for Sylius. -> Plugin allowing to mark product variants as available on demand in Sylius.
     * - Acme\\SyliusExamplePlugin\\ -> IronMan\\SyliusProductOnDemandPlugin\\
     * - Tests\\Acme\\SyliusExamplePlugin\\ -> Tests\\IronMan\\SyliusProductOnDemandPlugin\\
     *
     * @param InputInterface $input
     * @param string $directory
     * @throws Exception
     */
    private function customizeComposerFile(InputInterface $input, string $directory) : void
    {
        $reader = new ComposerReader($directory . '/composer.json');

        $composerFile = $reader->getContent();

        $composerFile['name'] = $input->getOption('name');
        $composerFile['description'] = $input->getOption('description');
        $composerFile['autoload']['psr-4'][$this->getNamespace() . '\\'] = 'src/';
        $composerFile['autoload']['psr-4']['Tests\\' . $this->getNamespace() . '\\'] = 'tests/';

        unset($composerFile['autoload']['psr-4']['Acme\\SyliusExamplePlugin\\']);
        unset($composerFile['autoload']['psr-4']['Tests\\Acme\\SyliusExamplePlugin\\']);

        $reader->writeContent($composerFile);
    }

    /**
     * @return string
     */
    public function getNamespace() : string
    {
        return Strings::toPascalCase($this->vendor) . '\\' . Strings::toPascalCase($this->name);
    }

    /**
     * @param string $directory
     * @return string
     */
    protected static function getSrcFolder(string $directory) : string
    {
        return $directory . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
    }

    /**
     * @param $search
     * @param $replace
     * @param $file
     */
    private static function writeFile($search, $replace, SplFileInfo $file) : void
    {
        $content = $file->getContents();
        $content = str_replace($search, $replace, $content);
        file_put_contents($file->getRealPath(), $content);
    }

    /**
     * @param string $directory
     */
    private function changeDummyPluginNameToCustomPluginName(string $directory) : void
    {
        $excludedDirs = ['node_modules', 'vendor'];

        $finder = new Finder();
        $finder->files()->in($directory)->exclude($excludedDirs);

        $plainPluginName = Strings::remove($directory, 'Plugin');

        foreach ($finder as $file) {
            if (in_array($file->getFilename(), $excludedDirs)) {
                continue;
            }

            self::writeFile(
                [self::ACME_NAMESPACE, Strings::toPascalCase(self::ACME_EXAMPLE_PLUGIN), ltrim(Strings::toSnakeCase(self::ACME_EXAMPLE), '_'), Strings::lower(self::ACME_EXAMPLE_PLUGIN)],
                [$this->getNamespace(), $directory, ltrim(Strings::toSnakeCase($plainPluginName), '_'), Strings::lower($directory)],
                $file
            );
        }
    }

    /**
     * @param string $directory
     */
    private function renameClasses(string $directory) : void
    {
        $plainPluginName = Strings::remove($directory, 'Plugin');

        $finder = new Finder();
        $finder->files()->in(self::getSrcFolder($directory))->name('*.php');

        foreach ($finder as $file) {
            // Rename Class
            self::writeFile(self::ACME_EXAMPLE, $plainPluginName, $file);

            // Rename File
            $this->filesystem->rename(
                $file->getRealPath(),
                str_replace('AcmeSyliusExample', $plainPluginName, $file->getRealPath()),
                true
            );
        }
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

}
