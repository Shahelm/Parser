<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 16.07.15
 * Time: 0:10
 */
namespace ConsoleCommands;

use Exceptions\ApplicationException;
use Exceptions\ContainerException;
use Helper\Console;
use Helper\ProcessPool;
use Helper\Resource;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\Process\Process;

/**
 * Class AbstractExecutor
 *
 * @package ConsoleCommands\Exceptions
 */
abstract class AbstractExecutor extends AbstractCommand
{
    use Resource, Console;
    
    /**
     * @var int
     */
    protected $numbersOfProjects = 0;
    
    /**
     * @var array
     */
    protected $projects = [];

    /**
     * @param resource $handle
     *
     * @return array
     */
    abstract protected function getProjects($handle);

    /**
     * @param mixed $project
     *
     * @return string
     */
    abstract  protected function extractProjectName($project);

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
            $pathToBrandPageUrls = $this->container->getParameter($this->getParserName() . '.path.brandPageUrls');
        } catch (InvalidArgumentException $e) {
            throw ContainerException::wrapException($e);
        }

        if ($this->fs->exists($pathToBrandPageUrls)) {
            $handle = $this->openResource($pathToBrandPageUrls, 'rb', false);

            if (is_resource($handle)) {
                $this->projects = $this->getProjects($handle);

                $this->numbersOfProjects = count($this->projects);

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
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function collectionImages(OutputInterface $output)
    {
        $commandName = ProductImagesCollector::COMMAND_NAME;

        $this->writeln();
        $formattedLine = $this->formatter->formatSection('Stage: download images', '');
        $this->writeln($formattedLine);

        foreach ($this->projects as $project) {
            $projectName = $this->extractProjectName($project);

            $this->runCommand(
                $output,
                $commandName,
                [
                    AbstractCommand::PROJECT_NAME                                        => $projectName,
                    $this->prepareOptionName(ProductImagesCollector::OPTION_PARSER_NAME) => $this->getParserName()
                ]
            );
        }
    }

    /**
     * @param OutputInterface $output
     * @param string $stageTitle
     * @param callable $commandCreator
     *
     * @throws ApplicationException
     */
    protected function runProcessesInParallel(OutputInterface $output, $stageTitle, $commandCreator)
    {
        if ($this->numbersOfProjects > 0) {
            $formattedLine = $this->formatter->formatSection($stageTitle, '');
            $this->writeln($formattedLine);

            $processQueue = new \SplQueue();

            foreach ($this->projects as $project) {
                $command = $commandCreator($project);
                $processQueue->enqueue(new Process($command));
            }

            try {
                $poolSize = $this->container->getParameter(
                    $this->getParserName() . '.executor.poolSize'
                );

                $timeOut = $this->container->getParameter(
                    $this->getParserName() . '.executor.iterationTimOut'
                );
            } catch (InvalidArgumentException $e) {
                throw ContainerException::wrapException($e);
            }

            $progressBar = new ProgressBar($output, $this->numbersOfProjects);
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

            $progressBar->finish();
            $this->writeln();
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function shutdown(InputInterface $input, OutputInterface $output)
    {
        $this->logger->info(
            'Finished collection products url',
            [
                'numbers-of-projects' => $this->numbersOfProjects,
                'time'                => round((microtime(true) - $this->start), 2),
            ]
        );
    }
}
