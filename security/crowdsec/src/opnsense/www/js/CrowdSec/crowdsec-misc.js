/* global moment, $, ajaxCall */
/* exported CrowdSec */
/* eslint no-undef: "error" */
/* eslint semi: "error" */

const CrowdSec = (function () {
  'use strict';

  function _humanizeDate(text) {
    return moment(text).fromNow();
  }

  const formatters = {
    yesno: function(column, row) {
      const val = row[column.id];
      if (val) {
        return '<i class="fa fa-check text-success"></i>';
      } else {
        return '<i class="fa fa-times text-danger"></i>';
      }
    },

    datetime: function (column, row) {
      const val = row[column.id];
      const parsed = moment(val);
      if (!val) {
        return '';
      }
      if (!parsed.isValid()) {
        console.error('Cannot parse timestamp: %s', val);
        return '???';
      }
      return $('<div>')
        .attr({
          'data-toggle': 'tooltip',
          'data-placement': 'left',
          title: parsed.format(),
        })
        .text(_humanizeDate(val))
        .prop('outerHTML');
    },
  };

  // show the result of the bouncer -> LAPI connection test in $target
  function testConnection($target) {
    $target.html('<i class="fa fa-spinner fa-pulse"></i>');
    ajaxCall('/api/crowdsec/status/test', {}, function (data, status) {
      const ok = status === 'success' && data.result === 'ok';
      const message = (data && data.message) || status;
      $target.empty().append(
        $('<span>')
          .addClass(ok ? 'text-success' : 'text-danger')
          .append($('<i class="fa fa-fw">').addClass(ok ? 'fa-check' : 'fa-times'))
          .append(document.createTextNode(' ' + message))
      );
    });
  }

  // bootgrid responseHandler: show the message of a failed search in $alert
  function messageHandler($alert) {
    return function (response) {
      if (response && response.message && !response.rows) {
        $alert.text(response.message).removeClass('hidden');
        return {rows: [], rowCount: 0, total: 0, current: 1};
      }
      $alert.addClass('hidden');
      return response;
    };
  }

  return {
    formatters: formatters,
    testConnection: testConnection,
    messageHandler: messageHandler,
  };
})();
