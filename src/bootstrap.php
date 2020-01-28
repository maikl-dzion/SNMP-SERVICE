<?php

//header('Access-Control-Allow-Credentials', true);
//header('Access-Control-Allow-Origin: *');
//header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
//header('Access-Control-Allow-Headers: X-Requested-With, X-HTTP-Method-Override, Origin, Content-Type, Cookie, Accept');
header('Content-Type: text/html; charset=utf-8');

// ГЛОБАЛЬНЫЕ КОНСТАНТЫ -----
const QUEUE_NAME = 'SNMP_QUEUE';
const SRC_DIR    = ROOT_DIR . '/src';
const CONFIG_DIR = SRC_DIR  . '/config';
const LOGS_DIR   = SRC_DIR  . '/logs';

// die(CONFIG_DIR . '/rabbit.php');
// $rabbitConf = require_once CONFIG_DIR . '/rabbit.php';

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