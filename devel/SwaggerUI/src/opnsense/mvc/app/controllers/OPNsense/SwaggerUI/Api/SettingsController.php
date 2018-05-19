<?php
namespace OPNsense\SwaggerUI\Api;

use \OPNsense\Base\ApiControllerBase;	// Needed for extends
use \OPNsense\Core\Backend;		// Needed for actions
use \OPNsense\SwaggerUI\SwaggerUI;	// Needed for Model Class
use \OPNsense\Core\Config;		// Needed for Config Class

class SettingsController extends ApiControllerBase
{
    /**
     * General SwaggerUI Settings
     */
    public function generalAction()
    {
	// GET Verb
        if ($this->request->isGet()) {
            $mdlSwaggerUI = new SwaggerUI();
            $result = $mdlSwaggerUI->getNodes();
            return $result['general'];
        }

	// POST or PUT Verb
        if ($this->request->isPost() || $this->request->isPut()) {
            // load model and update with provided data
            $mdlSwaggerUI = new SwaggerUI();
            $data['general'] = $this->request->getPost();
            $mdlSwaggerUI->setNodes($data);

            // perform validation
            $valMsgs = $mdlSwaggerUI->performValidation();
            foreach ($valMsgs as $field => $msg) {
                if (!array_key_exists("validations", $result)) {
                    $result["validations"] = array();
                }
                $validations["general.".$msg->getField()] = $msg->getMessage();
            }

            // serialize model to config and save
            if ($valMsgs->count() == 0) {
                $mdlSwaggerUI->serializeToConfig();
                Config::getInstance()->save();
                $result["result"] = "saved";
		return $result;
            }

            // Output validation Error
            $result["result"] = "failed";
            $result["validations"] = $validations;
            return $result;
        }

	// Other Verbs not implemented
        $result["result"] = "failed";
        return $result;
    }

    /**
     * test SwaggerUI
     */
    public function testAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $bckresult = json_decode(trim($backend->configdRun("SwaggerUI test")), true);
            if ($bckresult !== null) {
                // only return valid json type responses
                return $bckresult;
            }
        }
        return array("message" => "unable to run config action");
    }
}
