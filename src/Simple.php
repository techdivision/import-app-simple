<?php

/**
 * TechDivision\Import\App\Simple
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @author    Tim Wagner <t.wagner@techdivision.com>
 * @copyright 2016 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/techdivision/import-app-simple
 * @link      http://www.techdivision.com
 */

namespace TechDivision\Import\App;

use Psr\Log\LogLevel;
use League\Event\EmitterInterface;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\DependencyInjection\TaggedContainerInterface;
use TechDivision\Import\Utils\LoggerKeys;
use TechDivision\Import\Utils\EventNames;
use TechDivision\Import\ApplicationInterface;
use TechDivision\Import\ConfigurationInterface;
use TechDivision\Import\Plugins\PluginFactoryInterface;
use TechDivision\Import\Exceptions\LineNotFoundException;
use TechDivision\Import\Exceptions\FileNotFoundException;
use TechDivision\Import\Exceptions\ImportAlreadyRunningException;
use TechDivision\Import\Services\ImportProcessorInterface;
use TechDivision\Import\Services\RegistryProcessorInterface;

/**
 * The M2IF - Simple Application implementation.
 *
 * This is a example application implementation that should give developers an impression
 * on how the M2IF could be used to implement their own Magento 2 importer.
 *
 * @author    Tim Wagner <t.wagner@techdivision.com>
 * @copyright 2016 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/techdivision/import-app-simple
 * @link      http://www.techdivision.com
 */
class Simple implements ApplicationInterface
{

    /**
     * The default style to write messages to the symfony console.
     *
     * @var string
     */
    const DEFAULT_STYLE = 'info';

    /**
     * The TechDivision company name as ANSI art.
     *
     * @var string
     */
    protected $ansiArt = ' _______        _     _____  _       _     _
|__   __|      | |   |  __ \(_)     (_)   (_)
   | | ___  ___| |__ | |  | |___   ___ ___ _  ___  _ __
   | |/ _ \/ __| \'_ \| |  | | \ \ / / / __| |/ _ \| \'_ \
   | |  __/ (__| | | | |__| | |\ V /| \__ \ | (_) | | | |
   |_|\___|\___|_| |_|_____/|_| \_/ |_|___/_|\___/|_| |_|
';

    /**
     * The log level => console style mapping.
     *
     * @var array
     */
    protected $logLevelStyleMapping = array(
        LogLevel::INFO      => 'info',
        LogLevel::DEBUG     => 'comment',
        LogLevel::ERROR     => 'error',
        LogLevel::ALERT     => 'error',
        LogLevel::CRITICAL  => 'error',
        LogLevel::EMERGENCY => 'error',
        LogLevel::WARNING   => 'error',
        LogLevel::NOTICE    => 'info'
    );

    /**
     * The PID for the running processes.
     *
     * @var array
     */
    protected $pid;

    /**
     * The actions unique serial.
     *
     * @var string
     */
    protected $serial;

    /**
     * The array with the system logger instances.
     *
     * @var \Doctrine\Common\Collections\Collection
     */
    protected $systemLoggers;

    /**
     * The RegistryProcessor instance to handle running threads.
     *
     * @var \TechDivision\Import\Services\RegistryProcessorInterface
     */
    protected $registryProcessor;

    /**
     * The processor to read/write the necessary import data.
     *
     * @var \TechDivision\Import\Services\ImportProcessorInterface
     */
    protected $importProcessor;

    /**
     * The DI container builder instance.
     *
     * @var \Symfony\Component\DependencyInjection\TaggedContainerInterface
     */
    protected $container;

    /**
     * The system configuration.
     *
     * @var \TechDivision\Import\ConfigurationInterface
     */
    protected $configuration;

    /**
     * The output stream to write console information to.
     *
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * The plugins to be processed.
     *
     * @var array
     */
    protected $plugins = array();

    /**
     * The flag that stop's processing the operation.
     *
     * @var boolean
     */
    protected $stopped = false;

    /**
     * The filehandle for the PID file.
     *
     * @var resource
     */
    protected $fh;

    /**
     * The plugin factory instance.
     *
     * @var \TechDivision\Import\Plugins\PluginFactoryInterface
     */
    protected $pluginFactory;

    /**
     * The event emitter instance.
     *
     * @var \League\Event\EmitterInterface
     */
    protected $emitter;

    /**
     * The constructor to initialize the instance.
     *
     * @param \Symfony\Component\DependencyInjection\TaggedContainerInterface $container         The DI container instance
     * @param \TechDivision\Import\Services\RegistryProcessorInterface        $registryProcessor The registry processor instance
     * @param \TechDivision\Import\Services\ImportProcessorInterface          $importProcessor   The import processor instance
     * @param \TechDivision\Import\ConfigurationInterface                     $configuration     The system configuration
     * @param \TechDivision\Import\Plugins\PluginFactoryInterface             $pluginFactory     The plugin factory instance
     * @param \Symfony\Component\Console\Output\OutputInterface               $output            The output instance
     * @param \Doctrine\Common\Collections\Collection                         $systemLoggers     The array with the system logger instances
     * @param \League\Event\EmitterInterface                                  $emitter           The event emitter instance
     */
    public function __construct(
        TaggedContainerInterface $container,
        RegistryProcessorInterface $registryProcessor,
        ImportProcessorInterface $importProcessor,
        ConfigurationInterface $configuration,
        PluginFactoryInterface $pluginFactory,
        OutputInterface $output,
        Collection $systemLoggers,
        EmitterInterface $emitter
    ) {

        // register the shutdown function
        register_shutdown_function(array($this, 'shutdown'));

        // initialize the instance with the passed values
        $this->setOutput($output);
        $this->setEmitter($emitter);
        $this->setContainer($container);
        $this->setConfiguration($configuration);
        $this->setSystemLoggers($systemLoggers);
        $this->setPluginFactory($pluginFactory);
        $this->setImportProcessor($importProcessor);
        $this->setRegistryProcessor($registryProcessor);
    }

    /**
     * Set's the event emitter instance.
     *
     * @param \League\Event\EmitterInterface $emitter The event emitter instance
     *
     * @return void
     */
    public function setEmitter(EmitterInterface $emitter)
    {
        $this->emitter = $emitter;
    }

    /**
     * Return's the event emitter instance.
     *
     * @return \League\Event\EmitterInterface The event emitter instance
     */
    public function getEmitter()
    {
        return $this->emitter;
    }

    /**
     * Set's the container instance.
     *
     * @param \Symfony\Component\DependencyInjection\TaggedContainerInterface $container The container instance
     *
     * @return void
     */
    public function setContainer(TaggedContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Return's the container instance.
     *
     * @return \Symfony\Component\DependencyInjection\TaggedContainerInterface The container instance
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Set's the output stream to write console information to.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output The output stream
     *
     * @return void
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * Return's the output stream to write console information to.
     *
     * @return \Symfony\Component\Console\Output\OutputInterface The output stream
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Set's the system configuration.
     *
     * @param \TechDivision\Import\ConfigurationInterface $configuration The system configuration
     *
     * @return void
     */
    public function setConfiguration(ConfigurationInterface $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Return's the system configuration.
     *
     * @return \TechDivision\Import\ConfigurationInterface The system configuration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * Set's the RegistryProcessor instance to handle the running threads.
     *
     * @param \TechDivision\Import\Services\RegistryProcessor $registryProcessor The registry processor instance
     *
     * @return void
     */
    public function setRegistryProcessor(RegistryProcessorInterface $registryProcessor)
    {
        $this->registryProcessor = $registryProcessor;
    }

    /**
     * Return's the RegistryProcessor instance to handle the running threads.
     *
     * @return \TechDivision\Import\Services\RegistryProcessor The registry processor instance
     */
    public function getRegistryProcessor()
    {
        return $this->registryProcessor;
    }

    /**
     * Set's the import processor instance.
     *
     * @param \TechDivision\Import\Services\ImportProcessorInterface $importProcessor The import processor instance
     *
     * @return void
     */
    public function setImportProcessor(ImportProcessorInterface $importProcessor)
    {
        $this->importProcessor = $importProcessor;
    }

    /**
     * Return's the import processor instance.
     *
     * @return \TechDivision\Import\Services\ImportProcessorInterface The import processor instance
     */
    public function getImportProcessor()
    {
        return $this->importProcessor;
    }

    /**
     * The array with the system loggers.
     *
     * @param \Doctrine\Common\Collections\Collection $systemLoggers The system logger instances
     *
     * @return void
     */
    public function setSystemLoggers(Collection $systemLoggers)
    {
        $this->systemLoggers = $systemLoggers;
    }

    /**
     * Set's the plugin factory instance.
     *
     * @param \TechDivision\Import\Plugins\PluginFactoryInterface $pluginFactory The plugin factory instance
     *
     * @return void
     */
    public function setPluginFactory(PluginFactoryInterface $pluginFactory)
    {
        $this->pluginFactory = $pluginFactory;
    }

    /**
     * Return's the plugin factory instance.
     *
     * @return \TechDivision\Import\Plugins\PluginFactoryInterface The plugin factory instance
     */
    public function getPluginFactory()
    {
        return $this->pluginFactory;
    }

    /**
     * Return's the logger with the passed name, by default the system logger.
     *
     * @param string $name The name of the requested system logger
     *
     * @return \Psr\Log\LoggerInterface The logger instance
     * @throws \Exception Is thrown, if the requested logger is NOT available
     */
    public function getSystemLogger($name = LoggerKeys::SYSTEM)
    {

        // query whether or not, the requested logger is available
        if (isset($this->systemLoggers[$name])) {
            return $this->systemLoggers[$name];
        }

        // throw an exception if the requested logger is NOT available
        throw new \Exception(
            sprintf(
                'The requested logger \'%s\' is not available',
                $name
            )
        );
    }

    /**
     * Query whether or not the system logger with the passed name is available.
     *
     * @param string $name The name of the requested system logger
     *
     * @return boolean TRUE if the logger with the passed name exists, else FALSE
     */
    public function hasSystemLogger($name = LoggerKeys::SYSTEM)
    {
        return isset($this->systemLoggers[$name]);
    }

    /**
     * Return's the array with the system logger instances.
     *
     * @return \Doctrine\Common\Collections\Collection The logger instance
     */
    public function getSystemLoggers()
    {
        return $this->systemLoggers;
    }

    /**
     * Return's the unique serial for this import process.
     *
     * @return string The unique serial
     */
    public function getSerial()
    {
        return $this->serial;
    }

    /**
     * The shutdown handler to catch fatal errors.
     *
     * This method is need to make sure, that an existing PID file will be removed
     * if a fatal error has been triggered.
     *
     * @return void
     */
    public function shutdown()
    {

        // check if there was a fatal error caused shutdown
        if ($lastError = error_get_last()) {
            // initialize error type and message
            $type = 0;
            $message = '';
            // extract the last error values
            extract($lastError);
            // query whether we've a fatal/user error
            if ($type === E_ERROR || $type === E_USER_ERROR) {
                // clean-up the PID file
                $this->unlock();
                // log the fatal error message
                $this->log($message, LogLevel::ERROR);
            }
        }
    }

    /**
     * Persist the UUID of the actual import process to the PID file.
     *
     * @return void
     * @throws \Exception Is thrown, if the PID can not be locked or the PID can not be added
     * @throws \TechDivision\Import\Exceptions\ImportAlreadyRunningException Is thrown, if a import process is already running
     */
    public function lock()
    {

        // query whether or not, the PID has already been set
        if ($this->pid === $this->getSerial()) {
            return;
        }

        // if not, initialize the PID
        $this->pid = $this->getSerial();

        // open the PID file
        $this->fh = fopen($filename = $this->getPidFilename(), 'a+');

        // try to lock the PID file exclusive
        if (!flock($this->fh, LOCK_EX|LOCK_NB)) {
            throw new ImportAlreadyRunningException(sprintf('PID file %s is already in use', $filename));
        }

        // append the PID to the PID file
        if (fwrite($this->fh, $this->pid . PHP_EOL) === false) {
            throw new \Exception(sprintf('Can\'t write PID %s to PID file %s', $this->pid, $filename));
        }
    }

    /**
     * Remove's the UUID of the actual import process from the PID file.
     *
     * @return void
     * @throws \Exception Is thrown, if the PID can not be removed
     */
    public function unlock()
    {
        try {
            // remove the PID from the PID file if set
            if ($this->pid === $this->getSerial() && is_resource($this->fh)) {
                // remove the PID from the file
                $this->removeLineFromFile($this->pid, $this->fh);

                // finally unlock/close the PID file
                flock($this->fh, LOCK_UN);
                fclose($this->fh);

                // if the PID file is empty, delete the file
                if (filesize($filename = $this->getPidFilename()) === 0) {
                    unlink($filename);
                }
            }
        } catch (FileNotFoundException $fnfe) {
            $this->getSystemLogger()->notice(sprintf('PID file %s doesn\'t exist', $this->getPidFilename()));
        } catch (LineNotFoundException $lnfe) {
            $this->getSystemLogger()->notice(sprintf('PID %s is can not be found in PID file %s', $this->pid, $this->getPidFilename()));
        } catch (\Exception $e) {
            throw new \Exception(sprintf('Can\'t remove PID %s from PID file %s', $this->pid, $this->getPidFilename()), null, $e);
        }
    }

    /**
     * Remove's the passed line from the file with the passed name.
     *
     * @param string   $line The line to be removed
     * @param resource $fh   The file handle of the file the line has to be removed
     *
     * @return void
     * @throws \Exception Is thrown, if the file doesn't exists, the line is not found or can not be removed
     */
    public function removeLineFromFile($line, $fh)
    {

        // initialize the array for the PIDs found in the PID file
        $lines = array();

        // initialize the flag if the line has been found
        $found = false;

        // rewind the file pointer
        rewind($fh);

        // read the lines with the PIDs from the PID file
        while (($buffer = fgets($fh, 4096)) !== false) {
            // remove the new line
            $buffer = trim($buffer);
            // if the line is the one to be removed, ignore the line
            if ($line === $buffer) {
                $found = true;
                continue;
            }

            // add the found PID to the array
            $lines[] = $buffer;
        }

        // query whether or not, we found the line
        if (!$found) {
            throw new LineNotFoundException(sprintf('Line %s can not be found', $line));
        }

        // empty the file and rewind the file pointer
        ftruncate($fh, 0);
        rewind($fh);

        // append the existing lines to the file
        foreach ($lines as $ln) {
            if (fwrite($fh, $ln . PHP_EOL) === false) {
                throw new \Exception(sprintf('Can\'t write %s to file', $ln));
            }
        }
    }

    /**
     * Process the given operation.
     *
     * @param string $serial The unique serial of the actual import process
     *
     * @return void
     * @throws \Exception Is thrown if the operation can't be finished successfully
     */
    public function process($serial)
    {

        try {
            // track the start time
            $startTime = microtime(true);

            // set the serial for this import process
            $this->serial = $serial;

            // invoke the event that has to be fired before the application start's the transaction
            // (if single transaction mode has been activated)
            $this->getEmitter()->emit(EventNames::APP_PROCESS_TRANSACTION_START, $this);

            // start the transaction, if single transaction mode has been configured
            if ($this->getConfiguration()->isSingleTransaction()) {
                $this->getImportProcessor()->getConnection()->beginTransaction();
            }

            // prepare the global data for the import process
            $this->setUp();

            // process the plugins defined in the configuration
            /** @var \TechDivision\Import\Configuration\PluginConfigurationInterface $pluginConfiguration */
            foreach ($this->getConfiguration()->getPlugins() as $pluginConfiguration) {
                // query whether or not the operation has been stopped
                if ($this->isStopped()) {
                    break;
                }
                // process the plugin if not
                $this->pluginFactory->createPlugin($pluginConfiguration)->process();
            }

            // tear down the  instance
            $this->tearDown();

            // commit the transaction, if single transation mode has been configured
            if ($this->getConfiguration()->isSingleTransaction()) {
                $this->getImportProcessor()->getConnection()->commit();
            }

            // track the time needed for the import in seconds
            $endTime = microtime(true) - $startTime;

            // log a message that import has been finished
            $this->log(
                sprintf(
                    'Successfully finished import with serial %s in %f s',
                    $this->getSerial(),
                    $endTime
                ),
                LogLevel::INFO
            );

            // invoke the event that has to be fired before the application has the transaction
            // committed successfully (if single transaction mode has been activated)
            $this->getEmitter()->emit(EventNames::APP_PROCESS_TRANSACTION_SUCCESS, $this);
        } catch (ImportAlreadyRunningException $iare) {
            // tear down
            $this->tearDown();

            // rollback the transaction, if single transaction mode has been configured
            if ($this->getConfiguration()->isSingleTransaction()) {
                $this->getImportProcessor()->getConnection()->rollBack();
            }

            // invoke the event that has to be fired after the application rollbacked the
            // transaction (if single transaction mode has been activated)
            $this->getEmitter()->emit(EventNames::APP_PROCESS_TRANSACTION_FAILURE, $this, $iare);

            // finally, if a PID has been set (because CSV files has been found),
            // remove it from the PID file to unlock the importer
            $this->unlock();

            // track the time needed for the import in seconds
            $endTime = microtime(true) - $startTime;

            // log a warning, because another import process is already running
            $this->getSystemLogger()->warning($iare->__toString());

            // log a message that import has been finished
            $this->getSystemLogger()->info(
                sprintf(
                    'Can\'t finish import with serial because another import process is running %s in %f s',
                    $this->getSerial(),
                    $endTime
                )
            );

            // re-throw the exception
            throw $iare;
        } catch (\Exception $e) {
            // tear down
            $this->tearDown();

            // rollback the transaction, if single transaction mode has been configured
            if ($this->getConfiguration()->isSingleTransaction()) {
                $this->getImportProcessor()->getConnection()->rollBack();
            }

            // invoke the event that has to be fired after the application rollbacked the
            // transaction (if single transaction mode has been activated)
            $this->getEmitter()->emit(EventNames::APP_PROCESS_TRANSACTION_FAILURE, $this, $e);

            // finally, if a PID has been set (because CSV files has been found),
            // remove it from the PID file to unlock the importer
            $this->unlock();

            // track the time needed for the import in seconds
            $endTime = microtime(true) - $startTime;

            // log a message that the file import failed
            foreach ($this->systemLoggers as $systemLogger) {
                $systemLogger->error($e->__toString());
            }

            // log a message that import has been finished
            $this->getSystemLogger()->info(
                sprintf(
                    'Can\'t finish import with serial %s in %f s',
                    $this->getSerial(),
                    $endTime
                )
            );

            // re-throw the exception
            throw $e;
        }
    }

    /**
     * Stop processing the operation.
     *
     * @param string $reason The reason why the operation has been stopped
     *
     * @return void
     */
    public function stop($reason)
    {

        // log a message that the operation has been stopped
        $this->log($reason, LogLevel::INFO);

        // stop processing the plugins by setting the flag to TRUE
        $this->stopped = true;
    }

    /**
     * Return's TRUE if the operation has been stopped, else FALSE.
     *
     * @return boolean TRUE if the process has been stopped, else FALSE
     */
    public function isStopped()
    {
        return $this->stopped;
    }

    /**
     * Gets a service.
     *
     * @param string $id The service identifier
     *
     * @return object The associated service
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException When a circular reference is detected
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException          When the service is not defined
     */
    public function get($id)
    {
        return $this->getContainer()->get($id);
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
     * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return bool
     */
    public function has($id)
    {
        return $this->getContainer()->has($id);
    }

    /**
     * Lifecycle callback that will be inovked before the
     * import process has been started.
     *
     * @return void
     */
    protected function setUp()
    {
        $this->getEmitter()->emit(EventNames::APP_SET_UP, $this);
    }

    /**
     * Lifecycle callback that will be inovked after the
     * import process has been finished.
     *
     * @return void
     */
    protected function tearDown()
    {
        $this->getEmitter()->emit(EventNames::APP_TEAR_DOWN, $this);
    }

    /**
     * Simple method that writes the passed method the the console and the
     * system logger, if configured and a log level has been passed.
     *
     * @param string $msg      The message to log
     * @param string $logLevel The log level to use
     *
     * @return void
     */
    public function log($msg, $logLevel = null)
    {

        // initialize the formatter helper
        $helper = new FormatterHelper();

        // map the log level to the console style
        $style = $this->mapLogLevelToStyle($logLevel);

        // format the message, according to the passed log level and write it to the console
        $this->getOutput()->writeln($logLevel ? $helper->formatBlock($msg, $style) : $msg);

        // log the message if a log level has been passed
        if ($logLevel && $systemLogger = $this->getSystemLogger()) {
            $systemLogger->log($logLevel, $msg);
        }
    }

    /**
     * Map's the passed log level to a valid symfony console style.
     *
     * @param string $logLevel The log level to map
     *
     * @return string The apropriate symfony console style
     */
    protected function mapLogLevelToStyle($logLevel)
    {

        // query whether or not the log level is mapped
        if (isset($this->logLevelStyleMapping[$logLevel])) {
            return $this->logLevelStyleMapping[$logLevel];
        }

        // return the default style => info
        return Simple::DEFAULT_STYLE;
    }

    /**
     * Return's the PID filename to use.
     *
     * @return string The PID filename
     */
    protected function getPidFilename()
    {
        return $this->getConfiguration()->getPidFilename();
    }
}
