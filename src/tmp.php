<?php


try {

//    $ip = '190.169.1.5';
//
//    $snmp = new SNMP(SNMP::VERSION_1, $ip, "public");
//    $get_iod_temperature = $snmp->get("iso.3.6.1.2.1.7.5.1.2.0.0.0.0.8520");

//    $session = new SNMP(SNMP::VERSION_1, "190.169.1.5", "public");
//    // $results = $session->get();
//    print_r($session);
//    $session->close();

//    $syscontact = snmp3_get("190.169.1.5", '', '', '',
//                            '', '', '', '');
    //lg($get_iod_temperature);

    $config      = getConfig();
    $rabbitConf  = $config['rabbit'];
    $sendApiConf = $config['send_data_api'];

    $connect = new AMQPStreamConnection(
        $rabbitConf['host'],
        $rabbitConf['port'],
        $rabbitConf['user'],
        $rabbitConf['password']);

    $main = new SNMPController($connect, $sendApiConf);

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