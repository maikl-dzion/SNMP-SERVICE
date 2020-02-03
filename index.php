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


$runCount   = RUN_COUNT_DEFAULT;
$scriptName = SNMP_SCRIPT_NAME;

if(!empty($argv[1])) {
    $runCount = $argv[1];
}

echo "\n\n ---------------------------------------------------- \n";
echo "     ---  Start=" . date('d.m.Y H:i:s') . "   ---  ";
echo "\n ---------------------------------------------------- \n\n";

// print_r($argv); die;
$ip = '192.168.2.184';

try {

    $result = array();

    $config      = getConfig();
    $rabbitConf  = $config['rabbit'];
    $sendApiConf = $config['send_data_api'];

    $connection = new AMQPStreamConnection($rabbitConf['host'],
                                           $rabbitConf['port'],
                                           $rabbitConf['user'],
                                           $rabbitConf['password']);


    // $result = snmpWalkData($ip);
    // lg($result);


//    $scriptName = 'testSnmp.php';
//    $timer = 0;
//    for ($i = 0; $i <= $runCount; $i++) {
//        $num = $i + 1;
//        $cmd = "php {$scriptName} {$ip} {$num} >/dev/null 2>&1 &";
//        // $cmd = "php {$scriptName}";
//        exec($cmd);
//
//        if(!$timer > 20) {
//            sleep(2);
//            $timer = 0;
//        }
//
//        $timer++;
//    }

    echo "\n\n ---- ###########----  SNMP-OK  ----- ######### ----- \n\n";

} catch(\Exception $e){
    echo "\n\n ---- ###########----  SNMP-ERROR  ----- ######### ----- \n\n";
    $errorMessage = $e->getMessage();
    lg($errorMessage);
}

echo "\n\n ---------------------------------------------------- \n";
echo "      ---  Finish=" . date('d.m.Y H:i:s') . "  ---   ";
echo "\n ---------------------------------------------------- \n\n";

