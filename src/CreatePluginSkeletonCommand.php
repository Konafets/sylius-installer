<?php namespace Konafets\Installer\Console;

use Exception;
use Nadar\PhpComposerReader\ComposerReader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
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
                new InputOption('package-name', 'pn', InputOption::VALUE_REQUIRED, 'Name of the package'),
                new InputOption('description', 'd', InputOption::VALUE_REQUIRED, 'The description of your plugin'),
                new InputOption('author', 'a', InputOption::VALUE_REQUIRED, 'Author name of the plugin'),
                new InputOption('license', 'l', InputOption::VALUE_REQUIRED, 'License of package'),
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
        /** @var QuestionHelper $questionHelper */
        $dialog = $this->getHelper('question');

        $directory = $this->makePluginFolderName();

        if (! $input->getOption('force')) {
            $this->verifyPluginDoesntExist($directory);
        }

        $composer = $this->findComposer();

        $this->installPluginSkeleton($input, $output, $composer, $directory);

        $this->customizeSkeleton($input, $output, $directory);

        $this->composerDumpAutoload($input, $output, $composer, $directory);

        $question = new ConfirmationQuestion('Install and build assets? ', false);

        if ($dialog->ask($input, $output, $question)) {
            $this->buildingAssets($input, $output, $composer, $directory);
        }

        $question = new ConfirmationQuestion('Setup and create a SQLite database? ', false);
        if ($dialog->ask($input, $output, $question)) {
            $this->createSQLiteDatabase($input, $output, $directory);
        }

        $question = new ConfirmationQuestion('Load Fixtures into database? ', false);
        if ($dialog->ask($input, $output, $question)) {
            $this->loadFixtures($input, $output, $directory);
        }

        $question = new ConfirmationQuestion('Start the internal server? ', false);
        if ($dialog->ask($input, $output, $question)) {
            $this->startServer($input, $output, $directory);
        }

        $output->writeln('<comment>Your plugin is ready in folder ' . $directory . '/</comment>');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<comment>We will guide you trough the installation.</comment>');
        $output->writeln('');

        /** @var QuestionHelper $questionHelper */
        $dialog = $this->getHelper('question');

        if (!$packageName = $input->getOption('package-name')) {
            $question = new Question('Package name (<vendor>/<name>): ');
            $question->setValidator(function ($answer) {
                $this->validatePluginName($answer);

                return $answer;
            });
            $question->setMaxAttempts(2);
            $packageName = $dialog->ask($input, $output, $question);
        } else {
            $this->validatePluginName($packageName);
        }

        $input->setOption('package-name', $packageName);

        list($this->vendor, $this->name) = $this->extractVendorAndPluginNameFromPackageName($packageName);

        $self = $this;
        if ( ! $input->getOption('author')) {
            $question = new Question('Author (Jane Doe <jane.doe@sylius.com>), n to skip: ');
            $question->setValidator(function ($answer) use ($self) {
                if ($answer === 'n' || $answer === 'no') {
                    return;
                }

                $author = $self->parseAuthorString($answer);

                return sprintf('%s <%s>', $author['name'], $author['email']);
            });

            $question->setMaxAttempts(2);
            $author = $dialog->ask($input, $output, $question);
            $input->setOption('author', $author);
        }

        if (! $input->getOption('description')) {
            $question = new Question('Description: ');
            $description = $dialog->ask($input, $output, $question);
            $input->setOption('description', $description);
        }

        if (! $license = $input->getOption('license')) {
            $question = new Question('License [<comment>'.$license.'</comment>]: ');
            $license = $dialog->ask($input, $output, $question);
            $input->setOption('license', $license);
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
     * @param OutputInterface $output
     * @throws Exception
     */
    private function customizeSkeleton(InputInterface $input, OutputInterface $output, string $directory) : void
    {
        $output->writeln('<info>Configure your plugin...</info>');

        $this->customizeComposerFile($input, $directory);
        $this->changeDummyPluginNameToCustomPluginName($directory);
        $this->renameClasses($directory);
    }

    /**
     * @return string
     */
    private function makePluginFolderName() : string
    {
        return Strings::toPascalCase($this->vendor) . Strings::toPascalCase($this->name);
    }

    /**
     * @param string $packageName
     * @return array
     */
    private function extractVendorAndPluginNameFromPackageName(string $packageName) : array
    {
        return explode('/', $packageName);
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

        $composerFile['name'] = $input->getOption('package-name');
        $composerFile['authors'] = $this->formatAuthors($input->getOption('author'));
        $composerFile['description'] = $input->getOption('description');
        $composerFile['license'] = $input->getOption('license');
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
     * @param OutputInterface $output
     */
    protected function printWelcomeMessage(OutputInterface $output) : void
    {
        $output->writeln('<bg=green>                                        </>');
        $output->writeln('<bg=green;fg=black>  Welcome to Sylius Plugin Kickstarter  </>');
        $output->writeln('<bg=green>                                        </>');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param $composer
     * @param $directory
     */
    protected function installPluginSkeleton(InputInterface $input, OutputInterface $output, $composer, $directory) : void
    {
        $output->writeln('<info>Installing Plugin Skeleton...</info>');

        $commands = [
            $composer . ' create-project sylius/plugin-skeleton ' . $directory,
        ];

        $this->executeProcess($input, $output, $commands, null);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param $composer
     * @param $directory
     */
    protected function buildingAssets(InputInterface $input, OutputInterface $output, $composer, $directory) : void
    {
        $output->writeln('<info>Building assets...</info>');

        $commands = [
            '(cd tests/Application && yarn install)',
            '(cd tests/Application && yarn build)',
            '(cd tests/Application && bin/console assets:install public -e test)',
        ];

        $this->executeProcess($input, $output, $commands, $directory);
    }

    /**
     * @param $packageName
     */
    protected function validatePluginName($packageName) : void
    {
        if ( ! preg_match('{^[a-z0-9_.-]+/[a-z0-9_.-]+$}', $packageName)) {
            throw new \InvalidArgumentException('The package name ' . $packageName . ' is invalid, it should be lowercase and have a vendor name, a forward slash, and a package name, matching: [a-z0-9_.-]+/[a-z0-9_.-]+');
        }

        list($vendor, $name) = $this->extractVendorAndPluginNameFromPackageName($packageName);

        if ( ! Strings::startsWith($name, 'sylius')) {
            throw new \InvalidArgumentException('The plugin name ' . $name . ' is invalid, it should be start with "sylius"');
        }

        if ( ! Strings::endsWith($name, 'plugin')) {
            throw new \InvalidArgumentException('The plugin name ' . $name . ' is invalid, it should be end with "plugin"');
        }
    }

    /**
     * @param string $author
     * @return array
     */
    protected function formatAuthors(string $author) : array
    {
        return [$this->parseAuthorString($author)];
    }

    /**
     * @param string $author
     * @return array
     */
    private function parseAuthorString(string $author) : array
    {
        if (preg_match('/^(?P<name>[- .,\p{L}\p{N}\p{Mn}\'’"()]+) <(?P<email>.+?)>$/u', $author, $match)) {
            if ($this->isValidEmail($match['email'])) {
                return array(
                    'name' => trim($match['name']),
                    'email' => $match['email'],
                );
            }
        }

        throw new \InvalidArgumentException(
            'Invalid author string.  Must be in the format: '.
            'John Smith <john@example.com>'
        );
    }

    /**
     * @param string $email
     * @return bool
     */
    protected function isValidEmail(string $email) : bool
    {
        // assume it's valid if we can't validate it
        if (!function_exists('filter_var')) {
            return true;
        }
        // php <5.3.3 has a very broken email validator, so bypass checks
        if (PHP_VERSION_ID < 50303) {
            return true;
        }

        return false !== filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param string $composer
     * @param string $directory
     */
    protected function composerDumpAutoload(InputInterface $input, OutputInterface $output, string $composer, string $directory) : void
    {
        $command = $composer . ' dump-autoload --optimize';
        $this->executeProcess($input, $output, $command, $directory);
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
     * @param OutputInterface $output
     * @param $directory
     */
    protected function createSQLiteDatabase(InputInterface $input, OutputInterface $output, $directory) : void
    {
        $applicationDirectory = $directory . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'Application' . DIRECTORY_SEPARATOR;
        $this->filesystem->copy($applicationDirectory . '.env.dist', $applicationDirectory . '.env');

        $finder = new Finder();
        $finder->files()->in($applicationDirectory)->ignoreDotFiles(false)->name('.env');

        foreach ($finder as $file) {
            self::writeFile(['DATABASE_URL=mysql://root@127.0.0.1/sylius_%kernel.environment%?serverVersion=5.5'],
                ["#DATABASE_URL=mysql://root@127.0.0.1/sylius_%kernel.environment%?serverVersion=5.5\nDATABASE_URL=sqlite:///%kernel.project_dir%/var/data.db"],
                $file);
        }

        $commands = [
            '(cd tests/Application && bin/console doctrine:database:create -e test)', '(cd tests/Application && bin/console doctrine:schema:create -e test)'
        ];

        $this->executeProcess($input, $output, $commands, $directory);
    }

    private function loadFixtures(InputInterface $input, OutputInterface $output, string $directory)
    {
        $commands = [
            '(cd tests/Application && bin/console sylius:fixtures:load -e test)'
        ];

        $this->executeProcess($input, $output, $commands, $directory);
    }

    private function startServer(InputInterface $input, OutputInterface $output, string $directory)
    {
        $commands = [
            '(cd tests/Application && bin/console server:run -d public -e test)'
        ];

        $this->executeProcess($input, $output, $commands, $directory);
    }
}
