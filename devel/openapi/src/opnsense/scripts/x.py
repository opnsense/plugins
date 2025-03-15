#! /usr/bin/env python3

import json
import os
import re
import sys
from typing import Dict, List, Any

from collect_xml_models import parse_model

xml_file = "/gitroot/upstream/opnsense/plugins/dns/bind/src/opnsense/mvc/app/models/OPNsense/Bind/Dnsbl.xml"
parse_model(xml_file)
xml_file = "/gitroot/upstream/opnsense/plugins/dns/bind/src/opnsense/mvc/app/models/OPNsense/Bind/Domain.xml"
parse_model(xml_file)
