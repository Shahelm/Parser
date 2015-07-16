<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 28.06.15
 * Time: 1:29
 */
namespace ConsoleCommands;

use ConsoleCommands\Exceptions\NotValidInputData;
use Exceptions\ApplicationException;
use Exceptions\ContainerException;
use Guzzle\Http\Client;
use Helper\Container as ContainerHelper;
use Helper\Filesystem;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Class AbstractCommand
 *
 * @package Helper
 */
abstract class AbstractCommand extends Command
{
    const CSV_LINE_NUMBER = 'csv-line-number';
    const BRAND_PAGE = 'brand-page';
    const PROJECT_NAME = 'project-name';
    const PRODUCT_LIST_PAGE = 'page';

    /**
     * @var OutputInterface
     */
    private $output;
    
    /**
     * @var int
     */
    protected $start;
    
    /**
     * @var ContainerInterface
     */
    protected $container;
    
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var Client
     */
    protected $client;
    
    /**
     * @var Filesystem
     */
    protected $fs;
    
    /**
     * @var FormatterHelper
     */
    protected $formatter;

    /**
     * @return string
     */
    abstract protected function getParserName();
        
    /**
     * @param InputInterface $input
     *
     * @return bool
     */
    protected function validation(InputInterface $input)
    {
        
    }
    
    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws NotValidInputData
     * @throws ApplicationException
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->start = microtime(true);
        
        if (false === $this->validation($input)) {
            throw new NotValidInputData();
        }

        try {
            $this->formatter = $this->getHelper('formatter');
            $this->container = $this->getHelper(ContainerHelper::class)->getContainer();
            ini_set('memory_limit', $this->getMemoryLimit());
            $this->fs = new Filesystem();
            
            $projectPath = \Helper\Path\var_path() . DIRECTORY_SEPARATOR .  $this->getParserName();

            $paths = [
                $projectPath . DIRECTORY_SEPARATOR . 'log',
                $projectPath . DIRECTORY_SEPARATOR . 'tmp',
                $projectPath . DIRECTORY_SEPARATOR . 'images'
            ];
            
            foreach ($paths as $path) {
                $this->fs->createDirIfNotExist($path);
            }
            
            $this->logger = $this->getLogger();
            $this->client = $this->container->get($this->getParserName() . '.' . 'client');
        } catch (\Exception $e) {
            throw ApplicationException::wrapException($e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        $result = parent::run($input, $output);
        
        $this->shutdown($input, $output);
        
        return $result;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function shutdown(InputInterface $input, OutputInterface $output)
    {

    }

    /**
     * @return \Symfony\Component\DomCrawler\Crawler
     *
     * @throws ApplicationException
     */
    protected function newInstanceCrawler()
    {
        try {
            $crawler =$this->container->get('crawler');
        } catch (\Exception $e) {
            throw ApplicationException::wrapException($e);
        }
        
        return $crawler;
    }

    /**
     * @return string
     *
     * @throws ApplicationException
     */
    protected function getHost()
    {
        try {
            $host = $this->container->getParameter($this->getParserName() . '.' . 'host');
        } catch (InvalidArgumentException $e) {
            throw ContainerException::wrapException($e);
        }

        return $host;
    }
    
    /**
     * @param string $message
     *
     * @return void
     */
    protected function writeln($message = '')
    {
        try {
            $this->output->writeln($message);
        } catch (\InvalidArgumentException $e) {
            /*NOP*/
        }
    }
    
    /**
     * @return string
     *
     * @throws ApplicationException
     */
    private function getMemoryLimit()
    {
        try {
            $memoryLimit = $this->container->getParameter('app.memory_limit');

            $stageMemoryLimitKey = $this->getParserName() . '.' . 'memory_limit';

            if ($this->container->hasParameter($stageMemoryLimitKey)) {
                $memoryLimit = $this->container->getParameter($stageMemoryLimitKey);
            }
        } catch (\InvalidArgumentException $e) {
            throw ApplicationException::wrapException($e);
        }
        
        return $memoryLimit;
    }

    /**
     * @return Logger
     *
     * @throws ApplicationException
     */
    private function getLogger()
    {
        $loggerKey = $this->getParserName() . '.' . 'logger';
        
        try {
            $logger = $this->container->get($loggerKey);
        } catch (\Exception $e) {
            throw ApplicationException::wrapException($e);
        }
        
        return $logger;
    }
}
