<?php
require __DIR__ . '/../vendor/autoload.php';

use Pheanstalk\Pheanstalk;

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
        }

        // delay de processamento, para evitar sobrecarga
        sleep(1);
    }
});
