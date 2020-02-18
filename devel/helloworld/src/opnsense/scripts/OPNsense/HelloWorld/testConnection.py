#!/usr/local/bin/python3

"""
    Copyright (c) 2015-2019 Ad Schellevis <ad@opnsense.org>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
     this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
     notice, this list of conditions and the following disclaimer in the
     documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.

    --------------------------------------------------------------------------------------

    perform some tests for the helloworld application
"""
import os
import socket
import smtplib
import json
from configparser import ConfigParser

# set default timeout to 2 seconds
socket.setdefaulttimeout(2)

hello_world_config = '/usr/local/etc/helloworld/helloworld.conf'

result = {}
if os.path.exists(hello_world_config):
    cnf = ConfigParser()
    cnf.read(hello_world_config)
    if cnf.has_section('general'):
        try:
            smtpObj = smtplib.SMTP(cnf.get('general', 'SMTPHost'))
            msg_header = "From: " + cnf.get('general', 'FromEmail') + "\n" + \
                         "To: " + cnf.get('general', 'ToEmail') + "\n" + \
                         "Subject: " + cnf.get('general', 'Subject') + "\n" + \
                         "Test message!"

            smtpObj.sendmail(cnf.get('general', 'FromEmail'), [cnf.get('general', 'ToEmail')], msg_header)
            smtpObj.quit()
            result['message'] = 'test ok!'
        except smtplib.SMTPException as error:
            # unable to send mail
            result['message'] = '%s' % error
        except socket.error as error:
            # connect error
            if error.strerror is None:
                # probably hit timeout
                result['message'] = 'time out!'
            else:
                result['message'] = error.strerror
    else:
        # empty config
        result['message'] = 'empty configuration'
else:
    # no config
    result['message'] = 'no configuration file found'


print (json.dumps(result))
