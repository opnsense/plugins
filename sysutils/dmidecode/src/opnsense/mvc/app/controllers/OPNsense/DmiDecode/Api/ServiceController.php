<?php
namespace OPNsense\dmidecode\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;
class ServiceController extends ApiControllerBase
{
  public function getAction()
  {
    $status = "failed";
    $system = array();
    $bios = array();
    if ($this->request->isGet()) {
      $system = parse_ini_string(trim((new Backend())->configdRun('dmidecode system')), FALSE, INI_SCANNER_RAW);
      $bios = parse_ini_string(trim((new Backend())->configdRun('dmidecode bios')), FALSE, INI_SCANNER_RAW);
      $status = "ok";
    }
    return ["status" => $status, "system" => $system, "bios" => $bios];
  }
}