<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 01.07.15
 * Time: 1:47
 */
namespace ConsoleCommands\Autoplicity;

use ConsoleCommands\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AbstractAutoplicityStage
 *
 * @package ConsoleCommands\Autoplicity
 */
abstract class AbstractAutoplicity extends AbstractCommand
{
    const PARSER_NAME = 'autoplicity';

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
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function shutdown(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->writeln();

            $this->logger->info(
                get_called_class() . ':finish',
                [
                    'url'  => $this->brandPageUrl,
                    'time' => round((microtime(true) - $this->start), 2),
                ]
            );
        } catch (\Exception $e) {
            /*NOP*/
        }

        parent::shutdown($input, $output);
    }
}
