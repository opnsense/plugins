<?php

namespace OPNsense\SwaggerUI;

class CoreController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        // link rule dialog
        $this->view->formGeneralSettings = $this->getForm("general");

        // set additional view parameters
        $mdlSwaggerUI = new SwaggerUI();
        $mdlSwaggerUI = new \OPNsense\SwaggerUI\SwaggerUI();
	$data = $mdlSwaggerUI->getNodes();

        $this->view->debug = False;
	$this->view->data = json_encode( $data );

        $this->view->hideTitlebar  = $data['general']['ShowTitlebar'] == "0";
        $this->view->hideAuthorize = $data['general']['ShowAuthorize'] == "0";
        $this->view->hideServers   = $data['general']['ShowServers'] == "0";
        $this->view->hideHeader    = $data['general']['ShowHeader'] == "0";
        $this->view->collapsed     = $data['general']['Expanded'] == "0";

        $this->view->json          = '/ui/js/swaggerui/opnsense.json';

        // choose template
        $this->view->pick('OPNsense/SwaggerUI/swaggerui');
    }
}
