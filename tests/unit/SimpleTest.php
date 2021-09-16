<?php

/**
 * TechDivision\Import\App\SimpleTest
 *
 * PHP version 7
 *
 * @author    Tim Wagner <t.wagner@techdivision.com>
 * @copyright 2016 TechDivision GmbH <info@techdivision.com>
 * @license   https://opensource.org/licenses/MIT
 * @link      https://github.com/techdivision/import-app-simple
 * @link      http://www.techdivision.com
 */

namespace TechDivision\Import\App;

use PHPUnit\Framework\TestCase;
use Doctrine\Common\Collections\ArrayCollection;
use TechDivision\Import\Handlers\GenericFileHandlerInterface;
use TechDivision\Import\Handlers\PidFileHandlerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TechDivision\Import\Configuration\ConfigurationInterface;
use TechDivision\Import\Services\ImportProcessorInterface;
use TechDivision\Import\Services\RegistryProcessorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use League\Event\EmitterInterface;

/**
 * Test class for the simple, single-threaded, importer implementation.
 *
 * @author    Tim Wagner <t.wagner@techdivision.com>
 * @copyright 2016 TechDivision GmbH <info@techdivision.com>
 * @license   https://opensource.org/licenses/MIT
 * @link      https://github.com/techdivision/import-app-simple
 * @link      http://www.techdivision.com
 */
class SimpleTest extends TestCase
{

    /**
     * The instance to be tested.
     *
     * @var \TechDivision\Import\App\Simple
     */
    protected $instance;

    /**
     * Initializes the instance we want to test.
     *
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     *
     * @return void
     * @see \PHPUnit\Framework\TestCase::setUp()
     */
    protected function setUp()
    {

        // create a mock container
        $mockContainer = $this->getMockBuilder(ContainerInterface::class)->getMock();

        // create a mock registry processor
        $mockRegistryProcessor = $this->getMockBuilder(RegistryProcessorInterface::class)->getMock();

        // create a mock import processor
        $mockImportProcessor = $this->getMockBuilder(ImportProcessorInterface::class)->getMock();

        // create a mock configuration
        $mockConfiguration = $this->getMockBuilder(ConfigurationInterface::class)->getMock();

        // create a mock output
        $mockOutput = $this->getMockBuilder(OutputInterface::class)->getMock();

        // mock the event emitter
        $mockGenericFileHandler = $this->getMockBuilder(GenericFileHandlerInterface::class)->getMock();

        // mock the event emitter
        $mockPidFileHandler = $this->getMockBuilder(PidFileHandlerInterface::class)->getMock();

        // mock the event emitter
        $mockEmitter = $this->getMockBuilder(EmitterInterface::class)->getMock();

        // create the subject to be tested
        $this->instance = new Simple(
            $mockContainer,
            $mockRegistryProcessor,
            $mockImportProcessor,
            $mockConfiguration,
            $mockOutput,
            new ArrayCollection(),
            $mockEmitter,
            $mockGenericFileHandler,
            $mockPidFileHandler,
            new ArrayCollection()
        );
    }

    /**
     * Test's the getOutput() method.
     *
     * @return void
     */
    public function testGetOutput()
    {
        $this->assertInstanceOf('Symfony\Component\Console\Output\OutputInterface', $this->instance->getOutput());
    }
}
