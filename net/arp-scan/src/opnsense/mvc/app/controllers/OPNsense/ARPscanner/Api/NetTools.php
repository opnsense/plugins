<?php
namespace OPNsense\ARPscanner\Api\NetTools;

class NetTools
{
    public function ciao() {
        return "ciao";
    
    } // ciao()
    
    public function get_local_ipv4() {
      $out = split(PHP_EOL,shell_exec("/sbin/ifconfig"));
      $local_addrs = array();
      $ifname = 'unknown';
      foreach($out as $str) {
        $matches = array();
        if(preg_match('/^([a-z0-9]+)(:\d{1,2})?(\s)+Link/',$str,$matches)) {
          $ifname = $matches[1];
          if(strlen($matches[2])>0) {
            $ifname .= $matches[2];
          }
        } elseif(preg_match('/inet addr:((?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)(?:[.](?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)){3})\s/',$str,$matches)) {
          $local_addrs[$ifname] = $matches[1];
        }
      }
      return $local_addrs;
    } // get_local_ipv4



}


