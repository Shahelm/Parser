<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 15.07.15
 * Time: 21:07
 */
namespace ConsoleCommands\Amazon;

use ConsoleCommands\AbstractExecutor;
use ConsoleCommands\RecursiveDirectoryClearing;
use Exceptions\ApplicationException;
use Helper\CSV;
use Helper\Resource;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Executor
 *
 * @package ConsoleCommands\Amazon
 */
class Executor extends AbstractExecutor
{
    use Resource;

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
        $this->collectionProductInfo($output);
        $this->collectionImages($output);
        $this->collectionCompatibilityCharts($output);
    }

    /**
     * @param OutputInterface $output
     */
    private function cleatState(OutputInterface $output)
    {
        $parserFolder = \Helper\Path\var_path() . DIRECTORY_SEPARATOR . $this->getParserName();
        
        $tmpDirs = [
            $parserFolder . DIRECTORY_SEPARATOR . 'images',
            $parserFolder . DIRECTORY_SEPARATOR . 'compatibility-charts',
            $parserFolder . DIRECTORY_SEPARATOR . 'tmp',
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
        
        $this->runProcessesInParallel(
            $output,
            $stageTitle,
            $this->getCommandCreator(ProductUrlCollector::COMMAND_NAME)
        );
    }

    /**
     * @param OutputInterface $output
     *
     * @return void
     *
     * @throws ApplicationException
     */
    private function collectionProductInfo(OutputInterface $output)
    {
        $stageTitle = 'Stage: collecting products info';

        $this->runProcessesInParallel(
            $output,
            $stageTitle,
            $this->getCommandCreator(ProductInfoCollector::COMMAND_NAME)
        );
    }

    /**
     * @param OutputInterface $output
     *
     * @return void
     *
     * @throws ApplicationException
     */
    private function collectionCompatibilityCharts(OutputInterface $output)
    {
        $stageTitle = 'Stage: collecting compatibility charts';

        $this->runProcessesInParallel(
            $output,
            $stageTitle,
            $this->getCommandCreator(CompatibilityChartCollector::COMMAND_NAME)
        );
    }

    /**
     * @param string $commandName
     *
     * @return callable
     */
    private function getCommandCreator($commandName)
    {
        return function ($project) use ($commandName) {
            $binFile = \Helper\Path\bin_path();
            $fullCommandName = $this->getParserName() . $commandName;
            
            if (ProductUrlCollector::COMMAND_NAME === $commandName) {
                $command = $this->buildCommandForProductUrlCollector(
                    $binFile,
                    $fullCommandName,
                    $project
                );
            } else {
                $command = $this->buildCommandForProductInfoCollector(
                    $binFile,
                    $fullCommandName,
                    $project
                );
            }

            return $command;
        };
    }

    /**
     * @param string $binFile
     * @param string $commandName
     * @param array $project
     *
     * @return string
     */
    private function buildCommandForProductUrlCollector($binFile, $commandName, $project)
    {
        $brandPageUrl = $project['brandPageUrl'];
        $projectName = $project['projectName'];
            
        return \PHP_BINARY . ' ' . $binFile . ' ' . $commandName .
            ' ' . '"' . $brandPageUrl . '"' . ' ' . '--project-name=' . $projectName;
    }

    /**
     * @param string $binFile
     * @param string $commandName
     * @param array $project
     *
     * @return string
     */
    private function buildCommandForProductInfoCollector($binFile, $commandName, $project)
    {
        $projectName = $project['projectName'];
        
        return \PHP_BINARY . ' ' . $binFile . ' ' . $commandName . ' ' . $projectName;
    }

    /**
     * @return string
     */
    protected function getParserName()
    {
        return AbstractAmazon::PARSER_NAME;
    }

    /**
     * @param resource $handle
     *
     * @return array
     */
    protected function getProjects($handle)
    {
        $projects = [];
        
        $columnNames = ['brandPageUrl', 'projectName'];

        while (false !== ($row = CSV::readRow($handle, $columnNames))) {
            if (isset($row['brandPageUrl'], $row['projectName'])) {
                $brandPageUrl = $row['brandPageUrl'];
                $projectName = $row['projectName'];

                $projects[] = [
                    'brandPageUrl' => trim($brandPageUrl),
                    'projectName'  => trim($projectName)
                ];
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
        $projectName = $project['projectName'];

        return $projectName;
    }
}
