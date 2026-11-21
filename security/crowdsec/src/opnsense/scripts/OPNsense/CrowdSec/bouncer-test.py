#!/usr/bin/env python3

# SPDX-License-Identifier: MIT
# SPDX-FileCopyrightText: © 2026 Michael Pihlblad

# Check that the firewall bouncer (remediation component) can reach its LAPI
# with its API key, as seen from this firewall: works when it connects to a
# local or a remote LAPI.

import json
import ssl
import urllib.error
import urllib.request
from typing import Any
import yaml

BOUNCER_CONFIG = '/usr/local/etc/crowdsec/bouncers/crowdsec-firewall-bouncer.yaml'
TEST_TIMEOUT = 5


def load_config() -> dict[str, Any]:
    with open(BOUNCER_CONFIG) as fin:
        return yaml.safe_load(fin) or {}


def test(config: dict[str, Any]) -> dict[str, Any]:
    url = config.get('api_url', '')
    if not url:
        return {'result': 'error', 'message': 'no LAPI url configured'}

    # HEAD only checks the API key. A GET would update the "last pull" time of
    # the bouncer on the LAPI, which the bouncer's own stream relies on.
    request = urllib.request.Request(url.rstrip('/') + '/v1/decisions', method='HEAD',
                                     headers={'X-Api-Key': str(config.get('api_key', ''))})

    context = ssl.create_default_context(cafile=config.get('ca_cert_path') or None)
    if config.get('insecure_skip_verify'):
        context.check_hostname = False
        context.verify_mode = ssl.CERT_NONE

    try:
        with urllib.request.urlopen(request, timeout=TEST_TIMEOUT, context=context) as response:
            code = response.status
    except urllib.error.HTTPError as e:
        code = e.code
    except (urllib.error.URLError, OSError, ValueError) as e:
        reason = getattr(e, 'reason', e)
        return {'result': 'unreachable', 'message': str(reason)}

    if code == 200:
        return {'result': 'ok', 'message': 'connected, API key accepted', 'http_code': code}
    if code == 403:
        return {'result': 'invalid_key', 'message': 'the API key was rejected', 'http_code': code}
    return {'result': 'error', 'message': 'unexpected HTTP status {}'.format(code), 'http_code': code}


def main():
    try:
        config = load_config()
    except (OSError, yaml.YAMLError) as e:
        print(json.dumps({'result': 'error', 'message': 'cannot read {}: {}'.format(BOUNCER_CONFIG, e)}))
        return

    print(json.dumps(test(config)))


if __name__ == '__main__':
    main()
