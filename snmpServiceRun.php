<?php

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();

declare(ticks=1);

const ROOT_DIR = __DIR__;
require_once ROOT_DIR . '/src/bootstrap.php';
require_once ROOT_DIR . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Src\App\SNMPController;

_echo('Start');

try {

    $config        = getConfig();
    $rabbitConfig  = $config['rabbit'];
    $sendApiConfig = $config['send_data_api'];

    $connect = new AMQPStreamConnection(
                    $rabbitConfig['host'],
                    $rabbitConfig['port'],
                    $rabbitConfig['user'],
                    $rabbitConfig['password']);

    $main = new SNMPController($connect, $sendApiConfig, LOG_DIR);
    $main->run();

} catch(\Exception $e){
    _echo('SNMP - ERROR');
    $errorMessage = $e->getMessage();
    lg($errorMessage);
}

_echo('SNMP - OK');
_echo('Finish');

//////////////////////////////////
///
///


function _echo($data) {
    echo "\n\n ---------------------------------------------------- \n";
    echo "      ---  {$data}=" . date('d.m.Y H:i:s') . "  ---   ";
    echo "\n ---------------------------------------------------- \n\n";
}