<?php

/**
 * TechDivision\Import\App\Simple
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

use Psr\Log\LogLevel;
use League\Event\EmitterInterface;
use Psr\Container\ContainerInterface;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\FormatterHelper;
use TechDivision\Import\Exceptions\MissingFileException;
use TechDivision\Import\Exceptions\OkFileNotEmptyException;
use TechDivision\Import\Utils\LoggerKeys;
use TechDivision\Import\Utils\EventNames;
use TechDivision\Import\ApplicationInterface;
use TechDivision\Import\App\Utils\DependencyInjectionKeys;
use TechDivision\Import\Configuration\ConfigurationInterface;
use TechDivision\Import\Exceptions\ImportAlreadyRunningException;
use TechDivision\Import\Services\ImportProcessorInterface;
use TechDivision\Import\Services\RegistryProcessorInterface;
use TechDivision\Import\Exceptions\ApplicationStoppedException;
use TechDivision\Import\Exceptions\ApplicationFinishedException;
use TechDivision\Import\Handlers\PidFileHandlerInterface;
use TechDivision\Import\Handlers\GenericFileHandlerInterface;

/**
 * The M2IF - Simple Application implementation.
 *
 * This is a example application implementation that should give developers an impression
 * on how the M2IF could be used to implement their own Magento 2 importer.
 *
 * @author    Tim Wagner <t.wagner@techdivision.com>
 * @copyright 2016 TechDivision GmbH <info@techdivision.com>
 * @license   https://opensource.org/licenses/MIT
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
     * @var \Psr\Container\ContainerInterface
     */
    protected $container;

    /**
     * The system configuration.
     *
     * @var \TechDivision\Import\Configuration\ConfigurationInterface
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
     * The array with the module instances.
     *
     * @var \TechDivision\Import\Modules\ModuleInterface[]
     */
    protected $modules;

    /**
     * The event emitter instance.
     *
     * @var \League\Event\EmitterInterface
     */
    protected $emitter;

    /**
     * The generic file handler instance.
     *
     * @var \TechDivision\Import\Handlers\GenericFileHandlerInterface
     */
    protected $genericFileHanlder;

    /**
     * The PID file handler instance.
     *
     * @var \TechDivision\Import\Handlers\PidFileHandlerInterface
     */
    protected $pidFileHanlder;

    /**
     * The constructor to initialize the instance.
     *
     * @param \Psr\Container\ContainerInterface                         $container          The DI container instance
     * @param \TechDivision\Import\Services\RegistryProcessorInterface  $registryProcessor  The registry processor instance
     * @param \TechDivision\Import\Services\ImportProcessorInterface    $importProcessor    The import processor instance
     * @param \TechDivision\Import\Configuration\ConfigurationInterface $configuration      The system configuration
     * @param \Symfony\Component\Console\Output\OutputInterface         $output             The output instance
     * @param \Doctrine\Common\Collections\Collection                   $systemLoggers      The array with the system logger instances
     * @param \League\Event\EmitterInterface                            $emitter            The event emitter instance
     * @param \TechDivision\Import\Handlers\GenericFileHandlerInterface $genericFileHandler The generic file handler instance
     * @param \TechDivision\Import\Handlers\PidFileHandlerInterface     $pidFileHandler     The PID file handler instance
     * @param \Traversable                                              $modules            The modules that provides the business logic
     */
    public function __construct(
        ContainerInterface $container,
        RegistryProcessorInterface $registryProcessor,
        ImportProcessorInterface $importProcessor,
        ConfigurationInterface $configuration,
        OutputInterface $output,
        Collection $systemLoggers,
        EmitterInterface $emitter,
        GenericFileHandlerInterface $genericFileHandler,
        PidFileHandlerInterface $pidFileHandler,
        \Traversable $modules
    ) {

        // register the shutdown function
        register_shutdown_function(array($this, 'shutdown'));

        // initialize the instance with the passed values
        $this->setOutput($output);
        $this->setEmitter($emitter);
        $this->setModules($modules);
        $this->setContainer($container);
        $this->setConfiguration($configuration);
        $this->setSystemLoggers($systemLoggers);
        $this->setPidFileHandler($pidFileHandler);
        $this->setImportProcessor($importProcessor);
        $this->setRegistryProcessor($registryProcessor);
        $this->setGenericFileHandler($genericFileHandler);
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
     * @param \Psr\Container\ContainerInterface $container The container instance
     *
     * @return void
     */
    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Return's the container instance.
     *
     * @return \Psr\Container\ContainerInterface The container instance
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
     * @param \TechDivision\Import\Configuration\ConfigurationInterface $configuration The system configuration
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
     * @return \TechDivision\Import\Configuration\ConfigurationInterface The system configuration
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
     * Set's the module instances.
     *
     * @param \Traversable $modules The modules instances
     *
     * @return void
     */
    public function setModules(\Traversable $modules)
    {
        $this->modules = $modules;
    }

    /**
     * Return's the module instances.
     *
     * @return \Traversable The module instances
     */
    public function getModules()
    {
        return $this->modules;
    }

    /**
     * Set's the PID file handler instance.
     *
     * @param \TechDivision\Import\Handlers\PidFileHandlerInterface $pidFileHandler The PID file handler instance
     *
     * @return void
     */
    public function setPidFileHandler(PidFileHandlerInterface $pidFileHandler) : void
    {
        $this->pidFileHanlder = $pidFileHandler;
    }

    /**
     * Return's the PID file handler instance.
     *
     * @return \TechDivision\Import\Handlers\PidFileHandlerInterface The PID file handler instance
     */
    public function getPidFileHandler() : PidFileHandlerInterface
    {
        return $this->pidFileHanlder;
    }

    /**
     * Set's the generic file handler instance.
     *
     * @param \TechDivision\Import\Handlers\GenericFileHandlerInterface $genericFileHandler The generic file handler instance
     *
     * @return void
     */
    public function setGenericFileHandler(GenericFileHandlerInterface $genericFileHandler) : void
    {
        $this->genericFileHandler = $genericFileHandler;
    }

    /**
     * Return's the generic file handler instance.
     *
     * @return \TechDivision\Import\Handlers\GenericFileHandlerInterface The generic file handler instance
     */
    public function getGenericFileHandler() : GenericFileHandlerInterface
    {
        return $this->genericFileHandler;
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
     * Returns the actual application version.
     *
     * @return string The application's version
     */
    public function getVersion()
    {
        return $this->getContainer()->get(DependencyInjectionKeys::APPLICATION)->getVersion();
    }

    /**
     * Returns the actual application name.
     *
     * @return string The application's name
     */
    public function getName()
    {
        return $this->getContainer()->get(DependencyInjectionKeys::APPLICATION)->getName();
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
                // log the fatal error message
                $this->log($message, LogLevel::ERROR);

                // clean-up the PID file
                $this->unlock();
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
        $this->getPidFileHandler()->lock();
    }

    /**
     * Remove's the UUID of the actual import process from the PID file.
     *
     * @return void
     * @throws \Exception Is thrown, if the PID can not be removed
     */
    public function unlock()
    {
        $this->getPidFileHandler()->unlock();
    }

    /**
     * Remove's the passed line from the file with the passed name.
     *
     * @param string   $line The line to be removed
     * @param resource $fh   The file handle of the file the line has to be removed
     *
     * @return void
     * @throws \Exception Is thrown, if the file doesn't exists, the line is not found or can not be removed
     * @deprecated Since version 17.0.0
     * @see \TechDivision\Import\Handlers\GenericFileHandler::removeLineFromFile()
     */
    public function removeLineFromFile($line, $fh)
    {

        // delegate the invocation to the generic file handler's method
        $this->getGenericFileHandler()->removeLineFromFile($line, $fh);

        // log a message that this method has been deprecated now
        $this->log(
            sprintf('Method "%s" has been deprecated since version 17.0.0, use  \TechDivision\Import\Handlers\GenericFileHandler::removeLineFromFile() instead', __METHOD__),
            LogLevel::WARNING
        );
    }

    /**
     * Process the given operation.
     *
     * @param string $serial The unique serial of the actual import process
     *
     * @return null|int null or 0 if everything went fine, or an error code
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

            // process the modules
            foreach ($this->getModules() as $module) {
                $module->process();
            }

            // commit the transaction, if single transation mode has been configured
            if ($this->getConfiguration()->isSingleTransaction()) {
                $this->getImportProcessor()->getConnection()->commit();
            }
            // track the time needed for the import in seconds
            $endTime = microtime(true) - $startTime;

            // log a debug message that import has been finished
            $this->getSystemLogger()->info(sprintf('Execution time for operation with serial %s in %f s', $this->getSerial(), $endTime));

            // invoke the event that has to be fired before the application has the transaction
            // committed successfully (if single transaction mode has been activated)
            $this->getEmitter()->emit(EventNames::APP_PROCESS_TRANSACTION_SUCCESS, $this);
       } catch (MissingFileException $mfe) {
            // commit the transaction, if single transation mode has been configured
            if ($this->getConfiguration()->isSingleTransaction()) {
                $this->getImportProcessor()->getConnection()->commit();
            }

            // if a PID has been set (because CSV files has been found),
            // remove it from the PID file to unlock the importer
            $this->unlock();

            // log the exception message as warning
            $this->log($mfe->getMessage(), LogLevel::ERROR);
            
            return $mfe->getCode();
        } catch (ApplicationFinishedException $afe) {
            // commit the transaction, if single transation mode has been configured
            if ($this->getConfiguration()->isSingleTransaction()) {
                $this->getImportProcessor()->getConnection()->commit();
            }

            // if a PID has been set (because CSV files has been found),
            // remove it from the PID file to unlock the importer
            $this->unlock();

            // invoke the event that has to be fired before the application has the transaction
            // committed successfully (if single transaction mode has been activated)
            $this->getEmitter()->emit(EventNames::APP_PROCESS_TRANSACTION_SUCCESS, $this);

            // track the time needed for the import in seconds
            $endTime = microtime(true) - $startTime;

            // log a debug message that import has been finished
            $this->getSystemLogger()->notice(sprintf('Finished import with serial %s in %f s', $this->getSerial(), $endTime));

            // log the exception message as warning
            $this->log($afe->getMessage(), LogLevel::NOTICE);

            // return the exception code, 0 by default to signal NO error
            return $afe->getCode();
        } catch (ApplicationStoppedException $ase) {
            // rollback the transaction, if single transaction mode has been configured
            if ($this->getConfiguration()->isSingleTransaction()) {
                $this->getImportProcessor()->getConnection()->rollBack();
            }

            // invoke the event that has to be fired after the application rollbacked the
            // transaction (if single transaction mode has been activated)
            $this->getEmitter()->emit(EventNames::APP_PROCESS_TRANSACTION_FAILURE, $this, $ase);

            // finally, if a PID has been set (because CSV files has been found),
            // remove it from the PID file to unlock the importer
            $this->unlock();

            // track the time needed for the import in seconds
            $endTime = microtime(true) - $startTime;

            // log a message that the file import failed
            foreach ($this->systemLoggers as $systemLogger) {
                $systemLogger->warning($ase->__toString());
            }

            // log a message that import has been finished
            $this->getSystemLogger()->warning(sprintf('Stopped import with serial %s in %f s', $this->getSerial(), $endTime));

            // log the exception message as warning
            $this->log($ase->getMessage(), LogLevel::WARNING);

            // return the exception code, 1 by default to signal an error
            return $ase->getCode();
        } catch (ImportAlreadyRunningException $iare) {
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
            $this->getSystemLogger()->warning(sprintf('Can\'t finish import with serial because another import process is running %s in %f s', $this->getSerial(), $endTime));

            // log the exception message as warning
            $this->log($iare->getMessage(), LogLevel::WARNING);

            // return 1 to signal an error
            return 1;
        } catch (\Exception $e) {
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
            $this->getSystemLogger()->error(sprintf('Can\'t finish import with serial %s in %f s', $this->getSerial(), $endTime));

            // log the exception message as warning
            $this->log($e->getMessage(), LogLevel::ERROR);

            // return 1 to signal an error
            return 1;
        } finally {
            // tear down
            $this->tearDown();

            // invoke the event that has to be fired after the application transaction has been finished
            $this->getEmitter()->emit(EventNames::APP_PROCESS_TRANSACTION_FINISHED, $this);
        }
    }

    /**
     * Stop processing the operation immediately and should return an exit code > 0.
     *
     * This will stop the operation with an error output and rolling back the single transaction,
     * if it has been started by the CLI parameter `--single-transaction=true`.
     *
     * This method should be used when the import process should be interrupted in case of an
     * error and to signal the user that something went wrong.
     *
     * @param string $reason   The reason why the operation has been stopped
     * @param int    $exitCode The exit code to use, defaults to 1
     *
     * @return void
     * @throws \TechDivision\Import\Exceptions\ApplicationStoppedException Is thrown if the application has been stopped
     */
    public function stop($reason, $exitCode = 1)
    {

        // stop processing the plugins by setting the flag to TRUE
        $this->stopped = true;

        // throw the exeception
        throw new ApplicationStoppedException($reason, $exitCode);
    }

    /**
     * Finish processing the operation immediately and should return an exit code 0.
     *
     * This will stop the operation without an error output and commits the single transaction,
     * if it has been started by the CLI parameter `--single-transaction=true`.
     *
     * This method should be used when the import process should be interrupted in case
     * further processing makes no sense or is not necessary and to signal the user that
     * everything is as expected.
     *
     *
     * @param string $reason   The reason why the operation has been finish
     * @param int    $exitCode The exit code to use
     *
     * @return void
     * @throws \TechDivision\Import\Exceptions\ApplicationFinishedException Is thrown if the application has been finish
     */
    public function finish($reason, $exitCode = 0)
    {

        // stop processing the plugins by setting the flag to TRUE
        $this->stopped = true;

        // throw the exeception
        throw new ApplicationFinishedException($reason, $exitCode);
    }

    /**
     * @param string $reason
     * @param int    $exitCode
     *
     * @return void
     * @throws \TechDivision\Import\Exceptions\MissingFileException Is thrown if the file has been missed
     */
    public function missingFile($reason, $exitCode = 0)
    {
        // throw the exeception
        throw new MissingFileException($reason, $exitCode);
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
    protected function setUp(): void
    {
        $this->getEmitter()->emit(EventNames::APP_SET_UP, $this);
    }

    /**
     * Lifecycle callback that will be inovked after the
     * import process has been finished.
     *
     * @return void
     */
    protected function tearDown(): void
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
