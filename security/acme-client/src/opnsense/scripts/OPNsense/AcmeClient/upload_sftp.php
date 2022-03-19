#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2019 Juergen Kellerer
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

   This script implements a SFTP based certificate uploader that fully manages
   identities and "known_hosts" inside a local configuration folder.

   Primary purpose is to support the creation of automation tasks that deploy
   certificates to other hosts via SFTP after their creation or renewal.
   In addition to automations, all operations can also be triggered manually
   using simple CLI commands.

   Care has been taken to implement this in a secure way. Authorization is only
   possible via trusted public keys (identities) and restrictions are added to
   the key output created for inclusion in the remote side's "authorized_keys".
   This limits attack vectors that might arise form information leakage.

   Identities & "known_hosts" are kept in "/var/etc/acme-client/sftp-config"
   by default (see "config_path()"). Private info is owner accessible only.

   Implementation-wise, ssh/sftp tools are used via their CLI api. No attempts
   are made to understand key and file formats more than necessary to reduce
   maintenance efforts and not to break on system wide SSH setups like enabling
   hashing of hostnames.

   Since there is some complexity involved, the script has a rich commandline
   api for testing and integration purposes. It also runs perfectly fine in a
   local development environment without the opnsense api in place (using
   "--files=file,..." to specify files to upload directly).

   See: EXAMPLES & actions_acmeclient.conf

TXT;

// Commands & help
const COMMANDS = [
    "upload" => [
        "description" => "transfers certificates to the specified target host",
        "options" => [
            "host::", "port::", "host-key::", "user::", "identity-type::", "remote-path::",
            "certificates::", "files::", "chgrp::", "chmod::", "chmod-key::",
            "cert-name::", "key-name::", "ca-name::", "fullchain-name::"],
        "implementation" => "commandUpload",
        "default" => true,
    ],

    "test-connection" => [
        "description" => "connects to the host and returns results as JSON",
        "options" => [
            "host:", "port::", "host-key::", "user:", "remote-path::", "identity-type::",
            "chgrp::", "chmod::"],
        "implementation" => "commandTestConnection",
    ],

    "show-identity" => [
        "description" => "prints the ssh client identity (publickey)",
        "options" => ["identity-type::", "source-ip::", "host::", "unrestricted"],
        "implementation" => "commandShowIdentity",
    ],
];

const EXAMPLES = <<<TXT
- Show the public key used to communicate with the SFTP server
  ./upload_sftp.php --log --identity-type=ecdsa show-identity

- Test connectivity with host
  ./upload_sftp.php --log --host=sftpserver --user=name test-connection

- Upload certs to servers configured in the certs
  ./upload_sftp.php --log --certificates=my.domain.com,my.otherdomain.org

- Upload cert to specific server
  ./upload_sftp.php --log --certificates=my.domain.com --host=sftpserver --user=name

- Upload all enabled certs to specific server
  ./upload_sftp.php --log --host=sftpserver --user=name
TXT;

// Permissions
const DEFAULT_CERT_MODE = '0440';
const DEFAULT_KEY_MODE = '0400';

// Names
const UPLOAD_NAME_TEMPLATES = [
    "cert" => ["default" => "{{name}}/cert.pem", "option" => "cert-name"],
    "key" => ["default" => "{{name}}/key.pem", "option" => "key-name"],
    "ca" => ["default" => "{{name}}/ca.pem", "option" => "ca-name"],
    "fullchain" => ["default" => "{{name}}/fullchain.pem", "option" => "fullchain-name"],
];

// Exit codes
const EXITCODE_SUCCESS = 0;
const EXITCODE_ERROR = 1;
const EXITCODE_ERROR_NO_PERMISSION = 2;
const EXITCODE_ERROR_NOTHING_TO_UPLOAD = 4;
const EXITCODE_ERROR_UNKNOWN_COMMAND = 255;

// Optional imports
@include_once("config.inc");
@include_once("certs.inc");
@include_once("util.inc");

// Optional autoloader (for local dev environment)
if (!function_exists("log_error")) {
    spl_autoload_register(function ($class_name) {
        require_once(__DIR__ . "/../../../mvc/app/library/" . str_replace("\\", "/", $class_name) . ".php");
    });
}

// Importing classes
use OPNsense\AcmeClient\SftpUploader;
use OPNsense\AcmeClient\SftpClient;
use OPNsense\AcmeClient\SSHKeys;
use OPNsense\AcmeClient\Utils;

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
            && ($restrictions = SSHKeys::getIdentityRestrictions($host, $source_ip))
        ) {
            echo "$restrictions ";
        }

        echo file_get_contents($id_file);
        return EXITCODE_SUCCESS;
    } else {
        Utils::log()->error("Failed getting identity. See log output for details.");
    }
    return EXITCODE_ERROR;
}

function commandTestConnection(array &$options): int
{
    $result = ["actions" => ["connecting"]];

    // Testing connection
    $sftp = connectWithServer($options, $error);
    if ($result["success"] = ($sftp !== null && $sftp->connected())) {
        $result["actions"][] = "connected";
        $result["remote"] = array_merge($sftp->connected(), ["path" => $sftp->pwd()]);
    } else {
        $result = array_merge($result, ($error ?: []));
    }

    // Testing file upload
    if ($result["success"]) {
        $result["actions"][] = "upload-testing";

        $uploader = new SftpUploader($sftp);

        $chgrp = ($options["chgrp"] ?? "") ?: false;
        $chmod = isset($options["chmod"]) ? ($options["chmod"] ?: DEFAULT_CERT_MODE) : false;
        $filename = $uploader->addContent("upload-test", "", 0, $chmod, $chgrp);

        $upload_result = $uploader->upload();
        $result["success"] = $upload_result === SftpUploader::UPLOAD_SUCCESS;

        if ($result["success"]) {
            $result["actions"][] = "upload-tested";
        } else {
            if ($error = $sftp->lastError(3)) {
                $result = array_merge($result, $error);
            }

            if ($upload_result === SftpUploader::UPLOAD_ERROR_CHGRP_FAILED) {
                $result["chgrp_failed"] = true;
            } elseif ($upload_result === SftpUploader::UPLOAD_ERROR_CHMOD_FAILED) {
                $result["chmod_failed"] = true;
            }
        }

        $remove_file = in_array($upload_result, [
            SftpUploader::UPLOAD_SUCCESS,
            SftpUploader::UPLOAD_ERROR_CHGRP_FAILED,
            SftpUploader::UPLOAD_ERROR_CHMOD_FAILED]);

        if ($remove_file) {
            if ($error = $sftp->clearError()->rm($filename)->lastError(3)) {
                Utils::log()->error("Failed removing upload test file '$filename'", $error);
            }
        }

        $sftp->close();
    }

    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;

    return $result["success"] ? EXITCODE_SUCCESS : EXITCODE_ERROR;
}

function commandUpload(array &$options): int
{
    if (isset($options["certificates"])) {
        // Includes host, upload all certs to the same host.
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
    } elseif (isset($options["host"])) {
        return uploadCertificatesToHost($options);
    } else {
        Utils::log()->error("No work to do, neither --host nor --certificates is present.");
        return EXITCODE_ERROR_NOTHING_TO_UPLOAD;
    }
}

function uploadCertificatesToHost(array $options): int
{
    $sftp = connectWithServer($options, $error);
    if ($sftp === null) {
        Utils::log()->error("Aborting after connect failure.");
        return ($error["connect_failed"] ?? false)
            ? EXITCODE_ERROR
            : EXITCODE_ERROR_NO_PERMISSION;
    }

    try {
        $uploader = new SftpUploader($sftp);

        addFilesToUpload($options, $uploader);

        if (empty($uploader->pending())) {
            return EXITCODE_ERROR_NOTHING_TO_UPLOAD;
        }

        for ($max_restarts = 5; !empty($uploader->pending()) && $max_restarts > 0; $max_restarts--) {
            $result = $uploader->upload();

            if ($result != SftpUploader::UPLOAD_SUCCESS) {
                Utils::log()->error("Failed on " . json_encode($uploader->current(), JSON_UNESCAPED_SLASHES));

                switch ($result) {
                    case SftpUploader::UPLOAD_ERROR_NO_PERMISSION:
                        return EXITCODE_ERROR_NO_PERMISSION;

                    case SftpUploader::UPLOAD_ERROR:
                        return EXITCODE_ERROR;

                    case SftpUploader::UPLOAD_ERROR_CHGRP_FAILED:
                    case SftpUploader::UPLOAD_ERROR_CHMOD_FAILED:
                    case SftpUploader::UPLOAD_ERROR_NO_OVERWRITE:
                    default:
                        break;
                }
            } else {
                break;
            }
        }
    } finally {
        $sftp->close();
    }

    return EXITCODE_SUCCESS;
}

function connectWithServer(array $options, &$error): ?SftpClient
{
    $identity_type = trim(($options["identity-type"] ?? "")) ?: SSHKeys::DEFAULT_IDENTITY_TYPE;
    $host = trim(($options["host"] ?? ""));
    $host_key = ($options["host-key"] ?? "");
    $port = $options["port"] ?? 22;
    $username = $options["user"];

    $sftp = new SftpClient(configPath(), $identity_type);

    if (!$sftp->connect($host, $username, $host_key, $port)) {
        $error = $sftp->lastError();
        $error["connect_failed"] = true;
        return null;
    }

    // Apply start path (if one was specified, defaults to home dir)
    if (!empty($remote_path = ($options["remote-path"] ?? ""))) {
        if ($err = $sftp->cd($remote_path)->lastError()) {
            $error = $err;
            $error["change_home_dir_failed"] = true;
            Utils::log()->error("Failed cd into '{$remote_path}'", $err);
            return null;
        }
    }

    return $sftp;
}

function help()
{
    Utils::printCLIHelp(ABOUT, EXAMPLES, COMMANDS);
}

function getOptionsById($automation_id, $silent = false)
{
    if (!$silent) {
        Utils::log()->info("Reading options from automation: $automation_id");
    }

    if (is_object($action = Utils::getAutomationActionById($automation_id))) {
        if ($action->enabled && "configd_upload_sftp" === (string)$action->type) {
            return [
                "host" => trim((string)$action->sftp_host),
                "host-key" => trim((string)$action->sftp_host_key),
                "port" => trim((string)$action->sftp_port),
                "identity-type" => trim((string)$action->sftp_identity_type),
                "user" => trim((string)$action->sftp_user),
                "remote-path" => trim((string)$action->sftp_remote_path),
                "chgrp" => trim((string)$action->sftp_chgrp),
                "chmod" => trim((string)$action->sftp_chmod),
                "chmod-key" => trim((string)$action->sftp_chmod_key),
                "cert-name" => trim((string)$action->sftp_filename_cert),
                "key-name" => trim((string)$action->sftp_filename_key),
                "ca-name" => trim((string)$action->sftp_filename_ca),
                "fullchain-name" => trim((string)$action->sftp_filename_fullchain),
                "certificates" => "", // defaults to all (= empty), may be overridden via CLI
            ];
        } elseif (!$silent) {
            Utils::log()->error("Ignoring disabled or invalid automation '$automation_id'");
        }
    } else {
        Utils::log()->error("No upload automation found with uuid = '$automation_id'");
    }

    return false;
}

function addFilesToUpload(array $options, SftpUploader &$uploader)
{
    $chmod = isset($options["chmod"]) ? ($options["chmod"] ?: DEFAULT_CERT_MODE) : false;
    $chmod_key = isset($options["chmod-key"]) ? ($options["chmod-key"] ?: DEFAULT_KEY_MODE) : false;
    $chgrp = ($options["chgrp"] ?? "") ?: false;

    if (isset($options["certificates"])) {
        $cert_ids = preg_split('/[,;\s]+/', $options["certificates"] ?: "", 0, PREG_SPLIT_NO_EMPTY);

        foreach (findCertificates($cert_ids) as $cert) {
            if (!isset($cert["content"])) {
                Utils::log()->error("Ignoring upload for cert '{$cert["name"]}', since it is not available in trust storage.");
                continue;
            }

            foreach ($cert["content"] as $name => $content) {
                if (empty($content)) {
                    Utils::log()->error("Content for '{$name}.pem' in cert '{$cert["name"]}' is empty, skipping it.");
                    continue;
                }

                // Build the upload name
                $target_path = requireThat(UPLOAD_NAME_TEMPLATES[$name], "No upload template defined for '{$name}'");
                $target_path = stripcslashes($options[$target_path["option"]] ?: $target_path["default"]);

                $target_path = join("/", array_map(
                    function ($path_part) use (&$cert) {
                        // Replace template params "{{.+}}" & "%s"
                        $path_part = preg_replace_callback(
                            ['/%s/', '/{{([^}]+?)}}/'],
                            function ($m) use (&$cert) {
                                $index = $m[0] == '%s' ? "name" : trim($m[1]);

                                return in_array($index, ["name", "id", "updated"])
                                    ? stripcslashes($cert[$index])
                                    : "__unknown-template-param__{$index}__";
                            },
                            $path_part
                        );

                        // Sanitize user input. Allow unicode chars, numbers and some special characters [_-@.].
                        // Also replace all ".." with "." to avoid upwards tree traversal.
                        return preg_replace(['/\.+/', '/[^\w\d_\-@.]+/uim'], ['.', '-'], trim($path_part));
                    },
                    preg_split('-[/\\\\]+-', $target_path, 0, PREG_SPLIT_NO_EMPTY)
                ));


                // Add the file to upload (if valid)
                if (
                    !empty($target_path)
                    && preg_match('-^(?!/).+?(?<!/)$-', $target_path) /* must neither begin nor end with '/' */
                    && !preg_match('-^[/.]+$-', $target_path)  /* must not only consist of '/' and '.' */
                ) {
                    $mod = $name === "key"
                        ? $chmod_key
                        : $chmod;

                    $uploader->addContent($content, $target_path, $cert["updated"], $mod, $chgrp);
                } else {
                    Utils::log()->error("Cannot add '{$name}.pem' since the upload path '$target_path' is invalid.");
                }
            }
        }

        if (empty($uploader->pending())) {
            Utils::log()->error("Didn't find any certificates to upload (cert-ids: " . (empty($cert_ids) ? "*all*" : join(", ", $cert_ids)) . ").");
        }
    } elseif (isset($options["files"])) {
        $files = preg_split('/[,;\s]+/', $options["files"] ?: "", 0, PREG_SPLIT_NO_EMPTY);
        foreach ($files as $file) {
            $uploader->addFile($file, "", $chmod, $chgrp);
        }

        if (empty($uploader->pending())) {
            Utils::log()->error("Didn't files to upload (files: " . join(", ", $files) . ").");
        }
    } else {
        Utils::log()->error("Neither '--certificates' nor '--files' was specified. Have nothing to upload.");
    }
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
                    Utils::log()->error("Certificate '{$name}' (id: $id) is disabled, skipping it.");
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
    $config = OPNsense\Core\Config::getInstance()->object();
    foreach ($config->cert as $cert) {
        $refid = (string)$cert->refid;
        $item = [];
        if (in_array($refid, $cert_refids)) {
            $item["cert"] = str_replace(["\n\n", "\r"], ["\n", ""], base64_decode($cert->crt));
            $item["key"] = str_replace(["\n\n", "\r"], ["\n", ""], base64_decode($cert->prv));
            // check if a CA is linked
            if (!empty((string)$cert->caref)) {
                $cert = (array)$cert;
                $item["ca"] = ca_chain($cert);
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
        return $path . DIRECTORY_SEPARATOR . "sftp-config";
    }
    die("Failed detecting config path");
}

function requireThat($expression, $message)
{
    try {
        Utils::requireThat($message, $message);
    } catch (\AssertionError $e) {
        exit(EXITCODE_ERROR);
    }
    return $expression;
}

// Running the main script
Utils::runCLIMain(
    "help",
    "getOptionsById",
    COMMANDS,
    EXITCODE_SUCCESS,
    EXITCODE_ERROR_UNKNOWN_COMMAND
);
