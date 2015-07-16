<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 12.07.15
 * Time: 12:16
 */
namespace Helper;

/**
 * Class ArrayHelper
 *
 * @package Helper
 */
class ArrayHelper
{
    /**
     * @param array $values
     *
     * @return mixed
     */
    public static function getRandValue(array $values)
    {
        $randKey = array_rand($values);
        
        return $values[$randKey];
    }
}
