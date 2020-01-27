<?php

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

const ROOT_DIR = __DIR__;
require_once ROOT_DIR . '/src/bootstrap.php';
require_once ROOT_DIR . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Src\App\SNMPController;

try {

    $configs    = getConfig();
    $rabbitConf = $configs['rabbit'];

    $rabbitMqConnect = new AMQPStreamConnection($rabbitConf['host'],
                                                $rabbitConf['port'],
                                                $rabbitConf['user'],
                                                $rabbitConf['password']);

    $main   = new SNMPController($rabbitMqConnect);
    // $result = $main->walk(true);
    echo "Start-" . date('d.m.Y H:i:s') . "\n\n";
    $result = $main->walk();
    echo "Finish-" . date('d.m.Y H:i:s') . "\n\n";
    // lg($result);
    echo 'Snmp-Ok';

} catch(\Exception $e){
    lg($e->getMessage());
}
