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

/**
 * Utility class for managing SSH host-keys (known_hosts) and identity keys.
 * @package OPNsense\AcmeClient
 */
class SSHKeys
{
    // Permissions
    public const CONFIG_PATH_CREATE_MODE = 0750;
    public const KNOWN_HOSTS_FILE_CREATE_MODE = 0640;

    // Keys & bits
    public const DEFAULT_KEY_TYPE = "ecdsa"; // recent default.
    public const ALTERNATE_DEFAULT_KEY_TYPE = "rsa"; // failsafe default.

    public const IDENTITY_TYPES = [
        "rsa",
        "rsa_2048",
        "rsa_4096",
        "rsa_8192",
        "ecdsa",
        "ecdsa_256",
        "ecdsa_384",
        "ecdsa_521",
        "ed25519",
    ];

    public const DEFAULT_IDENTITY_TYPE = "ecdsa";

    public const DEFAULT_IDENTITY_KEY_BITS = [
        "rsa" => 4096,
        "ecdsa" => 521,
    ];

    public const DEFAULT_PORT = 22;

    private $config_path;
    private $known_hosts_file;

    public function __construct($config_path)
    {
        if (!is_dir($config_path)) {
            $dir_created = mkdir($config_path, self::CONFIG_PATH_CREATE_MODE, true);

            Utils::requireThat(
                $dir_created,
                "Failed creating directory '$config_path' with permission " . self::CONFIG_PATH_CREATE_MODE
            );
        }

        $this->config_path = realpath($config_path);
        $this->known_hosts_file = Utils::resolvePath("known_hosts", $this->config_path);
    }

    public function knownHostsFile()
    {
        if (!is_file($this->known_hosts_file)) {
            $file_created =
                touch($this->known_hosts_file)
                && chmod($this->known_hosts_file, self::KNOWN_HOSTS_FILE_CREATE_MODE);

            Utils::requireThat(
                $file_created,
                "Failed creating file '{$this->known_hosts_file}' with permission " . self::KNOWN_HOSTS_FILE_CREATE_MODE
            );
        }

        return $this->known_hosts_file;
    }

    /**
     * Establishes a trust in the specified host with the specified host-key. Fails if host-key mismatches or host cannot be reached.
     * @param string $host The name or IP address of the host to trust.
     * @param string $host_key The expected host-key required to establish a trust. Is auto-accepted on first connect and stored to "known_hosts" if omitted.
     * @param int $port The port of the SSH server on the host to trust.
     * @param bool $no_modification_allowed Indicates whether "known_hosts" may be modified to trust the host or not.
     * @return array A status following the format ["ok"=>true/false, "error"=>"reason"].
     */
    public function trustHost(string $host, $host_key = "", $port = self::DEFAULT_PORT, $no_modification_allowed = false): array
    {
        Utils::requireThat(!empty(trim($host)), "Hostname must not be empty.");


        // Convert the specified host_key to a data structure that can be compared
        if (empty($host_key = trim($host_key))) {
            $host_key = false;
        } else {
            $host_key = self::getHostKeyInfo($host_key);
            if ($host_key === false) {
                return ["ok" => false, "error" => "Invalid host_key specified."];
            }
        }


        // Check our current known_host file
        $addKeyInfo = function (array $key_list) {
            foreach ($key_list as &$item) {
                $item["key_info"] = self::getHostKeyInfo($item["host_key"]);
            }
            return array_filter($key_list, function (&$item) {
                return $item["key_info"] !== false;
            });
        };

        $known_keys = $addKeyInfo($this->getKnownHostKey($host, $port));

        // Find known_hosts item with same hostname
        $known_by_host = array_reduce($known_keys, function ($found, $key) use ($host) {
            return (!$found && !empty(trim($key["host"])) && strcasecmp(trim($host), trim($key["host"])) == 0)
                ? $key
                : $found;
        }, false);

        $known_by_host_matches_port = $known_by_host && ($port == self::DEFAULT_PORT || $known_by_host["host"] !== $known_by_host["host_query"]);

        // Find known_hosts item with same public host-key
        $known_by_key = array_reduce($known_keys, function ($found, $key) use ($host_key) {
            return (!$found && $host_key && $host_key === $key["key_info"])
                ? $key
                : $found;
        }, false);


        // Updating $host and $host_key from known_hosts and check if we need to update known_hosts.
        if ($host_key === false && $known_by_host) {
            if ($known_by_host_matches_port) {
                Utils::log()->info("No host key specified, using existing known_hosts entry for '$host'");
                $host_key = $known_by_host["key_info"];
            } else {
                Utils::log()->info("No host key specified and existing entry for '$host' cannot be used as isn't matching port $port.");
            }
        }

        $is_key_known = false;
        if ($known_by_host && $host_key && $host_key === $known_by_host["key_info"]) {
            $is_key_known = true;
        } elseif ($known_by_key) {
            if (strcasecmp(trim($host), trim($known_by_key["host"])) != 0) {
                Utils::log()->info("Host key is in known_hosts but hostname differs. Changing '$host' to '{$known_by_key["host"]}'.");
                $host = $known_by_key["host"];
            }
            $is_key_known = true;
        }


        // Check if we don't have a matching known_hosts entry and add or update it as required.
        if (!$is_key_known && !$no_modification_allowed) {
            // Query the key.
            $key_type = $host_key ? $host_key["key_type"] : self::DEFAULT_KEY_TYPE;
            $remote_host_keys = $addKeyInfo($this->queryHostKey($host, $key_type, $port, $query_error));

            // Retry with ALTERNATE_DEFAULT_KEY_TYPE when DEFAULT_KEY_TYPE was applied in the first place.
            if (
                empty($remote_host_keys)
                && $query_error
                && ($query_error["connection_refused"] ?? false)
                && !$host_key
                && self::ALTERNATE_DEFAULT_KEY_TYPE != self::DEFAULT_KEY_TYPE
            ) {
                $key_type = self::ALTERNATE_DEFAULT_KEY_TYPE;
                $remote_host_keys = $addKeyInfo($this->queryHostKey($host, $key_type, $port, $query_error));
            }

            $matching_remote_host_keys = array_filter($remote_host_keys, function ($key) use ($host_key) {
                return $key["key_info"] !== false && (!$host_key || $host_key === $key["key_info"]);
            });

            if (!empty($matching_remote_host_keys)) {
                if ($known_by_host && $known_by_host_matches_port) {
                    Utils::log()->info("Removing known_hosts entry with differing key for '{$known_by_host["host_query"]}' as it is in the way.");
                    $this->removeKnownHost($known_by_host["host_query"]);
                }

                foreach ($matching_remote_host_keys as $key) {
                    Utils::log()->info("Adding known_hosts entry: " . json_encode($key["key_info"], JSON_UNESCAPED_SLASHES));
                    $ok = file_put_contents($this->knownHostsFile(), $key["host_key"] . PHP_EOL, FILE_APPEND);
                    if (!$ok) {
                        Utils::log()->error("Failed adding known_hosts entry {$key["host_key"]}");
                    }
                }

                // Verify that known_hosts contains the correct keys after adding them (using recursion).
                return $this->trustHost($host, $matching_remote_host_keys[0]["host_key"], $port, true);
            } else {
                if (empty($remote_host_keys)) {
                    $msg = "No connection to '$host'; Failed querying host key from server.";
                } else {
                    $remote_infos = array_map(function ($key) {
                        return $key["key_info"];
                    }, $remote_host_keys);
                    $msg = "Key mismatch for '$host'; "
                        . "The expected key (" . json_encode($host_key) . ") was not found in (" . json_encode($remote_infos) . ")";
                }

                return array_merge(["ok" => false, "error" => $msg], ($query_error ?: []));
            }
        }


        if ($is_key_known) {
            return ["ok" => true, "host" => $host, "key_info" => $host_key];
        } else {
            return ["ok" => false, "error" => "Host unknown and remote key cannot be queried."];
        }
    }

    /**
     * Returns a normalized list of names and IP addresses (IPv4) that point to the same host.
     * @param string $host The name or IP address of the host.
     * @param int $port Add port specific host names and addresses to the search-list when greater 0.
     * @return array A list of hostnames / ip addresses pointing to the same host.
     */
    public static function getHostSearchList(string $host, int $port = 0)
    {
        $host = strtolower($host);
        $search_list = [$host];

        // Add IP-address to search list (IPv4 only)
        $has_ip = ($ip = gethostbyname($host))
            && ($ip !== $host || preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $ip));

        if ($has_ip) {
            $search_list[] = strtolower($ip);
        }

        // Add FQDN to search list if reverse lookup provides a valid one.
        $has_fqdn = $has_ip
            && ($reverse_fqdn = gethostbyaddr($ip))
            && $reverse_fqdn !== $ip
            && gethostbyname($reverse_fqdn) === $ip;

        if ($has_fqdn && isset($reverse_fqdn)) {
            $search_list[] = strtolower($reverse_fqdn);
        }

        // Build unique search list (dedup list)
        $search_list = array_filter($search_list, function ($value, $index) use (&$search_list) {
            return !empty(trim($value)) && array_search($value, $search_list) == $index;
        }, ARRAY_FILTER_USE_BOTH);

        // Insert port specific items at the beginning when a port was specified.
        // Reason: Multiple SSH servers may be used on the same host but different ports.
        //         This ensures specific keys (with port) are selected first.
        if ($port > 0) {
            foreach (array_reverse($search_list) as $item) {
                array_unshift($search_list, "[{$item}]:{$port}");
            }
        }

        return $search_list;
    }

    /**
     * Queries the host-key from the SSH server running on the specified host.
     * @param string $host The name or IP address of the host.
     * @param string $key_type The type of host-key to query (one of "rsa", "ecdsa" or "ed25519").
     * @param int $port the port of the SSH server.
     * @param array $error receives error details.
     * @return array A list of host-keys returned by the query.
     */
    public static function queryHostKey(string $host, $key_type = self::DEFAULT_KEY_TYPE, $port = self::DEFAULT_PORT, ?array &$error = [])
    {
        // Error list matching output from "ssh-keyscan". Keep names in sync with similar list in SftpClient.
        static $expected_errors = [
            ["host_not_resolved", /*   -> */ '/.*not known.*/i'],
            ["network_unreachable", /* -> */ '/.*no route.*/i'],
            ["failure", /*             -> */ '/.*(connect|write|broken).*/i'],
        ];

        $keys = [];
        $failed = false;
        $names = join(",", self::getHostSearchList($host));

        if (!empty($names) && ($p = Process::open(["ssh-keyscan", "-p", $port, "-t", $key_type, $names]))) {
            $lines = [];
            while (($line = $p->get(60)) !== false) {
                $line = trim($line);
                if (empty($line) || $line[0] == "#") {
                    continue;
                }

                if (!$failed) {
                    foreach ($expected_errors as $err) {
                        if (preg_match($err[1], $line)) {
                            $error = [$err[0] => true];
                            $failed = true;
                            break;
                        }
                    }
                }

                $lines[] = $line;
            }

            if ($p->close() == 0 && !$failed) {
                foreach ($lines as $line) {
                    $keys[] = ["host_key" => $line];
                }
            } else {
                $marker = $failed ? "yes" : "no";
                $output = empty($lines)
                    ? ""
                    : PHP_EOL . "ssh-keyscan: " . join(PHP_EOL . "ssh-keyscan: ", $lines);

                Utils::log()->error("Failed querying host keys ($key_type) for [$names] port $port. Exit code: {$p->exitCode} (error-marker: $marker) $output");
            }
        }

        if (empty($keys)) {
            Utils::log()->info("Couldn't fetch public host key ($key_type) from {$host}:{$port}");

            if (!is_array($error) || empty($error)) {
                $error = ["connection_refused" => true];
            }
        }

        return $keys;
    }

    /**
     * Returns all host-keys from "known_hosts" that match the specified hostname.
     * @param string $host The name of the host to lookup.
     * @param int $port The port of the SSH server on the host (used to find port specific known host entries).
     * @return array A list of all matching host keys.
     */
    public function getKnownHostKey(string $host, int $port = self::DEFAULT_PORT)
    {
        $keys = [];

        foreach (self::getHostSearchList($host, $port) as $name_or_ip) {
            if ($p = Process::open(["ssh-keygen", "-F", $name_or_ip, "-f", $this->knownHostsFile()])) {
                $lines = [];
                while (($line = $p->get()) !== false) {
                    $line = trim($line);
                    if (empty($line) || $line[0] == "#") {
                        continue;
                    }

                    $lines[] = $line;
                }

                if ($p->close() == 0) {
                    // Removing port from name or ip before returning it.
                    $hostname = preg_match('/^\[([^\]]+?)\]:\d+/', $name_or_ip, $matches)
                        ? $matches[1]
                        : $name_or_ip;

                    $keys[] = [
                        "host" => $hostname,
                        "host_key" => $lines[0],
                        "host_query" => $name_or_ip,
                    ];
                } elseif ($p->exitCode != 1 /* 1 == NOT_FOUND */) {
                    $output = empty($lines)
                        ? ""
                        : PHP_EOL . join(PHP_EOL, $lines);

                    Utils::log()->error("Failed querying known hosts for $name_or_ip ($host). Exit code: {$p->exitCode} $output");
                }
            }
        }

        if (empty($keys)) {
            Utils::log()->info("Didn't find $host in known_hosts");
        }

        return $keys;
    }

    /**
     * Removes a specific host from the "known_hosts" file.
     * @param string $host The name of the host to remove.
     * @return bool True on success.
     */
    public function removeKnownHost(string $host)
    {
        $ok = false;

        if ($p = Process::open(["ssh-keygen", "-R", $host, "-f", $this->knownHostsFile()])) {
            $ok = $p->close() === 0;
            if (!$ok) {
                Utils::log()->error("Failed removing known hosts for $host. Return code was: {$p->exitCode}");
            }
        }

        return $ok;
    }

    /**
     * Returns the key info (key hash, length, type) for a specified host key.
     * @param string $host_key The host key as formatted in "authorized_keys" or "known_hosts".
     * @return array|bool A host key info [hash=>.., key_type=>.., key_length=>..] or false on failure.
     */
    public static function getHostKeyInfo(string $host_key)
    {
        if ($p = Process::open(["ssh-keygen", "-l", "-f", "-"])) {
            $p->put($host_key);
            $p->closeInput();

            if (($hash = $p->get()) && preg_match('/^([0-9]+) (.+?) .+? \(([^()]+)\)$/', $hash, $matches)) {
                return [
                    "hash" => $matches[2],
                    "key_type" => $matches[3],
                    "key_length" => $matches[1]
                ];
            } else {
                Utils::log()->error("Unsupported hash type: $hash");
            }
        }

        Utils::log()->error("Failed getting hash for host_key");
        return false;
    }

    /**
     * Returns the path to the public identity key file, generating it if missing.
     * @param string $identity_type the type of identity to return {@see IDENTITY_TYPES}.
     * @param bool $private Return the path to the private key file instead.
     * @return string The path to the key file.
     */
    public function getIdentity(string $identity_type = self::DEFAULT_IDENTITY_TYPE, $private = false): string
    {
        Utils::requireThat(in_array($identity_type, self::IDENTITY_TYPES), "Identity type '$identity_type' unknown.");

        list($key_type, $key_size) = explode('_', "{$identity_type}_", 2);
        if (!$key_size && self::DEFAULT_IDENTITY_KEY_BITS[$key_type] > 0) {
            $key_size = self::DEFAULT_IDENTITY_KEY_BITS[$key_type];
        }

        $identity_path = "{$this->config_path}/id.{$identity_type}";

        if (!file_exists($identity_path)) {
            $generate_key = [
                "ssh-keygen", "-v",
                "-f", $identity_path,
                "-t", $key_type,
                "-N", "",
            ];

            if (intval($key_size) > 0) {
                array_push($generate_key, "-b", $key_size);
            }

            if ($p = Process::open($generate_key)) {
                while (($line = $p->get(10)) !== false) {
                    Utils::log()->info("SSH keygen: $line");
                }

                Utils::requireThat(
                    $p->close() == 0,
                    "Failed generating identity $identity_path: Error code: {$p->exitCode}" . PHP_EOL
                    . "Command: " . join(" ", $generate_key)
                );
            }
        }

        return $private ? $identity_path : "{$identity_path}.pub";
    }

    /**
     * Returns a restrictions comment to be used in "authorized_keys" to limit what an identity can be used for.
     * @param string $host the SSH server host to create the restriction for.
     * @param string $outgoing_ip the IP address of the interface that will be used to connect the SSH server or empty to autodetect.
     * @param string $command the command to restrict the identity to.
     * @return string A restriction comment to be used to prepend an "authorized_keys" entry.
     */
    public static function getIdentityRestrictions($host = "", $outgoing_ip = "", $command = "internal-sftp"): string
    {
        $restrictions = ['restrict'];

        if ($command) {
            $restrictions[] = 'command="' . $command . '"';
        }

        $restrict_ip = empty(trim($outgoing_ip))
            ? (empty(trim($host)) ? false : self::getOutgoingIpFor($host))
            : $outgoing_ip;

        if ($restrict_ip) {
            $restrictions[] = 'from="' . $restrict_ip . '"';
        }


        return count($restrictions) > 1
            ? join(",", $restrictions)
            : "";
    }

    /**
     * Returns the IP address of the interface that will be used when connecting to host.
     * @param string $host the host to check outgoing IP address for.
     * @return bool|mixed an IPv4 address when the route & interface was detected.
     */
    public static function getOutgoingIpFor(string $host)
    {
        $ip = gethostbyname($host);
        $interface = null;

        if ($p = Process::open(["route", "-n", "get", $ip])) {
            while (($line = $p->get(10)) !== false) {
                if (preg_match('/\s*interface:\s*([^\s]+).*$/', $line, $matches)) {
                    $interface = $matches[1];
                }
            }
        }

        if ($interface && $p = Process::open(["ifconfig", $interface, "inet"])) {
            while (($line = $p->get(10)) !== false) {
                if (preg_match('/\s*inet\s+([^\s]+)\s+netmask.*/', $line, $matches)) {
                    return $matches[1];
                }
            }
        }

        return false;
    }
}
