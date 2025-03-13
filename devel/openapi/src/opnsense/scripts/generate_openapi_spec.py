#!/usr/local/bin/python3

import os
import socket
import smtplib
import json
from configparser import ConfigParser

# set default timeout to 2 seconds
socket.setdefaulttimeout(2)

result = {"enabled": True}

print (json.dumps(result))
