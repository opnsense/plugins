<?php
namespace OPNsense\Quagga\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Quagga\General;
use \OPNsense\Core\Config;
class GeneralController extends ApiControllerBase
{
public function getAction()
{
    // define list of configurable settings
    $result = array();
    if ($this->request->isGet()) {
        $mdlGeneral = new General();
        $result['general'] = $mdlGeneral->getNodes();
    }
    return $result;
}
public function setAction()
{
    $result = array("result"=>"failed");
    if ($this->request->isPost()) {
        // load model and update with provided data
        $mdlGeneral = new General();
        $mdlGeneral->setNodes($this->request->getPost("general"));

        // perform validation
        $valMsgs = $mdlGeneral->performValidation();
        foreach ($valMsgs as $field => $msg) {
            if (!array_key_exists("validations", $result)) {
                $result["validations"] = array();
            }
            $result["validations"]["general.".$msg->getField()] = $msg->getMessage();
        }

        // serialize model to config and save
        if ($valMsgs->count() == 0) {
            $mdlGeneral->serializeToConfig();
            Config::getInstance()->save();
            $result["result"] = "saved";
        }
    }
    return $result;
}

}
