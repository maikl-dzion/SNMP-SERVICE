<?php

const LOG_DIR = __DIR__ . '/src/log';

$recorId = 1;
$ip = '192.168.2.184';

if(!empty($argv)) {

    if(!empty($argv[1])) {
        $ip =  $argv[1];
    }

    if(!empty($argv[2])) {
        $recorId =  $argv[2];
    }
}

// print_r('tyuu'); die;

$data = _run($ip, $recorId);

print_r($data); die;

//////////////////////////////////
///
///
///


function _run($ip, $recorId, $port = 161) {

    $data = snmpWalkData($ip);
    $output = $data['output'];
    $s = logger($recorId, $ip, $output);
    $data['log_state'] = $s;
    return $data;
}


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

function logger($recorId, $ip, $data = array()) {

    $datetime = date('d_m_Y___H_i_s');
    $rand     = rand(8, 155);
    $fileName = $recorId .'__'. $datetime . '__' . $ip .'__'.$rand.'__log.txt';
    $path     = LOG_DIR . '/' . $fileName;
    $content  =  "\n\n";

    foreach($data as $key => $value) {
        $content .= $value . "\n";
    }

    // die($path);

    $r = file_put_contents($path, $content);

    return $r;
}

