#!/usr/bin/env python3
# Sync ssl certificates from a yaml file into haproxy memory
import os
import sys
import argparse
import traceback
import yaml
import ssl
from io import StringIO
import base64
import OpenSSL


sys.path.append(os.path.join(os.path.dirname(__file__), 'lib'))
from haproxy.conn import HaPConn
from haproxy import cmds


class Diff:
    def __init__(self, local=None, remote=None):
        if local is None:
            local = []
        if remote is None:
            remote = []

        self.local = local
        self.remote = remote
        self.state = str(self)

    def show_state(self):
        """ Shows current local and remote state """
        print("## STATE ##")
        print(str(self))

    def show_diff(self):
        """ Shows what will be synced to target """
        print("## DIFF ##")
        print("TODO: Show the diff")

    def sync(self):
        print("## SYNC ##")
        print("TODO: Sync to target")

    def __iter__(self):
        return iter(self.local)

    def __str__(self):
        result = ""
        for item in self:
            result += f"{str(item)}\n"
        return result


class SyncWithTarget:
    """ Base class for sync objects to a target """
    def __init__(self, socket='/var/run/haproxy.socket'):
        self.socket = socket

    def execute_remote_cmd(self, command_class, **command_args):
        con = HaPConn(self.socket)
        if con:
            result = con.sendCmd(command_class(**command_args), objectify=True)
            con.close()
            return result

    def get_remote_state(self, command_class, **command_args):
        return self.execute_remote_cmd(command_class, **command_args)


class CertList(SyncWithTarget):
    """ Represents a haproxy ssl-crt-list """
    def __init__(self, path, certs=None):
        super().__init__()
        if certs is None:
            certs = []
        self.path = path
        self.certs = certs
        self.local = self.get_local_state()
        self.remote = self.get_remote_state(cmds.showSslCrtList, crt_list=self.path)

    def __iter__(self):
        return iter(self.local)

    def __str__(self):
        result = f"CRT LIST: {self.path}\n"
        result += f"  LOCAL:  {self.local}\n"
        result += f"  REMOTE: {self.remote}\n"
        for cert in self.certs:
            result += f"\n{str(cert)}\n"
        return result

    def get_local_state(self):
        return [f"{repr(cert)}" for cert in self.certs]

    def get_remote_state(self, command_class, **command_args):
        crt_list_data = super().get_remote_state(command_class, **command_args)
        return crt_list_data.get('certs', {})


class Cert(SyncWithTarget):
    """ Represents a haproxy ssl-cert  """
    def __init__(self, path, pem):
        super().__init__()
        self.path = path
        self.pem = pem
        self.local = self.get_local_state()
        self.remote = self.get_remote_state(cmds.showSslCert, certfile=self.path)

    def __repr__(self):
        return self.path

    def __str__(self):
        result = f"    CERT: {self.path}"
        result += f"\n      LOCAL:  {self.local}"
        result += f"\n      REMOTE: {self.remote}"
        return result

    def get_cert_data(self, dump=False, encoding='utf-8'):
        result = OpenSSL.crypto.load_certificate(OpenSSL.crypto.FILETYPE_PEM, self.pem)
        if dump:
            result = OpenSSL.crypto.dump_certificate(OpenSSL.crypto.FILETYPE_TEXT, result).decode(encoding)
        return result

    def glue(self, components):
        return "".join("/{0:s}={1:s}".format(name.decode(), value.decode()) for name, value in components)

    def get_local_state(self):
        cert_obj = self.get_cert_data()
        return {
            "Serial": '%.2x' % cert_obj.get_serial_number(),
            "Subject": self.glue(cert_obj.get_subject().get_components()),
            "Issuer": self.glue(cert_obj.get_issuer().get_components())
        }

    def get_remote_state(self, command_class, **command_args):
        cert_data = super().get_remote_state(command_class, **command_args)
        if 'error' in cert_data:
            return {}
        return {
            "Serial": cert_data['Serial'],
            "Subject": cert_data['Subject'],
            "Issuer": cert_data['Issuer']
        }

def dict_from_yaml(path):
    with open(path, 'r') as yaml_file:
        data = yaml.load(yaml_file, Loader=yaml.SafeLoader)
    return data


def skip_frontend(frontend_id, frontend):
    filter_frontend_names = list(filter(None, args.frontends.split(",")))
    filter_frontend_ids = list(filter(None, args.frontend_ids.split(",")))

    skip_id = False
    if filter_frontend_names and frontend['name'] not in filter_frontend_names:
        skip_id = True

    skip_name = False
    if filter_frontend_ids and frontend_id not in filter_frontend_ids:
        skip_name = True

    return skip_id and skip_name


def get_cert_data(cert, dump=False, encoding='utf-8'):
    if os.path.isfile(cert):
        cert = open(cert).read()

    cert = OpenSSL.crypto.load_certificate(OpenSSL.crypto.FILETYPE_PEM, cert)
    if dump:
        cert = OpenSSL.crypto.dump_certificate(OpenSSL.crypto.FILETYPE_TEXT, cert).decode(encoding)

    return cert


def base64_decode(base64_str, encoding='utf-8'):
    if base64_str:
        base64_bytes = base64_str.encode(encoding)
        message_bytes = base64.b64decode(base64_bytes)
        message = message_bytes.decode(encoding)
        return message
    return ''

def get_args():
    # noinspection PyTypeChecker
    parser = argparse.ArgumentParser(
        description="""
        Sync ssl certificates into HAProxy’s memory with certificates read from a configfile. If no frontend filter is
        given, all certificates will be synced.""",
        formatter_class=argparse.ArgumentDefaultsHelpFormatter
    )
    parser.add_argument(
        '--config',
        help='Path to the ssl certificate information configfile.',
        default="/usr/local/etc/haproxy/sslCerts.yaml"
    )
    parser.add_argument(
        '--frontends',
        help='Attempt action on a list of frontend names, specified as a comma separated list.',
        default=""
    )
    parser.add_argument(
        '--frontend_ids',
        help='Attempt action on a list of frontend ids, specified as a comma separated list.',
        default=""
    )
    parser.add_argument(
        '--output',
        help='Specify output format.',
        choices=['json', 'raw'],
        default="raw"
    )
    parser.add_argument(
        '--debug',
        type=bool,
        help='Show debug output.',
        default=False
    )
    return parser.parse_args()


args = get_args()
config = dict_from_yaml(args.config)

""" Get ssl crt-list with certificates from configfile"""
crt_lists = []
for frontend_id, frontend in config['frontends'].items():
    if skip_frontend(id, frontend_id):
        continue

    certs = []
    for cert_id, cert_data in frontend['certs'].items():
        crt = base64_decode(cert_data['crt'])
        key = base64_decode(cert_data['key'])
        ca = base64_decode(cert_data['ca'])
        full_cert = crt + key + ca

        certs.append(Cert(path=cert_data['path'], pem=full_cert))

    crt_lists.append(CertList(path=frontend['crt_list_path'], certs=certs))

""" Sync ssl certs from configfile to HaProxy """
diff = Diff(local=crt_lists)
diff.show_state()
diff.show_diff()
diff.sync()


#print(crt_lists)
#print(diff)
#diff.sync()
