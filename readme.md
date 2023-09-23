# Exemplo de filas com beanstalkd

Este repositório contém um exemplo de como processar filas usando o beanstalkd

O exemplo conta com loopConnection, uma técnica que permite os workers manterem uma execução contínua, caso aconteça um erro de conexão, ela será regerada automaticamente e o fluxo de consumo da fila continua normalmente

## Graceful stop - parar somente após terminar task

Para garantir que os consumers/workers não reiniciem durante a execução de uma task, foram
implementados mecanismos que detectam os "signals" para encerrar o script e assim só finalizam
o script após terminar a task atual.

Isso é feito através dos signals: 
- SIGINT 
- SIGTERM

Usando os recursos:
- pcntl_sigprocmask(SIG_BLOCK, [SIGTERM, SIGINT]);
- pcntl_sigprocmask(SIG_UNBLOCK, [SIGTERM, SIGINT]);
- pcntl_signal_dispatch();


## Como subir o ambiente?

Para maior comodidade, basta usar o ambiente docker:

```shell
docker-compose up -d

#OU

docker compose up -d
```

## Como executar a fila?

Dentro da public tem 3 scripts que devem ser **executados via terminal**:

**public/publisher.php:** publica 10 itens na fila

**public/consumer.php:** consome os itens da fila de forma simples e com programação funcional

**public/consumer-with-class.php:** consome os itens da fila de forma simples utilizando uma classe que automatiza o processamento

