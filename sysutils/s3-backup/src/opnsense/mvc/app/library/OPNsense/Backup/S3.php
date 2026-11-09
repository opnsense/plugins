<?php

/*
 * Copyright (C) 2026 Greelan
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

namespace OPNsense\Backup;

use OPNsense\Core\ACL;
use OPNsense\Core\AppConfig;
use OPNsense\Core\Config;
use OPNsense\Core\Shell;
use OPNsense\Firewall\Util;

/**
 * Configuration backups to S3-compatible object storage, through rclone.
 * @package OPNsense\Backup
 */
class S3 extends Base implements IBackupProvider
{
    /* names core gives backups: config-<microtime>[_<hrtime>].xml */
    private const BACKUP_NAME = '/^config-[0-9]+(\.[0-9]+)?(_[0-9]+)?\.xml$/D';
    /* seconds core can take a save's revision time after its backup's name: a whoami later, on the console */
    private const SAME_SAVE = 1;
    /* the most of an rclone call's output or log that is read */
    private const MAX_RESPONSE = 8 * 1024 * 1024;
    /* the start of this provider's temporary file names */
    private const TEMP_PREFIX = 's3-backup-';
    /* seconds an rclone call may take, so an endpoint that never stops answering cannot hold the page or the
     * nightly run */
    private const DEADLINE = 300;

    private $model = null;
    /* rclone's S3 providers, read once a request, as core's page makes a provider for each list it shows */
    private static $providers = null;

    public function __construct()
    {
        $this->model = new S3Settings();
    }

    /**
     * @inheritdoc
     */
    public function getConfigurationFields()
    {
        try {
            $providers = $this->providers();
        } catch (\Exception $e) {
            $providers = [];
        }
        $provider = $this->model->provider->getValue();
        /* the stored one when rclone does not list it, so a save shows why */
        $providers += [$provider => $provider];
        $fields = [
            [
                'name' => 'enabled',
                'type' => 'checkbox',
                'label' => gettext('Enable'),
            ],
            [
                'name' => 'endpoint',
                'type' => 'text',
                'label' => gettext('Endpoint'),
                'help' => gettext(
                    'Host name of the S3 service, optionally with a port, e.g. s3.eu-central-1.amazonaws.com,' .
                    ' s3.us-west-004.backblazeb2.com or ACCOUNT_ID.r2.cloudflarestorage.com. HTTPS is always ' .
                    'used.'
                ),
            ],
            [
                'name' => 'provider',
                'type' => 'dropdown',
                'label' => gettext('Provider'),
                'options' => $providers,
                /* the page shows help as it is */
                'help' => sprintf(
                    gettext('The S3 service, so rclone applies its ways. For one not listed, choose "%s".'),
                    htmlspecialchars($providers['Other'] ?? 'Other', ENT_QUOTES | ENT_HTML401)
                ),
            ],
            [
                'name' => 'region',
                'type' => 'text',
                'label' => gettext('Region'),
                'help' => gettext('Region the bucket is in, e.g. us-east-1. Cloudflare R2 uses auto.'),
            ],
            [
                'name' => 'bucket',
                'type' => 'text',
                'label' => gettext('Bucket'),
                'help' => gettext('An existing bucket.'),
            ],
            [
                'name' => 'prefix',
                'type' => 'text',
                'label' => gettext('Folder'),
                'help' => gettext(
                    'Folder in the bucket for this firewall, e.g. backups/fw1. Use a different folder for each ' .
                    'firewall: backups in the folder beyond the backup count are deleted, whichever firewall ' .
                    'wrote them.'
                ),
            ],
            [
                'name' => 'accessKey',
                'type' => 'text',
                'label' => gettext('Access Key ID'),
            ],
            [
                'name' => 'secretKey',
                'type' => 'password',
                'label' => gettext('Secret Access Key'),
                'help' => gettext(
                    'Never shown again: a saved key shows as stars. Leave empty to keep it. The key only needs ' .
                    'to list, write and delete objects in this bucket.'
                ),
                /* an empty box keeps a saved one, which core describes as stars */
                'value' => '',
                'placeholder' => $this->model->secretKey->getDescription(),
            ],
            [
                'name' => 'backupcount',
                'type' => 'text',
                'label' => gettext('Backup Count'),
                'help' => gettext('How many backups to keep in the folder. Older ones are deleted; 0 keeps them all.'),
            ],
            [
                'name' => 'password',
                'type' => 'password',
                'label' => gettext('Encrypt Password'),
                'help' => gettext(
                    'Backups are encrypted with this password before they are sent, and only it can restore ' .
                    'them, so keep a copy somewhere other than this firewall. Changing it does not re-encrypt ' .
                    'backups already sent; they still need the password they were made with. Never shown ' .
                    'again: a saved password shows as stars in both boxes. Leave them empty to keep it.'
                ),
                'value' => '',
                'placeholder' => $this->model->password->getDescription(),
            ],
            [
                'name' => 'passwordconfirm',
                'type' => 'password',
                'label' => gettext('Confirm'),
                'value' => '',
                'placeholder' => $this->model->password->getDescription(),
            ],
        ];
        foreach ($fields as &$field) {
            /* the page escapes them, with legacy_html_escape_form_data() */
            $field['value'] ??= $this->model->getNodeByReference($field['name'])->getValue();
        }
        return $fields;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return gettext('S3');
    }

    /**
     * @inheritdoc
     */
    public function setConfiguration($conf)
    {
        /* core's diag_backup.php checks this for a restore, not for a provider's setup */
        if (!empty($_SESSION['Username']) && (new ACL())->hasPrivilege($_SESSION['Username'], 'user-config-readonly')) {
            return [gettext('You do not have the permission to perform this action.')];
        }
        $this->setModelProperties($this->model, $conf);
        $messages = $this->validateModel($this->model);
        /* rclone runs an unknown provider as Other, so only one it lists is taken; a disabled backup needs no
         * rclone, so it always saves */
        $provider = $this->model->provider->getValue();
        if ($this->model->enabled->isEqual('1') && preg_match('/^[A-Za-z0-9]{1,32}$/D', $provider)) {
            try {
                if (!isset($this->providers()[$provider])) {
                    $messages[] = sprintf(gettext('rclone knows no S3 provider named %s.'), $provider);
                }
            } catch (\Exception $e) {
                $messages[] = $e->getMessage();
            }
        }
        if (($conf['password'] ?? '') !== ($conf['passwordconfirm'] ?? '')) {
            $messages[] = gettext("The supplied 'Password' and 'Confirm' field values must match.");
        }
        if (empty($messages)) {
            $this->model->serializeToConfig();
            Config::getInstance()->save();
        }
        return $messages;
    }

    /**
     * @inheritdoc
     */
    public function isEnabled()
    {
        return $this->model->enabled->isEqual('1');
    }

    /**
     * @inheritdoc
     */
    public function backup()
    {
        $cnf = Config::getInstance();
        if (!$this->isEnabled() || !$cnf->isValid()) {
            return [];
        }
        try {
            return $this->upload($cnf);
        } catch (\Throwable $e) {
            /* the page shows an Exception's message, in the GUI's language; the nightly run's go to the log */
            if (empty($_SESSION['Username']) || !$e instanceof \Exception) {
                syslog(LOG_ERR, 's3-backup: ' . $e->getMessage());
            }
            /* the page and the nightly run catch only Exception */
            throw $e instanceof \Exception ? $e : new \Exception(
                gettext('The S3 backup failed unexpectedly; the system log has the details.'),
                0,
                $e
            );
        }
    }

    /**
     * Send the running configuration unless the bucket has it, then apply the backup count.
     * @param Config $cnf the configuration
     * @return array the backups in the folder, newest first
     * @throws \Exception when a step fails
     */
    private function upload($cnf)
    {
        /* settings can arrive without the page, e.g. in a restored config.xml, so check them all */
        foreach ($this->model->validate(null, '', true) as $field => $text) {
            /* core quotes some values, e.g. an endpoint, line breaks and all */
            throw new \Exception(sprintf(
                gettext('The S3 settings are not valid: %s: %s'),
                $field,
                self::oneLine($text)
            ));
        }

        [$plain, $name, $revised] = $this->running($cnf);

        /* the folder as entered, which is this firewall's alone */
        $folder = $this->model->prefix->getValue() . '/';
        $keep = (int)$this->model->backupcount->getValue();
        $remote = $this->listBackups($folder);
        /* the same save under core's name and under its revision time, which is later, rounded to two decimals */
        foreach (in_array($name, $remote) ? [] : $remote as $other) {
            $later = $revised ? self::stamp($name) - self::stamp($other) : self::stamp($other) - self::stamp($name);
            if ($later > -0.01 && $later < self::SAME_SAVE) {
                $name = $other;
                break;
            }
        }
        if (!in_array($name, $remote)) {
            $encrypted = $this->encrypt($plain, $this->model->password->getValue());
            if ($encrypted === null) {
                throw new \Exception(gettext('The configuration could not be encrypted.'));
            }
            $this->put($folder . $name, $encrypted);
            /* an answer that is not S3's, e.g. a login page, can pass for a stored upload */
            try {
                $listed = $this->listBackups($folder, [$name]);
            } catch (\Exception $e) {
                throw new \Exception(sprintf(
                    gettext('The backup %s was sent, but the bucket could not be listed: %s'),
                    $folder . $name,
                    $e->getMessage()
                ));
            }
            if (!in_array($name, $listed)) {
                throw new \Exception(sprintf(
                    gettext('The backup %s was sent, but the bucket does not list it.'),
                    $folder . $name
                ));
            }
            syslog(LOG_NOTICE, 's3-backup: uploaded ' . $folder . $name);
        }

        /* newest first, and the backup just sent always stays; as in core's list, names stamped in the future,
         * from a clock that ran ahead, are left out, so they are never deleted */
        $now = microtime(true);
        $remote = array_filter($remote, function ($other) use ($now) {
            return self::stamp($other) <= $now;
        });
        usort($remote, function ($a, $b) {
            return [self::stamp($b), $b] <=> [self::stamp($a), $a];
        });
        $remote = array_merge([$name], array_values(array_diff($remote, [$name])));
        if ($keep > 0) {
            try {
                $old = array_slice($remote, $keep);
                if (!empty($old)) {
                    $this->delete($folder, $old);
                }
            } catch (\Exception $e) {
                throw new \Exception(sprintf(
                    gettext('The backup is stored, but removing older backups failed: %s'),
                    $e->getMessage()
                ));
            }
            $remote = array_slice($remote, 0, $keep);
        }

        return $remote;
    }

    /**
     * Store data under a key of the bucket.
     * @param string $key key in the bucket
     * @param string $data contents
     * @throws \Exception when rclone fails
     */
    private function put($key, $data)
    {
        $file = self::rcloneFile($data);
        try {
            /* no HEAD, so no read permission needed, and no ETag check, as it is no MD5 under KMS: S3 checks
             * the Content-MD5 rclone sends. Typed as bytes, not as XML by its name */
            $this->rclone(
                ['copyto', $file, $this->path($key), '--no-check-dest', '--ignore-checksum', '--header-upload',
                    'Content-Type: application/octet-stream'],
                $this->remote()
            );
        } finally {
            @unlink($file);
        }
    }

    /**
     * Remove backups from a folder; each one the listing after no longer holds is logged, and one it
     * still holds fails, saying why.
     * @param string $folder folder in the bucket
     * @param array $names backup names in it
     * @throws \Exception when a backup is still listed
     */
    private function delete($folder, $names)
    {
        $list = self::rcloneFile(implode("\n", $names) . "\n");
        $error = null;
        try {
            /* by name, so rclone does not list the folder first */
            $this->rclone(
                ['delete', $this->path($folder), '--files-from-raw', $list, '--no-traverse'],
                $this->remote()
            );
        } catch (\Exception $e) {
            $error = $e->getMessage();
        } finally {
            @unlink($list);
        }
        /* the listing decides: rclone takes any 2xx as deleted; S3 can answer 404 for one already gone */
        try {
            $listed = $this->listBackups($folder);
        } catch (\Exception $e) {
            throw new \Exception(sprintf(
                gettext('The bucket could not be listed to check which backups were removed: %s'),
                $e->getMessage()
            ));
        }
        $left = array_values(array_intersect($names, $listed));
        foreach (array_diff($names, $left) as $name) {
            syslog(LOG_NOTICE, 's3-backup: removed ' . $folder . $name);
        }
        if (!empty($left)) {
            throw new \Exception($error ?? sprintf(
                gettext('The bucket still lists %d of the backups to remove.'),
                count($left)
            ));
        }
    }

    /**
     * The running config and the name to store it under: that of the backup core wrote with it
     * on save. A file merely copied into the backup directory holds other contents, whatever
     * its name says.
     * @param Config $cnf the configuration
     * @return array the contents, the name, and whether the name is the save time it records
     * @throws \Exception when it cannot be read or named
     */
    private function running($cnf)
    {
        $handle = @fopen((new AppConfig())->application->configDir . '/config.xml', 'r');
        if ($handle === false) {
            throw new \Exception(gettext('The configuration could not be read.'));
        }
        /* a save holds an exclusive lock while it writes config.xml and its backup; wait up to 10 s,
         * not outright, as this process may hold it through another handle */
        for ($try = 0; !flock($handle, LOCK_SH | LOCK_NB); $try++) {
            if ($try >= 50) {
                fclose($handle);
                throw new \Exception(gettext('The configuration is being saved; try again in a moment.'));
            }
            usleep(200000);
        }
        try {
            $plain = stream_get_contents($handle);
            /* only names core gives, so a hand-made copy is never the one matched */
            $backups = array_filter($cnf->getBackups(), function ($backup) {
                return preg_match(self::BACKUP_NAME, basename($backup)) === 1;
            });
            usort($backups, function ($a, $b) {
                return self::stamp(basename($b)) <=> self::stamp(basename($a));
            });
            $name = null;
            foreach ($backups as $backup) {
                if (@filesize($backup) === strlen($plain) && @file_get_contents($backup) === $plain) {
                    $name = basename($backup);
                    break;
                }
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
        $revised = $name === null;
        if ($revised) {
            /* saved without a backup of its own: named by the save time it records, which core
             * writes with the GUI language's decimal mark, or a byte of one */
            $xml = @simplexml_load_string($plain, 'SimpleXMLElement', LIBXML_NONET);
            $time = $xml !== false ? trim(preg_replace('/[^0-9]+/', '.', (string)$xml->revision->time), '.') : '';
            $name = sprintf('config-%s.xml', $time);
        }
        if (!preg_match(self::BACKUP_NAME, $name)) {
            /* it would never show in the listing, so it would be sent again every time */
            throw new \Exception(gettext('The configuration records no save time.'));
        }
        return [$plain, $name, $revised];
    }

    /**
     * The bucket's settings, as rclone reads them from its environment: as they are, and never on a
     * command line.
     * @return array environment variables
     */
    private function remote()
    {
        $host = $this->model->endpoint->getValue();
        return [
            'RCLONE_CONFIG_S3_TYPE' => 's3',
            'RCLONE_CONFIG_S3_PROVIDER' => $this->model->provider->getValue(),
            'RCLONE_CONFIG_S3_ENV_AUTH' => 'false',
            'RCLONE_CONFIG_S3_ACCESS_KEY_ID' => $this->model->accessKey->getValue(),
            'RCLONE_CONFIG_S3_SECRET_ACCESS_KEY' => $this->model->secretKey->getValue(),
            'RCLONE_CONFIG_S3_REGION' => $this->model->region->getValue(),
            /* an IPv6 address without a port, which a URL takes in brackets */
            'RCLONE_CONFIG_S3_ENDPOINT' => 'https://' . (Util::isIpv6Address($host) ? "[{$host}]" : $host),
            'RCLONE_CONFIG_S3_NO_CHECK_BUCKET' => 'true',
            'RCLONE_CONFIG_S3_NO_HEAD' => 'true',
            'RCLONE_CONFIG_S3_NO_HEAD_OBJECT' => 'true',
        ];
    }

    /**
     * A file only this process can read, holding $contents, for rclone.
     * @param string $contents contents
     * @return string path
     * @throws \Exception when it cannot be made
     */
    private static function rcloneFile($contents = '')
    {
        $file = tempnam((new AppConfig())->application->tempDir, self::TEMP_PREFIX);
        if ($file !== false && file_put_contents($file, $contents) === false) {
            @unlink($file);
            $file = false;
        }
        if ($file === false) {
            throw new \Exception(gettext('No temporary file could be made for rclone.'));
        }
        return $file;
    }

    /**
     * rclone's name for a key, or a folder, of the bucket.
     * @param string $key key or folder in the bucket
     * @return string rclone path
     */
    private function path($key)
    {
        return 's3:' . $this->model->bucket->getValue() . '/' . $key;
    }

    /**
     * Run rclone with $settings and nothing else in its environment.
     * @param array $args arguments
     * @param array $settings environment
     * @param array $codes the failure to report for an exit code
     * @return string what it printed
     * @throws \Exception why it failed, one line
     */
    private function rclone(array $args, array $settings = [], array $codes = [])
    {
        /* its log goes to a file: a pipe it filled while the listing is read would stall it */
        $log = self::rcloneFile();
        try {
            /* stopped at the deadline by timeout(1) (exit 124), in 2 GB of address space (Go reserves much up
             * front), its log cut at 8 MB: it holds a whole listing, logs each odd name, and a listing can be
             * endless. rclone's S3 tries each request twice: 16 s to connect and 120 s without data at most */
            $argv = array_merge(['/usr/bin/limits', '-v', '2g', '-f', '8m', '/usr/bin/timeout', '-k', '5',
                (string)self::DEADLINE, '/usr/local/bin/rclone', '--config', '/dev/null', '--retries', '1',
                '--low-level-retries', '1', '--contimeout', '8s', '--timeout', '60s', '--use-json-log',
                '--log-level', 'NOTICE'], $args);
            $process = proc_open(
                Shell::exec_safe(array_fill(0, count($argv), '%s'), $argv),
                [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $log, 'w']],
                $pipes,
                null,
                $settings
            );
            if (!is_resource($process)) {
                throw new \Exception(gettext('rclone could not be started.'));
            }
            $out = (string)stream_get_contents($pipes[1], self::MAX_RESPONSE + 1);
            if (strlen($out) > self::MAX_RESPONSE) {
                /* a SIGTERM, which timeout(1) passes on to rclone */
                proc_terminate($process);
            }
            fclose($pipes[1]);
            /* proc_close() hides a signal: timeout(1) exits 128 + it on FreeBSD 14, dies of it on 15 */
            while (($status = proc_get_status($process))['running']) {
                usleep(10000);
            }
            proc_close($process);
            $code = $status['signaled'] ? 128 + $status['termsig'] : $status['exitcode'];
            $lines = explode("\n", (string)file_get_contents($log, false, null, 0, self::MAX_RESPONSE));
        } finally {
            @unlink($log);
        }
        /* rclone's first error names the cause (its last only sums up); else the first line anything
         * else printed, e.g. the shell */
        $message = '';
        $other = '';
        $unknown = false;
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (!is_array($entry)) {
                $other = $other === '' ? trim($line) : $other;
                continue;
            }
            $text = (string)($entry['msg'] ?? '');
            /* rclone runs a provider it does not know as Other */
            $unknown = $unknown || preg_match('/^s3 provider ".*" not known/', $text);
            if ($message === '' && in_array($entry['level'] ?? '', ['error', 'critical'])) {
                /* not the temporary file an upload is sent from */
                $object = (string)($entry['object'] ?? '');
                $message = $object === '' || strpos($object, self::TEMP_PREFIX) === 0 ? $text : $object . ': ' . $text;
            }
        }
        if (strlen($out) > self::MAX_RESPONSE) {
            $error = gettext('rclone sent more output than expected.');
        } elseif ($code == 124) {
            $error = sprintf(gettext('rclone did not finish within %d seconds.'), self::DEADLINE);
        } elseif ($unknown) {
            $error = sprintf(
                gettext('rclone knows no S3 provider named %s.'),
                $settings['RCLONE_CONFIG_S3_PROVIDER'] ?? ''
            );
        } elseif ($code > 128) {
            $error = sprintf(gettext('rclone was stopped by signal %d.'), $code - 128);
        } elseif (isset($codes[$code])) {
            $error = $codes[$code];
        } elseif ($code != 0) {
            $message = $message !== '' ? $message : $other;
            $error = $message !== '' ? sprintf(gettext('rclone: %s'), self::rcloneMessage($message)) :
                sprintf(gettext('rclone stopped with exit code %d.'), $code);
        } else {
            return $out;
        }
        throw new \Exception($error);
    }

    /**
     * The S3 providers the installed rclone knows.
     * @return array name => description
     * @throws \Exception when rclone lists none
     */
    private function providers()
    {
        if (self::$providers === null) {
            $backends = json_decode($this->rclone(['config', 'providers']), true);
            $backends = array_column(is_array($backends) ? $backends : [], null, 'Name');
            $options = array_column($backends['s3']['Options'] ?? [], null, 'Name');
            $names = array_column($options['provider']['Examples'] ?? [], 'Help', 'Value');
            if (empty($names)) {
                throw new \Exception(gettext('rclone listed no S3 providers.'));
            }
            self::$providers = $names;
        }
        return self::$providers;
    }

    /**
     * Timestamp a backup name carries, as core reads it.
     * @param string $name backup name
     * @return float timestamp
     */
    private static function stamp($name)
    {
        return (float)substr($name, strlen('config-'), -strlen('.xml'));
    }

    /**
     * Backup file names in a folder of the bucket; with patterns, only those matching one.
     * @param string $folder folder in the bucket
     * @param array $only rclone include patterns
     * @return array backup names
     * @throws \Exception when rclone fails
     */
    private function listBackups($folder, array $only = [])
    {
        /* rclone lists this folder only, not its subfolders, e.g. other firewalls under this one's */
        $args = ['lsf', $this->path($folder), '--files-only'];
        /* only backup names, so other files in the folder do not fill the output */
        foreach ($only ?: ['config-*.xml'] as $pattern) {
            $args = array_merge($args, ['--include', $pattern]);
        }
        /* rclone's "directory not found" is any 404: S3 has no folders, so no bucket, or not S3 */
        $out = $this->rclone($args, $this->remote(), [3 => sprintf(
            gettext('The endpoint has no bucket %s.'),
            $this->model->bucket->getValue()
        )]);
        /* core prints the names it gets back unescaped, so only backup names pass */
        return array_values(preg_grep(self::BACKUP_NAME, explode("\n", $out)));
    }

    /**
     * An rclone message as one short line: the AWS SDK's fixed wording left out, and its start and end
     * kept when it is still long, as a Go error names its cause last.
     * @param string $text message
     * @return string one line
     */
    private static function rcloneMessage($text)
    {
        /* an endpoint can send megabytes, which the patterns would read over and over */
        $text = self::cut($text, 2048, 2048);
        $replace = [
            '/operation error S3: \w+, /' => '',
            '/https response error /' => '',
            '/StatusCode: 0, /' => '',
            '/RequestID: [^,]*, HostID: [^,]*, /' => '',
            '/exceeded maximum number of attempts, \d+, /' => '',
            '/("https?:\/\/[^"?]*)\?[^"]*"/' => '$1"',
        ];
        return self::cut(self::oneLine(preg_replace(array_keys($replace), array_values($replace), $text)), 120, 115);
    }

    /**
     * Text as one line: control characters, which could add lines to the log, become spaces.
     * @param string $text text
     * @return string one line
     */
    private static function oneLine($text)
    {
        return trim(preg_replace('/[\x00-\x1f\x7f]+/', ' ', $text));
    }

    /**
     * The start and end of a long text, cut between characters, as syslog readers expect UTF-8.
     * @param string $text text
     * @param int $head bytes kept from the start
     * @param int $tail bytes kept from the end
     * @return string at most $head + $tail + 5 bytes
     */
    private static function cut($text, $head, $tail)
    {
        if (strlen($text) <= $head + $tail + 5) {
            return $text;
        }
        return preg_replace('/[\xc0-\xff][\x80-\xbf]*$/', '', substr($text, 0, $head)) . ' ... ' .
            preg_replace('/^[\x80-\xbf]+/', '', substr($text, -$tail));
    }
}
