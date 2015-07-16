<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 30.06.15
 * Time: 23:53
 */
namespace Helper;

use Monolog\Logger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Console
 *
 * @package Helper
 *
 * @property Logger $logger
 * @method Application getApplication
 */
trait Console
{
    /**
     * @return void
     */
    protected function exitWithError()
    {
        exit(1);
    }

    /**
     * @param string $optionName
     *
     * @return string
     */
    protected function prepareOptionName($optionName)
    {
        return '--' . $optionName;
    }
    
    /**
     * @param OutputInterface $output
     * @param string $commandName
     * @param array $arguments
     *
     * @return void
     */
    protected function runCommand(OutputInterface $output, $commandName, array $arguments)
    {
        try {
            /**
             * @var Command $command
             */
            $command = $this->getApplication()->find($commandName);
            
            $arguments['command'] = $command->getName();
            
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
        } catch (\InvalidArgumentException $e) {
            $this->logger->alert('Unable to find a command.', [$commandName]);
            $this->exitWithError();
        }
    }
}
