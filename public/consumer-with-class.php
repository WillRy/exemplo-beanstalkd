<?php
require __DIR__ . '/../vendor/autoload.php';

use Pheanstalk\Contract\JobIdInterface;
use Pheanstalk\Job;
use Pheanstalk\Pheanstalk;
use WillRy\Bean\Queue;

$queue = new Queue("beanstalkd");


/**
 * Callback que o retorno boolean determina se o item
 * vai ser processado ou devolvido para a fila, util quando
 * queremos dar uma pausa nos workers
 */
$queue->onCheckStatus(function (Job $job) {
    return true;
});

/**
 * Callback que o retorno boolean determina se o item
 * vai ser excluído ou vai continuar o processamento
 * 
 * Util quando queremos validar se o item não é muito velho na fila,
 * se foi anulado via banco de dados ou algo o tipo
 */
$queue->onReceive(function (Job $job) {
    return true;
});


/**
 * Callback que vai executar a fila, recebendo o job e o beanstalkd
 * para que o usuário controle o que fazer com o Job recebido.
 * 
 * O usuário pode:
 * - Deletar o job (em caso de erro ou sucesso)
 * - Liberar o job para outros workers
 */
$queue->onExecuting(function (Pheanstalk $beanstalk, Job $job) {
    print_r("[EXECUTING]" . PHP_EOL);

    var_dump($job->getData());


    $randError = rand(0, 10) % 2 === 0;

    if ($randError) {
        throw new \Exception("Erro de processamento forçado");
    }

    $beanstalk->delete($job);

    return true;
});

/**
 * Callback que vai executar a caso aconteça alguma exception inesperada
 * e não tratada por try/catch no "onExecuting"
 */
$queue->onError(function (Pheanstalk $beanstalk, Job $job) {

    $beanstalk->release($job);

    return true;
});

$queue->consume("testtube");
