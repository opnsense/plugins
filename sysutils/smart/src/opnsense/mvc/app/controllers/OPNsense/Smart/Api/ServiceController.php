<?php
namespace OPNsense\Smart\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;

class ServiceController extends ApiControllerBase
{
    private function getDevices ()
    {
        $backend = new Backend();

        $devices = preg_split ("/[\s]+/", trim($backend->configdRun("smart list")));

        return $devices;
    }

    public function listAction ()
    {
        if ($this->request->isPost())
            return array("devices" => $this->getDevices ());

        return array("message" => "Unable to run list action");
    }

    public function infoAction ()
    {
        if ($this->request->isPost()) {
            $device = $this->request->getPost ('device');
            $type   = $this->request->getPost ('type');

            if (!in_array ($device, $this->getDevices ()))
                return array("message" => "Invalid device name");

            $valid_info_types = array("i", "H", "c", "A", "a");

            if (!in_array ($type, $valid_info_types))
                return array("message" => "Invalid info type");

            $backend = new Backend();

            $output = $backend->configdpRun("smart", array("info", $type, "/dev/".$device));

            return array("output" => $output);
        }

        return array("message" => "Unable to run info action");
    }

    public function logsAction ()
    {
        if ($this->request->isPost()) {
            $device = $this->request->getPost ('device');
            $type   = $this->request->getPost ('type');

            if (!in_array ($device, $this->getDevices ()))
                return array("message" => "Invalid device name");

            $valid_log_types = array("error", "selftest");

            if (!in_array ($type, $valid_log_types))
                return array("message" => "Invalid log type");

            $backend = new Backend();

            $output = $backend->configdpRun("smart", array("log", $type, "/dev/".$device));

            return array("output" => $output);
        }

        return array("message" => "Unable to run logs action");
    }

    public function testAction ()
    {
        if ($this->request->isPost()) {
            $device = $this->request->getPost ('device');
            $type   = $this->request->getPost ('type');

            if (!in_array ($device, $this->getDevices ()))
                return array("message" => "Invalid device name");

            $valid_test_types = array("offline", "short", "long", "conveyance");

            if (!in_array ($type, $valid_test_types))
                return array("message" => "Invalid test type");

            $backend = new Backend();

            $output = $backend->configdpRun("smart", array("test", $type, "/dev/".$device));

            return array("output" => $output);
        }

        return array("message" => "Unable to run test action");
    }

    public function abortAction ()
    {
        if ($this->request->isPost()) {
            $device = $this->request->getPost ('device');

            if (!in_array ($device, $this->getDevices ()))
                return array("message" => "Invalid device name");

            $backend = new Backend();

            $output = $backend->configdpRun("smart", array("abort", "/dev/".$device));

            return array("output" => $output);
        }

        return array("message" => "Unable to run abort action");
    }
}
