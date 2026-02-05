<?php

/**
 *    Copyright (C) 2026 Frank Wall
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

namespace OPNsense\HAProxy\Migrations;

use OPNsense\Base\BaseModelMigration;

class M5_0_0 extends BaseModelMigration
{
    public function run($model)
    {
        foreach ($model->getNodeByReference('actions.action')->iterateItems() as $action) {
            // Rules have an 'enabled' field now
            $action->enabled = '1';
            // Migrate TCP/HTTP rules to new format
            switch ((string)$action->type) {
                case 'http-request_add-header':
                    $action->type = 'http-request';
                    $action->http_request_action = 'add-header';
                    if (!empty((string)$action->http_request_add_header_name)) {
                        $action->http_request_option = (string)$action->http_request_add_header_name . ' ' . (string)$action->http_request_add_header_content;
                        $action->http_request_add_header_name = null;
                        $action->http_request_add_header_content = null;
                    }
                    break;
                case 'http-request_allow':
                    $action->type = 'http-request';
                    $action->http_request_action = 'allow';
                    break;
                case 'http-request_auth':
                    $action->type = 'http-request';
                    $action->http_request_action = 'auth';
                    if (!empty((string)$action->http_request_auth)) {
                        $action->http_request_option = 'realm ' . (string)$action->http_request_auth;
                        $action->http_request_auth = null;
                    }
                    break;
                case 'http-request_del-header':
                    $action->type = 'http-request';
                    $action->http_request_action = 'del-header';
                    if (!empty((string)$action->http_request_del_header_name)) {
                        $action->http_request_option = (string)$action->http_request_del_header_name;
                        $action->http_request_del_header_name = null;
                    }
                    break;
                case 'http-request_deny':
                    $action->type = 'http-request';
                    $action->http_request_action = 'deny';
                    break;
                case 'http-request_lua':
                    $action->type = 'http-request';
                    $action->http_request_action = 'lua';
                    if (!empty((string)$action->http_request_lua)) {
                        $action->http_request_option = (string)$action->http_request_lua;
                        $action->http_request_lua = null;
                    }
                    break;
                case 'http-request_redirect':
                    $action->type = 'http-request';
                    $action->http_request_action = 'redirect';
                    if (!empty((string)$action->http_request_redirect)) {
                        $action->http_request_option = (string)$action->http_request_redirect;
                        $action->http_request_redirect = null;
                    }
                    break;
                case 'http-request_replace-header':
                    $action->type = 'http-request';
                    $action->http_request_action = 'replace-header';
                    if (!empty((string)$action->http_request_replace_header_name)) {
                        $action->http_request_option = (string)$action->http_request_replace_header_name . ' ' . (string)$action->http_request_replace_header_regex;
                        $action->http_request_replace_header_name = null;
                        $action->http_request_replace_header_regex = null;
                    }
                    break;
                case 'http-request_replace-value':
                    $action->type = 'http-request';
                    $action->http_request_action = 'replace-value';
                    if (!empty((string)$action->http_request_replace_value_name)) {
                        $action->http_request_option = (string)$action->http_request_replace_value_name . ' ' . (string)$action->http_request_replace_value_regex;
                        $action->http_request_replace_value_name = null;
                        $action->http_request_replace_value_regex = null;
                    }
                    break;
                case 'http-request_set-header':
                    $action->type = 'http-request';
                    $action->http_request_action = 'set-header';
                    if (!empty((string)$action->http_request_set_header_name)) {
                        $action->http_request_option = (string)$action->http_request_set_header_name . ' ' . (string)$action->http_request_set_header_content;
                        $action->http_request_set_header_name = null;
                        $action->http_request_set_header_content = null;
                    }
                    break;
                case 'http-request_set-path':
                    $action->type = 'http-request';
                    $action->http_request_action = 'set-path';
                    if (!empty((string)$action->http_request_set_path)) {
                        $action->http_request_option = (string)$action->http_request_set_path;
                        $action->http_request_set_path = null;
                    }
                    break;
                case 'http-request_set-var':
                    $action->type = 'http-request';
                    $action->http_request_action = 'set-var';
                    if (!empty((string)$action->http_request_set_var_name)) {
                        $action->http_request_option = '(' . (string)$action->http_request_set_var_scope . '.' . (string)$action->http_request_set_var_name . ') ' . (string)$action->http_request_set_var_expr;
                        $action->http_request_set_var_scope = null;
                        $action->http_request_set_var_name = null;
                        $action->http_request_set_var_expr = null;
                    }
                    break;
                case 'http-request_silent-drop':
                    $action->type = 'http-request';
                    $action->http_request_action = 'silent-drop';
                    break;
                case 'http-request_tarpit':
                    $action->type = 'http-request';
                    $action->http_request_action = 'tarpit';
                    break;
                case 'http-request_use-service':
                    $action->type = 'http-request';
                    $action->http_request_action = 'use-service';
                    if (!empty((string)$action->http_request_use_service)) {
                        $action->http_request_option = (string)$action->http_request_use_service;
                        $action->http_request_use_service = null;
                    }
                    break;
                case 'http-response_add-header':
                    $action->type = 'http-response';
                    $action->http_response_action = 'add-header';
                    if (!empty((string)$action->http_response_add_header_name)) {
                        $action->http_response_option = (string)$action->http_response_add_header_name . ' ' . (string)$action->http_response_add_header_content;
                        $action->http_response_add_header_name = null;
                        $action->http_response_add_header_content = null;
                    }
                    break;
                case 'http-response_allow':
                    $action->type = 'http-response';
                    $action->http_response_action = 'allow';
                    break;
                case 'http-response_del-header':
                    $action->type = 'http-response';
                    $action->http_response_action = 'del-header';
                    if (!empty((string)$action->http_response_del_header_name)) {
                        $action->http_response_option = (string)$action->http_response_del_header_name;
                        $action->http_response_del_header_name = null;
                    }
                    break;
                case 'http-response_deny':
                    $action->type = 'http-response';
                    $action->http_response_action = 'deny';
                    break;
                case 'http-response_lua':
                    $action->type = 'http-response';
                    $action->http_response_action = 'lua';
                    if (!empty((string)$action->http_response_lua)) {
                        $action->http_response_option = (string)$action->http_response_lua;
                        $action->http_response_lua = null;
                    }
                    break;
                case 'http-response_replace-header':
                    $action->type = 'http-response';
                    $action->http_response_action = 'replace-header';
                    if (!empty((string)$action->http_response_replace_header_name)) {
                        $action->http_response_option = (string)$action->http_response_replace_header_name . ' ' . (string)$action->http_response_replace_header_regex;
                        $action->http_response_replace_header_name = null;
                        $action->http_response_replace_header_regex = null;
                    }
                    break;
                case 'http-response_replace-value':
                    $action->type = 'http-response';
                    $action->http_response_action = 'replace-value';
                    if (!empty((string)$action->http_response_replace_value_name)) {
                        $action->http_response_option = (string)$action->http_response_replace_value_name . ' ' . (string)$action->http_response_replace_value_regex;
                        $action->http_response_replace_value_name = null;
                        $action->http_response_replace_value_regex = null;
                    }
                    break;
                case 'http-response_set-header':
                    $action->type = 'http-response';
                    $action->http_response_action = 'set-header';
                    if (!empty((string)$action->http_response_set_header_name)) {
                        $action->http_response_option = (string)$action->http_response_set_header_name . ' ' . (string)$action->http_response_set_header_content;
                        $action->http_response_set_header_name = null;
                        $action->http_response_set_header_content = null;
                    }
                    break;
                case 'http-response_set-status':
                    $action->type = 'http-response';
                    $action->http_response_action = 'set-status';
                    if (!empty((string)$action->http_response_set_status_code)) {
                        if (!empty((string)$action->http_response_set_status_reason)) {
                            $status_reason = ' reason "' . (string)$action->http_response_set_status_reason . '"';
                        } else {
                            $status_reason = '';
                        }
                        $action->http_response_option = (string)$action->http_response_set_status_code . $status_reason;
                        $action->http_response_set_status_code = null;
                        $action->http_response_set_status_reason = null;
                  }
                    break;
                case 'http-response_set-var':
                    $action->type = 'http-response';
                    $action->http_response_action = 'set-var';
                    if (!empty((string)$action->http_response_set_var_name)) {
                        $action->http_response_option = '(' . (string)$action->http_response_set_var_scope . '.' . (string)$action->http_response_set_var_name . ') ' . (string)$action->http_response_set_var_expr;
                        $action->http_response_set_var_scope = null;
                        $action->http_response_set_var_name = null;
                        $action->http_response_set_var_expr = null;
                    }
                    break;
                case 'tcp-request_connection_accept':
                    $action->type = 'tcp-request';
                    $action->tcp_request_action = 'connection_accept';
                    break;
                case 'tcp-request_connection_reject':
                    $action->type = 'tcp-request';
                    $action->tcp_request_action = 'connection_reject';
                    break;
                case 'tcp-request_content_accept':
                    $action->type = 'tcp-request';
                    $action->tcp_request_action = 'content_accept';
                    break;
                case 'tcp-request_content_lua':
                    $action->type = 'tcp-request';
                    $action->tcp_request_action = 'content_lua';
                    if (!empty((string)$action->tcp_request_content_lua)) {
                        $action->tcp_request_option = (string)$action->tcp_request_content_lua;
                        $action->tcp_request_content_lua = null;
                    }
                    break;
                case 'tcp-request_content_reject':
                    $action->type = 'tcp-request';
                    $action->tcp_request_action = 'content_reject';
                    break;
                case 'tcp-request_content_use-service':
                    $action->type = 'tcp-request';
                    $action->tcp_request_action = 'content_use-service';
                    if (!empty((string)$action->tcp_request_content_use_service)) {
                        $action->tcp_request_option = (string)$action->tcp_request_content_use_service;
                        $action->tcp_request_content_use_service = null;
                    }
                    break;
                case 'tcp-request_inspect-delay':
                    $action->type = 'tcp-request';
                    $action->tcp_request_action = 'inspect-delay';
                    if (!empty((string)$action->tcp_request_inspect_delay)) {
                        $action->tcp_request_option = (string)$action->tcp_request_inspect_delay;
                        $action->tcp_request_inspect_delay = null;
                    }
                    break;
                case 'tcp-response_content_accept':
                    $action->type = 'tcp-response';
                    $action->tcp_response_action = 'content_accept';
                    break;
                case 'tcp-response_content_close':
                    $action->type = 'tcp-response';
                    $action->tcp_response_action = 'content_close';
                    break;
                case 'tcp-response_content_lua':
                    $action->type = 'tcp-response';
                    $action->tcp_response_action = 'content_lua';
                    if (!empty((string)$action->tcp_response_content_lua)) {
                        $action->tcp_response_option = (string)$action->tcp_response_content_lua;
                        $action->tcp_response_content_lua = null;
                    }
                    break;
                case 'tcp-response_content_reject':
                    $action->type = 'tcp-response';
                    $action->tcp_response_action = 'content_reject';
                    break;
                case 'tcp-response_inspect-delay':
                    $action->type = 'tcp-response';
                    $action->tcp_response_action = 'inspect-delay';
                    if (!empty((string)$action->tcp_response_inspect_delay)) {
                        $action->tcp_response_option = (string)$action->tcp_response_inspect_delay;
                        $action->tcp_response_inspect_delay = null;
                    }
                    break;
            }
        }
    }
}
