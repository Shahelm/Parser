<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 02.07.15
 * Time: 0:09
 */
namespace ConsoleCommands\Amazon;

use ConsoleCommands\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AbstractAmazon
 *
 * @package ConsoleCommands\Amazon
 */
abstract class AbstractAmazon extends AbstractCommand
{
    const PARSER_NAME = 'amazon';
    
    /**
     * @var string
     */
    protected $projectName;
    
    /**
     * @var string
     */
    protected $brandPageUrl;

    /**
     * @return string
     */
    protected function getParserName()
    {
        return self::PARSER_NAME;
    }

    /**
     * @return string
     */
    protected function getProjectName()
    {
        return null === $this->projectName ? $this->brandPageUrl : $this->projectName;
    }

    /**
     * {@inheritdoc}
     */
    protected function shutdown(InputInterface $input, OutputInterface $output)
    {
        try {
            $output->writeln('');
            $this->logger->info(
                get_called_class() . ':finish',
                [
                    'url'  => $this->getProjectName(),
                    'time' => round((microtime(true) - $this->start), 2),
                ]
            );
        } catch (\Exception $e) {
            /*NOP*/
        }
    }
}
