<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 29.06.15
 * Time: 13:43
 */
namespace ConsoleCommands\Autoplicity;

use ConsoleCommands\AbstractExecutor;
use ConsoleCommands\RecursiveDirectoryClearing;
use Exceptions\ApplicationException;
use Helper\Console;
use Helper\CSV;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Executor
 *
 * @package ConsoleCommands
 */
class Executor extends AbstractExecutor
{
    use Console;
    
    const FILE_PATH_TO_BRAND_PAGE_URLS = 'file-path-to-brand-page-urls';
    
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
            \Helper\Path\tmp_path($this->getParserName()),
            \Helper\Path\images_path($this->getParserName())
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
        $stageTitle = 'Stage: collecting products urls';
        
        $this->runProcessesInParallel($output, $stageTitle, function ($url) {
            $binFile = \Helper\Path\bin_path();
            $commandName = $this->getParserName() . ProductUrlCollector::COMMAND_NAME;
            
            $command = \PHP_BINARY . ' ' . $binFile . ' ' . $commandName . ' ' . $url;
            
            return $command;
        });
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
        
        foreach ($this->projects as $url) {
            $this->runCommand($output, $commandName, [AbstractAutoplicity::BRAND_PAGE => $url]);
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
     * @return string
     */
    protected function getParserName()
    {
        return AbstractAutoplicity::PARSER_NAME;
    }

    /**
     * @param resource $handle
     *
     * @return array
     */
    protected function getProjects($handle)
    {
        $projects = [];
        
        $columnNames = ['url'];

        while (false !== ($row = CSV::readRow($handle, $columnNames))) {
            $path = $this->extractPath((array)$row);

            if ('' !== $path) {
                $projects[$path] = $path;
            }
        }
        
        return $projects;
    }
    
    /**
     * @param string $project
     *
     * @return mixed
     */
    protected function extractProjectName($project)
    {
        return $project;
    }
}
