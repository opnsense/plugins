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

namespace OPNsense\AcmeClient;

// Optional include to get "log_error"
@include_once("util.inc");

// Syslog level used for verbose info log.
// Change to "LOG_NOTICE" to make log output visible in the UI.
const SYSLOG_INFO_LEVEL = LOG_INFO;

/**
 * Interface for logging.
 * @package OPNsense\AcmeClient
 */
interface ILogger
{
    function info($message);

    function error($message, $error = null);
}

/**
 * Shared utilities.
 * @package OPNsense\AcmeClient
 */
class Utils
{
    public static function &log($reconfigure_to_stdout = false): ILogger
    {
        static $logger;

        if (!$logger || $reconfigure_to_stdout) {
            if (!$reconfigure_to_stdout && function_exists("log_error")) {
                $logger = new class implements ILogger
                {
                    function info($message)
                    {
                        syslog(SYSLOG_INFO_LEVEL, basename(__FILE__) . ": INFO: $message");
                    }

                    function error($message, $error = null)
                    {
                        log_error(
                            $error
                                ? ("$message ; Cause: " . json_encode($error, JSON_UNESCAPED_SLASHES))
                                : $message
                        );
                    }
                };
            } else {
                $logger = new class implements ILogger
                {
                    function info($message)
                    {
                        echo "INFO: {$message}" . PHP_EOL;
                    }

                    function error($message, $error = null)
                    {
                        echo "ERROR: "
                            . ($error
                                ? ("$message ; Cause: " . json_encode($error, JSON_UNESCAPED_SLASHES))
                                : $message)
                            . PHP_EOL;
                    }
                };
            }
        }

        return $logger;
    }

    public static function requireThat($expression, $message)
    {
        if (!$expression) {
            self::log()->error("FATAL: $message");
            throw new \AssertionError($message);
        }
        return $expression;
    }

    /**
     * Converts a relative to a normalized absolute path.
     * @param string $file The relative file path to resolve against base.
     * @param string $base The base path to use for resolving the file.
     * @return bool|string The resolved path or false when impossible.
     */
    public static function resolvePath(string $file, string $base = ".")
    {
        $combined_path = $file;

        if (empty($file) || $file[0] != DIRECTORY_SEPARATOR) {
            if (empty($base) || $base[0] != DIRECTORY_SEPARATOR) {
                $base = realpath(($base ?: "."));
            }

            $combined_path = $base . DIRECTORY_SEPARATOR . $file;
        }

        $path = [];

        foreach (explode(DIRECTORY_SEPARATOR, $combined_path) as $part) {
            if (empty($part) || $part === '.') {
                continue;
            }

            if ($part !== '..') {
                array_push($path, $part);
            } elseif (!empty($path)) {
                array_pop($path);
            } else {
                return false;
            }
        }

        return DIRECTORY_SEPARATOR . join(DIRECTORY_SEPARATOR, $path);
    }

    /**
     * @return string the path where acme.sh config is stored or null if not available.
     */
    public static function configPath(): string
    {
        static $paths = [
            '/var/etc/acme-client',
            __DIR__
        ];
        foreach ($paths as $path) {
            if (is_dir($path)) {
                return $path;
            }
        }
        return self::requireThat(false, "No config path");
    }

    /**
     * @param string $automation_id the automation numeric id or UUID.
     * @return mixed|null the automation action when found.
     */
    public static function getAutomationActionById($automation_id)
    {
        $config = \OPNsense\Core\Config::getInstance()->object();
        $client = $config->OPNsense->AcmeClient;

        foreach ($client->actions->children() as $action) {
            if (
                $automation_id === (string)$action->attributes()["uuid"]
                || $automation_id === (string)$action->id
            ) {
                return $action;
            }
        }

        return null;
    }

    /**
     * Print CLI help.
     * @see Utils::runCLIMain
     * @param string $about about text
     * @param string $examples examples text
     * @param array $commands the commands
     * @return void
     */
    public static function printCLIHelp($about, $examples, $commands = [])
    {
        static $options = [
            "-h, --help          Print commandline help",
            "--log               Enable log to stdout (instead of syslog)",
            "--automation-id     Read options from the action specified by id or uuid",
            "--no-error          Always exit with 0 (original exit codes are still logged)",
        ];

        echo $about . PHP_EOL
            . "Usage: " . basename($GLOBALS["argv"][0]) . " [options] [--command=]COMMAND" . PHP_EOL
            . PHP_EOL . join(PHP_EOL, $options) . PHP_EOL;

        foreach ($commands as $name => $cmd) {
            echo PHP_EOL . "COMMAND \"$name\" {$cmd["description"]}" . PHP_EOL . "Options:" . PHP_EOL;
            foreach ($cmd["options"] as $option) {
                $option = preg_replace(['/^([^:]+)$/', '/(.+)::$/', '/(.+):$/'], ['[$1]', '[$1=value]', '$1=value'], "--$option");
                echo "         $option" . PHP_EOL;
            }
        }

        echo PHP_EOL . "Examples:" . PHP_EOL
            . preg_replace('/\r\n|\n|\r/', PHP_EOL, $examples)
            . PHP_EOL . PHP_EOL;
    }

    /**
     * Helper that implements `main();` for a CLI application following the command design.
     *
     * `$commands` follows the format:
     * ```php
     * [
     *   "command-name" => [
     *     "description" => "...",
     *     "options" => ["arg1::", "arg2::", "arg3::"],
     *     "implementation" => "commandImplementationFunction",
     *     "default" => true | false,
     *   ],
     * ]
     * ```
     *
     * @param callable $help method that display's CLI help.
     * @param callable $optionsByActionId method that returns CLI args (assoc array) from an automation action id.
     * @param array $commands the list of commands that the CLI application can execute.
     * @param int $exit_success  exit code for success
     * @param int $exit_unknown_command exit code for no matching command
     * @return void
     */
    public static function runCLIMain(callable $help, callable $optionsByActionId, $commands = [], $exit_success = 0, $exit_unknown_command = 255)
    {
        global $argv;
        $command = self::getSelectedCLICommand($commands);
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
                $help();
            } else {
                if (isset($options["log"])) {
                    self::log(true)->info("Logging to stdout enabled");
                }

                $options = array_filter($options, function ($value) {
                    return !is_string($value)
                        || (!empty($value = trim($value)) && $value !== "__default_value");
                });

                if (isset($options["automation-id"])) {
                    if (is_array($config = $optionsByActionId($options["automation-id"]))) {
                        $options = array_merge($config, $options);
                    } else {
                        self::log()->error("No usable config found for automation-id {$options["automation-id"]}");
                        exit(1);
                    }
                }

                if (is_callable($runner = $command["implementation"])) {
                    $code = $runner($options);

                    if ($code != $exit_success) {
                        self::log()->error("Command execution failed, exit code $code. Last input was: " . json_encode($options, JSON_UNESCAPED_SLASHES));
                    }

                    exit(isset($options["no-error"]) ? $exit_success : $code);
                } else {
                    exit($exit_unknown_command);
                }
            }
        } else {
            if (count($argv) < 2) {
                $help();
            } else {
                $cmd = join(" ", $argv);
                self::log()->error("Parsing of '$cmd' failed at argument '{$argv[$index]}'");
            }
            exit(1);
        }
    }

    private static function getSelectedCLICommand($commands = [])
    {
        $default = null;
        $command = null;
        $parsed_args = getopt("", ["command::"]);
        foreach ($commands as $name => $cmd) {
            if (in_array($name, $GLOBALS["argv"]) || ($parsed_args["command"] ?? "") === $name) {
                $command = $cmd;
            }
            if (($cmd["default"] ?? false) === true) {
                $default = $cmd;
            }
        }

        return $command ?? $default;
    }
}
