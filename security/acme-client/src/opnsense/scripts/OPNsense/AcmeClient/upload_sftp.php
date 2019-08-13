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
            "certificates::", "files::", "chgrp::", "chmod::", "chmod-key::"],
        "implementation" => "commandUpload",
        "default" => true,
    ],

    "test-connection" => [
        "description" => "connects to the host and returns results as JSON",
        "options" => [
            "host:", "port::", "host-key::", "user:", "remote-path::", "identity-type::"],
        "implementation" => "commandTestConnection",
    ],

    "show-identity" => [
        "description" => "prints the ssh client identity (publickey)",
        "options" => ["identity-type::", "source-ip::", "host::", "unrestricted"],
        "implementation" => "commandShowIdentity",
    ],
];

const STATIC_OPTIONS = <<<TXT
-h, --help          Print commandline help
--log               Enable log to stdout (instead of syslog)
--automation-id     Read options from the action specified by id or uuid
--no-error          Always exit with 0 (original exit codes are still logged)
TXT;

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

// Syslog level used for verbose info log.
// Change to "LOG_NOTICE" to make log output visible in the UI.
const SYSLOG_INFO_LEVEL = LOG_INFO;

// Permissions
const CONFIG_PATH_CREATE_MODE = 0750;
const KNOWN_HOSTS_FILE_CREATE_MODE = 0640;
const DEFAULT_CERT_MODE = 0440;
const DEFAULT_KEY_MODE = 0400;

// Keys & bits
const DEFAULT_KEY_TYPE = "ecdsa";

const DEFAULT_IDENTITY_KEY_BITS = ["rsa" => 4096, "ecdsa" => 521];
const IDENTITY_TYPES = ["rsa", "rsa_2048", "rsa_4096", "rsa_8192", "ecdsa", "ecdsa_256", "ecdsa_384", "ecdsa_521", "ed25519"];
const DEFAULT_IDENTITY_TYPE = "ecdsa";

const EXITCODE_SUCCESS = 0;
const EXITCODE_ERROR = 1;
const EXITCODE_ERROR_NO_PERMISSION = 2;
const EXITCODE_ERROR_NOTHING_TO_UPLOAD = 4;
const EXITCODE_ERROR_UNKNOWN_COMMAND = 255;

// Optional imports
@include_once("config.inc");
@include_once("certs.inc");
@include_once("util.inc");

// --------------------------------------------------------------------------------------------------------------------
// Main script logic

function commandShowIdentity(array &$options): int
{
    $identity_type = trim(($options["identity-type"] ?: DEFAULT_IDENTITY_TYPE));
    $source_ip = trim(($options["source-ip"] ?: ""));
    $host = trim(($options["host"] ?: ""));

    $keys = new SSHKeys(configPath());
    if (($id_file = $keys->getIdentity($identity_type)) && is_readable($id_file)) {

        if (!isset($options["unrestricted"])
            && ($restrictions = SSHKeys::getIdentityRestrictions($host, $source_ip))) {
            echo "$restrictions ";
        }

        echo file_get_contents($id_file);
        return EXITCODE_SUCCESS;

    } else {
        logger()->error("Failed getting identity. See log output for details.");
    }
    return EXITCODE_ERROR;
}

function commandTestConnection(array &$options): int
{
    $result = ["actions" => ["connecting"]];

    // Testing connection
    $sftp = connectWithServer($options, $error);
    if ($result["success"] = ($sftp !== null)) {
        $result["actions"][] = "connected";
        $result["remote"] = [
            "address" => $sftp->remote_address,
            "path" => $sftp->pwd(),
        ];
    } else {
        $result = array_merge($result, $error);
    }

    // Testing file upload
    if ($result["success"]) {
        $result["actions"][] = "upload-testing";
        $file = temporaryFile();
        $filename = "." . basename($file);

        if ($error = $sftp->put($file, $filename)->lastError(3)) {
            $result["success"] = false;
            $result = array_merge($result, $error);
        } else if ($error = $sftp->rm($filename)->lastError(3)) {
            logger()->error("Failed removing upload test file '$filename'. Cause: " . json_encode($error, JSON_UNESCAPED_SLASHES));
        }

        if ($result["success"]) $result["actions"][] = "upload-tested";
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
                    if (!isset($tasks[$action_id]))
                        $tasks[$action_id] = [];
                    $tasks[$action_id][] = $id;
                }
            }

            $result = 0;
            foreach ($tasks as $action_id => $cert_list) {
                if (!empty($cert_list) && ($task_options = getOptionsById($action_id, true))) {
                    $task_options = array_merge($options, $task_options, ["certificates" => join(",", $cert_list)]);
                    $result = uploadCertificatesToHost($task_options);
                    if ($result != EXITCODE_SUCCESS)
                        break;
                }
            }

            return $result;
        }

    } else if (isset($options["host"])) {
        return uploadCertificatesToHost($options);

    } else {
        logger()->error("No work to do, neither --host nor --certificates is present.");
        return EXITCODE_ERROR_NOTHING_TO_UPLOAD;
    }
}

function uploadCertificatesToHost(array $options): int
{
    $sftp = connectWithServer($options, $error);
    if ($sftp === null) {
        logger()->error("Aborting after connect failure.");
        return $error["connect_failed"] ? EXITCODE_ERROR : EXITCODE_ERROR_NO_PERMISSION;
    }

    $username = $options["user"];

    $remote_home = $sftp->pwd();
    $remote_files = [];
    $remote_path = ".";

    $chmod = isset($options["chmod"]) ? ($options["chmod"] ?: DEFAULT_CERT_MODE) : false;
    $chmod_key = isset($options["chmod-key"]) ? ($options["chmod-key"] ?: DEFAULT_KEY_MODE) : false;
    $chgrp = $options["chgrp"] ?: false;

    // Collecting files to upload (sorted by target to reduce remote directory changes)
    $files_to_upload = getFilesToUpload($options);
    usort($files_to_upload, function (&$a, &$b) {
        return $a["target"] <=> $b["target"];
    });

    // Uploading the files
    foreach ($files_to_upload as $file) {
        // Checking if source is valid.
        if (!is_file($file["source"]) && is_readable($file["source"])) {
            logger()->error("Skipping {$file["source"]}, it is not a file or not readable.");
            continue;
        }

        // Changing remote directory if required.
        if (($dir = dirname($file["target"])) !== $remote_path) {
            $target_dir = $sftp->resolve($dir, $remote_home);

            $error = $sftp->cd($target_dir)->lastError();
            if ($error["file_not_found"]) {
                logger()->info("Creating remote directory: $target_dir");
                $sftp->clearError()
                    ->mkdir($target_dir)
                    ->cd($target_dir);
            }

            if ($error = $sftp->lastError()) {
                logger()->error("Failed to cd into '$dir'. Cause: " . json_encode($error, JSON_UNESCAPED_SLASHES));
                return EXITCODE_ERROR;
            } else {
                $remote_path = $dir;
                $remote_files = $sftp->ls();
                if ($error = $sftp->lastError()) {
                    logger()->error("Failed listing remote files. Cause: " . json_encode($error, JSON_UNESCAPED_SLASHES));
                    return EXITCODE_ERROR;
                }
            }
        }

        // Preparing copy
        $remote_filename = basename($file["target"]);
        $remote_file = $remote_files[$remote_filename] ?: ["type" => "-", "owner" => $username];
        $remote_is_file = $remote_file["type"] === "-";
        $remote_is_readonly = preg_match('/^-r-.r-.+$/', $remote_file["permissions"] ?: "");

        // Check if a folder/socket/symlink, etc is in the way
        if (!$remote_is_file) {
            logger()->error("Skipping file '{$file["source"]}' as there is a non-file in the way at '{$file["target"]}'.");
            continue;
        }

        $file_chmod = $file["is_key"] ? $chmod_key : $chmod;
        $file_chmod = preg_match('/^0[0-9]{3}$/', $file_chmod) ? (string)$file_chmod : false;
        $permission_change_retry_allowed = $file_chmod
            && $remote_file["owner"] === $username
            && isset($remote_files[$remote_filename]);

        // Initial upload when permissions are properly set.
        if (!$remote_is_readonly) {
            if ($error = $sftp->put($file["source"], $remote_filename)->lastError()) {
                logger()->error("Failed uploading file '{$file["source"]}' to '{$file["target"]}'. Cause: " . json_encode($error, JSON_UNESCAPED_SLASHES));

                if ($error["permission_denied"] !== true)
                    $permission_change_retry_allowed = false;

                if ($permission_change_retry_allowed)
                    logger()->info("Retrying file '{$file["source"]}' to '{$file["target"]}' with adjusted permissions.");
            } else {
                $permission_change_retry_allowed = false;
            }
        }

        // Second upload when initial failed or was skipped due to write protection (only possible if we have chmod defined to reset permissions later).
        if ($permission_change_retry_allowed) {
            $sftp->chmod($remote_filename, '0600');
            if ($error = $sftp->put($file["source"], $remote_filename)->lastError()) {
                logger()->error("Failed uploading file '{$file["source"]}' to '{$file["target"]}'. Cause: " . json_encode($error, JSON_UNESCAPED_SLASHES));
                return EXITCODE_ERROR_NO_PERMISSION;
            }
        } else if ($remote_is_readonly) {
            logger()->error("Failed uploading file '{$file["source"]}' to '{$file["target"]}'. Existing file is write protected and --chmod[-key] options are missing.");
            return EXITCODE_ERROR_NO_PERMISSION;
        }

        // Applying Chmod / chgrp if requested.
        if ($file_chmod) {
            if ($error = $sftp->chmod($remote_filename, $file_chmod)->lastError())
                logger()->error("Failed chmod ($file_chmod) for '{$file["target"]}'. Cause: " . json_encode($error, JSON_UNESCAPED_SLASHES));
        }

        if (!empty($chgrp)) {
            if ($error = $sftp->chgrp($remote_filename, $chgrp)->lastError())
                logger()->error("Failed chgrp ($chgrp) for '{$file["target"]}'. Cause: " . json_encode($error, JSON_UNESCAPED_SLASHES));
        }

        $sftp->clearError();
    }

    return EXITCODE_SUCCESS;
}

function connectWithServer(array $options, &$error): ?SftpClient
{
    $identity_type = trim(($options["identity-type"] ?: DEFAULT_IDENTITY_TYPE));
    $host = trim(($options["host"] ?: ""));
    $host_key = ($options["host-key"] ?: "");
    $port = $options["port"] ?: 22;
    $username = $options["user"];

    $sftp = new SftpClient(configPath(), $identity_type);

    if (!$sftp->connect($host, $username, $host_key, $port)) {
        $error = $sftp->lastError();
        $error["connect_failed"] = true;
        return null;
    }

    // Apply start path (if one was specified, defaults to home dir)
    if (($remote_path = $options["remote-path"])) {
        if ($err = $sftp->cd($remote_path)->lastError()) {
            $error = $err;
            $error["change_home_dir_failed"] = true;
            logger()->error("Failed cd into '{$remote_path}'. Cause: " . json_encode($err, JSON_UNESCAPED_SLASHES));
            return null;
        }
    }

    return $sftp;
}

function help()
{
    echo ABOUT . PHP_EOL
        . "Usage: " . basename($GLOBALS["argv"][0]) . " [options] [--command=]COMMAND" . PHP_EOL
        . PHP_EOL . STATIC_OPTIONS . PHP_EOL;

    foreach (COMMANDS as $name => $cmd) {
        echo PHP_EOL . "COMMAND \"$name\" {$cmd["description"]}" . PHP_EOL . "Options:" . PHP_EOL;
        foreach ($cmd["options"] as $option) {
            $option = preg_replace(['/^([^:]+)$/', '/(.+)::$/', '/(.+):$/'], ['[$1]', '[$1=value]', '$1=value'], "--$option");
            echo "         $option" . PHP_EOL;
        }
    }

    echo PHP_EOL . "Examples:" . PHP_EOL
        . str_replace('/\r\n|\n|\r/g', PHP_EOL, EXAMPLES)
        . PHP_EOL . PHP_EOL;
}

function getCommand()
{
    $default = null;
    $command = null;
    $parsed_args = getopt("", ["command::"]);
    foreach (COMMANDS as $name => $cmd) {
        if (in_array($name, $GLOBALS["argv"]) || $parsed_args["command"] === $name)
            $command = $cmd;
        if ($cmd["default"] === true)
            $default = $cmd;
    }

    return $command ?: $default;
}

function getActionById($automation_id)
{
    $config = OPNsense\Core\Config::getInstance()->object();
    $client = $config->OPNsense->AcmeClient;

    foreach ($client->actions->children() as $action) {
        if ($automation_id === (string)$action->attributes()["uuid"]
            || $automation_id === (string)$action->id)
            return $action;
    }

    return null;
}

function getOptionsById($automation_id, $silent = false)
{
    if (!$silent) logger()->info("Reading options from automation: $automation_id");

    if (is_object($action = getActionById($automation_id))) {
        if ($action->enabled && "upload_sftp" === (string)$action->type) {
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
            ];
        } else if (!$silent) {
            logger()->error("Ignoring disabled or invalid automation '$automation_id'");
        }
    } else {
        logger()->error("No upload automation found with uuid = '$automation_id'");
    }

    return false;
}

function getFilesToUpload(array $options)
{
    $files = [];
    $cert_ids = preg_split('/[,;\s]+/', $options["certificates"] ?: "", 0, PREG_SPLIT_NO_EMPTY);

    if (class_exists("OPNsense\\Core\\Config")) {
        foreach (findCertificates($cert_ids) as $cert) {
            if (isset($cert["content"])) {
                foreach ($cert["content"] as $name => $content) {
                    $source = temporaryFile();
                    $ok = file_put_contents($source, $content);

                    if (!$ok) {
                        logger()->error("Ignoring upload for cert '{$cert["name"]}', since the content cannot be prepared or is empty.");
                        continue;
                    }

                    if (($time = intval($cert["updated"])) && $time > 0)
                        touch($source, $time);

                    // Sanitize user input (allow unicode chars, numbers and some special characters)
                    $context_path = preg_replace('/[^\w\d_\-@.]+/uim', "-", $cert["name"]);

                    $file = ["source" => $source, "target" => "{$context_path}/{$name}.pem"];
                    $file["is_key"] = ($name === "key");
                    $files[] = $file;
                }
            } else {
                logger()->error("Ignoring upload for cert '{$cert["name"]}', since it is not available in trust storage.");
            }
        }
    } else {
        // Allow to specify "--files" only if we're not running in opnsense environment (for development only).
        if (isset($options["files"])) {
            foreach (preg_split('/[,;\s]+/', $options["files"] ?: "", 0, PREG_SPLIT_NO_EMPTY) as $file) {
                $files[] = ["source" => $file, "target" => "upload-test/" . basename($file)];
            };
        }
    }

    if (empty($files))
        logger()->error("Didn't find any certificates to upload (cert-ids: " . (empty($cert_ids) ? "all" : join(", ", $cert_ids)) . ").");

    return $files;
}

function findCertificates(array $certificate_ids_or_names, $load_content = true): array
{
    if (!class_exists("OPNsense\\Core\\Config")) return [];

    $config = OPNsense\Core\Config::getInstance()->object();
    $client = $config->OPNsense->AcmeClient;

    $result = [];
    $refids = [];

    foreach ($client->certificates->children() as $cert) {
        $item = [];
        $id = (string)$cert->id;
        $name = (string)$cert->name;

        if (empty($certificate_ids_or_names)
            || in_array($id, $certificate_ids_or_names)
            || in_array($name, $certificate_ids_or_names)) {

            if ($cert->enabled == 0) {
                if (!empty($certificate_ids_or_names))
                    logger()->error("Certificate '{$name}' (id: $id) is disabled, skipping it.");

                continue;
            }

            $item["name"] = $name;
            $item["updated"] = intval($cert->lastUpdate);
            $item["automations"] = preg_split('/[\s*,]+/', $cert->restartActions);
            if (isset($cert->certRefId)) {
                $refids[] = $item['content_id'] = (string)$cert->certRefId;
            }

            $result[$id] = $item;
        }
    }

    if ($load_content && ($certificates = exportCertificates($refids))) {
        foreach ($result as &$cert_info) {
            $id = $cert_info["content_id"];
            if (isset($certificates[$id]))
                $cert_info["content"] = $certificates[$id];
        }
    }

    return $result;
}

function exportCertificates(array $cert_refids)
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
            }
            $result[$refid] = $item;
        }
    }

    return $result;
}

function configPath(): string
{
    static $paths = [
        '/var/etc/acme-client',
        __DIR__
    ];
    foreach ($paths as $path) {
        if (is_dir($path)) return $path . DIRECTORY_SEPARATOR . 'sftp-config';
    }
    die("Failed detecting config path");
}

function main()
{
    global $argv;
    $command = getCommand();
    $options = ["help", "log", "no-error"];

    $has_automation_id = preg_match('/--automation-id=\S+/', join(" ", $argv));
    if ($has_automation_id) {
        $options = array_merge($options, ["automation-id:", "certificates::"]);
    } else {
        $options = array_merge($options, $command["options"]);
    }

    $index = 0;
    if ($options = getopt("h", $options, $index)) {
        if (isset($options["h"]) || isset($options["help"])) {
            help();
        } else {
            if (isset($options["log"]))
                logger(true)->info("Logging to stdout enabled");

            register_shutdown_function(function () {
                temporaryFile(true);
            });

            $options = array_filter($options, function ($value) {
                return !is_string($value)
                    || (!empty($value = trim($value)) && $value !== "__default_value");
            });

            if (isset($options["automation-id"]))
                $options = array_merge($options, getOptionsById($options["automation-id"]));

            if (is_callable($runner = $command["implementation"])) {
                $code = $runner($options);
                if ($code != EXITCODE_SUCCESS) {
                    logger()->error("Command execution failed, exit code $code. Last input was: " . json_encode($options, JSON_UNESCAPED_SLASHES));
                }
                exit(isset($options["no-error"]) ? EXITCODE_SUCCESS : $code);
            } else {
                exit(EXITCODE_ERROR_UNKNOWN_COMMAND);
            }
        }
    } else {
        if (count($argv) < 2) {
            help();
        } else {
            $cmd = join(" ", $argv);
            logger()->error("Parsing of '$cmd' failed at argument '{$argv[$index]}'");
        }
        exit(1);
    }
}


// --------------------------------------------------------------------------------------------------------------------
// Utility functions

interface ILogger
{
    function info($message);

    function error($message);
}

function &logger($reconfigure_to_stdout = false): ILogger
{
    static $logger;
    if (!$logger || $reconfigure_to_stdout) {
        if (!$reconfigure_to_stdout && function_exists("log_error")) {
            $logger = new class implements ILogger
            {
                function info($message) { syslog(SYSLOG_INFO_LEVEL, basename(__FILE__) . ": INFO: $message"); }

                function error($message) { log_error($message); }
            };
        } else {
            $logger = new class implements ILogger
            {
                function info($message) { echo "INFO: {$message}" . PHP_EOL; }

                function error($message) { echo "ERROR: {$message}" . PHP_EOL; }
            };
        }
    }
    return $logger;
}

function requireThat($expression, $message)
{
    if (!$expression) {
        logger()->error("FATAL: $message");
        exit(EXITCODE_ERROR);
    }
}

function resolvePath($file, $base = ".")
{
    if (!$base || $base[0] != DIRECTORY_SEPARATOR)
        $base = realpath(($base ?: "."));

    $path = [];
    $combined_path = ((!empty($file) && $file[0] == DIRECTORY_SEPARATOR) ? $file : $base . DIRECTORY_SEPARATOR . $file);
    foreach (explode(DIRECTORY_SEPARATOR, $combined_path) as $part) {
        if (empty($part) || $part === '.')
            continue;
        if ($part !== '..')
            array_push($path, $part);
        else if (!empty($path))
            array_pop($path);
        else
            return false;
    }

    return DIRECTORY_SEPARATOR . join(DIRECTORY_SEPARATOR, $path);
}

function temporaryFile($delete_all = false)
{
    static $__temporary_files = [];
    if ($delete_all) {
        foreach ($__temporary_files as $file)
            unlink($file);
        $__temporary_files = [];
    } else {
        if ($file = tempnam(sys_get_temp_dir(), "sftp-upload-")) {
            $file = realpath($file);
            $__temporary_files[] = $file;
            requireThat(chmod($file, 0600), "failed setting user-only permissions on '$file'.");
            return $file;
        };
    }
    return false;
}


// --------------------------------------------------------------------------------------------------------------------
// Classes


/**
 * Wrapper around the 'sftp' commandline client.
 */
class SftpClient
{
    public $remote_address;
    private $identity_type;

    /* @var false|array */
    private $failed_status;
    /* @var SSHKeys */
    private $ssh_keys;
    /* @var null|Process */
    private $process = null;

    public function __construct($config_path, $identity_type = DEFAULT_IDENTITY_TYPE)
    {
        $this->ssh_keys = new SSHKeys($config_path);
        $this->identity_type = $identity_type;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function connect($host, $username, $host_key = "", $port = 22)
    {
        if (empty(trim($host)) || empty(trim($username))) {
            $this->failed_status = ["invalid_parameters" => true];
            logger()->error("Failed connecting to '$host'. Hostname or username is missing.");
            return false;
        }

        $trust = $this->ssh_keys->trustHost($host, $host_key, $port);
        if ($trust["ok"] !== true) {
            logger()->error("Failed establishing trust in '$host'; Cause: {$trust["error"]}");
            unset($trust["ok"]);
            $this->failed_status = array_merge($trust, ["host_not_trusted" => true]);
            return false;
        } else {
            $host = $trust["host"];
        }

        // Building sftp command.
        $cmd = [
            "sftp",
            "-P", $port,
            "-oUser=$username",
            "-oUserKnownHostsFile={$this->ssh_keys->knownHostsFile()}",
        ];

        // Handle client side identity
        $identity = $this->ssh_keys->getIdentity($this->identity_type, true);
        if (is_file($identity) && is_readable($identity)) {
            array_push($cmd,
                "-i", $identity,
                "-oPreferredAuthentications=publickey");
        } else {
            logger()->error("Failed adding client identity ($identity). Connect will likely fail.");
        }

        // Adding the host
        array_push($cmd, "$host");

        // Creating the sftp process
        if ($this->process = Process::open($cmd)) {
            $this->processAvailableInput(120, 1);
            if ($error = $this->lastError()) {
                logger()->error("Failed connecting to '$host' (user: '$username'). Cause: " . json_encode($error));
                return false;
            }
            $this->remote_address = strpos($host, ':') ? "[$host]:$port" : "$host:$port";
            return true;
        }
        return false;
    }

    private function processAvailableInput(float $timeout = 0, $expected_lines = 0, Callable $lines_consumer = null)
    {
        requireThat($this->process !== null, "SFTP: process not connected");

        static $expected_errors = [
            ["host_not_resolved", /*   => */ '/.*not resolve.*/i'],
            ["host_not_trusted", /*    => */ '/.*IDENTIFICATION HAS CHANGED.*/i'],
            ["connection_refused", /*  => */ '/.*connection refused.*/i'],
            ["connection_closed", /*   => */ '/.*connection closed.*/i'],
            ["network_timeout", /*     => */ '/.*timed out.*/i'],
            ["network_unreachable", /* => */ '/.*network.+unreachable.*/i'],
            ["permission_denied", /*   => */ '/.*permission denied.*/i'],
            ["file_not_found", /*      => */ '/.*(no such|not found).*/i'],
            ["failure", /*             => */ '/.*(error|failure|you must supply).*/i'],
        ];

        while (($line = $this->process->get($timeout)) !== false) {
            if (--$expected_lines <= 0)
                $timeout = 0;

            foreach ($expected_errors as $ee) {
                if (preg_match($ee[1], $line)) {
                    $this->failed_status = [$ee[0] => true, "error" => trim($line)];
                    break;
                }
            }

            $hide = ($lines_consumer && $lines_consumer($line) === true);
            if (!$hide)
                logger()->info("SFTP: " . rtrim($line));
        }
    }

    public function close()
    {
        if (($p = $this->process) !== null) {
            $p->put("exit");
            $p->closeInput();

            $this->processAvailableInput(1.5);
            $p->close();

            $this->process = null;

            if ($this->failed_status && $this->failed_status["connection_closed"])
                $this->clearError();
        }
    }

    public function lastError($timeout = 0.5)
    {
        if ($this->failed_status === false)
            $this->processAvailableInput($timeout);
        return $this->failed_status;
    }

    public function clearError()
    {
        $this->failed_status = false;
        return $this;
    }

    public function ls()
    {
        $files = [];
        $this->processAvailableInput();
        $this->process->put("ls -la");

        $regex = '/^([bcdlsp\-][rwx\-]{9}[+@]?)\s+[0-9]+\s+([^\s]+)\s+([^\s]+)\s+([0-9]+)\s+(\w+\s+[0-9]+\s+[0-9:]+)\s+(.+)$/';
        $this->processAvailableInput(30, 2, function ($line) use (&$files, $regex) {
            if (preg_match($regex, $line, $matches)) {
                $files[trim($matches[6])] = [
                    "type" => $matches[1][0],
                    "permissions" => $matches[1],
                    "owner" => $matches[2],
                    "group" => $matches[3],
                    "size" => intval($matches[4]),
                    "mtime" => strtotime($matches[5])
                ];
                return true;
            }
            return false;
        });

        return $files;
    }

    public function pwd()
    {
        $remote_path = false;
        $this->processAvailableInput();
        $this->process->put("pwd");
        $this->processAvailableInput(30, 2, function ($line) use (&$remote_path) {
            if (preg_match('/^.+directory:\s(.+)$/i', $line, $matches))
                $remote_path = trim($matches[1]);
        });
        return $remote_path;
    }

    public function resolve($remote_path, $remote_pwd = null)
    {
        if (($pwd = ($remote_pwd ?: $this->pwd())) !== false) {
            $remote_path = resolvePath($remote_path, str_replace("/", DIRECTORY_SEPARATOR, $pwd));
            $remote_path = str_replace("\\", "/", $remote_path);
            return $remote_path;
        }
        return false;
    }

    public function get($remote_file, $local_file = "")
    {
        $this->processAvailableInput();
        $this->process->put("get " . escapeshellarg($remote_file) . " " . (empty($local_file) ? "" : escapeshellarg($local_file)));
        $this->processAvailableInput(30, 2);
        return $this;
    }

    public function put($local_file, $remote_file = "")
    {
        if (is_file($local_file)) {
            $this->processAvailableInput();
            $this->process->put("put -p " . escapeshellarg($local_file) . " " . (empty($remote_file) ? "" : escapeshellarg($remote_file)));
            $this->processAvailableInput(30, 2);
        } else {
            logger()->info("put: File $local_file doesn't exist.");
            $this->failed_status = ["file_not_found" => true, "error" => $local_file];
        }

        return $this;
    }

    public function mkdir($remote_path)
    {
        if (($remote_path = $this->resolve($remote_path)) !== false) {
            $this->process->put("mkdir " . escapeshellarg($remote_path));
            $this->processAvailableInput(30, 1);
        }
        return $this;
    }

    public function cd($remote_path)
    {
        if (($remote_path = $this->resolve($remote_path)) !== false) {
            $this->clearError();
            $this->process->put("cd " . escapeshellarg($remote_path));

            $this->processAvailableInput(30, 1);
            $error = $this->lastError();
            $pwd = false;

            if ($error || $remote_path !== ($pwd = $this->pwd())) {
                $this->failed_status = array_merge(($error ?: []), [
                    "failure" => true,
                    "error" => "Failed changing path to '$remote_path' (pwd: '$pwd'); Cause: {$error["error"]}"
                ]);
            }
        }
        return $this;
    }

    public function chmod($remote_file, $mode)
    {
        $this->processAvailableInput();
        $this->process->put("chmod " . escapeshellarg($mode) . " " . escapeshellarg($remote_file));
        $this->processAvailableInput(30, 2);
        return $this;
    }

    public function chgrp($remote_file, $group_id)
    {
        $this->processAvailableInput();
        $this->process->put("chgrp " . escapeshellarg($group_id) . " " . escapeshellarg($remote_file));
        $this->processAvailableInput(30, 2);
        return $this;
    }

    public function rm($remote_file)
    {
        $this->processAvailableInput();
        $this->process->put("rm " . escapeshellarg($remote_file));
        $this->processAvailableInput(30, 2);
        return $this;
    }
}

/**
 * Utility class for managing SSH host (known_hosts) and identity keys to be used in {@see SftpClient}.
 */
class SSHKeys
{
    private $config_path;
    private $known_hosts_file;

    public function __construct($config_path)
    {
        if (!is_dir($config_path)) {
            $dir_created = mkdir($config_path, CONFIG_PATH_CREATE_MODE, true);
            requireThat($dir_created, "Failed creating directory '$config_path' with permission " . CONFIG_PATH_CREATE_MODE);
        }

        $this->config_path = realpath($config_path);
        $this->known_hosts_file = resolvePath("known_hosts", $this->config_path);
    }

    public function knownHostsFile()
    {
        if (!is_file($this->known_hosts_file)) {
            $file_created =
                touch($this->known_hosts_file)
                && chmod($this->known_hosts_file, KNOWN_HOSTS_FILE_CREATE_MODE);

            requireThat($file_created, "Failed creating file '{$this->known_hosts_file}' with permission " . KNOWN_HOSTS_FILE_CREATE_MODE);
        }

        return $this->known_hosts_file;
    }

    public function trustHost($host, $host_key = "", $port = 22, $no_modification_allowed = false): array
    {
        requireThat(!empty(trim($host)), "Hostname must not be empty.");

        // Convert the specified host_key to a data structure that can be compared
        if (empty($host_key = trim($host_key))) {
            $host_key = false;
        } else {
            $host_key = self::getHostKeyInfo($host_key);
            if ($host_key === false)
                return ["ok" => false, "error" => "Invalid host_key specified."];
        }


        // Check our current known_host file
        $addKeyInfo = function (array &$key_list) {
            foreach ($key_list as &$item) {
                $item["key_info"] = self::getHostKeyInfo($item["host_key"]);
            }
            return array_filter($key_list, function (&$item) {
                return $item["key_info"] !== false;
            });
        };

        $known_keys = $addKeyInfo($this->getKnownHostKey($host));

        // Find known_hosts item with same hostname
        $known_by_host = array_reduce($known_keys, function ($found, $key) use ($host) {
            return (!$found && !empty(trim($key["host"])) && strcasecmp(trim($host), trim($key["host"])) == 0)
                ? $key
                : $found;
        }, false);

        // Find known_hosts item with same public host-key
        $known_by_key = array_reduce($known_keys, function ($found, $key) use ($host_key) {
            return (!$found && $host_key && $host_key === $key["key_info"])
                ? $key
                : $found;
        }, false);


        // Updating $host and $host_key from known_hosts and check if we need to update known_hosts.
        if ($host_key === false && $known_by_host) {
            logger()->info("No host key specified, using existing known_hosts entry for '$host'");
            $host_key = $known_by_host["key_info"];
        }

        $is_key_known = false;
        if ($known_by_host && $host_key && $host_key === $known_by_host["key_info"]) {
            $is_key_known = true;
        } else if ($known_by_key) {
            if (strcasecmp(trim($host), trim($known_by_key["host"])) != 0) {
                logger()->info("Host key is in known_hosts but hostname differs. Changing '$host' to '{$known_by_key["host"]}'.");
                $host = $known_by_key["host"];
            }
            $is_key_known = true;
        }


        // Check if we don't have a matching known_hosts entry and add or update it as required.
        if (!$is_key_known && !$no_modification_allowed) {
            $key_type = $host_key ? $host_key["key_type"] : DEFAULT_KEY_TYPE;

            $remote_host_keys = $addKeyInfo($this->queryHostKey($host, $key_type, $port));
            $matching_remote_host_keys = array_filter($remote_host_keys, function ($key) use ($host_key) {
                return $key["key_info"] !== false && (!$host_key || $host_key === $key["key_info"]);
            });

            if (!empty($matching_remote_host_keys)) {
                if ($known_by_host) {
                    logger()->info("Removing known_hosts entry with differing key for '{$known_by_host["host"]}' as it is in the way.");
                    $this->removeKnownHost($known_by_host["host"]);
                }

                foreach ($matching_remote_host_keys as $key) {
                    logger()->info("Adding known_hosts entry: " . json_encode($key["key_info"], JSON_UNESCAPED_SLASHES));
                    $ok = file_put_contents($this->knownHostsFile(), $key["host_key"] . PHP_EOL, FILE_APPEND);
                    if (!$ok)
                        logger()->error("Failed adding known_hosts entry {$key["host_key"]}");
                }

                // Verify that known_hosts contains the correct keys after adding them (using recursion).
                return $this->trustHost($host, $matching_remote_host_keys[0]["host_key"], $port, true);

            } else {
                if (empty($remote_host_keys)) {
                    $msg = "No connection to '$host'; Failed querying host key from server.";
                } else {
                    $msg = "Key mismatch for '$host'; "
                        . "The expected key (" . json_encode($host_key) . ") was not found in (" . json_encode($remote_host_keys) . ")";
                }
                return ["ok" => false, "error" => $msg];
            }
        }


        if ($is_key_known) {
            return ["ok" => true, "host" => $host, "key_info" => $host_key];
        } else {
            return ["ok" => false, "error" => "Host unknown and remote key cannot be queried."];
        }
    }

    public static function getHostSearchList($host)
    {
        $search_list = [($host = strtolower($host))];

        // Add IP-address to search list (IPv4 only)
        $has_ip = ($ip = gethostbyname($host))
            && ($ip !== $host || preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $ip));

        if ($has_ip)
            $search_list[] = strtolower($ip);

        // Add FQDN to search list if reverse lookup provides a valid one.
        $has_fqdn = $has_ip
            && ($reverse_fqdn = gethostbyaddr($ip))
            && $reverse_fqdn !== $ip
            && gethostbyname($reverse_fqdn) === $ip;

        if ($has_fqdn && isset($reverse_fqdn))
            $search_list[] = strtolower($reverse_fqdn);

        // Build unique search list (dedup list)
        $search_list = array_filter($search_list, function ($value, $index) use (&$search_list) {
            return !empty(trim($value)) && array_search($value, $search_list) == $index;
        }, ARRAY_FILTER_USE_BOTH);

        return $search_list;
    }

    public static function queryHostKey($host, $key_type = DEFAULT_KEY_TYPE, $port = 22)
    {
        $keys = [];
        $failed = false;
        $names = join(",", self::getHostSearchList($host));

        if (!empty($names) && ($p = Process::open(["ssh-keyscan", "-p", $port, "-t", $key_type, $names]))) {
            $lines = [];
            while (($line = $p->get(60)) !== false) {
                $line = trim($line);
                if (empty($line) || $line[0] == "#")
                    continue;

                if (preg_match('/.*(connect|write|broken|no route|not known).*/i', $line))
                    $failed = true;

                $lines[] = $line;
            }

            if ($p->close() == 0 && !$failed) {
                foreach ($lines as $line) {
                    $keys[] = ["host_key" => $line];
                }
            } else {
                logger()->error("Failed querying public keys for [$names] ($host / $key_type). "
                    . "Exit code: {$p->exitCode} (failed flag: $failed) ; " . PHP_EOL
                    . "ssh-keyscan: " . join(PHP_EOL . "ssh-keyscan: ", $lines));
            }
        }

        if (empty($keys))
            logger()->info("Couldn't fetch public host key ($key_type) from $host");

        return $keys;
    }


    public function getKnownHostKey($host)
    {
        $keys = [];
        foreach (self::getHostSearchList($host) as $name_or_ip) {
            if ($p = Process::open(["ssh-keygen", "-F", $name_or_ip, "-f", $this->knownHostsFile()])) {
                $lines = [];
                while (($line = $p->get()) !== false) {
                    $line = trim($line);
                    if (empty($line) || $line[0] == "#")
                        continue;

                    $lines[] = $line;
                }

                if ($p->close() == 0) {
                    $keys[] = ["host" => $name_or_ip, "host_key" => $lines[0]];
                } else if ($p->exitCode != 1 /* 1 == NOT_FOUND */) {
                    logger()->error("Failed querying known hosts for $name_or_ip ($host). Return code was: {$p->exitCode}"
                        . " ; " . PHP_EOL . join(PHP_EOL, $lines));
                }
            }
        }

        if (empty($keys))
            logger()->info("Didn't find $host in known_hosts");

        return $keys;
    }

    public function removeKnownHost($host)
    {
        $ok = false;
        if ($p = Process::open(["ssh-keygen", "-R", $host, "-f", $this->knownHostsFile()])) {
            $ok = $p->close() === 0;
            if (!$ok)
                logger()->error("Failed removing known hosts for $host. Return code was: {$p->exitCode}");
        }
        return $ok;
    }

    public static function getHostKeyInfo($host_key)
    {
        if ($p = Process::open(["ssh-keygen", "-l", "-f", "-"])) {
            $p->put($host_key);
            $p->closeInput();

            if (($hash = $p->get()) && preg_match('/^([0-9]+) (.+?) .+? \(([^()]+)\)$/', $hash, $matches)) {
                return ["hash" => $matches[2], "key_type" => $matches[3], "key_length" => $matches[1]];
            } else {
                logger()->error("Unsupported hash type: $hash");
            }
        }

        logger()->error("Failed getting hash for host_key");
        return false;
    }

    // Returns the path to the public identity key file, generating it if missing (optionally returns the private key path)
    public function getIdentity($identity_type = DEFAULT_IDENTITY_TYPE, $private = false)
    {
        requireThat(in_array($identity_type, IDENTITY_TYPES), "Identity type $identity_type unknown.");

        list($key_type, $key_size) = explode('_', $identity_type, 2);
        if (!$key_size && DEFAULT_IDENTITY_KEY_BITS[$key_type] > 0)
            $key_size = DEFAULT_IDENTITY_KEY_BITS[$key_type];

        $identity_path = "{$this->config_path}/id.{$identity_type}";

        if (!file_exists($identity_path)) {
            $generate_key = [
                "ssh-keygen", "-v",
                "-f", $identity_path,
                "-t", $key_type,
                "-N", "",
            ];

            if (intval($key_size) > 0)
                array_push($generate_key, "-b", $key_size);

            if ($p = Process::open($generate_key)) {
                while (($line = $p->get(10)) !== false) {
                    logger()->info("SSH keygen: $line");
                }

                requireThat($p->close() == 0,
                    "Failed generating identity $identity_path: Error code: {$p->exitCode}" . PHP_EOL
                    . "Command: " . join(" ", $generate_key));
            }
        }

        return $private ? $identity_path : "{$identity_path}.pub";
    }

    public static function getIdentityRestrictions($host = "", $source_ip = "")
    {
        $restrictions = ['restrict', 'command="internal-sftp"'];

        $restrict_ip = empty(trim($source_ip))
            ? (empty(trim($host)) ? false : self::getOutgoingIpFor($host))
            : $source_ip;

        if ($restrict_ip)
            $restrictions[] = 'from="' . $restrict_ip . '"';

        return join(",", $restrictions);
    }

    public static function getOutgoingIpFor($host)
    {
        $ip = gethostbyname($host);
        $interface = null;

        if ($p = Process::open(["route", "-n", "get", $ip])) {
            while (($line = $p->get(10)) !== false)
                if (preg_match('/\s*interface:\s*([^\s]+).*$/', $line, $matches)) {
                    $interface = $matches[1];
                }
        }

        if ($interface && $p = Process::open(["ifconfig", $interface, "inet"])) {
            while (($line = $p->get(10)) !== false)
                if (preg_match('/\s*inet\s+([^\s]+)\s+netmask.*/', $line, $matches)) {
                    return $matches[1];
                }
        }

        return false;
    }
}

/**
 * Utility class to execute shell processes and handle their IO.
 */
class Process
{
    private $handle;
    private $inputs;
    private $outputs;

    public $exitCode = null;

    // Starts the specified process and returns an object to manage it.
    public static function open(array $cmd, $cwd = null, $env = null): Process
    {
        global $__process_terminate_hook, $__open_processes;
        if (!is_array($__open_processes))
            $__open_processes = [];

        // Ensure we never leave zombies around: Hooking into script shutdown and kill processes that are still running.
        if (!$__process_terminate_hook) {
            register_shutdown_function($__process_terminate_hook = function () {
                global $__open_processes;
                foreach ($__open_processes as $handle) {
                    if (is_resource($handle)) {
                        logger()->error("Terminating process: " . json_encode(proc_get_status($handle)));
                        @proc_terminate($handle);
                    }
                }
            });
        }

        $p = new Process($cmd, $cwd, $env);
        return $p->isRunning() ? $p : null;
    }

    public function __construct($cmd, $cwd = null, $env = null)
    {
        $cmd = join(" ", array_map(function ($v) {
            return escapeshellarg($v);
        }, $cmd));

        $spec = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
        $this->handle = proc_open($cmd, $spec, $pipes, $cwd, $env);

        if (is_resource($this->handle)) {
            $this->outputs = $pipes;
            $this->inputs = [array_shift($this->outputs)];

            foreach ($this->outputs as $stream)
                stream_set_blocking($stream, false);

            global $__open_processes;
            $__open_processes[] = $this->handle;
        } else {
            logger()->error("Failed opening '$cmd' in '$cwd'");
        }
    }

    public function __destruct()
    {
        $this->close();
        if ($this->isRunning())
            $this->close(true);
    }

    public function get($timeout = 5, $max_length = 8192, $ending = PHP_EOL)
    {
        $readables = array_filter($this->outputs, function ($stream) {
            return is_resource($stream) && !feof($stream);
        });

        $micros = intval(($timeout - floor($timeout)) * 1000000);
        $can_read = !empty($readables) && stream_select($readables, $w = [], $e = [], $timeout, $micros);
        $stream = array_reduce(($can_read ? $readables : []), function ($a, $b) {
            return is_resource($a) && !feof($a) ? $a : $b;
        }, null);

        return is_resource($stream)
            ? stream_get_line($stream, $max_length, $ending)
            : false;
    }

    public function put($data, $append = PHP_EOL)
    {
        if ($this->isRunning() && is_resource($stdin = $this->inputs[0]) && !feof($stdin)) {
            fwrite($stdin, $data);
            if ($append)
                fwrite($stdin, $append);
        }
    }

    public function closeInput()
    {
        if (!feof($stdin = $this->inputs[0])) fclose($stdin);
    }

    public function close($force = false)
    {
        global $__open_processes;

        // Read up-to 10k remaining lines from STDOUT/ERR to release locks before closing.
        for ($i = 0; ($line = $this->get(0)) && $i < 10000; $i++) {
            logger()->error("WARN: process: $line");
        };

        if ($this->isRunning())
            $this->exitCode = ($force ? proc_terminate($this->handle) : proc_close($this->handle));

        if (!$this->isRunning() && in_array($this->handle, $__open_processes))
            $__open_processes = array_diff($__open_processes, [$this->handle]);

        return $this->exitCode;
    }

    public function isRunning()
    {
        $status = is_resource($this->handle) ? proc_get_status($this->handle) : false;
        if (is_array($status)) {
            if (!$this->exitCode && $this->exitCode !== 0 && !$status["running"])
                $this->exitCode = $status["exitcode"];
            return $status["running"];
        }
        return false;
    }
}


// Calling main if we have been called via CLI
if (isset($GLOBALS["argc"])) main();
