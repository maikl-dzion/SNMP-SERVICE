<?php

namespace Src\App;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class SNMPController {

    protected $rabbitConnect;
    protected $sendApiConfig;
    protected $logPath;
    const SEND_API_LINK = '/data/save';

    protected $errorStatus   = 0;
    protected $errorFileName = '';
    protected $errorTitle = '';
    protected $errorData     = array();

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

        if(!empty($message))
            $this->_print('Сообщение успешно получено из очереди');

        return $message;
    }

    protected function parseMessageData(AMQPMessage $message) : \stdClass {

        $data = new \stdClass;

        $content = $message->body;
        $item    = explode(' ', $content);

        $data->id    = trim($this->isValue($item, 0));
        $data->ip    = trim($this->isValue($item, 1));
        $data->oid   = trim($this->isValue($item, 2));
        $data->port  = trim($this->isValue($item, 3));
        $data->type  = trim($this->isValue($item, 4));
        $data->error = false;

        if(!$data->ip) {
            $data->error = true;
            $logTitle    = 'Пустой Ip-адрес';
            $logFileName = 'error/' . date('d.m.Y__H.i.s');
            $logData     = array('data' => $message);
            $logHeader = $this->logHeader($logTitle, __FUNCTION__, __LINE__, __FILE__);
            $this->saveLogger($logData, $logHeader, $logFileName);
            $this->logShow($logData, $logHeader);
            return $data;
        }

        $this->_print('Пред-обработка сообщения успешно выполнена');

        return $data;
    }

    protected function _print($title) {
        print PHP_EOL . " ####### ---  {$title} --- ####### " . PHP_EOL;
    }

    public function run() : bool {

        // -----------------------------
        // Получаем сообщение из очереди
        $messageObject = $this->getMessageOfQueue();
        $message       = $this->parseMessageData($messageObject);
        if($message->error)
            return false;

        // -----------------------------
        // Выполняем задание и обрабатываем данные
        $data  = $this->snmpExecute($message);

        if(empty($data)) {
            return false;
        }

        // -----------------------------
        // Данные успешно обработаны, отправляем на сохранение
        foreach ($data as $key => $item) {
            $this->sendData($item);
        }

        return true;
    }

    protected function snmpExecute(\stdClass $message, $action = 'get') : array {

        $_id  = $message->id;
        $ip   = $message->ip;
        $oid  = $message->oid;
        $port = $message->port;
        $type = $message->type;

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
            $logTitle    = 'Не удалось выполнить snmp-запрос';
            $logFileName = 'error/' . date('d.m.Y__H.i.s') . '__ip-' . $ip;
            $logData     = array('data' => $data);
            $logHeader   = $this->logHeader($logTitle, __FUNCTION__, __LINE__, __FILE__);
            $this->saveLogger($logData, $logHeader, $logFileName);
            $this->logShow($logData, $logHeader);
            return array();
        }

        $this->_print('SNMP-запрос успешно выполнен, данные от устройства получены');

        $snmpData = $this->postDataFormatted($data['data'], $ip, $action);

        $this->_print('Пост-обработка данных успешно выполнена');

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
        if(!$this->pingIp($ip)) return array();
        $snmpCommand = "snmpwalk -c " .$shema. " " .$ver. " " . $ip;
        return $this->commandRun($snmpCommand);
    }

    protected function snmpGet($ip, $oid, $shema = 'public', $ver = '-v2c') : array {
        if(!$this->pingIp($ip))
            return array();

        $kill = ' & pid=$! && sleep 4 && kill -9 $pid';
        $snmpCommand = "snmpget {$ver} -c {$shema}  {$ip} {$oid} " . $kill;
        return $this->commandRun($snmpCommand);
    }

    protected function pingIp(string $ip) {
        $cmd = "ping -w 3 -c 3  {$ip}";
        $result = $this->commandRun($cmd);
        if(!empty($result['data'])) {
            foreach ($result['data'] as $key => $value) {
                if(!$this->_find($value, 'transmitted')) continue;
                if($this->_find($value, '0 received')){

                    $logTitle    = 'IP-адрес устройства недоступен - ' . $ip;
                    $logFileName = 'error/' . date('d.m.Y__H.i.s') . '__ip-' . $ip;
                    $logData     = array('data' => $result['data']);
                    $logHeader   = $this->logHeader($logTitle, __FUNCTION__, __LINE__, __FILE__);
                    $this->saveLogger($logData, $logHeader, $logFileName);
                    $this->logShow($logData, $logHeader);
                    return false;
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

    protected function sendData(array $postData) : bool {

        $host = $this->sendApiConfig['host'];
        $port = $this->sendApiConfig['port'];
        $link = self::SEND_API_LINK;

        $url = $host . ':' . $port . $link;
        $jsonData = json_encode($postData, JSON_UNESCAPED_UNICODE);
        $jsonErrorMsg  = json_last_error_msg();

        if($jsonErrorMsg != 'No error') {
            $logTitle    = 'Произошла ошибка при отправке данных: json_last_error_msg-' . $jsonErrorMsg;
            $logFileName = 'error/' . date('d.m.Y__H.i.s');
            $logData     = array('post' => $postData, 'json' => $jsonData);
            $logHeader   = $this->logHeader($logTitle, __FUNCTION__, __LINE__, __FILE__);
            $this->saveLogger($logData, $logHeader, $logFileName);
            $this->logShow($logData, $logHeader);
            return true;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $result = curl_exec($ch);
        $info   = curl_getinfo($ch);
        curl_close($ch);

        $logArray = array('curl_result' => $result,
                          'curl_info'   => $info,
                          'post'        => $postData,
                          'json'        => $jsonData);

        $logTitle = 'Сообщение успешно отправлено';
        $logHeader   = $this->logHeader($logTitle, __FUNCTION__, __LINE__, __FILE__);
        $this->logShow($logArray, $logHeader);

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

    protected function logShow($data, string $header = ''){
        $delimiter = "########################## ";
        $logString = print_r($data, true);
        if($header)
            $logString = $header . PHP_EOL . $logString;
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

    protected function saveLogger(array $messages, $logHeader, $logFileName = '') {

        $logPath  = $this->logPath . '/';
        $datetime = date('Y-m-d H:i:s');
        $_logData =  '################## --- START --- ##################'   . PHP_EOL
                    . $logHeader . print_r($messages, true)   . PHP_EOL
                    . '################## --- END --- ##################' . PHP_EOL;

        if($logFileName)
            $logPath .= $logFileName;
        else
            $logPath .= $datetime;

        $_fileName = $logPath . '.log';

        $r = file_put_contents($_fileName, $_logData . PHP_EOL, FILE_APPEND);

        return array(
            'status' => $r,
            'log_file_name' => $logFileName
        );
    }

    protected function logHeader($title = '', $funcName = '', $line = '', $file = '') {
        $datetime = date('Y-m-d H:i:s');
        $header = '[datetime] : ' . $datetime . PHP_EOL
                 .'[title] : '    . $title    . PHP_EOL
                 .'[line]  : '     . $line     . PHP_EOL
                 .'[func_name] : '. $funcName . PHP_EOL
                 .'[file_name] : '. $file     . PHP_EOL;

        return $header;
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