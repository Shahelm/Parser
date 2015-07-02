<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 29.06.15
 * Time: 13:43
 */
namespace ConsoleCommands\Autoplicity;

use ConsoleCommands\ProductImagesCollector;
use ConsoleCommands\RecursiveDirectoryClearing;
use Exceptions\ApplicationException;
use Exceptions\ContainerException;
use Helper\Console;
use Helper\CSV;
use Helper\ProcessPool;
use Helper\Resource;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\Process\Process;

/**
 * Class Executor
 *
 * @package ConsoleCommands
 */
class Executor extends AbstractAutoplicity
{
    use Resource, Console;
    
    const FILE_PATH_TO_BRAND_PAGE_URLS = 'file-path-to-brand-page-urls';
    
    /**
     * @var array
     */
    private $brandPageUrls = [];
    
    /**
     * @var int
     */
    private $numbersOfBrandPageUrls = 0;

    /**
     * @throws \InvalidArgumentException
     */
    protected function configure()
    {
        $this->setName($this->getParserName() . ':executor:run');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws ApplicationException
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        
        try {
            $pathToBrandPageUrls = $this->container->getParameter('autoplicity.path.brandPageUrls');
        } catch (InvalidArgumentException $e) {
            throw ContainerException::wrapException($e);
        }
        
        if ($this->fs->exists($pathToBrandPageUrls)) {
            $handle = $this->openResource($pathToBrandPageUrls, 'rb', false);
            
            if (is_resource($handle)) {
                $columnNames = ['url'];

                while (false !== ($row = CSV::readRow($handle, $columnNames))) {
                    $path = $this->extractPath((array)$row);
                    
                    if ('' !== $path) {
                        $this->brandPageUrls[$path] = $path;
                    }
                }

                $this->closeResource($handle, $pathToBrandPageUrls);
            } else {
                $this->logger->addAlert(
                    sprintf('Could not open file for reading brand-page url. Path: %s', $pathToBrandPageUrls)
                );
            }
        } else {
            $this->logger->addAlert('File does not exist.', [$pathToBrandPageUrls]);
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws ApplicationException
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->cleatState($output);
        $this->collectionProductUrls($output);
        $this->collectionImagePaths($output);
        $this->collectionImages($output);
    }

    /**
     * @param OutputInterface $output
     */
    protected function cleatState(OutputInterface $output)
    {
        $tmpDirs = [
            \Helper\Path\tmp_path() . DIRECTORY_SEPARATOR . $this->getParserName(),
            \Helper\Path\images_path() . DIRECTORY_SEPARATOR . $this->getParserName()
        ];

        $formattedLine = $this->formatter->formatSection('Stage: clear tmp dirs.', implode(', ', $tmpDirs));
        $this->writeln($formattedLine);

        foreach ($tmpDirs as $path) {
            $this->runCommand(
                $output,
                RecursiveDirectoryClearing::COMMAND_NAME,
                [RecursiveDirectoryClearing::PATH => $path]
            );
        }

        $this->writeln();
    }

    /**
     * @param OutputInterface $output
     *
     * @return void
     *
     * @throws ApplicationException
     */
    protected function collectionProductUrls(OutputInterface $output)
    {
        if (false === empty($this->brandPageUrls)) {
            $this->numbersOfBrandPageUrls = count($this->brandPageUrls);
            
            $formattedLine = $this->formatter->formatSection('Stage: collecting products urls', '');
            $this->writeln($formattedLine);

            try {
                $poolSize = $this->container->getParameter('autoplicity.executor.productUrlCollector.poolSize');
                $timeOut = $this->container->getParameter('autoplicity.executor.productUrlCollector.iterationTimOut');
            } catch (InvalidArgumentException $e) {
                throw ContainerException::wrapException($e);
            }
            
            $binFile = \Helper\Path\bin_path();
            
            $processQueue = new \SplQueue();

            $commandName = $this->getParserName() . ':' . ProductUrlCollector::COMMAND_NAME;

            foreach ($this->brandPageUrls as $url) {
                $command = \PHP_BINARY . ' ' . $binFile . ' ' . $commandName . ' ' . $url;
                $processQueue->enqueue(new Process($command));
            }

            $progressBar = new ProgressBar($output, $this->numbersOfBrandPageUrls);
            $progressBar->setFormat('%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
            $progressBar->start();
            
            (new ProcessPool($processQueue, $poolSize, $timeOut))
                ->onProcessError(function (Process $process) {
                    $this->logger->addAlert('Process terminated with error!', [$process->getCommandLine()]);
                })
                ->onProcessFinish(function () use ($progressBar) {
                    try {
                        $progressBar->advance();
                    } catch (\LogicException $e) {
                        /*NOP*/
                    }
                })
                ->wait();
            ;
            
            $progressBar->finish();
        }
        $this->writeln();
    }

    /**
     * @param OutputInterface $output
     *
     * @return void
     */
    private function collectionImagePaths(OutputInterface $output)
    {
        $commandName = $this->getParserName() . ':' . ProductImagePathCollector::COMMAND_NAME;

        $this->writeln();
        $formattedLine = $this->formatter->formatSection('Stage: collecting url-paths', '');
        $this->writeln($formattedLine);
        
        foreach ($this->brandPageUrls as $url) {
            $this->runCommand($output, $commandName, [AbstractAutoplicity::BRAND_PAGE => $url]);
        }
    }

    /**
     * @param OutputInterface $output
     *
     * @return void
     */
    private function collectionImages(OutputInterface $output)
    {
        $commandName = ProductImagesCollector::COMMAND_NAME;

        $this->writeln();
        $formattedLine = $this->formatter->formatSection('Stage: download images', '');
        $this->writeln($formattedLine);
        
        foreach ($this->brandPageUrls as $url) {
            $this->runCommand(
                $output,
                $commandName,
                [
                    AbstractAutoplicity::BRAND_PAGE            => $url,
                    $this->prepareOptionName(ProductImagesCollector::OPTION_PARSER_NAME)
                        => AbstractAutoplicity::PARSER_NAME
                ]
            );
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
     * {@inheritdoc}
     */
    protected function shutdown(InputInterface $input, OutputInterface $output)
    {
        $this->logger->info(
            'Finished collection products url',
            [
                'numbers-of-brand-page-urls' => $this->numbersOfBrandPageUrls,
                'time'                       => round((microtime(true) - $this->start), 2),
            ]
        );
    }
}
