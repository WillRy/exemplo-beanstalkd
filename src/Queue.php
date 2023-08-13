<?php

namespace WillRy\Bean;

use Pheanstalk\Pheanstalk;

class Queue
{
    protected string $connectionHost;

    protected Pheanstalk $connection;

    protected \Closure $onReceiveCallback;

    protected \Closure $onExecutingCallback;

    protected \Closure $onErrorCallback;

    protected \Closure $onCheckStatusCallback;


    public function __construct(string $connectionHost)
    {
        $this->connectionHost = $connectionHost;
        $this->connection = Pheanstalk::create('beanstalkd');
    }

    public function onCheckStatus(\Closure $callback)
    {
        $this->onCheckStatusCallback = $callback;
    }

    public function onReceive(\Closure $callback)
    {
        $this->onReceiveCallback = $callback;
    }

    public function onExecuting(\Closure $callback)
    {
        $this->onExecutingCallback = $callback;
    }

    public function onError(\Closure $callback)
    {
        $this->onErrorCallback = $callback;
    }

    public function loopConnection($callback)
    {
        while (true) {
            try {
                $callback($this->connection);
            } catch (\Pheanstalk\Exception\ConnectionException $e) {
                echo 'ConnectionException ' . $e->getMessage() . " | file:" . $e->getFile() . " | line:" . $e->getLine() . PHP_EOL;
                sleep(2);
            } catch (\Exception $e) {
                echo 'Exception ' . $e->getMessage() . " | file:" . $e->getFile() . " | line:" . $e->getLine() . PHP_EOL;
                sleep(2);
            }
        }
    }

    public function consume(string $tubeName)
    {

        $this->loopConnection(function (Pheanstalk $beanstalk) use ($tubeName) {
            while (true) {

                $beanstalk->watch($tubeName);

                $job = $beanstalk->reserve();

                if (isset($job)) {
                    try {

                        if (!empty($this->onCheckStatusCallback)) {
                            $checkStatusCallback = $this->onCheckStatusCallback;
                            $statusBoolean = $checkStatusCallback($job);

                            if (!$statusBoolean && isset($statusBoolean)) {
                                print_r("[WORKER STOPPED]" . PHP_EOL);
                                return $beanstalk->release($job);
                            }
                        }

                        $task = $job->getData();

                        if (!empty($this->onReceiveCallback)) {
                            $receiveCallback = $this->onReceiveCallback;
                            $statusBoolean = $receiveCallback($job);

                            if (!$statusBoolean && isset($statusBoolean)) {
                                print_r("[TASK IGNORED BY ON RECEIVE RETURN]" . PHP_EOL);
                                return $beanstalk->delete($job);
                            }
                        }

                        var_dump($task);

                        $executingCallback = $this->onExecutingCallback;
                        $executingCallback($beanstalk, $job);
                    } catch (\Throwable $t) {
                        echo "\n{$t->getMessage()}\n";

                        if (!empty($this->onErrorCallback)) {
                            print_r("[ERROR]" . PHP_EOL);
                            $errorCallback = $this->onErrorCallback;
                            $errorCallback($beanstalk, $job);
                        }
                    }
                }

                // delay de processamento, para evitar sobrecarga
                sleep(1);
            }
        });
    }
}
