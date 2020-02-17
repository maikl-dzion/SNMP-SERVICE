<?php


////////////////////////////////////
/// SNMP -  Functions


function _snmpWalkData($ip, $shema = 'public', $ver = '-v2c') {
    $snmpCmd = "snmpwalk -c " .$shema. " " .$ver. " " . $ip;
    // snmpwalk -c public -v2c 192.168.2.184 iso.3.6.1.2.1.25.3.2.1.3.1
    return cmdRun($snmpCmd);
}

function _cmdRun($cmd) {
    $output = array();
    $r = exec($cmd, $output, $returnVar);
    return array(
        'r'   => $r,
        'cmd' => $cmd,
        'return_var' => $returnVar,
        'output'     => $output,
    );
}


///////////////////////////////////
// ----- Helpers Functions -------

function getConfig($configDir = CONFIG_DIR) {

    $configs = array();
    $files   = scandir($configDir);

    foreach($files as $key => $file) {
        if($file == '.' || $file == '..')
            continue;

        $path = $configDir .'/'. $file;
        $conf = require_once $path;
        $arr  = explode('.', $file);
        $configs[$arr[0]] = $conf;
    }

    return $configs;
}

function lg() {

    $debugTrace = debug_backtrace();
    $args = func_get_args();

    $get = false;
    $output = $traceStr = '';

    $style = 'margin:10px; padding:10px; border:3px red solid;';

    foreach ($args as $key => $value) {
        $itemArr = array();
        $itemStr = '';
        is_array($value) ? $itemArr = $value : $itemStr = $value;
        if ($itemStr == 'get') $get = true;
        $line = print_r($value, true);
        $output .= '<div style="' . $style . '" ><pre>' . $line . '</pre></div>';
    }

    foreach ($debugTrace as $key => $value) {
        // if($key == 'args') continue;
        $itemArr = array();
        $itemStr = '';
        is_array($value) ? $itemArr = $value : $itemStr = $value;
        if ($itemStr == 'get') $get = true;
        $line = print_r($value, true);
        $output .= '<div style="' . $style . '" ><pre>' . $line . '</pre></div>';
    }

    // if ($get) return $output;

    print $output;

    die("--Lg--") ;

}

function error_log_handler($errno, $message, $filename, $line) {
    $date = date('Y-m-d H:i:s (T)');
    $fp   = fopen('error.txt', 'a');
    if(!empty($fp)) {
//        $filename  =str_replace(LOG_PATH,'', $filename);
//        $err  = " $message = $filename = $line\r\n ";
//        fwrite($fp, $err);
//        fclose($fp);
    }
}


//function getMessages($connection) {
//
//    $channel = $connection->channel();
//    $channel->queue_declare(QUEUE_NAME, false, false, false, false);
//
//    echo " [*] Waiting for messages. To exit press CTRL+C\n";
//
//    $readMessage = function (AMQPMessage $message) {
//        messageProcessing($message);
//        echo ' [x] Received ', $message->body, "\n";
//    };
//
//    $channel->basic_consume(QUEUE_NAME, '', false, true, false, false, $readMessage);
//
////    while ($channel->is_consuming()) {
////        $channel->wait();
////    }
//
//    $channel->wait();
//
//    $channel->close();
//    $connection->close();
//
//}
//
//function snmpRun(AMQPMessage $message) {
//    $content = $message->body;
//    $item = explode(' ', $content);
//    list($messageId, $ip, $port, $type) = $item;
//    $result = snmpwalk($ip, "public", "");
//    $state = logger($content, $ip, $result, $messageId);
//    echo ' [x] Received ', $content, "\n";
//}
//
//
//function logger($message, $ip, $result, $messageId = '') {
//    $datetime = date('d_m_Y___H_i_s');
//    $fileName =  $ip . '__id' . $messageId . '__' . $datetime . '__log.txt';
//    $path = LOG_DIR . '/' . $fileName;
//    $content  = $message . "\n\n";
//
//    foreach($result as $key => $value) {
//        $content .= $value . "\n";
//    }
//
//    return file_put_contents($path, $content);
//}
//
//function addMessages($connection, $newMessage = '45 192.168.2.184 161 SNMP') {
//
//    $channel = $connection->channel();
//    $channel->queue_declare(QUEUE_NAME, false, true, false, false);
//    $msg = new AMQPMessage($newMessage);
//    $channel->basic_publish($msg, '', QUEUE_NAME);
//
//    echo " [x] Sent 'Add Message Ok!'\n";
//
//    $channel->close();
//    $connection->close();
//
//}