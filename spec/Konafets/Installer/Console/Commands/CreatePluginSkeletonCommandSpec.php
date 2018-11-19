<?php

namespace spec\Konafets\Installer\Console\Commands;

use Konafets\Installer\Console\Commands\BaseCommand;
use Konafets\Installer\Console\Commands\CreatePluginSkeletonCommand;
use PhpSpec\ObjectBehavior;
use Symfony\Component\Console\Input\InputOption;

class CreatePluginSkeletonCommandSpec extends ObjectBehavior
{
    function it_is_initializable() : void
    {
        $this->shouldHaveType(CreatePluginSkeletonCommand::class);
    }

    function it_extends_base_command()
    {
        $this->shouldHaveType(BaseCommand::class);
    }

    function it_has_a_name()
    {
        $this->getName()->shouldReturn('new:plugin');
    }

    function it_has_a_description()
    {
        $this->getDescription()->shouldReturn('Installs and customize the plugin skeleton');
    }

    function it_has_package_name_option()
    {
        $this->getDefinition()->getOption('package-name')->shouldReturnAnInstanceOf(InputOption::class);
    }

    function it_has_description_option()
    {
        $this->getDefinition()->getOption('description')->shouldReturnAnInstanceOf(InputOption::class);
    }

    function it_has_author_option()
    {
        $this->getDefinition()->getOption('author')->shouldReturnAnInstanceOf(InputOption::class);
    }

    function it_has_license_option()
    {
        $this->getDefinition()->getOption('license')->shouldReturnAnInstanceOf(InputOption::class);
    }

    function it_has_force_option()
    {
        $this->getDefinition()->getOption('force')->shouldReturnAnInstanceOf(InputOption::class);
    }
}
