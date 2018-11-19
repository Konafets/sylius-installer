<?php
/**
 * Created by PhpStorm.
 * User: sok
 * Date: 17.11.18
 * Time: 21:52
 */

namespace Konafets\Tests\Unit;

use Konafets\Installer\Console\Commands\CreatePluginSkeletonCommand;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CreatePluginSkeletonCommandTest extends TestCase
{

    /** @var CreatePluginSkeletonCommand */
    protected $command;

    protected function setUp()
    {
        $this->command = new CreatePluginSkeletonCommand();

        parent::setUp();
    }

    /**
     * @test
     * @throws \ReflectionException
     */
    public function extractVendorAndPluginNameFromPackageName()
    {
        $result = $this->invokeMethod(
            $this->command,
            'extractVendorAndPluginNameFromPackageName',
            ['iron-man/sylius-product-on-demand-plugin']
        );

        $this->assertSame(['iron-man', 'sylius-product-on-demand-plugin'], $result);
    }

    /**
     * @test
     * @throws \ReflectionException
     */
    public function makePluginFolderName()
    {
        $reflection = new \ReflectionClass(get_class($this->command));
        $reflection->vendor = 'iron-man';
        $reflection->name = 'sylius-product-on-demand-plugin';

        $result = $this->invokeMethod($this->command, 'makeProjectFolderName', []);

        $this->assertSame('IronManSyliusProductOnDemandPlugin', $result);
    }

    /**
     * @test
     * @throws \ReflectionException
     */
    public function getSrcFolder()
    {
        $result = $this->invokeMethod(
            $this->command,
            'getSrcFolder',
            ['IronManSyliusProductOnDemandPlugin']
        );

        $this->assertSame('IronManSyliusProductOnDemandPlugin/src/', $result);
    }

    /**
     * @return array
     */
    public function emailAddressProvider()
    {
        return [
            ['info@arroba-it.de', 'True'],
            ['infoarroba-it.de', 'False']
        ];
    }

    /**
     * @test
     * @throws \ReflectionException
     * @dataProvider emailAddressProvider
     */
    public function isValidEmail($email, $value)
    {
        $assertMethod = 'assert' . $value;
        $this->$assertMethod($this->invokeMethod($this->command, 'isValidEmail', [$email]));
    }

    /**
     * @param $object
     * @param $methodName
     * @param array $params
     * @return mixed
     * @throws \ReflectionException
     */
    public function invokeMethod($object, $methodName, array $params = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $params);
    }

    /**
     * @test
     * @throws \ReflectionException
     */
    public function findComposerPhar()
    {
        $result = $this->invokeMethod($this->command, 'findComposer', []);
        $this->assertSame('composer', $result);
    }

    /**
     * @test
     * @throws \ReflectionException
     */
    public function it_throws_no_exception_when_folder_does_not_exist()
    {
        $this->assertNull(
            $this->invokeMethod(
                $this->command,
                'verifyProjectFolderDoesNotExist',
                ['root/foo']
            )
        );
    }

    /**
     * @test
     * @throws \ReflectionException
     * @expectedException RuntimeException
     * @expectedExceptionMessage Directory already exists
     */
    public function it_throws_exception_when_folder_does_exist()
    {
        $structure = [
            'foo' => []
        ];

        $vfs = vfsStream::setup('root', null, $structure);

        $this->assertNull(
            $this->invokeMethod(
                $this->command,
                'verifyProjectFolderDoesNotExist',
                [$vfs->url() . DIRECTORY_SEPARATOR . 'foo']
            )
        );
    }
}
