<?php
namespace OPNsense\opnblock\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\opnblock\opnblock;
use \OPNsense\Core\Config;
class SettingsController extends ApiControllerBase
{
    public function getAction()
    {
        $result = array();
        if ($this->request->isGet()) {
            $mdlopnblock = new opnblock();
            $result['opnblock'] = $mdlopnblock->getNodes();
        }
        return $result;
    }
    public function setAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            $mdlopnblock = new opnblock();
            $mdlopnblock->setNodes($this->request->getPost("opnblock"));
            $valMsgs = $mdlopnblock->performValidation();
            foreach ($valMsgs as $field => $msg) {
                if (!array_key_exists("validations", $result)) {
                    $result["validations"] = array();
                }
                $result["validations"]["opnblock.".$msg->getField()] = $msg->getMessage();
            }
            if ($valMsgs->count() == 0) {
                $mdlopnblock->serializeToConfig();
                Config::getInstance()->save();
                $result["result"] = "saved";
            }
        }
        return $result;
    }
}