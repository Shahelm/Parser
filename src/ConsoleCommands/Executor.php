<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 29.06.15
 * Time: 13:43
 */
namespace ConsoleCommands;

use Helper\File;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Process\Process;

/**
 * Class Executor
 *
 * @package ConsoleCommands
 */
class Executor extends AbstractCommand
{
    const FILE_PATH_TO_BRAND_PAGE_URLS = 'file-path-to-brand-page-urls';
    
    private $brandPageUrls = [];
    
    /**
     * @throws \InvalidArgumentException
     */
    protected function configure()
    {
        $this->setName('executor:run')
            ->addOption(
                self::FILE_PATH_TO_BRAND_PAGE_URLS,
                null,
                InputOption::VALUE_REQUIRED,
                'Path to file with links to pages brand-page.'
            )
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws \InvalidArgumentException
     * @throws InvalidArgumentException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        
        $pathToBrandPageUrls = $input->getOption(self::FILE_PATH_TO_BRAND_PAGE_URLS);

        if (null === $pathToBrandPageUrls) {
            $pathToBrandPageUrls = $this->getContainer()->getParameter('app.path.brandPageUrls');
        }
        
        if ($this->fs->exists($pathToBrandPageUrls)) {
            $handle = $this->openResource($pathToBrandPageUrls, 'rb', false);
            
            if (is_resource($handle)) {
                $columnNames = ['url'];

                while (false !== ($row = File::readCsvRow($handle, $columnNames))) {
                    $path = $this->extractPath((array)$row);
                    
                    if ('' !== $path) {
                        $this->brandPageUrls[$path] = $path;
                    }
                }

                $this->closeResource($handle, $pathToBrandPageUrls);
            } else {
                $this->writelnError(
                    $output,
                    sprintf('Could not open file for reading brand -page url. Path: %s', $pathToBrandPageUrls)
                );
            }
        } else {
            $message = 'File does not exist.';
            
            $this->logger->addAlert($message, [$pathToBrandPageUrls]);
            $message .= " Path: {$pathToBrandPageUrls}";
            $this->writelnError($output, $message);
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws \InvalidArgumentException
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->clearState();
        $this->collectionProductUrls($output);
        $this->collectionImagePaths($output);
        $this->collectionImages($output);
    }

    /**
     * @param OutputInterface $output
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function collectionProductUrls(OutputInterface $output)
    {
        $start = microtime(true);

        $numbersOfBrandPageUrls = 0;

        if (false === empty($this->brandPageUrls)) {
            $numbersOfBrandPageUrls = count($this->brandPageUrls);
            $output->writeln('Began the process of collecting url-s products.');

            $poolSize = $this->getContainer()->getParameter('executor.productUrlCollector.poolSize');
            $timeOut = $this->getContainer()->getParameter('executor.productUrlCollector.iterationTimOut');
            $binFile = $this->getContainer()->getParameter('app.bin.file');
            
            $processQueue = new \SplQueue();

            $commandName = ProductUrlCollector::COMMAND_NAME;

            foreach ($this->brandPageUrls as $url) {
                $command = \PHP_BINARY . ' ' . $binFile . ' ' . $commandName . ' ' . $url;
                $processQueue->enqueue(new Process($command));
            }

            /**
             * @var Process[] $activeProcess
             */
            $activeProcess = [];

            $progressBar = new ProgressBar($output, $numbersOfBrandPageUrls);
            $progressBar->setFormat('%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
            $progressBar->start();

            while (true) {
                if (empty($activeProcess) && $processQueue->isEmpty()) {
                    break;
                }

                foreach ($activeProcess as $key => $process) {
                    if (false === $process->isRunning()) {
                        if (false === $process->isSuccessful()) {
                            $this->logger->addAlert('Process terminated with error!', [$process->getCommandLine()]);
                        }

                        try {
                            $progressBar->advance();
                        } catch (\LogicException $e) {
                            /*NOP*/
                        }

                        unset($activeProcess[$key]);
                    }
                }

                if (count($activeProcess) < $poolSize && false === $processQueue->isEmpty()) {
                    $numbersOfProcess = $poolSize - count($activeProcess);

                    for ($i = 0; $i < $numbersOfProcess; $i++) {
                        if (false === $processQueue->isEmpty()) {
                            $process = $processQueue->dequeue();
                            $activeProcess[] = $process;
                            $process->start();
                        }
                    }
                }

                
                sleep($timeOut);
            }

            $progressBar->finish();
        }

        $output->writeln('');
        $this->logger->info(
            'Finished collection products url',
            [
                'numbers-of-brand-page-urls' => $numbersOfBrandPageUrls,
                'time'                       => round((microtime(true) - $start), 2),
            ]
        );
    }

    /**
     * @param OutputInterface $output
     *
     * @return void
     */
    private function collectionImagePaths(OutputInterface $output)
    {
        $commandName = ProductImagePathCollector::COMMAND_NAME;
        $startupMessage = 'Began the process of collecting url-paths by product urls.';
        $this->runCommand($output, $commandName, $startupMessage);
    }

    /**
     * @param OutputInterface $output
     *
     * @return void
     */
    private function collectionImages(OutputInterface $output)
    {
        $commandName = ProductImagesCollector::COMMAND_NAME;
        $startupMessage = 'Began the process of download images by image path.';
        
        $this->runCommand($output, $commandName, $startupMessage);
    }

    /**
     * @param OutputInterface $output
     * @param string $commandName
     * @param string $startupMessage
     *
     * @return void
     */
    private function runCommand(OutputInterface $output, $commandName, $startupMessage)
    {
        try {
            $output->writeln($startupMessage);

            /**
             * @var Command $command
             */
            $command = $this->getApplication()->find($commandName);

            foreach ($this->brandPageUrls as $url) {
                $arguments = [
                    'command'                             => $command->getName(),
                    ProductUrlCollector::PRODUCT_LIST_URL => $url
                ];

                try {
                    $input = new ArrayInput($arguments);
                    $returnCode = $command->run($input, $output);
                } catch (\Exception $e) {
                    $this->logger->addAlert('Process terminated with error!', [$arguments]);
                    $returnCode = 1;
                }

                if (0 !== $returnCode) {
                    $this->logger->addAlert('Process terminated with error!', [$arguments]);
                }
            }
        } catch (\InvalidArgumentException $e) {
            $this->logger->alert('Unable to find a command.', [ProductImagePathCollector::COMMAND_NAME]);
            $this->exitWithError();
        }
    }
    
    /**
     * @param array $row
     *
     * @return string
     */
    private function extractPath(array $row)
    {
        $result = '';

        if (isset($row['url']) && is_string($row['url']) && false === empty($row['url'])) {
            $url = $row['url'];

            $urlPath = parse_url($url, \PHP_URL_PATH);

            if (null !== $urlPath) {
                $result = $urlPath;
            }
        }

        return $result;
    }

    /**
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    private function clearState()
    {
        $tmp = $this->getContainer()->getParameter('app.tmp.dir');
        $images = $this->getContainer()->getParameter('app.images.dir');

        $this->clearDir($tmp);
        $this->clearDir($images);
    }

    /**
     * @param string $path
     */
    private function clearDir($path)
    {
        if ($this->fs->exists($path)) {
            $directoryIterator = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);
            $iterator = new \RecursiveIteratorIterator($directoryIterator, \RecursiveIteratorIterator::CHILD_FIRST);

            $filesToRemove = [];
            $dirsToRemove = [];

            /**
             * @var \SplFileInfo $fileInfo
             */
            foreach ($iterator as $fileInfo) {
                if ($path !== $fileInfo->getRealPath()) {
                    if ($fileInfo->isFile()) {
                        $filesToRemove[] = $fileInfo->getRealPath();
                    } else {
                        $dirsToRemove[] = $fileInfo->getRealPath();
                    }
                }
            }

            foreach ($filesToRemove as $path) {
                $isDelete = unlink($path);
                if (false === $isDelete) {
                    $this->logger->warn('Failed to delete the resource.', [$path]);
                }
            }

            foreach ($dirsToRemove as $path) {
                $isDelete = rmdir($path);
                if (false === $isDelete) {
                    $this->logger->warn('Failed to delete the resource.', [$path]);
                }
            }
        }
    }
}
