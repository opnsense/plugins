#! /usr/bin/env python3

import json
import os
import re
import sys
from typing import Dict, List, Any

model_files = []
for root, dirs, files in os.walk(os.path.dirname(__file__), topdown=True):
    path_segments = root.split("/")
    if path_segments[-3] != "models":
        continue
    xml_files = [os.path.join(root, f) for f in files if f.endswith(".xml")]
    model_files.extend(xml_files)

print(model_files)
