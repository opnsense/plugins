<?php
/*
 *    Copyright (C) 2017 Michael Muenz <m.muenz@gmail.com>
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
 */
use OPNsense\Core\Config;
use OPNsense\Firewall\Alias;
use OPNsense\Firewall\Plugin;

function add_alias_if_not_exist($name, $description, $proto)
{
	$model = new Alias();

	if ($model->getByName($name) != null) {
		return;
	}

	$new_alias = $model->aliases->alias->Add();
	$new_alias->name = $name;
	$new_alias->description = $description;
	$new_alias->proto = $proto;
	$new_alias->type = 'external';
	$model->serializeToConfig();
	Config::getInstance()->save();
}

function crowdsec_firewall(Plugin $fw)
{
	global $config;
	if (
		isset($config['OPNsense']['abuseipdb']['general']['enabled']) &&
		$config['OPNsense']['abuseipdb']['general']['enabled'] == 1
	) {
		add_alias_if_not_exist('abuseipdb', 'abuseipdb blocklist', 'IPv4');
		$fw->registerFilterRule(
			1, /* priority */
			array(
				'ipprotocol'	=> 'inet',
				'descr'			=> 'abuseipdb blocklist',
				'from'			=> '$abuseipdb',     # $ to reference an alias
				'direction'		=> 'in',
				'type'			=> 'block',
				'log'			=> $rules_log_enabled,
				'tag'			=> $rules_tag,
				'quick'			=> true
			)
		);
	}

function abuseipdb_services()
{
	global $config;
	$services = array();

	if (
		isset($config['OPNsense']['abuseipdb']['general']['enabled']) &&
		$config['OPNsense']['abuseipdb']['general']['enabled'] == 1
	) {
		$services[] = array(
			'description' => 'abuseipdb Daemon',
			'configd' => array(
				'restart'	=> array('abuseipdb restart'),
				'start'		=> array('abuseipdb start'),
				'stop'		=> array('abuseipdb stop'),
			),
			'name' => 'abuseipdb',
			'pidfile' => '/var/run/abuseipdb.pid'
		);
	}

	return $services;
}

