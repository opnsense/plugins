var fs = require('fs');
var tls_fingerprints = JSON.parse(fs.readFileSync('/usr/local/etc/nginx/tls_fingerprints.json'));
// ignore GREASE cipher suite values when compiling a browser fingerprint (see rfc8701)
const GREASE = ["0x0a0a", "0x1a1a", "0x2a2a", "0x3a3a", "0x4a4a", "0x5a5a", "0x6a6a", "0x7a7a", "0x8a8a", "0x9a9a", "0xaaaa", "0xbaba", "0xcaca", "0xdada", "0xeaea", "0xfafa"];
// ignore SCSV cipher suite values when compiling a browser fingerprint (see rfc5746 and rfc7507)
const SCSV = ["TLS_EMPTY_RENEGOTIATION_INFO_SCSV", "TLS_FALLBACK_SCSV"];

function check_cipher_array(r, browser_ciphers, fingerprint_ciphers, result) {
  if (result.status.includes('Intercepted')) {
    return;
  }
  if (browser_ciphers.length > fingerprint_ciphers.length) {
    // the proxy supports more ciphers than the browser -> intercepted
    result.status = "Intercepted; Reason=\"excess suite\"";
    return;
  }
  var browser_cipher;
  var browser_cipher_index;
  var last_index = -1;
  var current_index;
  for (browser_cipher_index in browser_ciphers) {
    browser_cipher = browser_ciphers[browser_cipher_index];
    current_index = fingerprint_ciphers.indexOf(browser_cipher);
    if (current_index === -1 || current_index <= last_index) {
      // a cipher has been found, which is not supported by the browser or order of preference changed
      // such a connection is definitly intercepted
      result.status = "Intercepted; Reason=\"excess suite or wrong order\"";
      return;
    }
    last_index = current_index;
  }
  if (result.status == 'Unknown') {
    result.status = browser_ciphers.length === fingerprint_ciphers.length ? 'Original' : 'Hardened'
  }
}

function check_intercept(r) {
    var tls_result = {'status': 'Unknown'};
    if (r.headersIn['User-Agent'] && r.variables.ssl_ciphers != '') {
      var ua = r.headersIn['User-Agent'];
      if (ua in tls_fingerprints) {
        var fp = tls_fingerprints[ua];
        fp.ciphers = fp.ciphers.filter( function( el ) {
          return ((GREASE.indexOf( el ) < 0) && (SCSV.indexOf( el ) < 0));
        } );
        fp.curves = fp.curves.filter( function( el ) {
          return GREASE.indexOf( el ) < 0;
        } );
        var browser_ciphers = r.variables.ssl_ciphers.split(':');
        browser_ciphers = browser_ciphers.filter( function( el ) {
          return ((GREASE.indexOf( el ) < 0) && (SCSV.indexOf( el ) < 0));
        } );
        check_cipher_array(r, browser_ciphers, fp.ciphers, tls_result);
        if (r.variables.ssl_curves != '')
        {
          var browser_curves = r.variables.ssl_curves.split(':');
          browser_curves = browser_curves.filter( function( el ) {
            return GREASE.indexOf( el ) < 0;
          } );
          check_cipher_array(r, browser_curves, fp.curves, tls_result);
        }
      }
    }
    return tls_result.status;
}

export default { check_intercept };
