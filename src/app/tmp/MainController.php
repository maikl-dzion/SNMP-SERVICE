<?php

namespace Src\App;

class MainController {

    protected $ipAddress;
    protected $port;
    protected $params = array();

    public function __construct($params = array()) {
        $this->params = $params;
        $this->setParams();
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

    public function walk() {
        $port = $this->port;
        $ip   = $this->ipAddress;
        $result = snmpwalk($ip, "public", "");
        return $result;
    }

    public function getLanInfo() {
        $port = $this->port;
        $ip   = $this->ipAddress;
        $result = snmpwalkoid($ip, "public", "");
        return $result;
    }


}