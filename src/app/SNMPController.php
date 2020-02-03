<?php

namespace Src\App;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class SNMPController {

    protected $rabbitConnect;
    protected $sendApiConf;

    public function __construct(AMQPStreamConnection $rabbit, $sendApiConf) {
        $this->rabbitConnect = $rabbit;
        $this->sendApiConf   = $sendApiConf;
    }

    public function run() {

        $message  = $this->getMessage();

        // print_r($message); die;

        $data     = $this->snmpQuery($message);

        // print_r($data); die;

        $sendData = $this->dataFormatted($data);

        // print_r($sendData); die;

        $this->sendData($sendData);

        return true;
    }

    protected function getMessage() : AMQPMessage {

        $channel = $this->rabbitConnect->channel();
        $channel->queue_declare(QUEUE_NAME, false, false, false, false);
        $message = $channel->basic_get(QUEUE_NAME, true);
        $channel->close();
        $this->rabbitConnect->close();

        $this->_log('Get message - Ok', $message->body, __LINE__, __FUNCTION__);

        return $message;
    }

    protected function snmpQuery(AMQPMessage $message) {

        $content = $message->body;
        $item    = explode(' ', $content);

        // $data = snmpwalk('192.168.2.184', "public", "");

        $messageId = $this->isValue($item, 0);
        $ip   = trim($this->isValue($item, 1));
        $port = $this->isValue($item, 2);
        $type = $this->isValue($item, 3);

        $snmpData = array();

        $data = $this->snmpWalk($ip);

        if(isset($data['end_line']))
            $logValue = $data['end_line'];

        if(isset($data['data']))
            $snmpData = $data['data'];

        $this->_log('Snmp Walk - Ok', $logValue, __LINE__, __FUNCTION__);

        $this->logger($content, $ip, $snmpData, $messageId);

        return $snmpData;
    }

    protected function snmpWalk($ip, $shema = 'public', $ver = '-v2c') {
        // snmpwalk -c public -v2c 192.168.2.184 iso.3.6.1.2.1.25.3.2.1.3.1
        // $data = snmpwalk($ip, "public", "");
        $snmpCmd = "snmpwalk -c " .$shema. " " .$ver. " " . $ip;
        return $this->commandRun($snmpCmd);
    }

    protected function commandRun($cmd) {
        $output = array();
        $endLine = exec($cmd, $output, $returnVar);
        return array(
            'end_line'  => $endLine,
            'cmd'       => $cmd,
            'var_state' => $returnVar,
            'data'      => $output,
        );
    }

    protected function logger(string $message, string $ip, $data = array(), $messageId = '') {

        $saveResult = '';

        $datetime = date('d_m_Y___H_i_s');
        $fileName =  $ip . '__id_' . $messageId . '__' . $datetime . '__log.txt';
        $path     = LOG_DIR . '/' . $fileName;
        $content  = $message . "\n\n";

        foreach($data as $key => $value) {
            $content .= $value . "\n";
        }

        // $saveResult = file_put_contents($path, $content);

        $this->_log('Save Log file , FaleName -' . $path, $saveResult, __LINE__, __FUNCTION__);

        return $saveResult;
    }


    protected function sendData(array $postData) {

        $host = $this->sendApiConf['host'];
        $port = $this->sendApiConf['port'];

        $url = $host . ':' . $port;
        $jsonData = json_encode($postData, JSON_UNESCAPED_UNICODE);
        $jsonErrorMsg  = json_last_error_msg();
        $this->_log('Send Data - Json Error', $jsonErrorMsg, __LINE__, __FUNCTION__);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $result = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        $this->_log('Send Json Data - Ok, Curl result -' . $result, $result , __LINE__, __FUNCTION__);

    }

    public function sendTest() {

        $data = snmpwalk('192.168.2.184', "public", "");
        $postData = $this->dataFormatted($data);
        $this->sendData($postData);

    }

    protected function dataFormatted(array $data) :array {

        $postData = array();
        // print_r($data); die;
        foreach($data as $key => $values) {

            $item = explode('=', $values);
            // print_r($item); die;
            $fieldName = $this->isValue($item, 0);
            $data  = $this->isValue($item, 1);

            if(!is_array($fieldName) && $fieldName) {
                $res = explode(':', $data);
                // print_r($res); die;
                if(!empty($res[0])) {
                    // print_r($res); die;
                    $type  = trim($res[0]);
                    $param = 0;
                    if(!empty($res[1]))
                       $param = trim($res[1]);
                    // print_r($type); die;
                    switch ($type) {
                        case 'INTEGER' :
                              $postData[$fieldName] = (integer)($param);
                              // print_r($postData); die;
                              break;
                    }
                }

                //  $index = trim($field);
                //  $data  = trim($this->cleanStr($data));
            }
        }

        // print_r($postData); die;

        return $postData;
    }

    protected function cleanStr($string) {
        return preg_replace('/[^A-Za-z0-9" "\-]/', '', $string);
    }

    protected function isValue(array $arr, string $fname) :string {
        if(!empty($arr[$fname]))
            return $arr[$fname];
        return false;
    }

    protected function _log($title, $data, $line, $funcName){

        $s   = "";
        $del = "########################## ";
        $n   = "\n";

        $values = print_r($data, true);

        $log  = $s . 'Title:' . $title . $s . $n;
        $log .= $s . 'Data:'  . $values  . $s . $n;
        $log .= $s . 'Line:'  . $line  . $s . $n;
        $log .= $s . 'FuncName:'  . $funcName  . $s ;

        $log = $n . $n . $del. $n . $log .$n . $del .$n . $n;
        echo $log;

        return $log;
    }

//    protected function setParams() {
//         $this->ipAddress = $this->isValue($this->params, 'ip');
//         $this->port      = $this->isValue($this->params, 'port');
//    }
//

//
//    public function run($while = false){
//        $this->getRabbitInfo($while = false);
//        // $this->addMessageRabbit('maikl add new message');
//    }
//
//    public function walk($while = false) {
//
//        // $this->getRabbitInfo($while)
//        // lg($this);
//        // die($this->port);
//
//        // $this->port = '167';
//        // $this->ipAddress = '192.168.2.184';
//
//        $port   = $this->port;
//        $ip     = $this->ipAddress;
//        $result = snmpwalk($ip, "public", "");
//
//        $date = date('d_m_Y___H_i_s');
//        $fileName = $this->messageId . '--' .$date. ' --log.txt';
//        $message  = $this->message;
//        $this->logger($message, $fileName);
//
//        echo "\n" . "[*] SaveInfo Ok {$ip}" . "\n";
//
//        print_r($result); die;
//
//        return $result;
//    }
//
//    public function getLanInfo() {
//        $port = $this->port;
//        $ip   = $this->ipAddress;
//        $result = snmpwalkoid($ip, "public", "");
//        return $result;
//    }
//
//    public function getRabbitInfo($while = false) {
//
//        $connection = $this->rabbit;
//        $channel    = $connection->channel();
//
//        $channel->queue_declare(QUEUE_NAME, false, false, false, false);
//
//        echo ' [*] Waiting for messages. To exit press CTRL+C', "\n";
//
//        //Функция, которая будет обрабатывать данные, полученные из очереди
//        $callbackInfoProcessing = function($message) {
//            $this->rabbitMessageProcessing($message);
//            echo " [x] Received ", $message->body, "\n";
//            //$channel->close();
//            //$connection->close();
//        };
//
//        //Уходим слушать сообщения из очереди в бесконечный цикл
//        $channel->basic_consume(QUEUE_NAME, '', false, true, false, false, $callbackInfoProcessing);
//
//        if($while) {   // -- Бесконечный цикл  -- ctrl+c = stop
//            while (count($channel->callbacks)) {
//                $channel->wait();
//            }
//        } else {  // Одно выполнение
//            // $channel->wait();
//        }
//
//        // $channel->wait();
//
//        // Не забываем закрыть соединение и канал
//        $channel->close();
//        $connection->close();
//    }
//
//    protected function rabbitMessageProcessing($message){
//        //if($message) return false;
//
//        $body = $this->message = $message->body;
//        $data = $this->messages  = explode(' ', $body);
//        $this->messageId = $this->isValue($data, 0);
//        $this->ipAddress = $this->isValue($data, 1);
//        $this->port      = $this->isValue($data, 2);
//        $this->type      = $this->isValue($data, 3);
//
//        $this->walk();
//
//        return true;
//    }
//
//    public function addMessage(string $newMessage = '') {
//
////        $messageId = 1;
////        if(!empty($_SESSION['message_id'])) {
////            $_SESSION['message_id']++;
////            $messageId = $_SESSION['message_id'];
////        } else {
////            $_SESSION['message_id'] = $messageId;
////        }
////        $newMessage = $messageId .' 192.168.2.184 161 SNMP';
//
//        $this->addMessageRabbit($newMessage);
//    }
//
//    protected function addMessageRabbit($newMessage = '') {
//
//        $connection = $this->rabbit;
//
//        //Берем канал и декларируем в нем новую очередь, первый аргумент - название
//        $channel = $connection->channel();
//        // $channel->queue_declare(QUEUE_NAME, false, false, false, false);
//
//        //Создаем новое сообщение
//        // $msg = new AMQPMessage($newMessage);
//
//        //Отправляем его в очередь
//        // $channel->basic_publish($msg, '', 'hello');
//
//        echo " [x] Sent 'Hello World!'\n";
//
//        $channel->basic_publish(new AMQPMessage($newMessage), '', QUEUE_NAME);
//
//        //Не забываем закрыть канал и соединение
//        $channel->close();
//        $connection->close();
//    }
//
//    protected function logger($message, $fileName) {
//        $path = LOGS_DIR . '/' . $fileName;
//        return file_put_contents($path, $message);
//    }

}