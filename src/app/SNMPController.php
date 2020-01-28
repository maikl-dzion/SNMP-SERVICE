<?php

namespace Src\App;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class SNMPController {

//    protected $ipAddress;
//    protected $port;
//    protected $message;
//    protected $messages = array();
//    protected $messageId;
//    protected $type;
//    protected $params = array();
    protected $rabbitConnect;

    public function __construct(AMQPStreamConnection $rabbit) {
        $this->rabbitConnect = $rabbit;
    }

    public function run() {
        $message = $this->getMessage();
        $state   = $this->snmpWalk($message);
        print_r($state);
    }

    protected function getMessage() : AMQPMessage {

        $channel = $this->rabbitConnect->channel();
        $channel->queue_declare(QUEUE_NAME, false, false, false, false);
        $message = $channel->basic_get(QUEUE_NAME, true);

        // $channel->wait();
        // snmpRun($message);

        $channel->close();
        $this->rabbitConnect->close();

        return $message;
    }

    protected function snmpWalk(AMQPMessage $message) {

        $content = $message->body;
        $item = explode(' ', $content);
        list($messageId, $ip, $port, $type) = $item;

        $result = snmpwalk($ip, "public", "");
        echo " [x] Snmp Walk Ok \n ";

        $this->sendData($result);
        
        $state = $this->logger($content, $ip, $result, $messageId);

        return $state;
    }

    protected function logger(string $message, string $ip, array $result, $messageId = '') {

        $datetime = date('d_m_Y___H_i_s');
        $fileName =  $ip . '__id_' . $messageId . '__' . $datetime . '__log.txt';
        $path     = LOG_DIR . '/' . $fileName;
        $content  = $message . "\n\n";

        foreach($result as $key => $value) {
            $content .= $value . "\n";
        }

        return file_put_contents($path, $content);
    }


    protected function sendData(array $result) {
        $r = $result;
        echo " [x] Send Data Ok \n ";
    }


//    protected function setParams() {
//         $this->ipAddress = $this->isValue($this->params, 'ip');
//         $this->port      = $this->isValue($this->params, 'port');
//    }
//
//    protected function isValue(array $arr, string $fname) :string {
//         if(!empty($arr[$fname]))
//             return $arr[$fname];
//         return false;
//    }
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