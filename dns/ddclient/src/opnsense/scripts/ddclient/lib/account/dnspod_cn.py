"""
    Copyright (c) 2024 AnShen <root@lshell.com>
    Copyright (c) 2023 Ad Schellevis <ad@opnsense.org>
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
"""
import json
import syslog
import time
import hashlib
import hmac
import requests
from datetime import datetime
from . import BaseAccount


# dnspod api 3.0
# https://cloud.tencent.com/document/api/1427

class DNSPod_CN(BaseAccount):
    _priority = 65535

    _services = {
        'dnspodcn': 'dnspod.tencentcloudapi.com'
    }

    def __init__(self, account: dict):
        super().__init__(account)
        self.service = 'dnspod'

    @staticmethod
    def known_services():
        return  {'dnspodcn': 'dnspodcn'}

    @staticmethod
    def match(account):
        return account.get('service') in DNSPod_CN._services


    @staticmethod
    def _sign(key, msg):
        """
        Generate HMAC-SHA256 signature.
        
        Args:
            key (bytes): Signing key
            msg (str): Message to sign
        
        Returns:
            bytes: Signature digest
        """
        return hmac.new(key, msg.encode("utf-8"), hashlib.sha256).digest()

    def generate_signature(self, action, payload="{}"):
        """
        Generate signature and headers for a Tencent Cloud API request.
        
        Args:
            action (str): API action name
            payload (str or dict, optional): Request payload. Defaults to "{}".
        
        Returns:
            tuple: Request headers and canonical request
        """
        # Ensure payload is a string
        payload = json.dumps(payload) if isinstance(payload, dict) else payload
        
        # Get current timestamp
        timestamp = int(time.time())
        date = datetime.utcfromtimestamp(timestamp).strftime("%Y-%m-%d")
        
        # Step 1: Create Canonical Request
        http_request_method = "POST"
        canonical_uri = "/"
        canonical_querystring = ""
        ct = "application/json; charset=utf-8"
        canonical_headers = f"content-type:{ct}\nhost:{self._services[self.settings.get('service')]}\nx-tc-action:{action.lower()}\n"
        signed_headers = "content-type;host;x-tc-action"
        hashed_request_payload = hashlib.sha256(payload.encode("utf-8")).hexdigest()
        
        canonical_request = (
            f"{http_request_method}\n"
            f"{canonical_uri}\n"
            f"{canonical_querystring}\n"
            f"{canonical_headers}\n"
            f"{signed_headers}\n"
            f"{hashed_request_payload}"
        )
        
        # Step 2: Create String to Sign
        algorithm = "TC3-HMAC-SHA256"
        credential_scope = f"{date}/{self.service}/tc3_request"
        hashed_canonical_request = hashlib.sha256(canonical_request.encode("utf-8")).hexdigest()
        
        string_to_sign = (
            f"{algorithm}\n"
            f"{timestamp}\n"
            f"{credential_scope}\n"
            f"{hashed_canonical_request}"
        )
        
        # Step 3: Calculate Signature
        secret_date = self._sign(("TC3" + self.settings.get('password')).encode("utf-8"), date)
        secret_service = self._sign(secret_date, self.service)
        secret_signing = self._sign(secret_service, "tc3_request")
        signature = hmac.new(secret_signing, string_to_sign.encode("utf-8"), hashlib.sha256).hexdigest()
        
        # Step 4: Create Authorization Header
        authorization = (
            f"{algorithm} "
            f"Credential={self.settings.get('username', '')}/{credential_scope}, "
            f"SignedHeaders={signed_headers}, "
            f"Signature={signature}"
        )
        
        # Prepare headers
        headers = {
            "Authorization": authorization,
            "Content-Type": "application/json; charset=utf-8",
            "Host": self._services[self.settings.get('service')],
            "X-TC-Action": action,
            "X-TC-Timestamp": str(timestamp),
            "X-TC-Version": "2021-03-23",
            'User-Agent': 'OPNsense-dyndns',
        }
        
        return headers, payload

    def send_request(self, action, payload="{}", region="", token=""):
        """
        Send a request to the Tencent Cloud API.
        
        Args:
            action (str): API action name
            payload (str or dict, optional): Request payload. Defaults to "{}".
            region (str, optional): Optional region parameter
            token (str, optional): Optional token parameter
        
        Returns:
            dict: API response JSON
        """
        # Get headers and prepared payload
        headers, payload = self.generate_signature(action, payload)
        
        # Add optional headers
        if region:
            headers["X-TC-Region"] = region
        if token:
            headers["X-TC-Token"] = token
        
        try:
            # Send request using requests library
            response = requests.post(
                url=f"https://{self._services[self.settings.get('service')]}", 
                headers=headers, 
                data=payload,
                timeout=10
            )
            
            # Raise an exception for bad responses
            response.raise_for_status()
            
            # Return JSON response
            return response
        
        except requests.RequestException as err:
            print(f"Request error: {err}")
            
            # If there's a response, print its content for debugging
            if hasattr(err, 'response') and err.response is not None:
                print(f"Response content: {err.response.text}")
            
            return None


    def execute(self):
        if super().execute():
            # IPv4/IPv6
            recordType = "AAAA" if str(self.current_address).find(':') > 1 else "A"

            subdomains = []
            hostnames = self.settings.get('hostnames').split(',')
            for _subdomain in hostnames:
                if _subdomain == self.settings.get('zone') or _subdomain == '@':
                    subdomains.append('@')
                else:
                    subdomains.append(_subdomain.replace(f".{self.settings.get('zone')}", ''))
            
            if len(subdomains) < 1:
                syslog.syslog(
                        syslog.LOG_ERR,
                        "Account %s hostnames format error" % self.description
                    )
                return False

            # Get record ID
            response = self.send_request(
                action='DescribeRecordList',
                payload={
                    'RecordType': recordType,
                    'Domain': self.settings.get('zone')
                }
            )
            try:
                payload = response.json()
            except requests.exceptions.JSONDecodeError:
                payload = {}
            if 'Response' in payload and 'Error' in payload['Response']:
                syslog.syslog(
                        syslog.LOG_ERR,
                        "Account %s error parsing JSON response [ZoneID] %s" % (self.description, payload['Response']['Error']['Code'])
                    )
                return False
            if not payload['Response'].get('RecordList', False):
                syslog.syslog(
                    syslog.LOG_ERR,
                    "Account %s error receiving ZoneID [%s]" % (self.description, response.text)
                )
                return False

            record_id_list = [x['RecordId'] for x in payload['Response']['RecordList'] if x['Name'] in subdomains]
            if len(record_id_list) < 1:
                syslog.syslog(
                    syslog.LOG_ERR,
                    "Account %s error Not Found Record [%s]" % (self.description, self.settings.get('hostnames'))
                )
                return False

            if self.is_verbose:
                syslog.syslog(
                    syslog.LOG_NOTICE,
                    "Account %s ZoneID for %s %s" % (self.description, self.settings.get('zone'), record_id_list)
                )

            # Send IP address update
            response = self.send_request(
                action='ModifyRecordBatch',
                payload={
                    'RecordIdList': record_id_list,
                    'Change': 'value',
                    'ChangeTo': str(self.current_address),
                }
            )
            try:
                payload = response.json()
            except requests.exceptions.JSONDecodeError:
                payload = {}
            if 'Response' in payload and 'Error' in payload['Response']:
                syslog.syslog(
                        syslog.LOG_ERR,
                        "Account %s error parsing JSON response [UpdateIP] %s" % (self.description, payload['Response']['Error']['Code'])
                    )
                return False
            if len(payload['Response']['DetailList']) < 1:
                syslog.syslog(
                    syslog.LOG_ERR,
                    "Account %s failed to set new ip %s [%s]" % (self.description, self.current_address, response.text)
                )
                return False
            
            record_list = payload['Response']['DetailList'][0].get('RecordList', False)
            if record_list and len(record_list) == len(subdomains):
                syslog.syslog(
                    syslog.LOG_NOTICE,
                    "Account %s set new ip %s %s" % (
                        self.description,
                        self.current_address,
                        subdomains
                    )
                )

                self.update_state(address=self.current_address)
                return True

            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s failed to set new ip %s %s" % (self.description, self.current_address, subdomains)
            )


        return False
