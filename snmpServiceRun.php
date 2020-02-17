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

echo "\n\n ---------------------------------------------------- \n";
echo "     ---  Start=" . date('d.m.Y H:i:s') . "   ---  ";
echo "\n  ---------------------------------------------------- \n\n";

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

    echo "\n\n ---- ###########----  SNMP-OK  ----- ######### ----- \n\n";

} catch(\Exception $e){

    echo "\n\n ---- ###########----  SNMP-ERROR  ----- ######### ----- \n\n";
    $errorMessage = $e->getMessage();
    lg($errorMessage);

}

echo "\n\n ---------------------------------------------------- \n";
echo "      ---  Finish=" . date('d.m.Y H:i:s') . "  ---   ";
echo "\n ---------------------------------------------------- \n\n";

