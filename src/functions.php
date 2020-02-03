<?php


////////////////////////////////////
/// SNMP -  Functions


function snmpWalkData($ip, $shema = 'public', $ver = '-v2c') {
    $snmpCmd = "snmpwalk -c " .$shema. " " .$ver. " " . $ip;
    // snmpwalk -c public -v2c 192.168.2.184 iso.3.6.1.2.1.25.3.2.1.3.1
    return cmdRun($snmpCmd);
}

function cmdRun($cmd) {
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