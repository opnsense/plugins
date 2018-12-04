<?php
namespace OPNsense\Smart2\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;

class ServiceController extends ApiControllerBase
{
    private function getDevices ()
    {
        exec("/bin/ls /dev | grep '^\(ad\|da\|ada\)[0-9]\{1,2\}$'", $devices);
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

            $output = shell_exec ("/usr/local/sbin/smartctl -" . escapeshellarg($type) . " /dev/" . escapeshellarg($device));

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

            $output = shell_exec ("/usr/local/sbin/smartctl -l " . escapeshellarg($type) . " /dev/" . escapeshellarg($device));

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

            $output = shell_exec ("/usr/local/sbin/smartctl -t " . escapeshellarg($type) . " /dev/" . escapeshellarg($device));

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

            $output = shell_exec ("/usr/local/sbin/smartctl -X /dev/" . escapeshellarg($device));

            return array("output" => $output);
        }

        return array("message" => "Unable to run abort action");
    }
}
