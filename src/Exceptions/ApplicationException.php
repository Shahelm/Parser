<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 01.07.15
 * Time: 0:12
 */
namespace Exceptions;

/**
 * Class ApplicationException
 *
 * @package Exceptions
 */
class ApplicationException extends \Exception
{
    /**
     * @param \Exception $e
     *
     * @return self
     */
    public static function wrapException(\Exception $e)
    {
        return new static($e->getMessage(), $e->getCode(), $e);
    }
}
