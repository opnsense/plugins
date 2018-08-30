<?php
namespace OPNsense\UnboundBL\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\UnboundBL\UnboundBL;
use \OPNsense\Core\Config;
class SettingsController extends ApiControllerBase
{
    public function getAction()
    {
        $result = array();
        if ($this->request->isGet()) {
            $mdlUnboundBL = new UnboundBL();
            $result['UnboundBL'] = $mdlUnboundBL->getNodes();
        }
        return $result;
    }
    public function setAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            $mdlUnboundBL = new UnboundBL();
            $mdlUnboundBL->setNodes($this->request->getPost("UnboundBL"));
            $valMsgs = $mdlUnboundBL->performValidation();
            foreach ($valMsgs as $field => $msg) {
                if (!array_key_exists("validations", $result)) {
                    $result["validations"] = array();
                }
                $result["validations"]["UnboundBL.".$msg->getField()] = $msg->getMessage();
            }
            if ($valMsgs->count() == 0) {
                $mdlUnboundBL->serializeToConfig();
                Config::getInstance()->save();
                $result["result"] = "saved";
            }
        }
        return $result;
    }
}
