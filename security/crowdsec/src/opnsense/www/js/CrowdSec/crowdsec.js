/*global moment, $ */
/*exported CrowdSec */
/*eslint no-undef: "error"*/
/*eslint semi: "error"*/

var CrowdSec = (function() {
    'use strict';

    var _refresh_template = '<button class="btn btn-default" type="button" title="Refresh"><span class="icon glyphicon glyphicon-refresh"></span></button>';

    var _dataFormatters = {
        yesno: function(column, row) {
            return _yesno2html(row[column.id]);
        },

        delete: function(column, row) {
            var val = row.id;
            if (isNaN(val)) {
                return '';
            }
            return '<button type="button" class="btn btn-secondary btn-sm" value="'+val+'" onclick="CrowdSec.deleteDecision('+val+')"><i class="fa fa-trash" /></button>';
        },

        duration: function(column, row) {
            var duration = row[column.id];
            if (!duration) {
                return 'n/a';
            }
            return $('<div>').attr({
                'data-toggle': 'tooltip',
                'data-placement': 'left',
                'title': duration
            }).text(_humanizeDuration(duration)).prop('outerHTML');
        },

        datetime: function(column, row) {
            var dt = row[column.id];
            var parsed = moment(dt);
            if (!dt) {
                return '';
            }
            if (!parsed.isValid()) {
                console.error("Cannot parse timestamp: %s", dt);
                return '???';
            }
            return $('<div>').attr({
                'data-toggle': 'tooltip',
                'data-placement': 'left',
                'title': parsed.format()
            }).text(_humanizeDate(dt)).prop('outerHTML');
        },
    };

    function _parseDuration(duration) {
        var re = /(-?)(?:(?:(\d+)h)?(\d+)m)?(\d+).\d+(m?)s/m;
        var matches = duration.match(re);
        var seconds = 0;

        if (!matches.length) {
            throw new Error("Unable to parse the following duration: " + duration + ".");
        }
        if (typeof matches[2] !== "undefined") {
            seconds += parseInt(matches[2], 10) * 3600; // hours
        }
        if (typeof matches[3] !== "undefined") {
            seconds += parseInt(matches[3], 10) * 60; // minutes
        }
        if (typeof matches[4] !== "undefined") {
            seconds += parseInt(matches[4], 10); // seconds
        }
        if ("m" === parseInt(matches[5], 10)) {
            // units in milliseconds
            seconds *= 0.001;
        }
        if ("-" === parseInt(matches[1], 10)) {
            // negative
            seconds = -seconds;
        }
        return seconds;
    }

    function _updateFreshness(selector, timestamp) {
        var $freshness = $(selector).find('.actionBar .freshness');
        if (timestamp) {
            $freshness.data('refresh_timestamp', timestamp);
        } else {
            timestamp = $freshness.data('refresh_timestamp');
        }
        var howlong_human = '???';
        if (timestamp) {
            var howlong_ms = moment() - moment(timestamp);
            howlong_human = moment.duration(howlong_ms).humanize();
        }
        $freshness.text(howlong_human + ' ago');
    }

    function _addFreshness(selector) {
        // this creates one timer per tab
        var freshness_template = '<span style="float:left">Last refresh: <span class="freshness"></span></span>';
        $(selector).find('.actionBar').prepend(freshness_template);
        setInterval(function() {
           _updateFreshness(selector);
        }, 5000);
    }

    function _humanizeDate(text) {
        return moment(text).fromNow();
    }

    function _humanizeDuration(text) {
        return moment.duration(_parseDuration(text), 'seconds').humanize();
    }

    function _yesno2html(val) {
        if (val) {
            return '<i class="fa fa-check text-success"></i>';
        } else {
        return '<i class="fa fa-times text-danger"></i>';
        }
    }

    function _decisionsByType(decisions) {
        var dectypes = {};
        if (!decisions) {
            return '';
        }
        decisions.map(function(decision) {
            // TODO ignore negative expiration?
            dectypes[decision.type] = dectypes[decision.type] ? (dectypes[decision.type]+1) : 1;
        });
        var ret = '';
        for (var type in dectypes) {
            if (ret !== '') {
                ret += ' ';
            }
            ret += (type + ':' + dectypes[type]);
        }
        return ret;
    }

    function _initService() {
        $.ajax({
            url: '/api/crowdsec/service/status',
            cache: false
        }).done(function(data) {
            // TODO handle errors
            var crowdsec_status = data['crowdsec-status'];
            if (crowdsec_status === 'unknown') {
                crowdsec_status = '<span class="text-danger">Unknown</span>';
            } else {
                crowdsec_status = _yesno2html(crowdsec_status === 'running');
            }
            $('#crowdsec-status').html(crowdsec_status);

            var crowdsec_firewall_status = data['crowdsec-firewall-status'];
            if (crowdsec_firewall_status === 'unknown') {
                crowdsec_firewall_status = '<span class="text-danger">Unknown</span>';
            } else {
                crowdsec_firewall_status = _yesno2html(crowdsec_firewall_status === 'running');
            }
            $('#crowdsec-firewall-status').html(crowdsec_firewall_status);
        });
    }

    function _initDebug() {
        $.ajax({
            url: '/api/crowdsec/service/debug',
            cache: false
        }).done(function(data) {
            $('#debug pre').text(data.message);
        });
    }

    function _initTab(selector, url, dataCallback) {
        var $tab = $(selector);
        if ($tab.find('table.bootgrid-table').length) {
            return;
        }
        $tab.find('table').
            on("initialized.rs.jquery.bootgrid", function() {
                $(_refresh_template).on('click', function() {
                    _refreshTab(selector, url, dataCallback);
                }).insertBefore($tab.find('.actionBar .actions .dropdown:first'));
                _addFreshness(selector);
                _refreshTab(selector, url, dataCallback);
            }).
            bootgrid({
                caseSensitive: false,
                formatters: _dataFormatters
            });
    }

    function _refreshTab(selector, url, dataCallback) {
        $.ajax({
            url: url,
            cache: false
        }).done(dataCallback);
        _updateFreshness(selector, moment());
    }

    function _initMachines() {
        var url = '/api/crowdsec/machines/get';
        var dataCallback = function(data) {
            var rows = [];
            data.map(function(row) {
                rows.push({
                    name:        row.machineId,
                    ip_address:  row.ipAddress || ' ',
                    last_update: row.updated_at || ' ',
                    validated:   row.isValidated,
                    version:     row.version || ' '
                });
            });
        $('#machines table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab('#machines', url, dataCallback);
    }

    function _initCollections() {
        var url = '/api/crowdsec/collections/get';
        var dataCallback = function(data) {
            var rows = [];
            data.collections.map(function(row) {
                rows.push({
                    name:          row.name,
                    status:        row.status,
                    local_version: row.local_version || ' ',
                    local_path:    row.local_path || ' '
                });
            });
        $('#collections table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab('#collections', url, dataCallback);
    }

    function _initScenarios() {
        var url = '/api/crowdsec/scenarios/get';
        var dataCallback = function(data) {
            var rows = [];
            data.scenarios.map(function(row) {
                rows.push({
                    name:          row.name,
                    status:        row.status,
                    local_version: row.local_version || ' ',
                    local_path:    row.local_path || ' ',
                    description:   row.description || ' '
                });
            });
            $('#scenarios table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab('#scenarios', url, dataCallback);
    }

    function _initParsers() {
        var url = '/api/crowdsec/parsers/get';
        var dataCallback = function(data) {
            var rows = [];
            data.parsers.map(function(row) {
                rows.push({
                    name:          row.name,
                    status:        row.status,
                    local_version: row.local_version || ' ',
                    local_path:    row.local_path || ' ',
                    description:   row.description || ' '
                });
            });
            $('#parsers table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab('#parsers ', url, dataCallback);
    }

    function _initPostoverflows() {
        var url = '/api/crowdsec/postoverflows/get';
        var dataCallback = function(data) {
            var rows = [];
            data.postoverflows.map(function(row) {
                rows.push({
                    name:          row.name,
                    status:        row.status,
                    local_version: row.local_version || ' ',
                    local_path:    row.local_path || ' ',
                    description:   row.description || ' '
                });
            });
            $('#postoverflows table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab('#postoverflows ', url, dataCallback);
    }

    function _initBouncers() {
        var url = '/api/crowdsec/bouncers/get';
        var dataCallback = function(data) {
            var rows = [];
            data.map(function(row) {
                // TODO - remove || ' ' later, it was fixed for 1.3.3
                rows.push({
                    name:       row.name,
                    ip_address: row.ip_address || ' ',
                    valid:      row.revoked ? false : true,
                    last_pull:  row.last_pull,
                    type:       row.type || ' ',
                    version:    row.version || ' '
                });
            });
            $('#bouncers table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab('#bouncers ', url, dataCallback);
    }

    function _initAlerts() {
        var url = '/api/crowdsec/alerts/get';
        var dataCallback = function(data) {
            var rows = [];
            data.map(function(row) {
                rows.push({
                    id:         row.id,
                    value:      row.source.scope + (row.source.value?(':'+row.source.value):''),
                    reason:     row.scenario || ' ',
                    country:    row.source.cn || ' ',
                    as:         row.source.as_name || ' ',
                    decisions:  _decisionsByType(row.decisions) || ' ',
                    created_at: row.created_at
                });
            });
            $('#alerts table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab('#alerts ', url, dataCallback);
    }

    function _initDecisions() {
        var url = '/api/crowdsec/decisions/get';
        var dataCallback = function(data) {
            var rows = [];
            data.map(function(row) {
                row.decisions.map(function(decision) {
                    // ignore deleted decisions
                    if (decision.duration.startsWith('-')) {
                        return;
                    }
                    rows.push({
                        // search will break on empty values when using .append(). so we use spaces
                        delete:       '',
                        id:           decision.id,
                        source:       decision.origin || ' ',
                        scope_value:  decision.scope + (decision.value?(':'+decision.value):''),
                        reason:       decision.scenario || ' ',
                        action:       decision.type || ' ',
                        country:      row.source.cn || ' ',
                        as:           row.source.as_name || ' ',
                        events_count: row.events_count,
                        // XXX pre-parse duration to seconds, and integer type, for sorting
                        expiration:   decision.duration || ' ',
                        alert_id:     row.id || ' '
                    });
                });
            });
            $('#decisions table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab('#decisions ', url, dataCallback);
    }

    function deleteDecision(decisionId) {
        var $modal = $('#delete-decision-modal');
        $modal.find('.modal-title').text('Delete decision #' + decisionId);
        $modal.find('.modal-body').text('Are you sure?');
        $modal.find('#delete-decision-confirm').on('click', function() {
            $.ajax({
                // XXX handle errors
                url: '/api/crowdsec/decisions/delete/' + decisionId,
                type: 'DELETE',
                success: function(result) {
                    if (result && result.message === 'OK') {
                        $('#decisions table').bootgrid('remove', [decisionId]);
                        $modal.modal('hide');
                    }
                }
            });
        });
        $modal.modal('show');
    }

    function init() {
        _initService();

        $('#machines_tab').on('click', _initMachines);
        $('#collections_tab').on('click', _initCollections);
        $('#scenarios_tab').on('click', _initScenarios);
        $('#parsers_tab').on('click', _initParsers);
        $('#postoverflows_tab').on('click', _initPostoverflows);
        $('#bouncers_tab').on('click', _initBouncers);
        $('#alerts_tab').on('click', _initAlerts);
        $('#decisions_tab').on('click', _initDecisions);

        $('[data-toggle="tooltip"]').tooltip();

        if (window.location.hash) {
            // activate a tab from the hash, if it exists
            $(window.location.hash+'_tab').click();
        } else {
            // otherwise, machines
            $('#machines_tab').click();
        }

        $(window).on('hashchange', function(e) {
            $(window.location.hash+'_tab').click();
        });

        if (new URLSearchParams(window.location.search).has('debug')) {
            $('#debug_tab').show().on('click', _initDebug);
        }

        // navigation
        if(window.location.hash != "") {
            $('a[href="' + window.location.hash + '"]').click()
        }
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });
    }

    return {
        deleteDecision: deleteDecision,
        init: init
    };

}());
