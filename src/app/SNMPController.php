<?php

namespace Src\App;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class SNMPController {

    protected $ipAddress;
    protected $port;
    protected $message;
    protected $messages = array();
    protected $messageId;
    protected $type;
    protected $params = array();
    protected $rabbit;

    public function __construct(AMQPStreamConnection $rabbit) {
        $this->rabbit = $rabbit;
        // $this->setParams();
    }

    protected function setParams() {
         $this->ipAddress = $this->isValue($this->params, 'ip');
         $this->port      = $this->isValue($this->params, 'port');
    }

    protected function isValue(array $arr, string $fname) :string {
         if(!empty($arr[$fname]))
             return $arr[$fname];
         return false;
    }

    public function walk($while = false) {

        $this->getRabbitInfo($while)
        //lg($this);
        //die($this->port);

        $port   = $this->port;
        $ip     = $this->ipAddress;
        $result = snmpwalk($ip, "public", "");

        $date = date('d_m_Y___H_i_s');
        $fileName = $this->messageId . '--' .$date. ' --log.txt';
        $message  = $this->message;
        $this->logger($message, $fileName);

        return $result;
    }

    public function getLanInfo() {
        $port = $this->port;
        $ip   = $this->ipAddress;
        $result = snmpwalkoid($ip, "public", "");
        return $result;
    }

    public function getRabbitInfo($while = false) {

        $connection = $this->rabbit;
        $channel    = $connection->channel();

        $channel->queue_declare(QUEUE_NAME, false, false, false, false);

        echo ' [*] Waiting for messages. To exit press CTRL+C', "\n";

        //Функция, которая будет обрабатывать данные, полученные из очереди
        $callbackInfoProcessing = function($message) {
            $this->rabbitMessageProcessing($message);
            echo " [x] Received ", $message->body, "\n";
        };

        //Уходим слушать сообщения из очереди в бесконечный цикл
        $channel->basic_consume(QUEUE_NAME, '', false, true, false, false, $callbackInfoProcessing);

        if($while) {   // -- Бесконечный цикл  -- ctrl+c = stop
            while (count($channel->callbacks)) {
                $channel->wait();
            }
        } else {  // Одно выполнение
            $channel->wait();
        }

        // Не забываем закрыть соединение и канал
        $channel->close();
        $connection->close();
    }

    protected function rabbitMessageProcessing($message){
        //if($message) return false;

        $body = $message->body;
        $this->message = $body;
        $data = $this->messages  = explode(' ', $body);
        $this->messageId = $this->isValue($data, 0);
        $this->ipAddress = $this->isValue($data, 1);
        $this->port      = $this->isValue($data, 2);
        $this->type      = $this->isValue($data, 3);

        return true;
    }

    protected function addMessageRabbit($newMessage = '') {

        $connection = $this->rabbit;

        //Берем канал и декларируем в нем новую очередь, первый аргумент - название
        $channel = $connection->channel();
        $channel->queue_declare(QUEUE_NAME, false, false, false, false);

        //Создаем новое сообщение
        $msg = new AMQPMessage($newMessage);
        
        //Отправляем его в очередь
        $channel->basic_publish($msg, '', 'hello');

        echo " [x] Sent 'Hello World!'\n";

        //Не забываем закрыть канал и соединение
        $channel->close();
        $connection->close();
    }

    protected function logger($message, $fileName) {
        $path = LOGS_DIR . '/' . $fileName;
        return file_put_contents($path, $message);
    }

}