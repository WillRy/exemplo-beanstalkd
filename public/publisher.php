<?php
require __DIR__ . '/../vendor/autoload.php';

use Pheanstalk\Pheanstalk;

$pheanstalk = Pheanstalk::create('beanstalkd');

// Queue a Job

for ($i=0; $i < 500; $i++) { 
    $pheanstalk
    ->useTube('testtube')
    ->put(
        json_encode(['id' => $i, 'email' => "fulano{$i}@email.com","conteudo" => "blablabla"]),  // encode data in payload
        Pheanstalk::DEFAULT_PRIORITY,     // default priority
        3, // delay by 3
        120  // beanstalk will retry job after 120s
     );
}