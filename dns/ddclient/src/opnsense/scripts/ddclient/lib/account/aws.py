"""
    Copyright (c) 2023 Greg Glockner <greg@glockners.net>
    Copyright (c) 2026 Andrew Watkins <andreww@gmail.com>
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
    ----------------------------------------------------------------------------------------------------
    AWS Route53 DNS provider
    Usage:
      Route53 Hosted Zone ID: zone
      Authentication (aws_auth):
        access_key:     AWS access key: username, AWS secret key: password
        rolesanywhere:  certificate from the trust store, exchanged for temporary credentials of
                        aws_role_arn using IAM Roles Anywhere (aws_trust_anchor_arn, aws_profile_arn)
        iot:            certificate from the trust store, exchanged for temporary credentials of
                        the role behind aws_iot_role_alias using the AWS IoT credential provider
      Certificates are exported to /var/etc/ddclient by export_certificates.php on service start.
"""
import base64
import datetime
import hashlib
import json
import os
import syslog
import time
import boto3
import requests
from cryptography import x509
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import ec, padding, rsa
from . import BaseAccount


class AWS(BaseAccount):
    _services = ['aws']
    _certificate_dir = '/var/etc/ddclient'
    _session_duration = 3600
    _refresh_margin = 300
    _request_timeout = 15

    def __init__(self, account: dict):
        super().__init__(account)
        self._credentials = None

    @staticmethod
    def known_services():
        return  AWS._services

    @staticmethod
    def match(account):
        return account.get('service') in AWS._services

    @property
    def auth_method(self):
        return self.settings.get('aws_auth') or 'access_key'

    def _certificate_file(self, extension):
        return os.path.join(self._certificate_dir, '%s.%s' % (self.id, extension))

    @staticmethod
    def _der_base64(certificate):
        return base64.b64encode(certificate.public_bytes(serialization.Encoding.DER)).decode()

    @staticmethod
    def _temporary_credentials(credentials):
        """ map a credentials object (IoT or Roles Anywhere) to boto3 client arguments plus expiration
        """
        return {
            'aws_access_key_id': credentials['accessKeyId'],
            'aws_secret_access_key': credentials['secretAccessKey'],
            'aws_session_token': credentials['sessionToken'],
            'expiration': datetime.datetime.fromisoformat(credentials['expiration'].replace('Z', '+00:00')).timestamp()
        }

    def _iot_credentials(self):
        """ exchange the certificate for temporary credentials using the AWS IoT credential provider,
            https://docs.aws.amazon.com/iot/latest/developerguide/authorizing-direct-aws.html
        """
        headers = {'User-Agent': 'OPNsense-dyndns'}
        if self.settings.get('aws_iot_thing_name'):
            headers['x-amzn-iot-thingname'] = self.settings.get('aws_iot_thing_name')
        response = requests.get(
            'https://%s/role-aliases/%s/credentials' % (
                self.settings.get('aws_iot_endpoint'), self.settings.get('aws_iot_role_alias')
            ),
            headers=headers,
            cert=(self._certificate_file('crt'), self._certificate_file('key')),
            timeout=self._request_timeout
        )
        if response.status_code != 200:
            raise RuntimeError(
                'AWS IoT credential provider returned HTTP %d %s' % (response.status_code, response.text[:256])
            )
        return self._temporary_credentials(response.json()['credentials'])

    def _intermediate_certificates(self):
        """ intermediate CA certificates of the signing certificate, the self-signed root is the trust anchor
        """
        if not os.path.isfile(self._certificate_file('ca')):
            return []
        with open(self._certificate_file('ca'), 'rb') as f_in:
            chain = x509.load_pem_x509_certificates(f_in.read())
        return [self._der_base64(item) for item in chain if item.issuer != item.subject]

    def _rolesanywhere_credentials(self):
        """ exchange the certificate for temporary credentials using IAM Roles Anywhere CreateSession,
            signed as described in https://docs.aws.amazon.com/rolesanywhere/latest/userguide/authentication-sign-process.html
        """
        with open(self._certificate_file('crt'), 'rb') as f_in:
            certificate = x509.load_pem_x509_certificate(f_in.read())
        with open(self._certificate_file('key'), 'rb') as f_in:
            private_key = serialization.load_pem_private_key(f_in.read(), password=None)
        if isinstance(private_key, rsa.RSAPrivateKey):
            algorithm = 'AWS4-X509-RSA-SHA256'
            signature_args = (padding.PKCS1v15(), hashes.SHA256())
        elif isinstance(private_key, ec.EllipticCurvePrivateKey):
            algorithm = 'AWS4-X509-ECDSA-SHA256'
            signature_args = (ec.ECDSA(hashes.SHA256()),)
        else:
            raise RuntimeError('unsupported private key type, IAM Roles Anywhere requires RSA or ECDSA')

        region = self.settings.get('aws_region') or 'us-east-1'
        suffix = 'amazonaws.com.cn' if region.startswith('cn-') else 'amazonaws.com'
        host = 'rolesanywhere.%s.%s' % (region, suffix)
        body = json.dumps({
            'durationSeconds': self._session_duration,
            'profileArn': self.settings.get('aws_profile_arn'),
            'roleArn': self.settings.get('aws_role_arn'),
            'trustAnchorArn': self.settings.get('aws_trust_anchor_arn')
        }).encode()
        now = datetime.datetime.now(datetime.timezone.utc)
        amz_date = now.strftime('%Y%m%dT%H%M%SZ')
        scope = '%s/%s/rolesanywhere/aws4_request' % (now.strftime('%Y%m%d'), region)
        headers = {
            'content-type': 'application/json',
            'host': host,
            'x-amz-date': amz_date,
            'x-amz-x509': self._der_base64(certificate)
        }
        chain = self._intermediate_certificates()
        if chain:
            headers['x-amz-x509-chain'] = ','.join(chain)

        signed_headers = ';'.join(sorted(headers))
        canonical_request = '\n'.join([
            'POST',
            '/sessions',
            '',
            ''.join('%s:%s\n' % (name, ' '.join(headers[name].split())) for name in sorted(headers)),
            signed_headers,
            hashlib.sha256(body).hexdigest()
        ])
        string_to_sign = '\n'.join([
            algorithm, amz_date, scope, hashlib.sha256(canonical_request.encode()).hexdigest()
        ])
        signature = private_key.sign(string_to_sign.encode(), *signature_args).hex()
        headers['authorization'] = '%s Credential=%d/%s, SignedHeaders=%s, Signature=%s' % (
            algorithm, certificate.serial_number, scope, signed_headers, signature
        )
        headers['user-agent'] = 'OPNsense-dyndns'

        response = requests.post(
            'https://%s/sessions' % host, data=body, headers=headers, timeout=self._request_timeout
        )
        if response.status_code != 201:
            raise RuntimeError(
                'IAM Roles Anywhere returned HTTP %d %s' % (response.status_code, response.text[:256])
            )
        return self._temporary_credentials(response.json()['credentialSet'][0]['credentials'])

    def _client_credentials(self):
        """ boto3 client arguments for the configured authentication method,
            temporary credentials are reused until shortly before they expire
        """
        if self.auth_method == 'access_key':
            return {
                'aws_access_key_id': self.settings.get('username'),
                'aws_secret_access_key': self.settings.get('password')
            }
        if self._credentials is None or self._credentials['expiration'] - time.time() < self._refresh_margin:
            if self.auth_method == 'iot':
                self._credentials = self._iot_credentials()
            elif self.auth_method == 'rolesanywhere':
                self._credentials = self._rolesanywhere_credentials()
            else:
                raise RuntimeError('unknown authentication method %s' % self.auth_method)
            if self.is_verbose:
                syslog.syslog(
                    syslog.LOG_NOTICE,
                    "Account %s obtained temporary credentials (%s) valid until %s" % (
                        self.description,
                        self.auth_method,
                        time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime(self._credentials['expiration']))
                    )
                )
        return {key: value for key, value in self._credentials.items() if key != 'expiration'}

    def execute(self):
        """ AWS DNS update
        """

        if super().execute():
            if not self.current_address:
                syslog.syslog(
                    syslog.LOG_WARNING,
                    f"No address found for {self.description}"
                )
                return False
            try:
                client = boto3.client('route53', **self._client_credentials())
            except Exception as e:
                syslog.syslog(syslog.LOG_ERR, f"Account {self.description} unable to obtain AWS credentials: {e}")
                return False
            ip = str(self.current_address)
            if ':' in ip:
                addrType = 'AAAA'
            else:
                addrType = 'A'
            TTL = self.settings.get('ttl')
            changeBatch = {
                'Changes': [{
                    'Action': 'UPSERT',
                    'ResourceRecordSet': {
                        'Name': host,
                        'Type': addrType,
                        'TTL': int(TTL),
                        'ResourceRecords': [{'Value': ip}]
                    }
                } for host in self.settings.get('hostnames').split(",")]
            }
            try:
                response = client.change_resource_record_sets(
                    HostedZoneId = self.settings.get('zone'),
                    ChangeBatch = changeBatch)
            except Exception as e:
                syslog.syslog(syslog.LOG_ERR, str(e))
                return False

            syslog.syslog(
                syslog.LOG_NOTICE,
                f"Account {self.description} set new ip {self.current_address}, ID {response['ChangeInfo']['Id']}")

            self.update_state(address=self.current_address)
            return True
