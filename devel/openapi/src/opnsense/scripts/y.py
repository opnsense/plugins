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

for model_filename in special_snowflakes:
    print(model_filename)
    parse_model(model_filename)
    print("")


# import json
# import os
# from collections import defaultdict
# from typing import Any, Dict, List
# from xml.etree import ElementTree
# from xml.etree.ElementTree import Element
# import collect_xml_models
# from collect_xml_models import *

# model_filename = "/gitroot/upstream/opnsense/plugins/dns/bind/src/opnsense/mvc/app/models/OPNsense/Bind/Dnsbl.xml"
# model_filename = "/gitroot/upstream/opnsense/plugins/dns/bind/src/opnsense/mvc/app/models/OPNsense/Bind/Domain.xml"


# tree = ElementTree.parse(model_filename)
# items_element: Element = tree.find("items")
# find_model_elements(items_element)
