<?php

/**
 *    Copyright (C) 2023 Thomas Rogdakis <thomas@rogdakis.com>
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\Backup;

use Exception;
use OPNsense\Core\Config;

class GitHub extends Base implements IBackupProvider
{
    protected GitHubSettings $model;

    public function __construct()
    {
        $this->model = new GitHubSettings();
    }

    /**
     * @inheritdoc
     */
    public function getConfigurationFields(): array
    {
        return [
            [
                'name' => 'enabled',
                'type' => 'checkbox',
                'label' => gettext('Enable'),
                'value' => $this->model->enabled
            ],
            [
                'name' => 'url',
                'type' => 'text',
                'label' => gettext('GitHub API URL'),
                'value' => $this->model->url,
                'help' => gettext(
                    'Change only when using GitHub Enterprise.<br/>' .
                    'For GitHub Enterprise use the following format:<br/>' .
                    'https://[GH_ENTERPRISE_HOST]/api/v3'
                )
            ],
            [
                'name' => 'token',
                'type' => 'password',
                'label' => gettext('GitHub token'),
                'value' => $this->model->token
            ],
            [
                'name' => 'repository',
                'type' => 'text',
                'label' => gettext('Repository'),
                'value' => $this->model->repository,
                'help' => gettext(
                    'Provide in the following format:<br/>' .
                    '[USER]/[REPOSITORY]'
                )
            ],
            [
                'name' => 'branch',
                'type' => 'text',
                'label' => gettext('Branch'),
                'value' => $this->model->branch
            ],
            [
                'name' => 'encryption_password',
                'type' => 'password',
                'label' => gettext('Encryption Password'),
                'value' => $this->model->encryption_password,
                'help' => gettext('When provided, the config will be encrypted before uploading to GitHub')
            ],
            [
                'name' => 'skip_ssl_verify',
                'type' => 'checkbox',
                'label' => gettext('Skip SSL Verification'),
                'value' => $this->model->skip_ssl_verify
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function setConfiguration($conf): array
    {
        $this->setModelProperties($this->model, $conf);

        if (!$validationMessages = $this->validateModel($this->model)) {
            $this->model->serializeToConfig();
            Config::getInstance()->save();
        }

        return $validationMessages;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return gettext('GitHub');
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function backup(): array
    {
        $config = Config::getInstance()->object();
        $currentRevision = $config->revision;
        $fileName = "config-{$config->system->hostname}.{$config->system->domain}-" . date('YmdHis') . '.xml';

        $rawConfig = file_get_contents('/conf/config.xml');
        if ($this->model->encryption_password !== null && mb_strlen($this->model->encryption_password) !== 0) {
            $rawConfig = $this->encrypt($rawConfig, (string)$this->model->encryption_password);
        }

        $this->uploadFile($currentRevision, $fileName, $rawConfig);
        return $this->getFileList();
    }

    /**
     * @inheritdoc
     */
    public function isEnabled(): bool
    {
        return (string)$this->model->enabled === '1';
    }

    /**
     * Upload file to GitHub using the API
     *
     * @param object $currentRevision
     * @param string $fileName
     * @param string $rawConfig
     * @return void
     * @throws Exception
     */
    protected function uploadFile(object $currentRevision, string $fileName, string $rawConfig): void
    {
        $resp = $this->sendRequestToGitHub('PUT', "/repos/{$this->model->repository}/contents/{$fileName}", [
            'message' => "{$currentRevision->description} ({$currentRevision->username})",
            'content' => base64_encode($rawConfig),
            'branch' => (string)$this->model->branch
        ]);

        if ($resp['statusCode'] !== 201) {
            if (!empty($resp['json']['message'])) {
                throw new Exception("Error from GitHub: {$resp['json']['message']}");
            }

            throw new Exception(
                "Unexpected status code returned from GitHub while uploading the config: {$resp['statusCode']}"
            );
        }
    }

    /**
     * Get the repository file list using the GitHub API
     *
     * @return array
     * @throws Exception
     */
    protected function getFileList(): array
    {
        $resp = $this->sendRequestToGitHub('GET', "/repos/{$this->model->repository}/contents");

        if ($resp['statusCode'] !== 200) {
            if (!empty($resp['json']['message'])) {
                throw new Exception("Error from GitHub: {$resp['json']['message']}");
            }

            throw new Exception(
                "Unexpected status code returned from GitHub: {$resp['statusCode']}"
            );
        }

        $files = array_filter($resp['json'], fn ($file) => $file['type'] === 'file');
        return array_map(fn ($file) => $file['name'], $files);
    }

    /**
     * Send request to GitHub API
     *
     * @param string $method
     * @param string $endpoint
     * @param array $data
     * @return array
     */
    protected function sendRequestToGitHub(
        string $method,
        string $endpoint,
        array $data = [],
    ): array {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->model->url . $endpoint,
            CURLOPT_VERBOSE => false,
            CURLOPT_SSL_VERIFYPEER => (string)$this->model->skip_ssl_verify === '0',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_USERAGENT => 'OPNsense-GitHub-Backup/1.0',
            CURLOPT_HTTPHEADER => [
                'X-GitHub-Api-Version: 2022-11-28',
                'Accept: application/vnd.github+json',
                "Authorization: token {$this->model->token}"
            ]
        ]);

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $resp = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $json = json_decode($resp, true);

        curl_close($ch);

        return compact('statusCode', 'json');
    }
}