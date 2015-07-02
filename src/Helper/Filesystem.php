<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 01.07.15
 * Time: 1:24
 */
namespace Helper;

use Exceptions\ApplicationException;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * Class Filesystem
 *
 * @package Helper
 */
class Filesystem extends \Symfony\Component\Filesystem\Filesystem
{
    /**
     * @param string $path
     *
     * @return void
     *
     * @throws ApplicationException
     */
    public function createDirIfNotExist($path)
    {
        if (false === $this->exists($path)) {
            try {
                $this->mkdir($path);
            } catch (IOException $e) {
                throw ApplicationException::wrapException($e);
            }
        }
    }
}
