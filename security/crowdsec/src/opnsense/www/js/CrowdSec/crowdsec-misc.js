/* global moment, $ */
/* exported CrowdSec */
/* eslint no-undef: "error" */
/* eslint semi: "error" */

const CrowdSec = (function () {
  'use strict';

  const config_dir_path = "/usr/local/etc/crowdsec/";

  function _humanizeDate(text) {
    return moment(text).fromNow();
  }

  const formatters = {
    yesno: function(val) {
      if (val) {
        return '<i class="fa fa-check text-success"></i>';
      } else {
        return '<i class="fa fa-times text-danger"></i>';
      }
    },

    trimpath: function (val) {
      return val ? val.replace(config_dir_path, '') : '';
    },

    datetime: function (val) {
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
