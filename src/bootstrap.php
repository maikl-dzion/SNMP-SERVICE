<?php

//header('Access-Control-Allow-Credentials', true);
//header('Access-Control-Allow-Origin: *');
//header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
//header('Access-Control-Allow-Headers: X-Requested-With, X-HTTP-Method-Override, Origin, Content-Type, Cookie, Accept');
// header('Content-Type: text/html; charset=utf-8');

// ГЛОБАЛЬНЫЕ КОНСТАНТЫ -----

const SRC_DIR    = ROOT_DIR . '/src';
const CONFIG_DIR = SRC_DIR  . '/config';
const LOG_DIR    = SRC_DIR  . '/log';

const QUEUE_NAME        = 'SNMP_QUEUE';
const RUN_COUNT_DEFAULT = 200;
const SNMP_SCRIPT_NAME  = 'snmpServiceRun.php';

include_once SRC_DIR . '/functions.php';


