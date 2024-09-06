/* global moment, $ */
/* exported CrowdSec */
/* eslint no-undef: "error" */
/* eslint semi: "error" */

const CrowdSec = (function () {
  'use strict';

  const crowdsec_path = '/usr/local/etc/crowdsec/';
  const _refreshTemplate =
    '<button class="btn btn-default" type="button" title="Refresh"><span class="icon glyphicon glyphicon-refresh"></span></button>';

  const _dataFormatters = {
    yesno: function (column, row) {
      return _yesno2html(row[column.id]);
    },

    delete: function (column, row) {
      const val = row.id;
      if (isNaN(val)) {
        return '';
      }
      return (
        '<button type="button" class="btn btn-secondary btn-sm" value="' +
        val +
        '" onclick="CrowdSec.deleteDecision(' +
        val +
        ')"><i class="fa fa-trash" /></button>'
      );
    },

    duration: function (column, row) {
      const duration = row[column.id];
      if (!duration) {
        return 'n/a';
      }
      return $('<div>')
        .attr({
          'data-toggle': 'tooltip',
          'data-placement': 'left',
          title: duration,
        })
        .text(_humanizeDuration(duration))
        .prop('outerHTML');
    },

    datetime: function (column, row) {
      const dt = row[column.id];
      const parsed = moment(dt);
      if (!dt) {
        return '';
      }
      if (!parsed.isValid()) {
        console.error('Cannot parse timestamp: %s', dt);
        return '???';
      }
      return $('<div>')
        .attr({
          'data-toggle': 'tooltip',
          'data-placement': 'left',
          title: parsed.format(),
        })
        .text(_humanizeDate(dt))
        .prop('outerHTML');
    },
  };
  function _decisionsByType(decisions) {
    const dectypes = {};
    if (!decisions) {
      return '';
    }
    decisions.map(function (decision) {
      // TODO ignore negative expiration?
      dectypes[decision.type] = dectypes[decision.type]
        ? dectypes[decision.type] + 1
        : 1;
    });
    let ret = '';
    for (const type in dectypes) {
      if (ret !== '') {
        ret += ' ';
      }
      ret += type + ':' + dectypes[type];
    }
    return ret;
  }

  function _updateFreshness(selector, timestamp) {
    const $freshness = $(selector).find('.actionBar .freshness');
    if (timestamp) {
      $freshness.data('refresh_timestamp', timestamp);
    } else {
      timestamp = $freshness.data('refresh_timestamp');
    }
    const howlongHuman = '???';
    if (timestamp) {
      const howlongms = moment() - moment(timestamp);
      howlongHuman = moment.duration(howlongms).humanize();
    }
    $freshness.text(howlongHuman + ' ago');
  }

  function _addFreshness(selector) {
    // this creates one timer per tab
    const freshnessTemplate =
      '<span style="float:left">Last refresh: <span class="freshness"></span></span>';
    $(selector).find('.actionBar').prepend(freshnessTemplate);
    setInterval(function () {
      _updateFreshness(selector);
    }, 5000);
  }

  function _refreshTab(selector, url, dataCallback) {
    $.ajax({
      url: url,
      cache: false,
    }).done(dataCallback);
    _updateFreshness(selector, moment());
  }

  function _parseDuration(duration) {
    const re = /(-?)(?:(?:(\d+)h)?(\d+)m)?(\d+).\d+(m?)s/m;
    const matches = duration.match(re);
    let seconds = 0;

    if (!matches.length) {
      throw new Error(
        'Unable to parse the following duration: ' + duration + '.',
      );
    }
    if (typeof matches[2] !== 'undefined') {
      seconds += parseInt(matches[2], 10) * 3600; // hours
    }
    if (typeof matches[3] !== 'undefined') {
      seconds += parseInt(matches[3], 10) * 60; // minutes
    }
    if (typeof matches[4] !== 'undefined') {
      seconds += parseInt(matches[4], 10); // seconds
    }
    if (parseInt(matches[5], 10) === 'm') {
      // units in milliseconds
      seconds *= 0.001;
    }
    if (parseInt(matches[1], 10) === '-') {
      // negative
      seconds = -seconds;
    }
    return seconds;
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

  function _initTab(selector, url, dataCallback) {
    const $tab = $(selector);
    if ($tab.find('table.bootgrid-table').length) {
      return;
    }
    $tab
      .find('table')
      .on('initialized.rs.jquery.bootgrid', function () {
        $(_refreshTemplate)
          .on('click', function () {
            _refreshTab(selector, url, dataCallback);
          })
          .insertBefore($tab.find('.actionBar .actions .dropdown:first'));
        _addFreshness(selector);
        _refreshTab(selector, url, dataCallback);
      })
      .bootgrid({
        caseSensitive: false,
        formatters: _dataFormatters,
      });
  }

  function _initStatusMachines() {
    const url = '/api/crowdsec/machines/get';
    const id = '#machines';
    const dataCallback = function (data) {
      const rows = [];
      data.map(function (row) {
        rows.push({
          name: row.machineId,
          ip_address: row.ipAddress || ' ',
          last_update: row.updated_at || ' ',
          validated: row.isValidated,
          version: row.version || ' ',
        });
      });
      $(id + ' table')
        .bootgrid('clear')
        .bootgrid('append', rows);
    };
    _initTab(id, url, dataCallback);
  }

  function _initStatusCollections() {
    const url = '/api/crowdsec/hub/get';
    const id = '#collections';
    const dataCallback = function (data) {
      const rows = [];
      data.collections.map(function (row) {
        rows.push({
          name: row.name,
          status: row.status,
          local_version: row.local_version || ' ',
          local_path: row.local_path
            ? row.local_path.replace(crowdsec_path, '')
            : ' ',
          description: row.description || ' ',
        });
      });
      $(id + ' table')
        .bootgrid('clear')
        .bootgrid('append', rows);
    };
    _initTab(id, url, dataCallback);
  }

  function _initStatusScenarios() {
    const url = '/api/crowdsec/hub/get';
    const id = '#scenarios';
    const dataCallback = function (data) {
      const rows = [];
      data.scenarios.map(function (row) {
        rows.push({
          name: row.name,
          status: row.status,
          local_version: row.local_version || ' ',
          local_path: row.local_path
            ? row.local_path.replace(crowdsec_path, '')
            : ' ',
          description: row.description || ' ',
        });
      });
      $(id + ' table')
        .bootgrid('clear')
        .bootgrid('append', rows);
    };
    _initTab(id, url, dataCallback);
  }

  function _initStatusParsers() {
    const url = '/api/crowdsec/hub/get';
    const id = '#parsers';
    const dataCallback = function (data) {
      const rows = [];
      data.parsers.map(function (row) {
        rows.push({
          name: row.name,
          status: row.status,
          local_version: row.local_version || ' ',
          local_path: row.local_path
            ? row.local_path.replace(crowdsec_path, '')
            : ' ',
          description: row.description || ' ',
        });
      });
      $(id + ' table')
        .bootgrid('clear')
        .bootgrid('append', rows);
    };
    _initTab(id, url, dataCallback);
  }

  function _initStatusPostoverflows() {
    const url = '/api/crowdsec/hub/get';
    const id = '#postoverflows';
    const dataCallback = function (data) {
      const rows = [];
      data.postoverflows.map(function (row) {
        rows.push({
          name: row.name,
          status: row.status,
          local_version: row.local_version || ' ',
          local_path: row.local_path
            ? row.local_path.replace(crowdsec_path, '')
            : ' ',
          description: row.description || ' ',
        });
      });
      $(id + ' table')
        .bootgrid('clear')
        .bootgrid('append', rows);
    };
    _initTab(id, url, dataCallback);
  }

  function _initStatusBouncers() {
    const url = '/api/crowdsec/bouncers/get';
    const id = '#bouncers';
    const dataCallback = function (data) {
      const rows = [];
      data.map(function (row) {
        // TODO - remove || ' ' later, it was fixed for 1.3.3
        rows.push({
          name: row.name,
          ip_address: row.ip_address || ' ',
          valid: row.revoked ? false : true,
          last_pull: row.last_pull,
          type: row.type || ' ',
          version: row.version || ' ',
        });
      });
      $(id + ' table')
        .bootgrid('clear')
        .bootgrid('append', rows);
    };
    _initTab(id, url, dataCallback);
  }

  function _initStatusAlerts() {
    const url = '/api/crowdsec/alerts/get';
    const id = '#alerts';
    const dataCallback = function (data) {
      const rows = [];
      data.map(function (row) {
        rows.push({
          id: row.id,
          value:
            row.source.scope + (row.source.value ? ':' + row.source.value : ''),
          reason: row.scenario || ' ',
          country: row.source.cn || ' ',
          as: row.source.as_name || ' ',
          decisions: _decisionsByType(row.decisions) || ' ',
          created_at: row.created_at,
        });
      });
      $(id + ' table')
        .bootgrid('clear')
        .bootgrid('append', rows);
    };
    _initTab(id, url, dataCallback);
  }

  function _initStatusDecisions() {
    const url = '/api/crowdsec/decisions/get';
    const id = '#decisions';
    const dataCallback = function (data) {
      const rows = [];
      data.map(function (row) {
        row.decisions.map(function (decision) {
          // ignore deleted decisions
          if (decision.duration.startsWith('-')) {
            return;
          }
          rows.push({
            // search will break on empty values when using .append(). so we use spaces
            delete: '',
            id: decision.id,
            source: decision.origin || ' ',
            scope_value:
              decision.scope + (decision.value ? ':' + decision.value : ''),
            reason: decision.scenario || ' ',
            action: decision.type || ' ',
            country: row.source.cn || ' ',
            as: row.source.as_name || ' ',
            events_count: row.events_count,
            // XXX pre-parse duration to seconds, and integer type, for sorting
            expiration: decision.duration || ' ',
            alert_id: row.id || ' ',
          });
        });
      });
      $(id + ' table')
        .bootgrid('clear')
        .bootgrid('append', rows);
    };
    _initTab(id, url, dataCallback);
  }

  function initService() {
    $.ajax({
      url: '/api/crowdsec/service/status',
      cache: false,
    }).done(function (data) {
      // TODO handle errors
      let crowdsecStatus = data['crowdsec-status'];
      if (crowdsecStatus === 'unknown') {
        crowdsecStatus = '<span class="text-danger">Unknown</span>';
      } else {
        crowdsecStatus = _yesno2html(crowdsecStatus === 'running');
      }
      $('#crowdsec-status').html(crowdsecStatus);

      let crowdsecFirewallStatus = data['crowdsec-firewall-status'];
      if (crowdsecFirewallStatus === 'unknown') {
        crowdsecFirewallStatus = '<span class="text-danger">Unknown</span>';
      } else {
        crowdsecFirewallStatus = _yesno2html(
          crowdsecFirewallStatus === 'running',
        );
      }
      $('#crowdsec-firewall-status').html(crowdsecFirewallStatus);
    });
  }

  function deleteDecision(decisionId) {
    const $modal = $('#remove-decision-modal');
    $modal.find('.modal-title').text('Delete decision #' + decisionId);
    $modal.find('.modal-body').text('Are you sure?');
    $modal.find('#remove-decision-confirm').on('click', function () {
      $.ajax({
        // XXX handle errors
        url: '/api/crowdsec/decisions/delete/' + decisionId,
        method: 'DELETE',
        success: function (result) {
          if (result && result.message === 'OK') {
            $('#decisions table').bootgrid('remove', [decisionId]);
            $modal.modal('hide');
          }
        },
      });
    });
    $modal.modal('show');
  }

  function init() {
    initService();

    $('#machines_tab').on('click', _initStatusMachines);
    $('#collections_tab').on('click', _initStatusCollections);
    $('#scenarios_tab').on('click', _initStatusScenarios);
    $('#parsers_tab').on('click', _initStatusParsers);
    $('#postoverflows_tab').on('click', _initStatusPostoverflows);
    $('#bouncers_tab').on('click', _initStatusBouncers);
    $('#alerts_tab').on('click', _initStatusAlerts);
    $('#decisions_tab').on('click', _initStatusDecisions);

    $('[data-toggle="tooltip"]').tooltip();

    if (window.location.hash) {
      // activate a tab from the hash, if it exists
      $(window.location.hash + '_tab').click();
    } else {
      // otherwise, machines
      $('#machines_tab').click();
    }

    $(window).on('hashchange', function (e) {
      $(window.location.hash + '_tab').click();
    });

    // navigation
    if (window.location.hash !== '') {
      $('a[href="' + window.location.hash + '"]').click();
    }
    $('.nav-tabs a').on('shown.bs.tab', function (e) {
      history.pushState(null, null, e.target.hash);
    });
  }

  return {
    deleteDecision: deleteDecision,
    init: init,
  };
})();
