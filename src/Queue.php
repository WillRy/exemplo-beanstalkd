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

        /**
         * Graceful shutdown
         * Faz a execucao parar ao enviar um sinal do linux para matar o script
         */
        if (php_sapi_name() == "cli") {
            \pcntl_signal(SIGTERM, function ($signal) {
                $this->shutdown($signal);
            }, false);
            \pcntl_signal(SIGINT, function ($signal) {
                $this->shutdown($signal);
            }, false);
        }
    }

    /**
     * Garante o desligamento correto dos workers
     * via sinal no sistema operacional
     * eliminando loops e conexões
     * @param $signal
     */
    public function shutdown($signal)
    {
        $data = date('Y-m-d H:i:s');
        switch ($signal) {
            case SIGTERM:
                print "Caught SIGTERM {$data}" . PHP_EOL;
                exit;
            case SIGKILL:
                print "Caught SIGKILL {$data}" . PHP_EOL;;
                exit;
            case SIGINT:
                print "Caught SIGINT {$data}" . PHP_EOL;;
                exit;
        }
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

                    pcntl_sigprocmask(SIG_BLOCK, [SIGTERM, SIGINT]);

                    try {

                        if (!empty($this->onCheckStatusCallback)) {
                            $checkStatusCallback = $this->onCheckStatusCallback;
                            $statusBoolean = $checkStatusCallback($job);

                            if (!$statusBoolean) {
                                print_r("[WORKER STOPPED]" . PHP_EOL);
                                pcntl_sigprocmask(SIG_UNBLOCK, [SIGTERM, SIGINT]);
                                return $beanstalk->release($job);
                            }
                        }


                        if (!empty($this->onReceiveCallback)) {
                            $receiveCallback = $this->onReceiveCallback;
                            $statusBoolean = $receiveCallback($job);

                            if (!$statusBoolean) {
                                print_r("[TASK IGNORED BY ON RECEIVE RETURN]" . PHP_EOL);
                                pcntl_sigprocmask(SIG_UNBLOCK, [SIGTERM, SIGINT]);
                                return $beanstalk->delete($job);
                            }
                        }


                        $executingCallback = $this->onExecutingCallback;
                        $executingCallback($beanstalk, $job);
                    } catch (\Throwable $t) {

                        if (!empty($this->onErrorCallback)) {
                            print_r("[ERROR]: {$t->getMessage()}" . PHP_EOL);
                            $errorCallback = $this->onErrorCallback;
                            $errorCallback($beanstalk, $job);
                        }
                    }

                    pcntl_sigprocmask(SIG_UNBLOCK, [SIGTERM, SIGINT]);
                }

                // delay de processamento, para evitar sobrecarga
                sleep(1);

                pcntl_signal_dispatch();
            }
        });
    }
}
