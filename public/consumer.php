<?php
require __DIR__ . '/../vendor/autoload.php';

use Pheanstalk\Pheanstalk;


//detectar sinais para finalizar script 
if (php_sapi_name() == "cli") {
    \pcntl_signal(SIGTERM, 'shutdown');
    \pcntl_signal(SIGINT, 'shutdown');
}

function shutdown($signal)
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

/**
 * IMPORTANTE: Um loop connection mantém a execução do worker e reconecta automaticamente
 * em caso de algum erro de conexão, é normal que sockets sofram desconexões por inatividade,
 * neste caso podemos reconectar automaticamente
 */
function loopConnection($callback)
{
    while (true) {
        try {
            $connection = Pheanstalk::create('beanstalkd');
            $callback($connection);
        } catch (\Pheanstalk\Exception\ConnectionException $e) {
            echo 'ConnectionException ' . $e->getMessage() . " | file:" . $e->getFile() . " | line:" . $e->getLine() . PHP_EOL;
            sleep(2);
        } catch (\Exception $e) {
            echo 'Exception ' . $e->getMessage() . " | file:" . $e->getFile() . " | line:" . $e->getLine() . PHP_EOL;
            sleep(2);
        }
    }
}

/**
 * Inicia o processamento
 */
loopConnection(function ($pheanstalk) {
    while (true) {

        $pheanstalk->watch('testtube');

        $job = $pheanstalk->reserve();

        if (isset($job)) {
            pcntl_sigprocmask(SIG_BLOCK, [SIGTERM, SIGINT]);

            try {
                $task = $job->getData();

                var_dump($task);

                echo "Deleting job: {$job->getId()}\n";

                $pheanstalk->delete($job);
            } catch (\Throwable $t) {
                echo "\n{$t->getMessage()}\n";

                // liberando para outro worker pegar de novo
                $pheanstalk->release($job);
            }

            pcntl_sigprocmask(SIG_UNBLOCK, [SIGTERM, SIGINT]);
        }

        // delay de processamento, para evitar sobrecarga
        sleep(1);

        pcntl_signal_dispatch();
    }
});
