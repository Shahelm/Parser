<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 13.07.15
 * Time: 23:14
 */
namespace Helper;

use Guzzle\Http\Client;
use Guzzle\Http\Exception\MultiTransferException;
use Guzzle\Http\Message\RequestInterface;

/**
 * Class ExecutorParallelQuery
 *
 * @package Helper
 */
class ExecutorParallelQuery
{
    /**
     * @var Client
     */
    private $client;
    
    /**
     * @var array
     */
    private $requests;
    
    /**
     * @var int
     */
    private $poolSize;
    
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
    private $afterProcessingCallback;

    /**
     * @param Client $client
     * @param RequestInterface[] $requests
     * @param int $poolSize
     */
    public function __construct(Client $client, array $requests, $poolSize)
    {
        $this->client = $client;
        $this->requests = $requests;
        $this->poolSize = $poolSize;
    }

    /**
     * @param $callback
     *
     * @return $this
     */
    public function onSuccess($callback)
    {
        $this->successCallback = $callback;
        
        return $this;
    }

    /**
     * @param $callback
     *
     * @return $this
     */
    public function onError($callback)
    {
        $this->errorCallback = $callback;

        return $this;
    }

    /**
     * @param $callback
     *
     * @return $this
     */
    public function afterProcessing($callback)
    {
        $this->afterProcessingCallback = $callback;
        
        return $this;
    }
    
    /**
     * @return void
     */
    public function wait()
    {
        foreach (array_chunk($this->requests, $this->poolSize, true) as $requests) {
            try {
                /**
                 * @var \Guzzle\Http\Message\Response[] $responses
                 */
                $this->client->send($requests);

                if (is_callable($this->successCallback)) {
                    /**
                     * @var \Guzzle\Http\Message\Request $request
                     */
                    foreach ($requests as $index => $request) {
                        call_user_func($this->successCallback, $request, $index);
                    }
                }
            } catch (MultiTransferException $e) {
                if (is_callable($this->errorCallback)) {
                    /**
                     * @var RequestInterface $request
                     */
                    foreach ($e->getFailedRequests() as $request) {
                        call_user_func($this->errorCallback, $request);
                    }
                }
                
                if (is_callable($this->successCallback)) {
                    foreach ($e->getSuccessfulRequests() as $index => $request) {
                        call_user_func($this->successCallback, $request, $index);
                    }
                }
            }
            
            if (is_callable($this->afterProcessingCallback)) {
                foreach ($requests as $index => $request) {
                    call_user_func($this->afterProcessingCallback, $request, $index);
                }
            }
        }
    }
}
