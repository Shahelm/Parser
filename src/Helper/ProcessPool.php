<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 01.07.15
 * Time: 21:26
 */
namespace Helper;

use Symfony\Component\Process\Process;

/**
 * Class CommandPool
 *
 * @package Exceptions
 */
class ProcessPool
{
    /**
     * @var \SplQueue
     */
    private $processesQueue;
    
    /**
     * @var int
     */
    private $poolSize;

    /**
     * @var int
     */
    private $iterationTimOut;

    /**
     * @var callback
     */
    private $successCallback;

    /**
     * @var callback
     */
    private $errorCallback;

    /**
     * @var callback
     */
    private $finishCallback;
    
    /**
     * @param \SplQueue $processesQueue
     * @param int $poolSize
     * @param int $iterationTimOut
     */
    public function __construct(\SplQueue $processesQueue, $poolSize, $iterationTimOut)
    {
        $this->processesQueue = $processesQueue;
        $this->poolSize = $poolSize;
        $this->iterationTimOut = $iterationTimOut;
    }

    /**
     * @param callback $callback
     *
     * @return $this
     */
    public function onProcessSuccess($callback)
    {
        $this->successCallback = $callback;
        return $this;
    }

    /**
     * @param callback $callback
     *
     * @return $this
     */
    public function onProcessError($callback)
    {
        $this->errorCallback = $callback;
        return $this;
    }

    /**
     * @param callback $callback
     *
     * @return $this
     */
    public function onProcessFinish($callback)
    {
        $this->finishCallback = $callback;
        return $this;
    }

    /**
     * @return void
     */
    public function wait()
    {
        /**
         * @var Process[] $activeProcess
         */
        $activeProcess = [];

        while (true) {
            if (empty($activeProcess) && $this->processesQueue->isEmpty()) {
                break;
            }

            foreach ($activeProcess as $key => $process) {
                if (false === $process->isRunning()) {
                    if ($process->isSuccessful()) {
                        if (is_callable($this->successCallback)) {
                            call_user_func($this->successCallback, $process);
                        }
                    } else {
                        if (is_callable($this->errorCallback)) {
                            call_user_func($this->errorCallback, $process);
                        }
                    }

                    if (is_callable($this->finishCallback)) {
                        call_user_func($this->finishCallback, $process);
                    }

                    unset($activeProcess[$key]);
                }
            }

            if (count($activeProcess) < $this->poolSize && false === $this->processesQueue->isEmpty()) {
                $numbersOfProcess = $this->poolSize - count($activeProcess);

                for ($i = 0; $i < $numbersOfProcess; $i++) {
                    if (false === $this->processesQueue->isEmpty()) {
                        $process = $this->processesQueue->dequeue();
                        $activeProcess[] = $process;
                        $process->start();
                    }
                }
            }


            sleep($this->iterationTimOut);
        }
    }
}
