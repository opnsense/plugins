# SPDX-License-Identifier: BSD-2-Clause
# Copyright (c) 2026 Andrew Watkins.
"""Offline tests for Route 53 certificate authentication.

Run: python -m unittest discover -s dns/ddclient/tests -v
Requires boto3, requests, cryptography, dnspython and ujson.
"""

import base64
import datetime
import hashlib
import json
import os
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import Mock, patch

from cryptography import x509
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import ec, padding, rsa
from cryptography.x509.oid import NameOID

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'src/opnsense/scripts/ddclient'))
from lib.account.aws import AWS


class CertificateAuthTests(unittest.TestCase):
    def account(self, **settings):
        return AWS({'id': 'test-account', 'service': 'aws', 'description': 'test', **settings})

    def test_existing_access_key_settings_are_passed_through(self):
        account = self.account(username='access', password='secret')
        self.assertEqual(account._client_credentials(), {
            'aws_access_key_id': 'access', 'aws_secret_access_key': 'secret'
        })

    def test_iot_uses_client_certificate_and_optional_thing_name(self):
        account = self.account(aws_auth='iot', aws_iot_endpoint='example.credentials.iot.us-east-1.amazonaws.com',
                               aws_iot_role_alias='Ddns', aws_iot_thing_name='router')
        account._certificate_dir = '/test-certificates'
        response = Mock(status_code=200)
        response.json.return_value = {'credentials': {
            'accessKeyId': 'access', 'secretAccessKey': 'secret', 'sessionToken': 'token',
            'expiration': '2030-01-01T00:00:00Z',
        }}
        with patch('lib.account.aws.requests.get', return_value=response) as request:
            credentials = account._iot_credentials()
        self.assertEqual(request.call_args.args[0],
                         'https://example.credentials.iot.us-east-1.amazonaws.com/role-aliases/Ddns/credentials')
        self.assertEqual(request.call_args.kwargs['cert'],
                         ('/test-certificates/test-account.crt', '/test-certificates/test-account.key'))
        self.assertEqual(request.call_args.kwargs['headers']['x-amzn-iot-thingname'], 'router')
        self.assertEqual(request.call_args.kwargs['timeout'], 15)
        self.assertEqual(credentials['aws_session_token'], 'token')

    def test_temporary_credentials_are_cached_and_refreshed_before_expiry(self):
        account = self.account(aws_auth='iot')
        provider = Mock(side_effect=[
            {'aws_access_key_id': 'first', 'expiration': 2000},
            {'aws_access_key_id': 'second', 'expiration': 3000},
        ])
        with patch.object(account, '_iot_credentials', provider), patch('lib.account.aws.time.time') as clock:
            clock.return_value = 1000
            self.assertEqual(account._client_credentials(), {'aws_access_key_id': 'first'})
            clock.return_value = 1600
            self.assertEqual(account._client_credentials(), {'aws_access_key_id': 'first'})
            clock.return_value = 1701
            self.assertEqual(account._client_credentials(), {'aws_access_key_id': 'second'})
        self.assertEqual(provider.call_count, 2)

    def test_failed_authentication_does_not_attempt_a_dns_change(self):
        account = self.account(aws_auth='iot')
        account._current_address = '203.0.113.1'
        with patch('lib.account.BaseAccount.execute', return_value=True), \
                patch.object(account, '_client_credentials', side_effect=RuntimeError('denied')), \
                patch('lib.account.aws.boto3.client') as client:
            self.assertFalse(account.execute())
        client.assert_not_called()

    def verify_rolesanywhere(self, private_key, region):
        now = datetime.datetime.now(datetime.timezone.utc)
        subject = x509.Name([x509.NameAttribute(NameOID.COMMON_NAME, 'ddclient-test')])
        certificate = (x509.CertificateBuilder().subject_name(subject).issuer_name(subject)
                       .public_key(private_key.public_key()).serial_number(12345)
                       .not_valid_before(now - datetime.timedelta(minutes=1))
                       .not_valid_after(now + datetime.timedelta(days=1)).sign(private_key, hashes.SHA256()))
        with tempfile.TemporaryDirectory(dir=os.environ.get('OPNSENSE_TEST_TMP')) as temporary:
            account = self.account(aws_auth='rolesanywhere', aws_region=region,
                                   aws_profile_arn='profile', aws_role_arn='role', aws_trust_anchor_arn='anchor')
            account._certificate_dir = temporary
            Path(account._certificate_file('crt')).write_bytes(certificate.public_bytes(serialization.Encoding.PEM))
            Path(account._certificate_file('key')).write_bytes(private_key.private_bytes(
                serialization.Encoding.PEM, serialization.PrivateFormat.PKCS8, serialization.NoEncryption()))
            response = Mock(status_code=201)
            response.json.return_value = {'credentialSet': [{'credentials': {
                'accessKeyId': 'access', 'secretAccessKey': 'secret', 'sessionToken': 'token',
                'expiration': '2030-01-01T00:00:00Z',
            }}]}
            with patch('lib.account.aws.requests.post', return_value=response) as request:
                credentials = account._rolesanywhere_credentials()
        headers = request.call_args.kwargs['headers']
        body = request.call_args.kwargs['data']
        self.assertEqual(json.loads(body)['trustAnchorArn'], 'anchor')
        self.assertEqual(credentials['aws_session_token'], 'token')
        self.assertEqual(headers['x-amz-x509'], base64.b64encode(
            certificate.public_bytes(serialization.Encoding.DER)).decode())
        authorization = headers['authorization']
        algorithm, details = authorization.split(' ', 1)
        fields = dict(item.split('=', 1) for item in details.split(', '))
        serial, scope = fields['Credential'].split('/', 1)
        self.assertEqual(serial, '12345')
        signed = fields['SignedHeaders'].split(';')
        canonical = '\n'.join([
            'POST', '/sessions', '',
            ''.join('%s:%s\n' % (name, ' '.join(headers[name].split())) for name in signed),
            fields['SignedHeaders'], hashlib.sha256(body).hexdigest(),
        ])
        string_to_sign = '\n'.join([
            algorithm, headers['x-amz-date'], scope, hashlib.sha256(canonical.encode()).hexdigest()
        ]).encode()
        signature = bytes.fromhex(fields['Signature'])
        if isinstance(private_key, rsa.RSAPrivateKey):
            self.assertEqual(algorithm, 'AWS4-X509-RSA-SHA256')
            private_key.public_key().verify(signature, string_to_sign, padding.PKCS1v15(), hashes.SHA256())
        else:
            self.assertEqual(algorithm, 'AWS4-X509-ECDSA-SHA256')
            private_key.public_key().verify(signature, string_to_sign, ec.ECDSA(hashes.SHA256()))
        return request.call_args.args[0]

    def test_rolesanywhere_rsa_signature(self):
        self.assertEqual(self.verify_rolesanywhere(rsa.generate_private_key(65537, 2048), 'us-east-1'),
                         'https://rolesanywhere.us-east-1.amazonaws.com/sessions')

    def test_rolesanywhere_ecdsa_signature_and_china_endpoint(self):
        self.assertEqual(self.verify_rolesanywhere(ec.generate_private_key(ec.SECP256R1()), 'cn-north-1'),
                         'https://rolesanywhere.cn-north-1.amazonaws.com.cn/sessions')


if __name__ == '__main__':
    unittest.main()
