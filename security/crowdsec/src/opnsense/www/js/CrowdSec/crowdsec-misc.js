/* global moment, $ */
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

  return {
    formatters: formatters,
  };
})();
