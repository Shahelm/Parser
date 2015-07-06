<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 02.07.15
 * Time: 0:09
 */
namespace ConsoleCommands\Amazon;

use ConsoleCommands\AbstractCommand;

/**
 * Class AbstractAmazon
 *
 * @package ConsoleCommands\Amazon
 */
abstract class AbstractAmazon extends AbstractCommand
{
    const PARSER_NAME = 'amazon';

    /**
     * @return string
     */
    protected function getParserName()
    {
        return self::PARSER_NAME;
    }
}
