<?php

/*
 * Copyright (C) 2025 Ralph Moser, PJ Monitoring GmbH
 * Copyright (C) 2025 squared GmbH
 * Copyright (C) 2025 Christopher Linn, BackendMedia IT-Services GmbH
 * Copyright (C) 2025 NetBird GmbH
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

function netbird_enabled()
{
    return !(new \OPNsense\Netbird\Settings())->general->enable->isEmpty();
}

function netbird_carp_enabled()
{
    $settings = new \OPNsense\Netbird\Settings();
    return (string)$settings->general->enable == '1' &&
           (string)$settings->general->enablecarp == '1';
}

/**
 * Check if the current host is CARP master for any monitored VIP.
 * Used to guard the service so it cannot start on a BACKUP node.
 * Returns true if CARP is not enabled OR if we are currently MASTER.
 * @return bool
 */
function netbird_carp_check_master()
{
    if (!netbird_carp_enabled()) {
        // CARP not enabled for NetBird, allow service to start
        return true;
    }

    // Check ifconfig output for CARP MASTER state
    $ifconfig = shell_exec('/sbin/ifconfig');
    if (preg_match('/carp:.*MASTER/', $ifconfig)) {
        return true;
    }

    return false;
}

/**
 * Name of the WireGuard tunnel device used by NetBird.  Single point of
 * truth for all plugin scripts; NetBird names its tun device wt0.
 * @return string
 */
function netbird_wg_iface()
{
    return 'wt0';
}

/**
 * Check whether a network interface currently exists.
 * @param string $iface interface name
 * @return bool
 */
function netbird_iface_exists($iface)
{
    exec('/sbin/ifconfig ' . escapeshellarg($iface) . ' > /dev/null 2>&1', $output, $ret);
    return $ret === 0;
}

/**
 * Query the NetBird daemon's management connection state.  The distinction
 * between "disconnected" and "not responding" matters: right after a
 * service (re)start the client socket is not listening yet, and a
 * "netbird down" fired into that window is silently lost.
 * @return bool|null true = connected, false = disconnected, null = daemon not responding
 */
function netbird_management_state()
{
    $status_json = shell_exec('/usr/local/bin/netbird status --json 2>/dev/null');
    if (!empty($status_json)) {
        $status = json_decode($status_json, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($status['management'])) {
            return ($status['management']['connected'] ?? false) === true;
        }
    }
    return null;
}

/**
 * Check whether NetBird is connected to its management service.
 * @return bool
 */
function netbird_connected()
{
    return netbird_management_state() === true;
}

/**
 * Disconnect NetBird and verify it actually happened, retrying while the
 * daemon socket is still coming up.  Must only run from backgrounded
 * workers, never from a syshook or rc start foreground.
 * @param int $attempts maximum number of probe/disconnect cycles
 * @param int $delay seconds between attempts
 * @param int $watch after disconnecting, keep watching this many seconds
 *                   and disconnect again if the daemon reconnects itself —
 *                   needed right after a service start, where the daemon
 *                   restores its previous "up" state asynchronously
 * @return bool true when NetBird ended up disconnected
 */
function netbird_down_converge($attempts = 10, $delay = 2, $watch = 0)
{
    $ok = false;
    for ($i = 1; $i <= $attempts; $i++) {
        $state = netbird_management_state();
        if ($state === false) {
            $ok = true;
            break;
        } elseif ($state === null) {
            log_msg("NetBird: daemon not responding, waiting to disconnect (attempt {$i}/{$attempts})");
        } else {
            mwexecfm('/usr/local/bin/netbird down');
        }
        sleep($delay);
    }

    if (!$ok) {
        if (netbird_management_state() !== true) {
            $ok = true;
        } else {
            log_msg("NetBird: still connected after {$attempts} disconnect attempts, giving up");
            return false;
        }
    }

    $end = time() + $watch;
    while (time() < $end) {
        sleep($delay);
        if (netbird_management_state() === true) {
            if (netbird_carp_check_master()) {
                /* node was promoted while we watched — the connection is
                   legitimate now, stop guarding */
                break;
            }
            log_msg('NetBird: reconnected while being guarded down, disconnecting again');
            mwexecfm('/usr/local/bin/netbird down');
        }
    }

    return true;
}

/**
 * Destroy a leftover tunnel interface from a previous NetBird run so a
 * fresh one can be created in its place.
 * @param string $iface interface name
 */
function netbird_destroy_orphan_iface($iface)
{
    if (netbird_iface_exists($iface)) {
        log_msg("NetBird: Found orphaned {$iface} interface, destroying");
        mwexecf('/sbin/ifconfig %s destroy', [$iface]);
    }
}

/**
 * Wait for the tunnel interface to disappear.  Used after triggering a
 * service restart: reloading the filter while the old interface is still
 * being torn down would attach the rules to a device the restart is about
 * to destroy.  Polls twice a second to keep the gone->recreated window
 * small.  Must only run from backgrounded workers.
 * @param string $iface interface name
 * @param int $timeout maximum seconds to wait
 * @return bool true when the interface disappeared within the timeout
 */
function netbird_wait_iface_gone($iface, $timeout = 30)
{
    for ($i = 0; $i < $timeout * 2; $i++) {
        if (!netbird_iface_exists($iface)) {
            return true;
        }
        usleep(500000);
    }

    log_msg("NetBird: Timeout ({$timeout}s) waiting for {$iface} interface teardown");
    return false;
}

/**
 * Wait for the tunnel interface to appear and reload the packet filter so
 * its rules attach to the freshly created device.  NetBird creates a tun
 * device and renames it, which takes a few seconds; without the reload all
 * traffic on the new interface hits the default block rule.  This is the
 * only place interface polling and the filter reload live — it must never
 * be called from a syshook foreground, only from backgrounded wrappers.
 * @param string $iface interface name
 * @param int $timeout maximum seconds to wait for the interface
 * @return bool true when the interface appeared and the filter was reloaded
 */
function netbird_sync_filter($iface, $timeout = 30)
{
    for ($i = 0; $i < $timeout; $i++) {
        if (netbird_iface_exists($iface)) {
            log_msg("NetBird: {$iface} interface detected, reloading packet filter");
            mwexecfm('/usr/local/sbin/configctl filter reload');
            return true;
        }
        sleep(1);
    }

    log_msg("NetBird: Timeout ({$timeout}s) waiting for {$iface} interface. Packet filter reload skipped.");
    return false;
}

function netbird_devices()
{
    $iface = netbird_wg_iface();
    $names = [];
    if (netbird_enabled()) {
        /*
         * Interfaces > Assignments only allows saving an assignment to a
         * device that appears in this "names" list (see core's
         * list_assign_options.php); it does not consult "volatile" at all.
         * Without this, the tunnel device is only listed while NetBird is
         * actually connected, so the assignment page can't be saved on a
         * CARP BACKUP node where it's intentionally kept down. Populate it
         * from our own enabled state instead of live interface presence,
         * mirroring how core's own WireGuard plugin (wireguard_devices())
         * handles the same volatile-interface gap.
         */
        $names[$iface] = ['descr' => sprintf('%s (%s)', $iface, gettext('NetBird'))];
    }

    return [[
        'pattern' => '^' . preg_quote($iface, '/') . '$',
        'configurable' => false,
        'spoofmac' => false,
        'volatile' => true,
        'type' => 'netbird',
        'names' => $names,
    ]];
}

function netbird_services()
{
    $services = [];
    if (!netbird_enabled()) {
        return $services;
    }

    $services[] = [
        'description' => gettext('NetBird'),
        'configd' => [
            'restart' => ['netbird restart'],
            'start' => ['netbird start'],
            'stop' => ['netbird stop'],
        ],
        'name' => 'netbird',
        'pidfile' => '/var/run/netbird.pid',
    ];

    return $services;
}

function netbird_configure()
{
    return [
        'netbird_sync_config' => ['netbird_configure_do']
    ];
}

function netbird_configure_do($verbose = false)
{
    service_log('Sync NetBird config...', $verbose);
    (new \OPNsense\Netbird\Settings())->syncConfig();
    service_log("done.\n", $verbose);
}
