<?php
if (class_exists('OPNsense\DynDNS\CheckIP_Service') && 
    class_exists('OPNsense\DynDNS\CheckIP_Factory_Default_Service')) {

    // Get the check IP services.
    $mdlCheckIPService = new OPNsense\DynDNS\CheckIP_Service();
    $a_checkipservices = $mdlCheckIPService->service->getNodes();

    // Append the factory default check IP service to the list.
    $mdlCheckIPFDS = new OPNsense\DynDNS\CheckIP_Factory_Default_Service();
    $a_checkipservices['FDS'] = $mdlCheckIPFDS->factory_default_service->getNodes();

    // Is the factory default service disabled?
    if ($mdlCheckIPService->disable_factory_default_service->__toString() == "1") {
        $a_checkipservices['FDS']['default'] = 0;
    }
} else {
    return 'Check IP services not available';
}

// Use the service specified by name.
// Else use the first service found with a matching interface and IP version assignment.
// Else use the enabled default service.
foreach ($a_checkipservices as $i => $checkipservice) {
    if ($checkipservice['name'] == $service || 
       (!$service && 
        $checkipservice['interfaces'][$int]['selected'] && 
        $checkipservice['ipv']['ipv'.$ipver]['selected'])) {
            $found = true;
    }
    if ($found || $checkipservice['default']) {
        $servicename = $checkipservice['name'];
        $hosttocheck = $checkipservice['url'];
        $regex = $checkipservice['regex'];
        $username = $checkipservice['username'];
        $password = $checkipservice['password'];
        $verifysslpeer = $checkipservice['verifysslpeer'] ? true : false;
        if ($found) { break; }
    }
}
