<?php
namespace OPNsense\Quagga\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Quagga\OSPF;
use \OPNsense\Core\Config;
use \OPNsense\Base\ApiMutableModelControllerBase;
use \OPNsense\Base\UIModelGrid;
class OspfsettingsController extends ApiMutableModelControllerBase
{
  static protected $internalModelName = 'OSPF';
  static protected $internalModelClass = '\OPNsense\Quagga\OSPF';
  public function getAction()
  {
    $result = array();
    if ($this->request->isGet()) {
        $mdlospf = new OSPF();
        $result['ospf'] = $mdlospf->getNodes();
    }
    return $result;
  }

  public function setAction()
  {
    $result = array("result"=>"failed");
    if ($this->request->isPost()) {
        // load model and update with provided data
        $mdlospf = new OSPF();
        $mdlospf->setNodes($this->request->getPost("ospf"));

        // perform validation
        $valMsgs = $mdlospf->performValidation();
        foreach ($valMsgs as $field => $msg) {
            if (!array_key_exists("validations", $result)) {
                $result["validations"] = array();
            }
            $result["validations"]["general.".$msg->getField()] = $msg->getMessage();
        }

        // serialize model to config and save
        if ($valMsgs->count() == 0) {
            $mdlospf->serializeToConfig();
            Config::getInstance()->save();
            $result["result"] = "saved";
        }
    }
    return $result;
  }


/////////////////////////////////////////////////////////////////////
    public function searchNetworkAction()
    {
        $this->sessionClose();
        $mdlOSPF = $this->getModel();
        $grid = new UIModelGrid($mdlOSPF->networks->network);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "ipaddr", "mask")
        );
    }
    /**
     * retrieve remote blacklist settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getNetworkAction($uuid = null)
    {
        $mdlOSPF = $this->getModel();
        if ($uuid != null) {
            $node = $mdlOSPF->getNodeByReference('networks.network.' . $uuid);
            if ($node != null) {
                // return node
                return array("network" => $node->getNodes());
            }
        } else {
            $node = $mdlOSPF->networks->network->add();
            return array("network" => $node->getNodes());
        }
        return array();
    }
}
