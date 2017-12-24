<?php

namespace OPNsense\ProxySSO;

class IndexController extends \OPNsense\Base\IndexController
{
	public function indexAction()
	{
		$this->view->pick('OPNsense/ProxySSO/index');
		$this->view->generalForm = $this->getForm("general");
		$this->view->testingCreateForm = $this->getForm("testing_create");
		$this->view->testingTestForm = $this->getForm("testing_test");
		$this->view->checkListForm = $this->getForm("checklist");
	}
}
