<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 28.06.15
 * Time: 1:29
 */
namespace ConsoleCommands;

use GuzzleHttp\Client;
use Helper\Container as ContainerHelper;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class AbstractCommand
 *
 * @package Helper
 */
abstract class AbstractCommand extends Command
{
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
     * @param OutputInterface $output
     * @param string $message
     */
    protected function writelnError(OutputInterface $output, $message)
    {
        try {
            $output->writeln("<error>{$message}</error>");
        } catch (\InvalidArgumentException $e) {
            /*NOP*/
        }
    }
    
    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws \InvalidArgumentException
     * @throws InvalidArgumentException
     * @throws ServiceNotFoundException
     * @throws ServiceCircularReferenceException
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        ini_set('memory_limit', $this->getContainer()->getParameter('app.memory_limit'));
        $this->logger = $this->getContainer()->get('logger');
        $this->client = $this->getClient();
        $this->fs = new Filesystem();
    }
    
    /**
     * @return ContainerInterface
     *
     * @throws \InvalidArgumentException
     */
    protected function getContainer()
    {
        return $this->getHelper(ContainerHelper::class)->getContainer();
    }

    /**
     * @return \GuzzleHttp\Client
     *
     * @throws \InvalidArgumentException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    protected function getClient()
    {
        return $this->getContainer()->get('client');
    }

    /**
     * @return \Symfony\Component\DomCrawler\Crawler
     *
     * @throws \InvalidArgumentException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    protected function getCrawler()
    {
        return $this->getContainer()->get('crawler');
    }

    /**
     * @return void
     */
    protected function exitWithError()
    {
        exit(1);
    }

    /**
     * @param string $filePath
     * @param string $mode
     * @param bool $onErrorExit
     *
     * @return resource
     */
    protected function openResource($filePath, $mode, $onErrorExit = true)
    {
        $handle = fopen($filePath, $mode);
        
        if (false === $handle) {
            $this->logger->addAlert('Failed create a file handle.', ['file' => $filePath, 'mode' => $mode]);
            
            if ($onErrorExit) {
                $this->exitWithError();
            }
        }
        
        return $handle;
    }
    
    /**
     * @param resource $handle
     * @param string $file
     *
     * @return void
     */
    protected function closeResource($handle, $file)
    {
        $isClosed = fclose($handle);

        if (false === $isClosed) {
            $this->logger->addWarning('Unable to close the file descriptor', ['file' => $file]);
        }
    }

    /**
     * @param string $path
     *
     * @return void
     */
    protected function createDirIfNotExist($path)
    {
        if (false === $this->fs->exists($path)) {
            try {
                $this->fs->mkdir($path);
            } catch (IOException $e) {
                $this->logger->alert('Unable to create directory.', ['message' => $e->getMessage(), 'file' => $path]);
                $this->exitWithError();
            }
        }
    }
}
