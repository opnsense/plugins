#! /usr/bin/env python3

import json
import os
import re
import sys
from typing import Dict, List, Any

from collect_xml_models import * #parse_model

special_snowflakes = [
    "/gitroot/upstream/opnsense/plugins/dns/bind/src/opnsense/mvc/app/models/OPNsense/Bind/Dnsbl.xml",
    "/gitroot/upstream/opnsense/plugins/dns/bind/src/opnsense/mvc/app/models/OPNsense/Bind/Domain.xml",
    "/gitroot/upstream/opnsense/plugins/security/tor/src/opnsense/mvc/app/models/OPNsense/Tor/General.xml",
]

# for model_filename in special_snowflakes:
#     print(model_filename)
#     parse_model(model_filename)
#     print("")


if __name__ == "__main__":
    if len(sys.argv) < 2:
        exit(1)
    model_filename = sys.argv[1]
    print(model_filename)
    parse_model(model_filename)
