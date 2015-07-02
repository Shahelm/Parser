<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 30.06.15
 * Time: 23:14
 */
namespace Helper;

use Monolog\Logger;

/**
 * Class Resource
 *
 * @package Helper
 *
 * @property Logger $logger
 */
trait Resource
{
    /**
     * @param string $filePath
     * @param string $mode
     * @param bool $onErrorExit
     *
     * @return resource
     */
    protected function openResource($filePath, $mode, $onErrorExit = true)
    {
        $handle = fopen($filePath, $mode);

        if (false === $handle) {
            $this->logger->addAlert('Failed create a file handle.', ['file' => $filePath, 'mode' => $mode]);

            if ($onErrorExit) {
                exit(1);
            }
        }

        return $handle;
    }

    /**
     * @param resource $handle
     * @param string $file
     *
     * @return void
     */
    protected function closeResource($handle, $file)
    {
        $isClosed = fclose($handle);

        if (false === $isClosed) {
            $this->logger->addWarning('Unable to close the file descriptor', ['file' => $file]);
        }
    }
}
