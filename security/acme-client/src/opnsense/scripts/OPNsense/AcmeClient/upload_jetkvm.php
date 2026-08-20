#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2026 Frank Wall
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

const ABOUT = <<<TXT

   This script uploads a certificate and private key to a JetKVM device
   (https://jetkvm.com) over SSH, using the same identity/"known_hosts"
   management as the generic SFTP and "Remote Command via SSH" automations
   (see "upload_sftp.php" / "run_remote_ssh.php" for details).

   JetKVM only supports key-based SSH authentication (root@<device>, no
   password logins) once "Developer Mode" and a SSH public key have been
   configured in its web UI (Settings > Advanced). The identity managed by
   this plugin can be reused for that purpose; see "show-identity".

   Since JetKVM does not (yet) expose a documented CLI/API to apply a new
   TLS certificate without using its web UI, the certificate and key are
   written to a configurable directory on the device (defaulting to
   "/userdata/jetkvm/tls", JetKVM's documented storage location for its
   "Custom" TLS mode at the time of writing) via a plain SSH exec session
   (no scp/sftp-server binary is assumed to exist on the device). An
   optional post-upload command may be configured to reload/restart
   whatever is needed to pick up the new files; this is device/firmware
   specific and left blank by default.

   In addition to automations, all operations can also be triggered
   manually using simple CLI commands.

   See: EXAMPLES & actions_acmeclient.conf

TXT;

// Commands & help
const COMMANDS = [
    "upload" => [
        "description" => "transfers a certificate and key to the specified JetKVM device",
        "options" => [
            "host::", "port::", "host-key::", "user::", "identity-type::", "remote-path::",
            "certificates::", "cert-name::", "key-name::", "chmod-cert::", "chmod-key::",
            "restart-command::"],
        "implementation" => "commandUpload",
        "default" => true,
    ],

    "test-connection" => [
        "description" => "connects to the device and returns results as JSON",
        "options" => ["host:", "port::", "host-key::", "user:", "identity-type::"],
        "implementation" => "commandTestConnection",
    ],

    "show-identity" => [
        "description" => "prints the ssh client identity (publickey)",
        "options" => ["identity-type::", "source-ip::", "host::", "unrestricted"],
        "implementation" => "commandShowIdentity",
    ],
];

const EXAMPLES = <<<TXT
- Show the public key used to communicate with the JetKVM device
  ./upload_jetkvm.php --log --identity-type=ecdsa show-identity

- Test connectivity with device
  ./upload_jetkvm.php --log --host=jetkvm.example.com --user=root test-connection

- Upload cert to specific device
  ./upload_jetkvm.php --log --certificates=my.domain.com --host=jetkvm.example.com --user=root

- Load settings from automation with ID and run the upload
  ./upload_jetkvm.php --log --automation-id=ID --certificates=my.domain.com
TXT;

// Permissions
const DEFAULT_CERT_MODE = '0644';
const DEFAULT_KEY_MODE = '0600';

// Remote defaults
//
// Confirmed against a real JetKVM device (root@..., firmware as of
// 2026-08): "Custom" TLS mode is stored at /userdata/jetkvm/tls as
// "user-defined.crt" / "user-defined.key" specifically -- other
// filenames in that directory (e.g. "jetkvm.crt") back other,
// non-custom TLS modes and are not what gets picked up here.
const DEFAULT_REMOTE_PATH = '/userdata/jetkvm/tls';
const DEFAULT_CERT_NAME = 'user-defined.crt';
const DEFAULT_KEY_NAME = 'user-defined.key';
const DEFAULT_USER = 'root';

// Connection test
const CONNECTION_TEST_RESULT = 'OpnSense_ACME_JetKVM_SSH_Connected';
const CONNECTION_TEST_COMMAND = 'echo "' . CONNECTION_TEST_RESULT . '"';

const CONNECTION_EXECUTE_TIMEOUT = 60 * 7; // Max seconds that the remote script may run

// Exit codes
const EXITCODE_SUCCESS = 0;
const EXITCODE_ERROR = 1;
const EXITCODE_ERROR_NO_PERMISSION = 2;
const EXITCODE_ERROR_NOTHING_TO_UPLOAD = 4;
const EXITCODE_ERROR_UNKNOWN_COMMAND = 255;

// Optional imports
@include_once("config.inc");
@include_once("util.inc");
require_once("script/load_phalcon.php");

// Optional autoloader (for local dev environment)
if (!function_exists("log_error")) {
    spl_autoload_register(function ($class_name) {
        require_once(__DIR__ . "/../../../mvc/app/library/" . str_replace("\\", "/", $class_name) . ".php");
    });
}

// Importing classes
use OPNsense\Trust\Cert;
use OPNsense\Trust\Store as CertStore;
use OPNsense\AcmeClient\Process;
use OPNsense\AcmeClient\SSHKeys;
use OPNsense\AcmeClient\Utils;
use OPNsense\AcmeClient\LeUtils;

// Implementing logic
function commandShowIdentity(array &$options): int
{
    $identity_type = trim(($options["identity-type"] ?? "")) ?: SSHKeys::DEFAULT_IDENTITY_TYPE;
    $source_ip = trim(($options["source-ip"] ?? ""));
    $host = trim(($options["host"] ?? ""));

    $keys = new SSHKeys(configPath());
    if (($id_file = $keys->getIdentity($identity_type)) && is_readable($id_file)) {
        if (
            !isset($options["unrestricted"])
            && ($restrictions = SSHKeys::getIdentityRestrictions($host, $source_ip, ""))
        ) {
            echo "$restrictions ";
        }

        echo file_get_contents($id_file);
        return EXITCODE_SUCCESS;
    } else {
        LeUtils::log_error("JetKVM failed getting identity. See log output for details.");
    }
    return EXITCODE_ERROR;
}

function commandTestConnection(array &$options): int
{
    $result = ["actions" => ["connecting"], "success" => false];

    $options["run"] = CONNECTION_TEST_COMMAND;
    $lines = runOnJetKVM($options, $error);

    if (!$error) {
        $result["actions"][] = "connected";
        if (($result["success"] = in_array(CONNECTION_TEST_RESULT, $lines))) {
            $result["actions"][] = "echo-tested";
        }
    } else {
        $result = array_merge($result, ($error ?: []));
    }

    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;

    return $result["success"] ? EXITCODE_SUCCESS : EXITCODE_ERROR;
}

function commandUpload(array &$options): int
{
    if (isset($options["certificates"])) {
        if (isset($options["host"])) {
            return uploadCertificatesToHost($options);
        } else {
            // Find the actions associated with the given certs.
            $tasks = [];
            $cert_ids = preg_split('/[,;\s]+/', $options["certificates"] ?: "", 0, PREG_SPLIT_NO_EMPTY);
            foreach (findCertificates($cert_ids, false) as $id => $cert) {
                foreach ($cert["automations"] as $action_id) {
                    if (!isset($tasks[$action_id])) {
                        $tasks[$action_id] = [];
                    }
                    $tasks[$action_id][] = $id;
                }
            }

            $result = 0;
            foreach ($tasks as $action_id => $cert_list) {
                if (!empty($cert_list) && ($task_options = getOptionsById($action_id, true))) {
                    $task_options = array_merge($options, $task_options, ["certificates" => join(",", $cert_list)]);
                    $result = uploadCertificatesToHost($task_options);
                    if ($result != EXITCODE_SUCCESS) {
                        break;
                    }
                }
            }

            return $result;
        }
    } else {
        LeUtils::log_error("No work to do, '--certificates' is required.");
        return EXITCODE_ERROR_NOTHING_TO_UPLOAD;
    }
}

function uploadCertificatesToHost(array $options): int
{
    $cert_ids = preg_split('/[,;\s]+/', $options["certificates"] ?: "", 0, PREG_SPLIT_NO_EMPTY);
    $certificates = findCertificates($cert_ids);

    if (empty($certificates)) {
        LeUtils::log_error("Could not find any certificates for JetKVM upload (cert-ids: " . (empty($cert_ids) ? "*all*" : join(", ", $cert_ids)) . ").");
        return EXITCODE_ERROR_NOTHING_TO_UPLOAD;
    }

    $remote_path = trim(($options["remote-path"] ?? "")) ?: DEFAULT_REMOTE_PATH;
    $cert_name = trim(($options["cert-name"] ?? "")) ?: DEFAULT_CERT_NAME;
    $key_name = trim(($options["key-name"] ?? "")) ?: DEFAULT_KEY_NAME;
    $chmod_cert = trim(($options["chmod-cert"] ?? "")) ?: DEFAULT_CERT_MODE;
    $chmod_key = trim(($options["chmod-key"] ?? "")) ?: DEFAULT_KEY_MODE;
    $restart_command = trim(($options["restart-command"] ?? ""));

    $result = EXITCODE_SUCCESS;

    // A JetKVM device can only hold a single active TLS certificate, so when
    // multiple certificates are routed to the same automation, only one can
    // be deployed. Only consider certificates that are actually usable
    // (i.e. their content could be resolved from trust storage), then pick
    // the most recently updated one among those.
    $usable_certificates = array_filter($certificates, function ($item) {
        return isset($item["content"])
            && !empty(trim($item["content"]["fullchain"] ?? ($item["content"]["cert"] ?? "")))
            && !empty(trim($item["content"]["key"] ?? ""));
    });

    if (empty($usable_certificates)) {
        LeUtils::log_error(
            "Ignoring JetKVM upload, none of the matched certificates ("
            . join(", ", array_map(fn($c) => $c["name"], $certificates))
            . ") have usable certificate/key content in trust storage."
        );
        return EXITCODE_ERROR_NOTHING_TO_UPLOAD;
    }

    $cert = array_reduce($usable_certificates, function ($carry, $item) {
        return ($carry === null || $item["updated"] > $carry["updated"]) ? $item : $carry;
    }, null);

    if (count($certificates) > 1) {
        LeUtils::log_debug(
            "JetKVM upload received multiple certificates, deploying only the most recently updated (usable) one: "
            . $cert["name"]
        );
    }

    $cert_content = $cert["content"]["fullchain"] ?? ($cert["content"]["cert"] ?? "");
    $key_content = $cert["content"]["key"] ?? "";

    if (($script = buildRemoteScript(
        $remote_path,
        $cert_name,
        $cert_content,
        $chmod_cert,
        $key_name,
        $key_content,
        $chmod_key,
        $restart_command
    )) === null) {
        LeUtils::log_error("Ignoring JetKVM upload for cert '{$cert["name"]}', remote path or filenames are invalid.");
        return EXITCODE_ERROR;
    }

    $options["run"] = $script;
    runOnJetKVM($options, $error);

    if ($error) {
        LeUtils::log_error("JetKVM upload failed for cert '{$cert["name"]}'", $error);
        return ($error["connect_failed"] ?? false) ? EXITCODE_ERROR_NO_PERMISSION : EXITCODE_ERROR;
    }

    LeUtils::log("JetKVM upload succeeded for cert '{$cert["name"]}' (deployed to {$options["host"]}:{$remote_path}).");

    return $result;
}

/**
 * Builds a POSIX shell script that writes the certificate and key to the
 * device and applies the requested permissions, followed by an optional
 * restart/reload command. The script is fed to the remote shell via stdin,
 * so it never needs to be passed as (length limited/escaped) argv.
 *
 * Both files are staged under temporary names in the same directory and
 * only "mv"-ed into their final names (an atomic rename on the same
 * filesystem) once both have been fully written and chmod'ed. This keeps
 * a dropped connection or a failed write (e.g. disk full) from ever
 * leaving the device with a truncated or mismatched cert/key pair,
 * since the existing files are only touched by the two final "mv" calls,
 * right next to each other at the end of the script.
 */
function buildRemoteScript(
    string $remote_path,
    string $cert_filename,
    string $cert_content,
    string $chmod_cert,
    string $key_filename,
    string $key_content,
    string $chmod_key,
    string $restart_command
): ?string {
    $remote_path = rtrim(trim($remote_path), '/');

    // Filenames are always written directly below $remote_path; strip any
    // directory components (e.g. "../../etc/passwd") so a crafted filename
    // can never escape the configured remote directory.
    $cert_filename = basename(trim($cert_filename));
    $key_filename = basename(trim($key_filename));

    $invalid_filename = fn($name) => empty($name) || $name === '.' || $name === '..';

    if (empty($remote_path) || $invalid_filename($cert_filename) || $invalid_filename($key_filename)) {
        LeUtils::log_error("JetKVM remote path and filenames must not be empty (path='$remote_path', cert='$cert_filename', key='$key_filename').");
        return null;
    }

    $cert_target = escapeshellarg($remote_path . '/' . $cert_filename);
    $key_target = escapeshellarg($remote_path . '/' . $key_filename);

    // Random, hard to guess markers to delimit heredocs; PEM content will
    // never coincidentally match these. The same random suffix also names
    // the temporary staging files, so concurrent runs can't collide.
    $run_id = bin2hex(random_bytes(16));
    $cert_marker = 'ACME_JETKVM_CERT_' . $run_id;
    $key_marker = 'ACME_JETKVM_KEY_' . $run_id;
    $cert_tmp_target = escapeshellarg($remote_path . '/.' . $cert_filename . '.tmp.' . $run_id);
    $key_tmp_target = escapeshellarg($remote_path . '/.' . $key_filename . '.tmp.' . $run_id);

    $lines = [
        '#!/bin/sh',
        'set -e',
        'umask 077',
        'mkdir -p ' . escapeshellarg($remote_path),
        "cat > $cert_tmp_target <<'{$cert_marker}'",
        rtrim($cert_content, "\r\n"),
        $cert_marker,
        'chmod ' . escapeshellarg($chmod_cert) . " $cert_tmp_target",
        "cat > $key_tmp_target <<'{$key_marker}'",
        rtrim($key_content, "\r\n"),
        $key_marker,
        'chmod ' . escapeshellarg($chmod_key) . " $key_tmp_target",
        "mv $cert_tmp_target $cert_target",
        "mv $key_tmp_target $key_target",
    ];

    if (trim($restart_command) !== '') {
        $lines[] = trim($restart_command);
    }

    return join("\n", $lines) . "\n";
}

/**
 * Connects to the JetKVM device and runs the given shell script/command
 * (passed as $options["run"]) via a plain "ssh ... sh" exec session, piping
 * the script through stdin. Re-uses the shared identity/known_hosts store.
 */
function runOnJetKVM(array $options, &$error): ?array
{
    static $expected_errors = [
        ["host_not_resolved", /*   -> */ '/.*not resolve.*/i'],
        ["host_not_trusted", /*    -> */ '/.*IDENTIFICATION HAS CHANGED.*/i'],
        ["connection_refused", /*  -> */ '/.*connection refused.*/i'],
        ["connection_closed", /*   -> */ '/.*connection closed.*/i'],
        ["network_timeout", /*     -> */ '/.*timed out.*/i'],
        ["network_unreachable", /* -> */ '/.*network.+unreachable.*/i'],
        ["permission_denied", /*   -> */ '/.*permission denied.*/i'],
        ["failure", /*             -> */ '/.*(error|failure|you must supply).*/i'],
    ];

    $ssh_keys = new SSHKeys(configPath());

    $identity_type = trim(($options["identity-type"] ?? ""));
    $host = trim(($options["host"] ?? ""));
    $host_key = ($options["host-key"] ?? "");
    $port = !empty($options["port"]) ? $options["port"] : SSHKeys::DEFAULT_PORT;
    $username = trim(($options["user"] ?? "")) ?: DEFAULT_USER;
    $script = $options["run"] ?? "";

    list($ok, $cmd) = buildSSHArguments($ssh_keys, $host, $username, $identity_type, $host_key, $port);
    if (!$ok) {
        $error = $cmd;
        $error["connect_failed"] = true;
        return null;
    }

    if (empty($script)) {
        $error = ["no_command" => true];
        return null;
    }

    // Run "sh" on the remote side and feed it the script via stdin, rather
    // than passing it as a single (length limited, quoting-sensitive)
    // command-line argument.
    $cmd[] = "sh";

    $result = [];
    $exit_code = null;
    $expected_error = null;

    if ($process = Process::open($cmd)) {
        $process->put($script, "");
        $process->closeInput();

        $lines = 0;
        $start = time();
        $mustClose = fn($lines) => (time() - $start) > CONNECTION_EXECUTE_TIMEOUT || $lines > 10000;

        while ($process->isRunning() && !$mustClose($lines)) {
            for (; ($line = $process->get()) !== false && !$mustClose($lines); $lines++) {
                if (!$expected_error) {
                    foreach ($expected_errors as $ee) {
                        if (preg_match($ee[1], $line)) {
                            if ($ee[0] !== "connection_closed") {
                                $expected_error = [$ee[0] => true, "error" => trim($line)];
                            }
                            break;
                        }
                    }
                }
                $result[] = $line;
            }
        }
        $exit_code = $process->close();
        $ok = $exit_code === 0;
    } else {
        $ok = false;
    }

    if (!$ok) {
        $cl = join(" ", array_map(fn($v) => escapeshellarg($v), $cmd));
        $error = array_merge(($expected_error ?? []), [
            "result" => $result,
            "exit_code" => $exit_code
        ]);
        $error["connect_failed"] = $exit_code == 255;
        LeUtils::log_error("JetKVM SSH failed with '$exit_code': $cl", $error);
    }

    return $result;
}

function buildSSHArguments(SSHKeys $ssh_keys, $host, $username, $identity_type = "", $host_key = "", $port = SSHKeys::DEFAULT_PORT): array
{
    if (empty(trim($host)) || empty(trim($username))) {
        LeUtils::log_error("Failed connecting to '$host'. Hostname or username is missing.");
        return [false, ["invalid_parameters" => true]];
    }

    if (empty($identity_type)) {
        $identity_type = SSHKeys::DEFAULT_IDENTITY_TYPE;
    }

    $trust = $ssh_keys->trustHost($host, $host_key, $port);
    if ($trust["ok"] !== true) {
        LeUtils::log_error("Failed establishing trust in '$host'; Cause: {$trust["error"]}");
        unset($trust["ok"]);
        return [false, array_merge($trust, ["host_not_trusted" => true])];
    } else {
        $host = $trust["host"];
    }

    // Building ssh command.
    $cmd = [
        "ssh",
        "-p", $port,
        "-oUser=$username",
        "-oUserKnownHostsFile={$ssh_keys->knownHostsFile()}",
    ];

    // Handle client side identity
    $identity = $ssh_keys->getIdentity($identity_type, true);
    if (is_file($identity) && is_readable($identity)) {
        array_push(
            $cmd,
            "-i",
            $identity,
            "-oPreferredAuthentications=publickey"
        );
    } else {
        LeUtils::log_error("Failed adding SSH client identity ($identity). Connect will likely fail.");
    }

    // Adding the host
    $cmd[] = "$host";

    return [true, $cmd];
}

function help()
{
    Utils::printCLIHelp(ABOUT, EXAMPLES, COMMANDS);
}

function getOptionsById($automation_id, $silent = false)
{
    if (!$silent) {
        LeUtils::log_debug("Reading options from automation: $automation_id");
    }

    if (is_object($action = Utils::getAutomationActionById($automation_id))) {
        if ($action->enabled && "configd_upload_jetkvm" === (string)$action->type) {
            return [
                "host" => trim((string)$action->jetkvm_host),
                "host-key" => trim((string)$action->jetkvm_host_key),
                "port" => trim((string)$action->jetkvm_port),
                "identity-type" => trim((string)$action->jetkvm_identity_type),
                "user" => trim((string)$action->jetkvm_user),
                "remote-path" => trim((string)$action->jetkvm_remote_path),
                "cert-name" => trim((string)$action->jetkvm_filename_cert),
                "key-name" => trim((string)$action->jetkvm_filename_key),
                "chmod-cert" => trim((string)$action->jetkvm_chmod_cert),
                "chmod-key" => trim((string)$action->jetkvm_chmod_key),
                "restart-command" => trim((string)$action->jetkvm_restart_command),
                "certificates" => "", // defaults to all (= empty), may be overridden via CLI
            ];
        } elseif (!$silent) {
            LeUtils::log_error("JetKVM ignoring disabled or invalid automation '$automation_id'");
        }
    } else {
        LeUtils::log_error("No JetKVM upload automation found with uuid = '$automation_id'");
    }

    return false;
}

function findCertificates(array $certificate_ids_or_names, $load_content = true): array
{
    if (!class_exists("OPNsense\\Core\\Config")) {
        return [];
    }

    $config = OPNsense\Core\Config::getInstance()->object();
    $client = $config->OPNsense->AcmeClient;

    $result = [];
    $refids = [];

    foreach ($client->certificates->children() as $cert) {
        $item = [];
        $id = (string)$cert->id;
        $name = (string)$cert->name;

        if (
            empty($certificate_ids_or_names)
            || in_array($id, $certificate_ids_or_names)
            || in_array($name, $certificate_ids_or_names)
        ) {
            if ($cert->enabled == 0) {
                if (!empty($certificate_ids_or_names)) {
                    LeUtils::log_error("Certificate '{$name}' (id: $id) is disabled, skipping JetKVM upload.");
                }

                continue;
            }

            $item["id"] = $id;
            $item["name"] = $name;
            $item["updated"] = intval($cert->lastUpdate);
            $item["automations"] = preg_split('/[\s,]+/', $cert->restartActions);
            if (isset($cert->certRefId)) {
                $refids[] = $item['content_id'] = (string)$cert->certRefId;
            }

            $result[$id] = $item;
        }
    }

    if ($load_content && ($certificates = exportCertificates($refids))) {
        foreach ($result as &$cert_info) {
            $id = $cert_info["content_id"];
            if (isset($certificates[$id])) {
                $cert_info["content"] = $certificates[$id];
            }
        }
    }

    return $result;
}

function exportCertificates(array $cert_refids): array
{
    $result = [];
    $certModel = new Cert();
    foreach ($certModel->cert->iterateItems() as $cert) {
        $refid = (string)$cert->refid;
        $item = [];
        if (in_array($refid, $cert_refids)) {
            $_tmp = CertStore::getCertificate($refid);
            $item["cert"] = $_tmp["crt"];
            $item["key"] = $_tmp["prv"];
            // check if a CA is linked
            if (!empty((string)$cert->caref)) {
                $item['ca'] = $_tmp['ca']['crt'];

                // combine files to export a fullchain.pem
                $item["fullchain"] = $item["cert"] . $item["ca"];
            }
            $result[$refid] = $item;
        }
    }

    return $result;
}

function configPath(): string
{
    if (($path = Utils::configPath())) {
        // shared with sftp/remote-ssh to reuse the same identities & known_hosts
        return $path . DIRECTORY_SEPARATOR . "sftp-config";
    }
    die("Failed detecting config path");
}

// Running the main script
Utils::runCLIMain(
    "help",
    "getOptionsById",
    COMMANDS,
    EXITCODE_SUCCESS,
    EXITCODE_ERROR_UNKNOWN_COMMAND
);
