<?php
namespace OPNsense\UserProvision\Api;

trait AuditTrait
{
    protected function audit(string $action, array $data = []): void
    {
        $path = '/var/log/userprovision_audit.log';
        $line = date('c') . ' userprovision ' . $action . ' ' . json_encode($data);
        @file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);
    }
}


