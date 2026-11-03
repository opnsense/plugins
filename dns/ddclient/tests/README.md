# AWS certificate authentication checks

Run from the plugin repository root:

```sh
python -m unittest discover -s dns/ddclient/tests -v
```

The tests use the native backend's `boto3`, `requests`, `cryptography`,
`dnspython`, and `ujson` dependencies. HTTP and Route 53 calls are mocked; no
AWS account or credentials are required.

Checks cover existing access-key behavior, mutual-TLS IoT requests and thing
headers, temporary-credential caching/refresh, failed-auth update suppression,
and cryptographic verification of RSA/ECDSA Roles Anywhere signatures. The
ECDSA case also verifies the China-region endpoint suffix.

Set `OPNSENSE_TEST_TMP` when test certificate files should use a particular
temporary directory.
