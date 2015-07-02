<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 28.06.15
 * Time: 14:44
 */
namespace Helper;

/**
 * Class File
 *
 * @package Helper
 */
class CSV
{
    const CSV_SEPARATOR = ',';
    const CSV_ENCLOSURE  = '"';
    const CSV_ESCAPE_CHAR  = '"';

    /**
     * @param resource $handle
     * @param array $fields
     *
     * @return bool
     */
    public static function writeRow($handle, array $fields)
    {
        $isWrite = fputcsv($handle, $fields, self::CSV_SEPARATOR, self::CSV_ENCLOSURE, self::CSV_ESCAPE_CHAR);
        
        return false === $isWrite ? false : true;
    }

    /**
     * @param resource $handle
     * @param array $columnNames
     *
     * @return array
     */
    public static function readRow($handle, array $columnNames = array())
    {
        $result = fgetcsv($handle, 0, self::CSV_SEPARATOR, self::CSV_ENCLOSURE, self::CSV_ESCAPE_CHAR);
        
        if (is_array($result) && $columnNames) {
            $result = array_combine($columnNames, $result);
        }

        return $result;
    }
    
    /**
     * @param string $file
     * @return bool(false)|int
     */
    public static function getRowCount($file)
    {
        $result = 0;
        
        if (is_readable($file)) {
            $handle = fopen($file, 'rb');
            
            if (false !== $handle) {
                while (false === feof($handle)) {
                    if (false === fgets($handle) && false === feof($handle)) {
                        $result = false;
                        break;
                    } else {
                        $result++;
                    }
                }
            }
        } else {
            $result = false;
        }
        
        return $result;
    }
}
