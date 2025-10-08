<?php
namespace OPNsense\UserProvision\Api;

trait ValidationTrait
{
    protected function isValidUsername(string $username): bool
    {
        // allow digits/letters/_/-/., 3-64 chars
        return (bool)preg_match('/^[A-Za-z0-9._-]{3,64}$/', $username);
    }


    

    protected function isValidGroupName(string $name): bool
    {
        return (bool)preg_match('/^[A-Za-z0-9._-]{3,64}$/', $name);
    }

    protected function isValidPassword(string $password): bool
    {
        if (strlen($password) < 8) { return false; }
        $hasUpper = preg_match('/[A-Z]/', $password);
        $hasLower = preg_match('/[a-z]/', $password);
        $hasDigit = preg_match('/[0-9]/', $password);
        $hasSpec  = preg_match('/[^A-Za-z0-9]/', $password);
        return $hasUpper && $hasLower && $hasDigit && $hasSpec;
    }

    protected function isValidMac(string $mac): bool
    {
        return (bool)preg_match('/^[0-9a-f]{2}(:[0-9a-f]{2}){5}$/i', $mac);
    }

    protected function isValidIp(string $ip): bool
    {
        return (bool)filter_var($ip, FILTER_VALIDATE_IP);
    }

    protected function isValidIface(string $iface): bool
    {
        // common interface naming patterns
        return (bool)preg_match('/^[A-Za-z0-9._-]{2,32}$/', $iface);
    }

    protected function isValidHostname(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) { return true; }
        return (bool)preg_match('/^([a-zA-Z0-9-]{1,63}\.)*[a-zA-Z0-9-]{1,63}$/', $host);
    }
}


