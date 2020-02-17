<?php

namespace Src\App;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class SNMPController {

    protected $rabbitConnect;
    protected $sendApiConfig;
    protected $logPath;
    const SEND_API_LINK = '/data/save';

    public function __construct(AMQPStreamConnection $rabbit, array $sendApiConfig, string $logPath) {
        $this->rabbitConnect = $rabbit;
        $this->sendApiConfig = $sendApiConfig;
        $this->logPath = $logPath;
    }

    protected function getMessageOfQueue() : AMQPMessage {

        $channel = $this->rabbitConnect->channel();
        $channel->queue_declare(QUEUE_NAME, false, false, false, false);
        $message = $channel->basic_get(QUEUE_NAME, true);
        $channel->close();
        $this->rabbitConnect->close();

        $logArray   = array('Line' => __LINE__, 'FuncName' => __FUNCTION__);
        $logMessage = 'Сообщение из очереди получено - OK';
        if(!empty($message->body)) {
            $logArray['Data'] = $message->body;
        } else {
            $logMessage = 'Сообщение из очереди НЕ получено - ERROR';
        }

        $this->logShow($logArray, $logMessage);

        return $message;
    }

    public function run() : bool {

        // -----------------------------
        // Получаем сообщение из очереди  --- Тип PhpAmqpLib\Message\AMQPMessage
        $message  = $this->getMessageOfQueue();

        // -----------------------------
        // Выполняем задание и обрабатываем данные
        $data  = $this->snmpExecute($message);

        $logArray  = array('Line' => __LINE__, 'FuncName' => __FUNCTION__, 'Data' => $data);
        if(empty($data)) {
            // $this->logShow($logArray, 'Сообщение НЕ удалось обработать - ERROR');
            return false;
        }
        // $this->logShow($logArray, 'Сообщение успешно обработано - OK');

        // -----------------------------
        // Данные успешно обработаны, отправляем на сохранение
        foreach ($data as $key => $item) {
            $this->sendData($item);
        }

        return true;
    }

    protected function snmpExecute(AMQPMessage $message, $action = 'get') : array {

        $content = $message->body;
        $item    = explode(' ', $content);

        $messageId = trim($this->isValue($item, 0));
        $ip   = trim($this->isValue($item, 1));
        $oid  = trim($this->isValue($item, 2));
        $port = trim($this->isValue($item, 3));
        $type = trim($this->isValue($item, 4));

        if(!$ip) {
            $logName = 'error/' . date('d.m.Y__H.i.s');
            $logData = array('title' => 'Пустой Ip-адрес', 'data' => $message);
            $this->saveLogger($logData, $logName , __FILE__, __LINE__);
            return array();
        }

        $data = array(
            'cmd'       => '',
            'end_line'  => '',
            'var_state' => '',
            'data'      => array("{$oid} = INTEGER : " . rand(10, 50)),
        );

        if($oid != 'signal_in' && $oid != 'signal_out') {
            $data = $this->snmpGet($ip, $oid);
        }

        if(empty($data['data'])) {
            $logName  = 'error/' . $ip;
            $logTitle = 'Сообщение НЕ получилось обработать - ERROR';
            $logData  = array('title' => $logTitle, 'data' => array($data));
            $this->saveLogger($logData, $logName , __FILE__, __LINE__);
            $this->logShow($logData, $logTitle);
            return array();
        }

        $logArray  = array('Line' => __LINE__, 'FuncName' => __FUNCTION__, 'Data' => $data['data']);
        $this->logShow($logArray, 'Сообщение успешно обработано - OK');

        $snmpData = $this->postDataFormatted($data['data'], $ip, $action);

        return $snmpData;
    }

    protected function postDataFormatted(array $data, $ip, $action = 'get') {

        $results = array();

        switch ($action) {
            case 'get' :
                $results = $this->getDataForm($data, $ip);
                break;

            case 'walk' :
                $results = $this->walkDataForm($data);
                break;
        }

        return $results;
    }

    protected function snmpWalk($ip, $shema = 'public', $ver = '-v2c') : array {
        // snmpwalk -c public -v2c 192.168.2.184 iso.3.6.1.2.1.25.3.2.1.3.1
        // $data = snmpwalk($ip, "public", "");
        if(!$this->pingIp($ip))
            return array();
        $snmpCommand = "snmpwalk -c " .$shema. " " .$ver. " " . $ip;
        return $this->commandRun($snmpCommand);
    }

    protected function snmpGet($ip, $oid, $shema = 'public', $ver = '-v2c') : array {
        // snmpwalk -c public -v2c 192.168.2.184 iso.3.6.1.2.1.25.3.2.1.3.1
        // $data = snmpwalk($ip, "public", "");
        // $ip = '192.168.2.45';

        if(!$this->pingIp($ip))
            return array();

        $kill = ' & pid=$! && sleep 4 && kill -9 $pid';
        $snmpCommand = "snmpget {$ver} -c {$shema}  {$ip} {$oid} " . $kill;
        return $this->commandRun($snmpCommand);

        //$snmpCommand = "snmpget {$ver} -c {$shema}  {$ip} {$oid}";
        //return $this->commandRun($snmpCommand);
    }

    protected function pingIp(string $ip) {
        $cmd = "ping -w 3 -c 3  {$ip}";
        $result = $this->commandRun($cmd);
        if(!empty($result['data'])) {
            foreach ($result['data'] as $key => $value) {
                if(!$this->_find($value, 'transmitted')) continue;
                if($this->_find($value, '0 received')){
                    $logName  = 'error/' . $ip;
                    $logTitle = 'Ip-адрес недоступен - ERROR';
                    $logData  = array('title' => $logTitle, 'data' => array($result));
                    $this->saveLogger($logData, $logName , __FILE__, __LINE__);
                    $this->logShow($logData, $logTitle);
                }
            }
            return true;
        }

        return false;
    }

    protected function _find($source, $findValue) {
        $pos = strrpos($source, $findValue);
        if($pos === false)
            return '';
        return $source;
    }

    protected function commandRun($cmd) {

        $output = array();
        $endLine = exec($cmd, $output, $returnVar);

        return array(
            'cmd'       => $cmd,
            'end_line'  => $endLine,
            'var_state' => $returnVar,
            'data'      => $output,
        );
    }

//    protected function logger(string $message, string $ip, $data = array(), $messageId = '') {
//
//        $saveResult = '';
//
//        $datetime = date('d_m_Y___H_i_s');
//        $fileName =  $ip . '__id_' . $messageId . '__' . $datetime . '__log.txt';
//        $path     = LOG_DIR . '/' . $fileName;
//        $content  = $message . "\n\n";
//
//        foreach($data as $key => $value) {
//            $content .= $value . "\n";
//        }
//
//        // $saveResult = file_put_contents($path, $content);
//
//        $this->_log('Save Log file , FaleName -' . $path, $saveResult, __LINE__, __FUNCTION__);
//
//        return $saveResult;
//    }


    protected function sendData(array $postData) : bool {

        $host = $this->sendApiConfig['host'];
        $port = $this->sendApiConfig['port'];
        $link = self::SEND_API_LINK;

        $url = $host . ':' . $port . $link;
        $jsonData = json_encode($postData, JSON_UNESCAPED_UNICODE);
        $jsonErrorMsg  = json_last_error_msg();

        $logArray  = array('Line' => __LINE__, 'FuncName' => __FUNCTION__);

        if($jsonErrorMsg != 'No error') {
            $logFileName = 'error/' . date('d.m.Y__H.i.s');
            $logArray['Data'] = array('msg' => $jsonErrorMsg, 'post' => $postData, 'json' => $jsonData);
            $logTitle = 'Произошла ошибка при отправке данных: json_last_error_msg';
            $this->logShow($logArray, $logTitle);
            $this->saveLogger($logArray['Data'], $logFileName , __FILE__, __LINE__);
            return true;
        }

        // print_r($jsonErrorMsg); die;
        // $this->_log('Send Data - Json Error', $jsonErrorMsg, __LINE__, __FUNCTION__);
        // print_r($postData); print_r($jsonData); die;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $result = curl_exec($ch);
        $info   = curl_getinfo($ch);
        curl_close($ch);

        $logArray['Date'] = array('curl_result' => $result,
                                  'curl_info'   => $info,
                                  'post' => $postData,
                                  'json' => $jsonData);
        $logTitle = 'Сообщение успешно отправлено - ОК';
        $this->logShow($logArray, $logTitle);

        // $this->_log('Send Json Data - Ok, Curl result -' . $result, $result , __LINE__, __FUNCTION__);
        // print_r($result);  print_r($info);  print_r($jsonErrorMsg);

        return true;
    }

    public function sendTest() {

        $data = snmpwalk('192.168.2.184', "public", "");
        $postData = $this->dataFormatted($data);
        $this->sendData($postData);

    }

    protected function walkDataForm(array $data) :array {

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

    protected function getDataForm(array $data, string $ip) :array {

        $postData = array();

        foreach($data as $key => $values) {

            $item = explode('=', $values);
            $oid = trim($this->isValue($item, 0));
            $param     = $this->isValue($item, 1);

            $json = array(
                'oid' => $oid,
                'value' => '',
                'ip'    => $ip,
                'device_id' => 0,
            );

            if(!is_array($oid) && $oid) {
                $_res = explode(':', $param);
                if(!empty($_res[1])) {
                    $json['value'] = (integer) trim($_res[1]);
                }
            }

            $postData[] = $json;
        }

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

    protected function logShow($data, string $title = ''){
        $delimiter = "########################## ";
        $logString = print_r($data, true);
        if($title)
            $logString = "[Title]:" . $title . PHP_EOL . $logString;

        $logString = $delimiter . PHP_EOL .
                     $logString . PHP_EOL .
                     $delimiter . PHP_EOL;
        print $logString;
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

    protected function saveLogger(array $messages, string $logName = '', string $file = '', string $line = '') {

        $logPath  = $this->logPath . '/';
        $datetime = date('Y-m-d H:i:s');

        $logHeader =  '[date]:' . $datetime . PHP_EOL
                     .'[file_name]:'. $file . PHP_EOL
                     .'[line]:'. $line . PHP_EOL;

        $logData =    '################## --- START --- ##################'   . PHP_EOL
                    . $logHeader . print_r($messages, true)   . PHP_EOL
                    . '################## --- END --- ##################' . PHP_EOL;

        if($logName)
            $logPath .= $logName;
        else
            $logPath .= $datetime;

        $logFileName = $logPath . '.log';

        $r = file_put_contents($logFileName, $logData . PHP_EOL, FILE_APPEND);

        return array(
            'status' => $r,
            'log_file_name' => $logFileName
        );
    }

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