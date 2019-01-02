var fs = require('fs');
var tls_fingerprints = JSON.parse(fs.readFileSync('/usr/local/etc/nginx/tls_fingerprints.json'));

function customArrayIndexOf(haystack, needle) {
  var element, element_id;
  for (element_id in haystack) {
    element = haystack[element_id];
    if (element == needle) return element_id;
  }
  return -1;
}

function check_cipher_array(r, browser_ciphers, fingerprint_ciphers, result) {
  if (result.status == 'Intercepted') {
    return;
  }
  if (browser_ciphers.length > fingerprint_ciphers.length) {
    // the proxy supports more cipers than the browser -> intercepted
    result.status = "Intercepted";
    return;
  }
  var browser_cipher;
  var browser_cipher_index;
  var last_index = -1;
  var current_index;
  for (browser_cipher_index in browser_ciphers) {
    browser_cipher = browser_ciphers[browser_cipher_index];
    current_index = customArrayIndexOf(fingerprint_ciphers, browser_cipher);
    if (current_index === -1 || current_index <= last_index) {
      // a cipher has been found, which is not supported by the browser
      // such a connection is definitly intercepted
      result.status = "Intercepted";
      //result.status = JSON.stringify(fingerprint_ciphers[0].toBytes().toString('hex'));
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
        var browser_ciphers = r.variables.ssl_ciphers.split(':');
        check_cipher_array(r, browser_ciphers, fp.ciphers, tls_result);
        if (r.variables.ssl_curves != '')
        {
          var browser_curves = r.variables.ssl_curves.split(':');
          check_cipher_array(r, browser_curves, fp.curves, tls_result);
        }
      }
    }
    return tls_result.status;
}
