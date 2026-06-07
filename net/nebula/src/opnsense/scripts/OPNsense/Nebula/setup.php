#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2026 Henry Stern <henry@stern.ca>
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

require_once('script/load_phalcon.php');

use OPNsense\Core\Config;
use OPNsense\Nebula\Nebula;

const NEBULA_ETC = '/usr/local/etc/nebula';
const NEBULA_RUN = '/var/run/nebula';
const NEBULA_BIN = '/usr/local/bin/nebula';
const DAEMON_BIN = '/usr/sbin/daemon';
/* Plugin diagnostics SSH identity: private key used by the `peers` query, public
 * key injected (user _opnsense_diag) into an instance's sshd authorized_users
 * when diag_access is on. */
const NEBULA_DIAG_DIR = NEBULA_ETC . '/.diag';
const NEBULA_DIAG_USER = '_opnsense_diag';

/**
 * Path of the rendered config for an instance.
 */
function nebula_config_file($uuid)
{
    return NEBULA_ETC . '/' . $uuid . '.yml';
}

/**
 * Path of the pidfile for an instance daemon.
 */
function nebula_pid_file($uuid)
{
    return NEBULA_RUN . '/' . $uuid . '.pid';
}

/**
 * Return the pid of a running instance, or null when not running.
 */
function nebula_running_pid($uuid)
{
    $pidfile = nebula_pid_file($uuid);
    if (!is_file($pidfile)) {
        return null;
    }
    $pid = (int)trim(@file_get_contents($pidfile));
    /* CLI php has no posix extension here; probe liveness with `kill -0`. */
    if ($pid > 0) {
        $rc = 0;
        $out = [];
        exec(sprintf('/bin/kill -0 %d 2>/dev/null', $pid), $out, $rc);
        if ($rc === 0) {
            return $pid;
        }
    }
    /* stale pidfile */
    @unlink($pidfile);
    return null;
}

/**
 * Write the three PKI files for one instance from the model's cert pool.
 *
 * Resolves instance->certref to a pki.certificates.certificate node, then
 * resolves that cert's caref to its signing authority, and writes:
 *   <certDir>/ca.crt   (trusted-CA bundle: signing CA + instance->trusted_cas, 0600)
 *   <certDir>/host.crt (certificate crt, 0600)
 *   <certDir>/host.key (certificate key, 0600)
 *
 * Returns true on success.  On any skip condition (missing certref, unresolved
 * cert/CA, empty PEM field) logs the reason and returns false so the caller
 * can skip starting this instance.
 */
function nebula_write_certs($model, $node, $uuid)
{
    $descr = (string)$node->description;
    $label = "instance {$uuid}" . ($descr !== '' ? " ({$descr})" : '');

    /* 1. certref must be set */
    $certref = (string)$node->certref;
    if ($certref === '') {
        syslog(LOG_WARNING, "nebula: {$label} has no certificate; skipping");
        return false;
    }

    /* 2. resolve the certificate node */
    $cert = null;
    foreach ($model->pki->certificates->certificate->iterateItems() as $cUuid => $cNode) {
        if ($cUuid === $certref) {
            $cert = $cNode;
            break;
        }
    }
    if ($cert === null) {
        syslog(LOG_WARNING, "nebula: {$label} certificate {$certref} not found in pool; skipping");
        return false;
    }

    /* 3. resolve the signing authority via cert->caref, indexing every authority
          by uuid so we can also pull the instance's additional trusted CAs. */
    $caref = (string)$cert->caref;
    if ($caref === '') {
        syslog(LOG_WARNING, "nebula: {$label} certificate {$certref} has no CA reference; skipping");
        return false;
    }
    $authority = null;
    $authByUuid = [];
    foreach ($model->pki->authorities->authority->iterateItems() as $aUuid => $aNode) {
        $authByUuid[$aUuid] = $aNode;
        if ($aUuid === $caref) {
            $authority = $aNode;
        }
    }
    if ($authority === null) {
        syslog(LOG_WARNING, "nebula: {$label} CA {$caref} not found in pool; skipping");
        return false;
    }

    /* 4. validate the host cert/key + signing CA PEM fields are non-empty */
    $signingCrt = (string)$authority->crt;
    $hostCrt    = (string)$cert->crt;
    $hostKey    = (string)$cert->key;
    if ($signingCrt === '' || $hostCrt === '' || $hostKey === '') {
        syslog(LOG_WARNING, "nebula: {$label} certificate or CA has empty PEM data; skipping");
        return false;
    }

    /* 4a. assemble the ca.crt bundle: the signing CA (always — the host cert must
           validate against it) plus any additional trusted CAs the instance
           selects. Deduplicated by uuid; a missing/empty trusted CA is logged and
           skipped rather than aborting the whole instance. */
    $caUuids = [$caref];
    foreach (explode(',', (string)$node->trusted_cas) as $tUuid) {
        $tUuid = trim($tUuid);
        if ($tUuid !== '' && !in_array($tUuid, $caUuids, true)) {
            $caUuids[] = $tUuid;
        }
    }
    $caCrtParts = [];
    foreach ($caUuids as $tUuid) {
        if (!isset($authByUuid[$tUuid])) {
            syslog(LOG_WARNING, "nebula: {$label} trusted CA {$tUuid} not found in pool; skipping that CA");
            continue;
        }
        $crt = trim((string)$authByUuid[$tUuid]->crt);
        if ($crt !== '') {
            $caCrtParts[] = $crt;
        }
    }
    $caCrt = implode("\n", $caCrtParts) . "\n";

    /* 5. ensure the cert directory exists (mode 0700) and write the files (0600) */
    $certDir = NEBULA_ETC . '/' . $uuid;
    if (!is_dir($certDir)) {
        mkdir($certDir, 0700, true);
    }
    foreach ([
        $certDir . '/ca.crt'   => $caCrt,
        $certDir . '/host.crt' => $hostCrt,
        $certDir . '/host.key' => $hostKey,
    ] as $path => $content) {
        file_put_contents($path, $content);
        chmod($path, 0600);
    }

    syslog(LOG_INFO, "nebula: {$label} wrote cert material from pool");
    return true;
}

/**
 * Ensure the plugin diagnostics SSH keypair exists; return its public-key string.
 */
function nebula_diag_pubkey()
{
    if (!is_dir(NEBULA_DIAG_DIR)) {
        mkdir(NEBULA_DIAG_DIR, 0700, true);
    }
    $priv = NEBULA_DIAG_DIR . '/id_ed25519';
    $pub = $priv . '.pub';
    if (!is_file($priv) || !is_file($pub)) {
        @unlink($priv);
        @unlink($pub);
        exec(sprintf(
            '/usr/local/bin/ssh-keygen -t ed25519 -N "" -C nebula-diag -f %s >/dev/null 2>&1',
            escapeshellarg($priv)
        ));
        @chmod($priv, 0600);
    }
    return is_file($pub) ? trim((string)@file_get_contents($pub)) : '';
}

/**
 * Ensure a per-instance sshd host key exists (used when diag_access defaults it).
 */
function nebula_ensure_sshd_host_key($uuid)
{
    $path = NEBULA_ETC . '/' . $uuid . '/sshd_host_key';
    if (!is_file($path)) {
        exec(sprintf(
            '/usr/local/bin/ssh-keygen -t ed25519 -N "" -C nebula-sshd -f %s >/dev/null 2>&1',
            escapeshellarg($path)
        ));
        @chmod($path, 0600);
    }
    return $path;
}

/**
 * Render the config for one instance to disk (mode 0600), ensure the per-instance
 * cert directory exists, and (when diag_access is on) materialise the diagnostics
 * sshd key material. Returns the config file path.
 */
function nebula_render($model, $node, $uuid)
{
    if (!is_dir(NEBULA_ETC)) {
        mkdir(NEBULA_ETC, 0700, true);
    }
    /* per-instance cert dir referenced by the pki: block */
    $certDir = NEBULA_ETC . '/' . $uuid;
    if (!is_dir($certDir)) {
        mkdir($certDir, 0700, true);
    }

    /* The sshd debug server is always on (the plugin's internal management
     * channel). Materialise the plugin diagnostics key + a per-instance sshd host
     * key (both idempotent) and hand the diag pubkey to the renderer so it adds
     * the _opnsense_diag authorized_user — the only principal with access. */
    $diagPubKey = nebula_diag_pubkey();
    nebula_ensure_sshd_host_key($uuid);

    $cnfFile = nebula_config_file($uuid);
    file_put_contents($cnfFile, $model->generateConfig($node, $diagPubKey));
    chmod($cnfFile, 0600);
    return $cnfFile;
}

/**
 * Run one command against a running instance's always-on nebula sshd debug
 * server. SSHes to 127.0.0.1 on the per-instance derived port (the same value
 * the renderer wrote, via $model->sshdPortFor) with the plugin diagnostics key.
 *
 * @return array {ok:bool, raw?:string, error?:string}
 */
function nebula_debug_cmd($model, $uuid, $command)
{
    if (nebula_running_pid($uuid) === null) {
        return ['ok' => false, 'error' => 'instance not running'];
    }
    $priv = NEBULA_DIAG_DIR . '/id_ed25519';
    if (!is_file($priv)) {
        return ['ok' => false, 'error' => 'diagnostics key missing (apply first)'];
    }
    $port = (int)$model->sshdPortFor($uuid);
    if ($port <= 0) {
        return ['ok' => false, 'error' => 'no debug port'];
    }
    $cmd = sprintf(
        '/usr/local/bin/ssh -i %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null ' .
        '-o BatchMode=yes -o ConnectTimeout=4 -p %d %s@127.0.0.1 %s 2>/dev/null',
        escapeshellarg($priv),
        $port,
        escapeshellarg(NEBULA_DIAG_USER),
        escapeshellarg($command)
    );
    $out = [];
    $rc = 0;
    exec($cmd, $out, $rc);
    $raw = trim(implode("\n", $out));
    if ($rc !== 0) {
        // nebula's `reload` acks with "Reloading config" and then closes the
        // connection from its side, so ssh exits non-zero (255) even though the
        // reload succeeded. Treat that specific ack as success rather than a
        // dead debug server.
        if ($command === 'reload' && stripos($raw, 'Reloading config') !== false) {
            return ['ok' => true, 'raw' => $raw];
        }
        return ['ok' => false, 'error' => 'could not reach the debug server'];
    }
    return ['ok' => true, 'raw' => $raw];
}

/**
 * Run several debug-server commands over ONE SSH session (the REPL reads them
 * from stdin), and split the output by the per-command prompt
 * "<user>@nebula > <cmd>". Returns ['ok'=>bool, 'parts'=>[cmd => raw output]].
 *
 * This collapses the Status page's 3 SSH connections per instance per poll into
 * a single connection.
 */
function nebula_debug_batch($model, $uuid, array $commands)
{
    if (nebula_running_pid($uuid) === null) {
        return ['ok' => false, 'error' => 'instance not running'];
    }
    $priv = NEBULA_DIAG_DIR . '/id_ed25519';
    if (!is_file($priv)) {
        return ['ok' => false, 'error' => 'diagnostics key missing (apply first)'];
    }
    $port = (int)$model->sshdPortFor($uuid);
    if ($port <= 0) {
        return ['ok' => false, 'error' => 'no debug port'];
    }
    $cmd = sprintf(
        '/usr/local/bin/ssh -i %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null ' .
        '-o BatchMode=yes -o ConnectTimeout=4 -p %d %s@127.0.0.1 2>/dev/null',
        escapeshellarg($priv),
        $port,
        escapeshellarg(NEBULA_DIAG_USER)
    );
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($proc)) {
        return ['ok' => false, 'error' => 'could not spawn ssh'];
    }
    fwrite($pipes[0], implode("\n", $commands) . "\n");
    fclose($pipes[0]);
    $raw = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);

    // Each command's output is preceded by a prompt line "...@nebula > <cmd>".
    $parts = [];
    $cur = null;
    foreach (explode("\n", $raw) as $line) {
        if (preg_match('/@nebula > (.*)$/', $line, $m)) {
            $echoed = trim($m[1]);
            // The final prompt carries the "Connection ... closed" notice, not a
            // real command — ignore it.
            $cur = ($echoed === '' || strncmp($echoed, 'Connection to', 13) === 0) ? null : $echoed;
            if ($cur !== null && !isset($parts[$cur])) {
                $parts[$cur] = '';
            }
        } elseif ($cur !== null) {
            $parts[$cur] .= ($parts[$cur] === '' ? '' : "\n") . $line;
        }
    }
    foreach ($parts as $k => $v) {
        $parts[$k] = trim($v);
    }
    return ['ok' => true, 'parts' => $parts];
}

/**
 * Query a running instance's live tunnels/peers (`list-hostmap -json`).
 *
 * @return array {ok:bool, peers?:mixed, error?:string}
 */
function nebula_instance_peers($model, $uuid)
{
    $res = nebula_debug_cmd($model, $uuid, 'list-hostmap -json');
    if (empty($res['ok'])) {
        return $res;
    }
    $raw = $res['raw'];
    if ($raw === '' || strtolower($raw) === 'null') {
        return ['ok' => true, 'peers' => []];
    }
    $peers = json_decode($raw, true);
    if ($peers === null) {
        return ['ok' => false, 'error' => 'unexpected response from the debug server'];
    }
    return ['ok' => true, 'peers' => $peers];
}

/**
 * Run a whitelisted debug-server sub-action for the Status page (enrichment +
 * verbs). The sub-action is mapped to a fixed nebula command; the vpn address is
 * validated as an IP and the remote (for change/create) to a safe host:port
 * charset, so nothing user-controlled reaches the command unchecked.
 *
 * @param object $model the Nebula model
 * @param string $uuid  instance uuid
 * @param string $sub   sub-action (tunnel|cert|pending|deviceinfo|querylh|close|changeremote|createtunnel)
 * @param string $vpn   target peer's nebula (overlay) IP, where applicable
 * @param string $remote underlay host:port (change-remote / create-tunnel only)
 * @return array {ok:bool, ...}
 */
function nebula_instance_debug($model, $uuid, $sub, $vpn, $remote)
{
    $vpn = trim((string)$vpn);
    $remote = trim((string)$remote);
    $isIp = filter_var($vpn, FILTER_VALIDATE_IP) !== false;
    $isAddr = preg_match('/^[0-9A-Za-z._:\[\]-]+$/', $remote) === 1;

    switch ($sub) {
        case 'deviceinfo':
            $res = nebula_debug_cmd($model, $uuid, 'device-info');
            if (empty($res['ok'])) {
                return $res;
            }
            // "name=nebula0 cidr=[1.2.3.4/32]"
            $dev = [];
            if (preg_match('/name=(\S+)/', $res['raw'], $m)) {
                $dev['name'] = $m[1];
            }
            if (preg_match('/cidr=\[([^\]]*)\]/', $res['raw'], $m)) {
                $dev['cidr'] = $m[1];
            }
            return ['ok' => true, 'device' => $dev];

        case 'pending':
            $res = nebula_debug_cmd($model, $uuid, 'list-pending-hostmap -json');
            if (empty($res['ok'])) {
                return $res;
            }
            $raw = $res['raw'];
            $j = ($raw === '' || strtolower($raw) === 'null') ? [] : json_decode($raw, true);
            return ['ok' => true, 'pending' => ($j === null ? [] : $j)];

        case 'tunnel':
        case 'cert':
            if (!$isIp) {
                return ['ok' => false, 'error' => 'invalid vpn address'];
            }
            $cmd = ($sub === 'tunnel' ? 'print-tunnel ' : 'print-cert ') . $vpn . ' -json';
            $res = nebula_debug_cmd($model, $uuid, $cmd);
            if (empty($res['ok'])) {
                return $res;
            }
            return ['ok' => true, $sub => json_decode($res['raw'], true)];

        case 'querylh':
            if (!$isIp) {
                return ['ok' => false, 'error' => 'invalid vpn address'];
            }
            return nebula_debug_cmd($model, $uuid, 'query-lighthouse ' . $vpn);

        case 'close':
            if (!$isIp) {
                return ['ok' => false, 'error' => 'invalid vpn address'];
            }
            return nebula_debug_cmd($model, $uuid, 'close-tunnel ' . $vpn);

        case 'createtunnel':
            if (!$isIp) {
                return ['ok' => false, 'error' => 'invalid vpn address'];
            }
            /* vpn addr only — the lighthouse resolves the underlay. Do NOT pass
               -address: nebula <= 1.10.3 SEGV-panics (nil deref in sshd
               dispatchCommand, session.go:172) on `create-tunnel -address ...`.
               PENDING: fixed upstream in slackhq/nebula PR #1749. Once that lands
               in a released nebula the plugin ships against, switch this to
               `create-tunnel -address ' . $remote . ' ' . $vpn` (mirroring
               changeremote below, and re-add the $isAddr guard) and restore the
               underlay-address field in the connectPeer dialogs of tunnels.volt
               and status.volt. */
            return nebula_debug_cmd($model, $uuid, 'create-tunnel ' . $vpn);

        case 'changeremote':
            if (!$isIp || !$isAddr) {
                return ['ok' => false, 'error' => 'invalid arguments'];
            }
            /* change-remote needs the new address via the -address flag; a
               positional address fails with "No address was provided". This path
               (unlike create-tunnel -address) does not crash. */
            return nebula_debug_cmd($model, $uuid, 'change-remote -address ' . $remote . ' ' . $vpn);

        default:
            return ['ok' => false, 'error' => 'unknown debug action'];
    }
}

/**
 * Validate a rendered config with `nebula -test`. Returns true when valid.
 */
function nebula_validate($cnfFile)
{
    $out = [];
    $rc = 0;
    exec(sprintf('%s -test -config %s 2>&1', NEBULA_BIN, escapeshellarg($cnfFile)), $out, $rc);
    if ($rc !== 0) {
        fwrite(STDERR, sprintf("nebula: config %s failed validation:\n%s\n", $cnfFile, implode("\n", $out)));
    }
    return $rc === 0;
}

/**
 * Re-run nebula in the foreground (bounded by `timeout`) to capture the reason a
 * daemon-launched instance died.  Nebula logs structured lines like
 *   time="..." level=error msg="Failed to get a tun/tap device: device busy"
 * on stderr; we extract the first level=error msg, falling back to the last
 * non-empty output line so the caller always gets *something* actionable.
 *
 * Returns a human-readable reason string (never empty).
 */
function nebula_capture_failure_reason($cnfFile)
{
    $out = [];
    $rc = 0;
    /* `timeout` bounds a config that would otherwise run forever; 2s is plenty
     * for the daemon to hit (or clear) a startup error like a busy tun device. */
    exec(
        sprintf('/usr/bin/timeout 2 %s -config %s 2>&1', NEBULA_BIN, escapeshellarg($cnfFile)),
        $out,
        $rc
    );

    $reason = '';
    foreach ($out as $line) {
        if (preg_match('/level=error\s+msg="([^"]*)"/', $line, $m)) {
            $reason = $m[1];
            break;
        }
    }
    if ($reason === '') {
        /* fall back to the last non-empty output line */
        for ($i = count($out) - 1; $i >= 0; $i--) {
            if (trim($out[$i]) !== '') {
                $reason = trim($out[$i]);
                break;
            }
        }
    }
    if ($reason === '') {
        $reason = 'instance failed to start (no diagnostic output)';
    }
    return $reason;
}

/**
 * Start a single instance daemon (no-op if already running). Writes cert
 * material from the PKI pool, renders + validates the config; skips start
 * when any prerequisite fails.
 *
 * Verifies the launch actually took: after handing the config to daemon(8) it
 * polls the pidfile briefly and confirms the pid is alive.  A daemon that dies
 * immediately (e.g. "Failed to get a tun/tap device: device busy") is detected
 * here, its reason captured by re-running nebula in the foreground, LOGged at
 * LOG_ERR, and returned to the caller.
 *
 * @return array ['started'=>bool] on success, or ['started'=>false,'error'=>str]
 */
function nebula_start_instance($model, $node, $uuid)
{
    $label = "instance {$uuid}";

    if (nebula_running_pid($uuid) !== null) {
        return ['started' => true]; /* already running */
    }
    if (!nebula_write_certs($model, $node, $uuid)) {
        /* reason already logged by nebula_write_certs */
        return ['started' => false, 'error' => 'missing or invalid certificate material'];
    }
    $cnfFile = nebula_render($model, $node, $uuid);
    if (!nebula_validate($cnfFile)) {
        $err = 'invalid configuration (failed nebula -test)';
        syslog(LOG_ERR, "nebula: not starting {$label}: {$err}");
        fwrite(STDERR, sprintf("nebula: not starting instance %s (invalid config)\n", $uuid));
        return ['started' => false, 'error' => $err];
    }
    if (!is_dir(NEBULA_RUN)) {
        mkdir(NEBULA_RUN, 0700, true);
    }
    $cmd = sprintf(
        '%s -f -p %s %s -config %s',
        DAEMON_BIN,
        escapeshellarg(nebula_pid_file($uuid)),
        NEBULA_BIN,
        escapeshellarg($cnfFile)
    );
    exec($cmd);

    /* Verify the launch took: poll up to ~1.5s for a live pid in the pidfile. */
    $alive = false;
    for ($i = 0; $i < 8; $i++) {
        usleep(200 * 1000);
        if (nebula_running_pid($uuid) !== null) {
            $alive = true;
            break;
        }
    }

    if (!$alive) {
        /* The daemon died on startup. Re-run in the foreground to learn why. */
        $reason = nebula_capture_failure_reason($cnfFile);
        syslog(LOG_ERR, "nebula: {$label} failed to start: {$reason}");
        @unlink(nebula_pid_file($uuid));
        return ['started' => false, 'error' => $reason];
    }

    syslog(LOG_NOTICE, "nebula instance {$uuid} started");

    /* Idiomatic OPNsense interface: drop the freshly-created tun into the
     * 'nebula' interface group (like wireguard's group). The daemon creates the
     * tun during startup, so it exists now that the pid is live. (Re-attaching an
     * ASSIGNED interface is done only on explicit restart — see the dispatch —
     * to avoid a reconfigure storm during interface bring-up.) */
    nebula_group_device($uuid);

    return ['started' => true];
}

/**
 * Drop an instance's tun device into the 'nebula' interface group (like
 * wireguard's group) so it groups cleanly and can carry group firewall rules.
 * Idempotent; safe to call from the interface bring-up (prepare) path.
 */
function nebula_group_device($uuid)
{
    $dev = nebula_tun_dev($uuid);
    if ($dev !== null && $dev !== '' && strpos($dev, 'nebula') === 0) {
        exec(sprintf('/sbin/ifconfig %s group nebula 2>/dev/null', escapeshellarg($dev)));
    }
}

/**
 * When an instance's tun is assigned to an OPNsense interface, reconfigure that
 * interface so pf rules / gateways referencing it re-attach to the (re)created
 * device. Backgrounded so it never blocks or deadlocks the caller (it may run
 * inside a configd action). Only meaningful after a full restart — a HUP reload
 * keeps the same device. No-op when the device is unassigned or at early boot
 * (configd not yet up; the in-progress interface_configure already attaches it).
 */
function nebula_reattach_interface($uuid)
{
    $dev = nebula_tun_dev($uuid);
    if ($dev === null || $dev === '' || strpos($dev, 'nebula') !== 0) {
        return;
    }
    $cfg = @file_get_contents('/conf/config.xml');
    if ($cfg === false) {
        return;
    }
    $xml = @simplexml_load_string($cfg);
    if ($xml === false || !isset($xml->interfaces)) {
        return;
    }
    foreach ($xml->interfaces->children() as $key => $ifnode) {
        if ((string)$ifnode->if === $dev) {
            exec(sprintf(
                '/usr/local/sbin/configctl interface reconfigure %s > /dev/null 2>&1 &',
                escapeshellarg((string)$key)
            ));
            break;
        }
    }
}

/**
 * Stop a single instance daemon via SIGTERM on its pidfile. Waits (bounded) for
 * the process to actually exit so a following start does not race the dying
 * daemon for the UDP listen port and the tun device (restart path).
 */
/**
 * Read the tun device name (tun.dev) from a rendered instance config, or null.
 */
function nebula_tun_dev($uuid)
{
    $cnf = NEBULA_ETC . '/' . $uuid . '.yml';
    if (!is_file($cnf)) {
        return null;
    }
    $yml = (string)@file_get_contents($cnf);
    if (preg_match('/^\s*dev:\s*"?([A-Za-z0-9._-]+)"?/m', $yml, $m)) {
        return $m[1];
    }
    return null;
}

/**
 * Destroy an instance's tun device. Killing the nebula daemon does NOT remove
 * the device on FreeBSD, so without this the orphaned nebulaN interface lingers
 * and shows up under Interfaces : Assignments.
 */
function nebula_destroy_tun($uuid)
{
    $dev = nebula_tun_dev($uuid);
    if ($dev !== null && $dev !== '' && strpos($dev, 'nebula') === 0) {
        exec(sprintf('/sbin/ifconfig %s destroy 2>/dev/null', escapeshellarg($dev)));
        syslog(LOG_INFO, "nebula: destroyed tun device {$dev} for instance {$uuid}");
    }
}

function nebula_stop_instance($uuid)
{
    $pid = nebula_running_pid($uuid);
    if ($pid !== null) {
        exec(sprintf('/bin/kill -TERM %d 2>/dev/null', $pid));
        syslog(LOG_NOTICE, "nebula instance {$uuid} stopped");
        /* wait up to ~5s for it to go away, then SIGKILL as a backstop */
        $alive = true;
        for ($i = 0; $i < 25; $i++) {
            $out = [];
            $rc = 0;
            exec(sprintf('/bin/kill -0 %d 2>/dev/null', $pid), $out, $rc);
            if ($rc !== 0) {
                $alive = false;
                break;
            }
            usleep(200 * 1000);
        }
        if ($alive) {
            exec(sprintf('/bin/kill -KILL %d 2>/dev/null', $pid));
        }
    }
    /* Destroy the tun device so it does not linger in Interfaces : Assignments. */
    nebula_destroy_tun($uuid);
    @unlink(nebula_pid_file($uuid));
}

/**
 * Return the status of a single instance as an array ['running'=>bool, 'pid'=>int|null].
 */
function nebula_instance_status($uuid)
{
    $pid = nebula_running_pid($uuid);
    return ['running' => ($pid !== null), 'pid' => $pid];
}

/**
 * Richer per-instance diagnostics for the Status page: process state, the tun
 * device + its addresses + up/down, and config validity. Validity uses
 * `nebula -test` (safe — it never starts the daemon, so viewing status cannot
 * side-effect a stopped instance up).
 *
 * @return array{running:bool,pid:?int,tun_dev:string,tun_addrs:string[],tun_up:bool,config_valid:?bool,config_error:string}
 */
function nebula_instance_diag($uuid)
{
    $pid = nebula_running_pid($uuid);
    $diag = [
        'running' => ($pid !== null),
        'pid' => $pid,
        'tun_dev' => '',
        'tun_addrs' => [],
        'tun_up' => false,
    ];

    $dev = nebula_tun_dev($uuid);
    if (is_string($dev) && $dev !== '') {
        $diag['tun_dev'] = $dev;
        $out = [];
        exec(sprintf('/sbin/ifconfig %s 2>/dev/null', escapeshellarg($dev)), $out);
        foreach ($out as $line) {
            if (preg_match('/^\s*inet6?\s+(\S+)/', $line, $m)) {
                $diag['tun_addrs'][] = $m[1];
            }
            if (preg_match('/flags=\w*<([^>]*)>/', $line, $m) && strpos($m[1], 'UP') !== false) {
                $diag['tun_up'] = true;
            }
        }
    }

    $cnf = nebula_config_file($uuid);
    if (is_file($cnf)) {
        // `nebula -test` forks a process; the config only changes on apply, so
        // cache the result keyed by the config file's mtime and re-test only when
        // the file is rewritten. This is the heaviest part of a Status poll.
        $cacheFile = $cnf . '.test';
        $cfgMtime = filemtime($cnf);
        $cached = is_file($cacheFile) ? json_decode((string)file_get_contents($cacheFile), true) : null;
        if (is_array($cached) && isset($cached['mtime']) && $cached['mtime'] === $cfgMtime) {
            $diag['config_valid'] = $cached['valid'];
            $diag['config_error'] = $cached['error'];
        } else {
            $out = [];
            $rc = 0;
            exec(sprintf('%s -test -config %s 2>&1', NEBULA_BIN, escapeshellarg($cnf)), $out, $rc);
            $valid = ($rc === 0);
            $err = '';
            if ($rc !== 0) {
                foreach ($out as $line) {
                    if (preg_match('/level=(?:error|fatal)\s+msg="([^"]*)"/', $line, $m)) {
                        $err = $m[1];
                        break;
                    }
                }
                if ($err === '' && !empty($out)) {
                    $err = trim((string)end($out));
                }
            }
            $diag['config_valid'] = $valid;
            $diag['config_error'] = $err;
            @file_put_contents(
                $cacheFile,
                json_encode(['mtime' => $cfgMtime, 'valid' => $valid, 'error' => $err])
            );
        }
    } else {
        $diag['config_valid'] = null;
        $diag['config_error'] = 'no rendered config (apply first)';
    }

    return $diag;
}

/**
 * Restart (re-provision certs, re-render, re-start) a single instance.
 * Stops it first; if disabled or cert is invalid, leaves it stopped.
 *
 * @return array ['started'=>bool] / ['started'=>false,'error'=>str], so the
 *               reload button can surface the real failure reason in the UI.
 */
function nebula_restart_instance($model, $uuid, $general_enabled)
{
    nebula_stop_instance($uuid);

    $node = null;
    foreach ($model->instances->instance->iterateItems() as $iUuid => $iNode) {
        if ($iUuid === $uuid) {
            $node = $iNode;
            break;
        }
    }
    if ($node === null) {
        syslog(LOG_WARNING, "nebula: restart_instance: uuid {$uuid} not found in model; skipping");
        return ['started' => false, 'error' => 'instance not found'];
    }
    if (!$general_enabled || (string)$node->enabled !== '1') {
        syslog(LOG_INFO, "nebula: instance {$uuid} is disabled; not restarting");
        return ['started' => false, 'error' => 'instance is disabled'];
    }
    $res = nebula_start_instance($model, $node, $uuid);
    if (!empty($res['started'])) {
        /* the tun was just recreated — re-attach an assigned OPNsense interface */
        nebula_reattach_interface($uuid);
    }
    return $res;
}

/**
 * Extract the structural config keys that nebula can NOT apply on a live reload
 * (HUP) — the UDP socket bind, the cipher, and the tun device. A change to any
 * of these requires a full restart. host:/port:/dev: also appear nested in other
 * blocks (lighthouse DNS, firewall rules), so we track the current top-level key
 * and only read them under listen:/tun:.
 *
 * @return array{listen_host:string, listen_port:string, tun_dev:string, cipher:string}
 */
function nebula_structural_keys(string $yml): array
{
    $out = ['listen_host' => '', 'listen_port' => '', 'tun_dev' => '', 'cipher' => ''];
    $top = '';
    foreach (preg_split('/\r?\n/', $yml) as $line) {
        if ($line === '') {
            continue;
        }
        if ($line[0] !== ' ' && $line[0] !== "\t") {
            $top = strtok($line, ':');
            if ($top === 'cipher' && preg_match('/^cipher:\s*"?([^"\n]*?)"?\s*$/', $line, $m)) {
                $out['cipher'] = trim($m[1]);
            }
            continue;
        }
        if ($top === 'listen') {
            if (preg_match('/^\s*host:\s*"?([^"\n]*?)"?\s*$/', $line, $m)) {
                $out['listen_host'] = trim($m[1]);
            } elseif (preg_match('/^\s*port:\s*"?([^"\n]*?)"?\s*$/', $line, $m)) {
                $out['listen_port'] = trim($m[1]);
            }
        } elseif ($top === 'tun') {
            if (preg_match('/^\s*dev:\s*"?([^"\n]*?)"?\s*$/', $line, $m)) {
                $out['tun_dev'] = trim($m[1]);
            }
        }
    }
    return $out;
}

/**
 * True when the new config differs from the old in a structural key that nebula
 * can only apply with a full restart (vs a live reload).
 */
function nebula_needs_restart(string $oldYml, string $newYml): bool
{
    return nebula_structural_keys($oldYml) !== nebula_structural_keys($newYml);
}

/**
 * Apply (reconfigure) WITHOUT tearing down live tunnels where possible. Per
 * instance: stop the disabled, start the not-yet-running, and for a running
 * instance reload in place (debug-server `reload`, same as SIGHUP) — falling
 * back to a full restart only when a structural key (listen bind / cipher / tun
 * device) changed, or when the reload itself fails. nebula re-reads the config
 * and the cert files on reload, so both are written before reloading.
 */
function nebula_apply($model, $general_enabled)
{
    $instances = nebula_instances($model);
    nebula_reconcile_orphans($instances);

    foreach ($instances as $uuid => $node) {
        $shouldRun = $general_enabled && (string)$node->enabled === '1';
        $running   = nebula_running_pid($uuid) !== null;

        if (!$shouldRun) {
            if ($running) {
                nebula_stop_instance($uuid);
            }
            continue;
        }
        if (!$running) {
            nebula_start_instance($model, $node, $uuid);
            continue;
        }

        // Running and should run: reload in place, or restart for a structural change.
        $cnfFile = nebula_config_file($uuid);
        $oldYml  = is_file($cnfFile) ? (string)@file_get_contents($cnfFile) : '';
        $newYml  = (string)$model->generateConfig($node, nebula_diag_pubkey());

        if (nebula_needs_restart($oldYml, $newYml)) {
            nebula_restart_instance($model, $uuid, $general_enabled);
            continue;
        }

        if (!nebula_write_certs($model, $node, $uuid)) {
            syslog(LOG_WARNING, "nebula: apply: {$uuid} missing cert material; not reloading");
            continue;
        }
        nebula_render($model, $node, $uuid);
        if (!nebula_validate($cnfFile)) {
            // Keep the running daemon on its previous (valid) config.
            if ($oldYml !== '') {
                file_put_contents($cnfFile, $oldYml);
                @chmod($cnfFile, 0600);
            }
            syslog(LOG_ERR, "nebula: apply: {$uuid} rendered an invalid config; kept the running config");
            continue;
        }

        $res = nebula_debug_cmd($model, $uuid, 'reload');
        if (empty($res['ok'])) {
            syslog(LOG_WARNING, "nebula: apply: {$uuid} reload failed (" . ($res['error'] ?? '') . "); restarting");
            nebula_restart_instance($model, $uuid, $general_enabled);
        } else {
            syslog(LOG_INFO, "nebula: apply: {$uuid} reloaded in place");
        }
    }
}

/**
 * Reconcile orphaned daemons left behind by deleted instances.
 *
 * Globs the rendered configs in NEBULA_ETC, and for every <uuid>.yml whose
 * <uuid> is NOT a currently-configured instance:
 *   - kills any nebula daemon running with that config / pidfile, and
 *   - removes the stale <uuid>.yml, its pidfile and its <uuid>/ cert dir.
 *
 * This is the long-deferred stale-instance cleanup (NOTES.md Phase-2): deleting
 * an enabled instance left an orphan daemon holding e.g. nebula0, which a later
 * `nebula stop` could not catch (the uuid is no longer in the model).
 *
 * @param array $configuredUuids uuid => node map of current instances
 */
function nebula_reconcile_orphans($configuredUuids)
{
    foreach (glob(NEBULA_ETC . '/*.yml') as $cnfFile) {
        $uuid = basename($cnfFile, '.yml');
        if (isset($configuredUuids[$uuid])) {
            continue; /* still configured */
        }

        /* Kill a daemon still running for this orphan (pidfile first). */
        $pid = nebula_running_pid($uuid);
        if ($pid !== null) {
            exec(sprintf('/bin/kill -TERM %d 2>/dev/null', $pid));
            $alive = true;
            for ($i = 0; $i < 25; $i++) {
                $out = [];
                $rc = 0;
                exec(sprintf('/bin/kill -0 %d 2>/dev/null', $pid), $out, $rc);
                if ($rc !== 0) {
                    $alive = false;
                    break;
                }
                usleep(200 * 1000);
            }
            if ($alive) {
                exec(sprintf('/bin/kill -KILL %d 2>/dev/null', $pid));
            }
            syslog(LOG_NOTICE, "nebula: reaped orphan daemon for deleted instance {$uuid}");
        }

        /* Destroy the orphan's tun device (read from its config) before removing it. */
        nebula_destroy_tun($uuid);

        /* Remove stale config, pidfile, and per-instance cert dir. */
        @unlink($cnfFile);
        @unlink(nebula_pid_file($uuid));
        $certDir = NEBULA_ETC . '/' . $uuid;
        if (is_dir($certDir)) {
            foreach (glob($certDir . '/*') as $f) {
                @unlink($f);
            }
            @rmdir($certDir);
        }
        syslog(LOG_INFO, "nebula: cleaned up stale files for deleted instance {$uuid}");
    }
}

/**
 * Iterate configured instances as [uuid => node].
 */
function nebula_instances($model)
{
    $items = [];
    foreach ($model->instances->instance->iterateItems() as $uuid => $node) {
        $items[$uuid] = $node;
    }
    return $items;
}

/**
 * Format a listen host:port the way it must be written/read — IPv6 literals are
 * wrapped in square brackets so the colons in the address are not confused with
 * the host:port separator. "::" (bind all v6) becomes "[::]:4242", a specific
 * "2001:db8::1" becomes "[2001:db8::1]:4242"; IPv4 and hostnames are unchanged
 * ("0.0.0.0:4242"). ":::4242" and "[::]:4242" are different strings — only the
 * bracketed form is correct.
 *
 * @param string $host the listen host (may be an IPv6 literal, IPv4, or hostname)
 * @param string $port the listen port
 * @return string the host:port string with IPv6 hosts bracketed
 */
function nebula_format_listen(string $host, string $port): string
{
    // An IPv6 literal contains ':'; bracket it unless it is already bracketed.
    if (strpos($host, ':') !== false && $host[0] !== '[') {
        $host = '[' . $host . ']';
    }
    return $host . ':' . $port;
}

/**
 * One-shot Status-page snapshot for ALL instances: status + diagnostics + the
 * resolved certificate summary, plus (for running instances) the tun device-info.
 * Live peers and handshaking now live on the Tunnels page, so the snapshot no
 * longer queries the hostmaps.
 *
 * @return array{instances: array<int,array>}
 */
function nebula_snapshot($model)
{
    $certByUuid = [];
    foreach ($model->pki->certificates->certificate->iterateItems() as $crt) {
        $certByUuid[$crt->getAttribute('uuid')] = [
            'name' => (string)$crt->cn,
            'descr' => (string)$crt->descr,
            'groups' => (string)$crt->groups,
            'networks' => (string)$crt->networks,
            'valid_to' => (string)$crt->valid_to,
        ];
    }

    $instances = [];
    foreach (nebula_instances($model) as $uuid => $node) {
        $d = nebula_instance_diag($uuid);
        $d['uuid'] = $uuid;
        $d['description'] = (string)$node->description;
        $d['enabled'] = ((string)$node->enabled === '1');
        $d['am_lighthouse'] = ((string)$node->am_lighthouse === '1');
        $d['listen'] = nebula_format_listen((string)$node->listen_host, (string)$node->listen_port);
        $certref = (string)$node->certref;
        $d['cert'] = ($certref !== '' && isset($certByUuid[$certref])) ? $certByUuid[$certref] : null;
        $d['diag_available'] = !empty($d['running']);

        if (!empty($d['running'])) {
            $res = nebula_debug_cmd($model, $uuid, 'device-info');
            if (!empty($res['ok'])) {
                $dev = [];
                $rawDev = $res['raw'];
                if (preg_match('/name=(\S+)/', $rawDev, $m)) {
                    $dev['name'] = $m[1];
                }
                if (preg_match('/cidr=\[([^\]]*)\]/', $rawDev, $m)) {
                    $dev['cidr'] = $m[1];
                }
                $d['device'] = $dev;
            }
        }
        $instances[] = $d;
    }
    return ['instances' => $instances];
}

/**
 * Live peers for ALL running instances, fanned out in parallel.
 *
 * Each running instance is queried with `list-hostmap -json` over its own ssh
 * connection to the always-on nebula debug server. The connections are launched
 * at once and multiplexed with stream_select(), so total wall-clock ~= the
 * slowest single instance rather than the sum. Each ssh carries ConnectTimeout=4
 * and the whole loop is bounded by an overall deadline; a dead or hung instance
 * simply contributes no rows. Every returned row is tagged with its instance.
 *
 * Both established tunnels (list-hostmap) and peers still handshaking
 * (list-pending-hostmap) are returned; handshaking rows carry handshaking=true
 * and usually have empty name/groups/remotes (no cert exchanged yet).
 *
 * Row shape (one per peer):
 *   instance_uuid, instance, vpn, name, groups, relayed (bool),
 *   handshaking (bool), currentRemote, remoteAddrs (array),
 *   remotes (space-joined string), messages (int)
 *
 * @return array{ok:bool, rows:array<int,array>}
 */
function nebula_all_peers($model)
{
    $priv = NEBULA_DIAG_DIR . '/id_ed25519';
    if (!is_file($priv)) {
        return ['ok' => true, 'rows' => []];
    }

    // Established tunnels come from list-hostmap; peers still handshaking come
    // from list-pending-hostmap. Both run over ONE ssh session per instance (the
    // debug REPL reads commands from stdin), so we still pay just one connection
    // per instance and the combined output is split by the per-command prompt.
    $commands = ['list-hostmap -json', 'list-pending-hostmap -json'];

    $procs = [];
    foreach (nebula_instances($model) as $uuid => $node) {
        if (nebula_running_pid($uuid) === null) {
            continue;
        }
        $port = (int)$model->sshdPortFor($uuid);
        if ($port <= 0) {
            continue;
        }
        // No command arg: the REPL reads our commands from stdin.
        $cmd = sprintf(
            '/usr/local/bin/ssh -i %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null ' .
            '-o BatchMode=yes -o ConnectTimeout=4 -p %d %s@127.0.0.1 2>/dev/null',
            escapeshellarg($priv),
            $port,
            escapeshellarg(NEBULA_DIAG_USER)
        );
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            continue;
        }
        fwrite($pipes[0], implode("\n", $commands) . "\n");
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $descr = (string)$node->description;
        $procs[$uuid] = [
            'proc'  => $proc,
            'out'   => $pipes[1],
            'err'   => $pipes[2],
            'descr' => ($descr !== '' ? $descr : $uuid),
        ];
    }

    // Multiplex stdout until every pipe hits EOF or the overall deadline passes.
    $bufs = array_fill_keys(array_keys($procs), '');
    $byId = [];
    foreach ($procs as $uuid => $p) {
        $byId[(int)$p['out']] = $uuid;
    }
    $pending = array_fill_keys(array_keys($procs), true);
    $deadline = microtime(true) + 8.0; // > ssh ConnectTimeout=4; overall budget for all instances in parallel
    while (!empty($pending) && microtime(true) < $deadline) {
        $read = [];
        foreach (array_keys($pending) as $uuid) {
            $read[] = $procs[$uuid]['out'];
        }
        $write = $except = [];
        $remaining = max(0.0, $deadline - microtime(true));
        $sec = (int)floor($remaining);
        $usec = (int)(($remaining - $sec) * 1e6);
        $n = @stream_select($read, $write, $except, $sec, $usec);
        if ($n === false || $n === 0) {
            break;
        }
        foreach ($read as $stream) {
            $uuid = $byId[(int)$stream] ?? null;
            if ($uuid === null) {
                continue;
            }
            $chunk = fread($stream, 65536); // one pipe buffer; loop accumulates until EOF
            if ($chunk !== '' && $chunk !== false) {
                $bufs[$uuid] .= $chunk;
            }
            if (feof($stream)) {
                unset($pending[$uuid]);
            }
        }
    }

    // Reap: close pipes, terminate any stragglers, close procs.
    foreach ($procs as $uuid => $p) {
        @fclose($p['out']);
        @fclose($p['err']);
        if (isset($pending[$uuid])) {
            @proc_terminate($p['proc']);
        }
        @proc_close($p['proc']);
    }

    /* Project one hostmap/pending entry to a grid row. $handshaking marks a
       peer that is still handshaking (from list-pending-hostmap); such an entry
       usually has no cert yet, so name/groups come out empty. */
    $projectRow = function ($uuid, $descr, $entry, $handshaking) {
        $vpn = (string)($entry['vpnAddrs'][0] ?? '');
        if ($vpn === '') {
            return null;
        }
        $details = $entry['cert']['details'] ?? [];
        $remoteAddrs = array_values(array_filter(
            (array)($entry['remoteAddrs'] ?? []),
            'is_string'
        ));
        return [
            'instance_uuid' => $uuid,
            'instance'      => $descr,
            'vpn'           => $vpn,
            'name'          => (string)($details['name'] ?? ''),
            'groups'        => implode(', ', (array)($details['groups'] ?? [])),
            'relayed'       => !empty($entry['currentRelaysToMe']),
            'handshaking'   => $handshaking,
            'currentRemote' => (string)($entry['currentRemote'] ?? ''),
            'remoteAddrs'   => $remoteAddrs,
            'remotes'       => implode(' ', $remoteAddrs),
            'messages'      => (int)($entry['messageCounter'] ?? 0),
        ];
    };

    // Split each instance's combined output by the per-command "@nebula > <cmd>"
    // prompt, decode each command's JSON, project established + handshaking rows.
    $rows = [];
    foreach ($procs as $uuid => $p) {
        $parts = [];
        $cur = null;
        foreach (explode("\n", $bufs[$uuid]) as $line) {
            if (preg_match('/@nebula > (.*)$/', $line, $m)) {
                $echoed = trim($m[1]);
                $cur = ($echoed === '' || strncmp($echoed, 'Connection to', 13) === 0) ? null : $echoed;
                if ($cur !== null && !isset($parts[$cur])) {
                    $parts[$cur] = '';
                }
            } elseif ($cur !== null) {
                $parts[$cur] .= ($parts[$cur] === '' ? '' : "\n") . $line;
            }
        }

        $decode = function ($raw) {
            $raw = trim((string)$raw);
            if ($raw === '' || strtolower($raw) === 'null') {
                return [];
            }
            $j = json_decode($raw, true);
            return is_array($j) ? $j : [];
        };
        $hostmap = $decode($parts['list-hostmap -json'] ?? '');
        $handshaking = $decode($parts['list-pending-hostmap -json'] ?? '');

        // Established first; dedupe handshaking entries against them (a peer that
        // is already established should not also show as handshaking).
        $seen = [];
        foreach ($hostmap as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $row = $projectRow($uuid, $p['descr'], $entry, false);
            if ($row !== null) {
                $seen[$row['vpn']] = true;
                $rows[] = $row;
            }
        }
        foreach ($handshaking as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $row = $projectRow($uuid, $p['descr'], $entry, true);
            if ($row !== null && !isset($seen[$row['vpn']])) {
                $seen[$row['vpn']] = true;
                $rows[] = $row;
            }
        }
    }
    return ['ok' => true, 'rows' => $rows];
}

openlog('nebula', LOG_ODELAY, LOG_DAEMON);

$action = $argv[1] ?? '';
$uuid_arg = isset($argv[2]) ? trim($argv[2]) : '';
$model = new Nebula();
$general_enabled = (string)$model->general->enabled === '1';

switch ($action) {
    case 'start':
        $instances = nebula_instances($model);
        /* reap any daemon/files left over from a deleted instance first */
        nebula_reconcile_orphans($instances);
        if ($uuid_arg !== '') {
            /* per-instance start — used by the nebula_prepare() interface hook so
             * an assigned nebulaX device gets created during interface bring-up.
             * JSON out so the caller can see the start result. */
            $node = $instances[$uuid_arg] ?? null;
            if ($node !== null && $general_enabled && (string)$node->enabled === '1') {
                echo json_encode(nebula_start_instance($model, $node, $uuid_arg)) . "\n";
            } else {
                echo json_encode(['started' => false, 'error' => 'instance not enabled or unknown']) . "\n";
            }
        } else {
            foreach ($instances as $uuid => $node) {
                if ($general_enabled && (string)$node->enabled === '1') {
                    nebula_start_instance($model, $node, $uuid);
                }
            }
        }
        break;
    case 'stop':
        foreach (nebula_instances($model) as $uuid => $node) {
            nebula_stop_instance($uuid);
        }
        break;
    case 'restart':
        $instances = nebula_instances($model);
        /* reap orphans on every restart (all-instances or single) so a deleted
         * instance's daemon holding e.g. nebula0 is cleared before we re-start. */
        nebula_reconcile_orphans($instances);
        if ($uuid_arg !== '') {
            /* per-instance restart — emit JSON so the reload button (configd
             * restart_instance action, type:script_output) can surface the
             * real start failure reason in the UI. */
            $res = nebula_restart_instance($model, $uuid_arg, $general_enabled);
            echo json_encode($res) . "\n";
        } else {
            /* all-instances restart */
            foreach ($instances as $uuid => $node) {
                nebula_stop_instance($uuid);
            }
            foreach ($instances as $uuid => $node) {
                if ($general_enabled && (string)$node->enabled === '1') {
                    $res = nebula_start_instance($model, $node, $uuid);
                    if (!empty($res['started'])) {
                        nebula_reattach_interface($uuid);
                    }
                }
            }
        }
        break;
    case 'apply':
        /* reconfigure path: reload running instances in place, restarting only
         * those whose structural config (listen bind / cipher / tun dev) changed.
         * Avoids tearing down live tunnels for firewall/route/lighthouse edits. */
        nebula_apply($model, $general_enabled);
        break;
    case 'status':
        if ($uuid_arg !== '') {
            /* per-instance status: JSON to stdout */
            $st = nebula_instance_status($uuid_arg);
            echo json_encode($st) . "\n";
        } else {
            /* all-instances status: human-readable for the service badge */
            $running = 0;
            foreach (nebula_instances($model) as $uuid => $node) {
                if (nebula_running_pid($uuid) !== null) {
                    $running++;
                }
            }
            /*
             * The string "is running" / "not running" is what
             * ApiMutableServiceControllerBase::statusAction() greps for, so this
             * one line drives both the UI status badge and the CLI report.
             */
            if ($running > 0) {
                echo "OK {$running} running, nebula is running\n";
            } else {
                echo "STOPPED, nebula is not running\n";
            }
        }
        break;
    case 'diag':
        /* per-instance diagnostics: JSON to stdout (Status page) */
        if ($uuid_arg !== '') {
            echo json_encode(nebula_instance_diag($uuid_arg)) . "\n";
        }
        break;
    case 'peers':
        /* per-instance live tunnels/peers via the nebula sshd debug server */
        if ($uuid_arg !== '') {
            echo json_encode(nebula_instance_peers($model, $uuid_arg)) . "\n";
        }
        break;
    case 'peers_all':
        /* Tunnels page: live peers for ALL running instances, fanned out in
         * parallel. JSON {ok, rows:[...]} to stdout. */
        echo json_encode(nebula_all_peers($model)) . "\n";
        break;
    case 'snapshot':
        /* one-shot Status-page snapshot for all instances (JSON to stdout) */
        echo json_encode(nebula_snapshot($model)) . "\n";
        break;
    case 'debug':
        /* per-instance debug-server sub-action (Status page enrichment + verbs).
         * argv: debug <uuid> <sub> [<vpn>] [<remote>] */
        if ($uuid_arg !== '') {
            $sub = isset($argv[3]) ? trim($argv[3]) : '';
            $vpn = isset($argv[4]) ? trim($argv[4]) : '';
            $remote = isset($argv[5]) ? trim($argv[5]) : '';
            echo json_encode(nebula_instance_debug($model, $uuid_arg, $sub, $vpn, $remote)) . "\n";
        }
        break;
    default:
        fwrite(STDERR, "Usage: setup.php [start|stop|restart|apply|status|diag|peers|peers_all|debug|snapshot] [<uuid>] ...\n");
        closelog();
        exit(1);
}

closelog();
