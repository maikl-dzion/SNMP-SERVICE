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
echo "\n ---------------------------------------------------- \n\n";

try {

    $config      = getConfig();
    $rabbitConf  = $config['rabbit'];
    $sendApiConf = $config['send_data_api'];



    $connection = new AMQPStreamConnection($rabbitConf['host'],
                                           $rabbitConf['port'],
                                           $rabbitConf['user'],
                                           $rabbitConf['password']);
    // lg($connection);

    $main = new SNMPController($connection, $sendApiConf);
    $main->run();

    // $main->sendTest();

    // getMessages($connection);
    // addMessages($connection);

//    $channel = $connection->channel();
//    $channel->queue_declare(QUEUE_NAME, false, false, false, false);
////    echo " [*] Waiting for messages. To exit press CTRL+C\n";
////    $readMessage = function (AMQPMessage $message) {
////        // messageProcessing($message);
////        echo ' [x] Received ', $message->body, "\n";
////    };
//
//    // $channel->basic_consume(QUEUE_NAME, '', false, true, false, false, $readMessage);
//    $message = $channel->basic_get(QUEUE_NAME, true);
//    // $channel->wait();
//    snmpRun($message);
//
//    $channel->close();
//    $connection->close();

    // print_r($r);

//    $main = new SNMPController($rabbitMqConnect);
//    $result = $main->walk(true);
//    $result = $main->run();
// $result = snmpwalk('192.168.2.184', "public", "");
//    for($i = 1; $i <= 10; $i++) {
//        $newMessage = $i .' 192.168.2.184 161 SNMP tttt';
//        $main->addMessage($newMessage);
//    }
    // lg($result);
    echo "\n\n ---- ###########----  SNMP-OK  ----- ######### ----- \n\n";

} catch(\Exception $e){
    echo "\n\n ---- ###########----  SNMP-ERROR  ----- ######### ----- \n\n";
    $errorMessage = $e->getMessage();
    lg($errorMessage);
}

echo "\n\n ---------------------------------------------------- \n";
echo "      ---  Finish=" . date('d.m.Y H:i:s') . "  ---   ";
echo "\n ---------------------------------------------------- \n\n";


/////////////////////////////////////////
///
///
///


function getMessages($connection) {

    $channel = $connection->channel();
    $channel->queue_declare(QUEUE_NAME, false, false, false, false);

    echo " [*] Waiting for messages. To exit press CTRL+C\n";

    $readMessage = function (AMQPMessage $message) {
        messageProcessing($message);
        echo ' [x] Received ', $message->body, "\n";
    };

    $channel->basic_consume(QUEUE_NAME, '', false, true, false, false, $readMessage);

//    while ($channel->is_consuming()) {
//        $channel->wait();
//    }

    $channel->wait();

    $channel->close();
    $connection->close();

}

function snmpRun(AMQPMessage $message) {
    $content = $message->body;
    $item = explode(' ', $content);
    list($messageId, $ip, $port, $type) = $item;
    $result = snmpwalk($ip, "public", "");
    $state = logger($content, $ip, $result, $messageId);
    echo ' [x] Received ', $content, "\n";
}


function logger($message, $ip, $result, $messageId = '') {
    $datetime = date('d_m_Y___H_i_s');
    $fileName =  $ip . '__id' . $messageId . '__' . $datetime . '__log.txt';
    $path = LOG_DIR . '/' . $fileName;
    $content  = $message . "\n\n";

    foreach($result as $key => $value) {
        $content .= $value . "\n";
    }

    return file_put_contents($path, $content);
}

function addMessages($connection, $newMessage = '45 192.168.2.184 161 SNMP') {

    $channel = $connection->channel();
    $channel->queue_declare(QUEUE_NAME, false, true, false, false);
    $msg = new AMQPMessage($newMessage);
    $channel->basic_publish($msg, '', QUEUE_NAME);

    echo " [x] Sent 'Add Message Ok!'\n";

    $channel->close();
    $connection->close();

}