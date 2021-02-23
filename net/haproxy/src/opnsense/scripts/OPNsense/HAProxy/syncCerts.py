#!/usr/bin/env python3
# Sync ssl certificates from a yaml file into haproxy memory
import os
import sys
import argparse
import yaml
import base64
import OpenSSL
import json
from typing import List

sys.path.append(os.path.join(os.path.dirname(__file__), 'lib'))
from haproxy.conn import HaPConn
from haproxy import cmds


class SyncWithTarget:
    """ Base class for sync objects to a target """

    def __init__(self, socket='/var/run/haproxy.socket'):
        self.socket = socket

    def _execute_remote_cmd(self, command_class, **command_args):
        con = HaPConn(self.socket)
        if con:
            command_obj = command_class(**command_args)
            result = con.sendCmd(command_obj, objectify=True)
            con.close()
            return result

    def _calc_diff(self):
        """ return needed operations to get remote object in sync """
        raise Exception("need to be implemented!")

    def diff_list(self, first: List, second: List):
        second = set(second)
        return [item for item in first if item not in second]


class Diff(SyncWithTarget):
    """ Represents a full diff to sync with remote """

    def __init__(self, crt_lists=None):
        super().__init__()
        if crt_lists is None:
            crt_lists = []
        self._crt_lists = crt_lists
        self._diff = self._calc_diff()
        self._status = self._get_status()
        self._transactions = self._get_transactions()

    @property
    def diff(self):
        return self._diff

    @property
    def crt_lists(self):
        return self._crt_lists

    @property
    def transactions(self):
        return self._transactions

    @property
    def status(self):
        return self._status

    def _calc_diff(self):
        result = {}
        for crt_list in self:
            result[crt_list.frontend_id] = crt_list.diff
        return result

    def abort(self, output_format):
        """ Abort transactions"""
        aborted = []
        for certfile in self.transactions:
            certfile = certfile.replace('*/', "/")

            output = self._execute_remote_cmd(cmds.abortSslCrt, certfile=certfile)
            aborted.append({
                "cert": certfile,
                "output": output,
            })

        if output_format == 'json':
            print(json.dumps({'abort': aborted}))

        if output_format == 'raw':
            for item in aborted:
                print(f"ABORT transaction: {item['cert']}")
                print(f"  {repr(item['output'])}")

    def _get_transactions(self):
        """ get open transactions"""
        return self._execute_remote_cmd(cmds.showSslCerts)['transaction']

    def _get_status(self):
        status = {}
        crt_list: CertList
        for crt_list in self.crt_lists:
            status[crt_list.frontend_id] = {
                "frontend_name": crt_list.frontend_name,
                "path": crt_list.path,
                "local_certs": crt_list.local,
                "local_default": crt_list.local_default,
                "remote_certs": crt_list.remote,
                "remote_default": crt_list.remote_default,
            }
            cert: Cert
            status[crt_list.frontend_id]['certs'] = {}
            for cert in crt_list.certs:
                status[crt_list.frontend_id]['certs'][cert.cert_id] = {
                    'path': cert.path,
                    'local': cert.local,
                    'remote': cert.remote,
                }
        return status

    def show_status(self, output_format):
        """ Shows current local and remote state """
        if output_format == 'json':
            print(json.dumps(self.status))

        if output_format == 'raw':
            print("## STATUS ##")
            for frontend_id, crt_list in self.status.items():
                print(f"CRT_LIST: {crt_list['path']}")
                print(f"  FRONTEND NAME:  {crt_list['frontend_name']}")
                print(f"  FRONTEND ID:    {frontend_id}")
                print(f"  LOCAL CERTS:    {crt_list['local_certs']}")
                print(f"  REMOTE CERTS:   {crt_list['remote_certs']}")
                print(f"  LOCAL DEFAULT:  {crt_list['local_default']}")
                print(f"  REMOTE DEFAULT: {crt_list['remote_default']}")

                for cert_id, cert in crt_list['certs'].items():
                    print()
                    print(f"    CERT: {cert['path']}")
                    print(f"      LOCAL:  {cert['local']}")
                    print(f"      REMOTE: {cert['remote']}")
                print()

    def show_diff(self, output_format):
        """ Shows what will be synced to target """
        if output_format == 'json':
            print(json.dumps(self.diff))

        if output_format == 'raw':
            print("## DIFF ##")
            for frontend_id, diff in self.diff.items():
                print(f"CRT LIST: {diff['path']}")
                print(f"  FRONTEND NAME: {diff['frontend_name']}")
                print(f"  FRONTEND ID: {diff['frontend_id']}")
                for update in diff['update']:
                    print(f"  CERT UPDATE:")
                    print(f"     Cert:    {update['certfile']}")
                    print(f"     Serial:  {update['meta']['Serial']}")
                    print(f"     Issuer:  {update['meta']['Issuer']}")
                    print(f"     Subject: {update['meta']['Subject']}")
                else:
                    if not diff['update']:
                        print(f"  CERT UPDATE: []")
                print(f"  CERT ADD :   {diff['add']}")
                print(f"  CERT DEL :   {diff['del']}")

    def show_transactions(self, output_format):
        if output_format == 'json':
            print(json.dumps({'transactions': self.transactions}))

        if output_format == 'raw':
            print("## OPEN TRANSACTIONS ##")
            for cert in self.transactions:
                print(cert)

    def sync(self, output_format):
        """ Sync to target """
        sync = {}
        certs_to_delete = []
        for frontend_id, diff in self.diff.items():
            sync[frontend_id] = {
                'frontend_name': diff['frontend_name'],
                'frontend_id': diff['frontend_id'],
                'path': diff['path'],
                'add': [],
                'remove': [],
                'update': [],
                'del': []
            }

            # update cert content
            for cert in diff['update']:
                messages = []
                if cert['certfile'] in diff['add']:
                    output = self._execute_remote_cmd(cmds.newSslCrt, certfile=cert['certfile'])
                    messages.append(output)

                output = self._execute_remote_cmd(cmds.updateSslCrt, certfile=cert['certfile'], payload=cert['pem'])
                messages.append(output)

                output = self._execute_remote_cmd(cmds.commitSslCrt, certfile=cert['certfile'])
                messages.append(output)

                sync[frontend_id]['update'].append({
                    'cert': cert['certfile'],
                    'messages': messages
                })

            # add to crt-list
            for cert in diff['add']:
                messages = []
                output = self._execute_remote_cmd(cmds.addToSslCrtList, crt_list=diff['path'], certfile=cert)
                messages.append(output)
                sync[frontend_id]['add'].append({
                    'cert': cert,
                    'messages': messages
                })

            # remove from crt-list
            for cert in diff['del']:
                messages = []
                output = self._execute_remote_cmd(cmds.delFromSslCrtList, crt_list=diff['path'], certfile=cert)
                messages.append(output)
                certs_to_delete.append(cert.split(":")[0])
                sync[frontend_id]['remove'].append({
                    'cert': cert,
                    'messages': messages
                })

        # delete unused certs operation - haproxy does not allow to delete certs in use
        for cert in certs_to_delete:
            messages = []
            output = self._execute_remote_cmd(cmds.delSslCrt, certfile=cert)
            messages.append(output)
            sync[frontend_id]['del'].append({
                'cert': cert,
                'messages': messages
            })

        if output_format == 'json':
            print(json.dumps(self.diff))

        if output_format == 'raw':
            print("## SYNC ##")
            for frontend_id, crt_list in sync.items():
                print(f"CRT-LIST: {crt_list['path']}")
                print(f"  FRONTEND NAME: {crt_list['frontend_name']}")
                print(f"  FRONTEND ID: {crt_list['frontend_id']}")
                for cert in crt_list['update']:
                    print(f"  UPDATE: {cert['cert']}")
                    for message in cert['messages']:
                        print("    " + repr(message))
                for cert in crt_list['add']:
                    print(f"  ADD: {cert['cert']}")
                    for message in cert['messages']:
                        print("    " + repr(message))

                for cert in crt_list['remove']:
                    print(f"  REMOVE: {cert['cert']}")
                    for message in cert['messages']:
                        print("    " + repr(message))

                for cert in crt_list['del']:
                    print(f"\n DEL: {cert['cert']}")
                    for message in cert['messages']:
                        print("    " + repr(message))
                print()

    def __iter__(self):
        return iter(self._crt_lists)

    def __str__(self):
        return self.status


class CertList(SyncWithTarget):
    """ Represents a haproxy ssl-crt-list """

    def __init__(self, path, frontend_id=None, frontend_name=None, certs=None, default_cert=None):
        super().__init__()
        if certs is None:
            certs = []
        self._path = path
        self._certs = certs
        self._frontend_name = frontend_name
        self._frontend_id = frontend_id
        self._local_default = default_cert
        self._local = self._get_local_state()
        self._remote_ln = self._get_remote_state(cmds.showSslCrtList, crt_list=self._path)
        self._remote = [cert_ln.split(":")[0] for cert_ln in self._remote_ln]
        self._diff = self._calc_diff()

    @property
    def path(self):
        return self._path

    @property
    def frontend_name(self):
        return self._frontend_name

    @property
    def frontend_id(self):
        return self._frontend_id

    @property
    def certs(self):
        return self._certs

    @property
    def local_default(self):
        return self._local_default

    @property
    def remote_default(self):
        return next(iter(self._remote), None)

    @property
    def local(self):
        return self._local

    @property
    def remote_ln(self):
        """ Certs with line number"""
        return self._remote_ln

    @property
    def remote(self):
        """
            if default certs are different return remote certs with line numbers, so they are deleted in the crt list.
            This ensures that the default cert is always on top.
        """
        if self._local_default is not None and self.local_default != self.remote_default:
            return self._remote_ln
        return self._remote

    @property
    def diff(self):
        return self._diff

    def _calc_diff(self):
        """ return needed operations to get remote object in sync """
        diff = {
            'frontend_name': self.frontend_name,
            'frontend_id': self.frontend_id,
            'path': self.path,
            'add': [],
            'del': [],
            'update': []
        }
        # skip when there is no remote crt list
        if self.remote is None:
            return diff

        # certs to add, delete and update on the remote target
        diff['add'] = self.diff_list(self.local, self.remote)
        diff['del'] = self.diff_list(self.remote, self.local)
        diff['update'] = [cert.diff for cert in self.certs if cert.diff]

        return diff

    def _get_local_state(self):
        return [f"{repr(cert)}" for cert in self._certs]

    def _get_remote_state(self, command_class, **command_args):
        crt_list_data = self._execute_remote_cmd(command_class, **command_args)
        return crt_list_data.get('certs', None)

    def __iter__(self):
        return iter(self._local)


class Cert(SyncWithTarget):
    """ Represents a haproxy ssl-cert  """

    def __init__(self, path, pem, cert_id=None):
        super().__init__()
        self._path = path
        self._pem = pem
        self._cert_id = cert_id
        self._local = self._get_local_state()
        self._remote = self._get_remote_state(cmds.showSslCert, certfile=self._path)
        self._diff = self._calc_diff()

    @property
    def path(self):
        return self._path

    @property
    def cert_id(self):
        return self._cert_id

    @property
    def pem(self):
        return self._pem.replace("\n\n", "\n")

    @property
    def local(self):
        return self._local

    @property
    def remote(self):
        return self._remote

    @property
    def diff(self):
        return self._diff

    def __repr__(self):
        return self._path

    def _get_cert_data(self, dump=False, encoding='utf-8'):
        result = OpenSSL.crypto.load_certificate(OpenSSL.crypto.FILETYPE_PEM, self.pem)
        if dump:
            result = OpenSSL.crypto.dump_certificate(OpenSSL.crypto.FILETYPE_TEXT, result).decode(encoding)
        return result

    def _glue(self, components):
        return "".join("/{0:s}={1:s}".format(name.decode(), value.decode()) for name, value in components)

    def _get_local_state(self):
        cert_obj = self._get_cert_data()
        return {
            "Serial": '%.2x'.upper() % cert_obj.get_serial_number(),
            "Subject": self._glue(cert_obj.get_subject().get_components()),
            "Issuer": self._glue(cert_obj.get_issuer().get_components())
        }

    def _get_remote_state(self, command_class, **command_args):
        cert_data = self._execute_remote_cmd(command_class, **command_args)

        if 'error' in cert_data:
            return cert_data

        if cert_data['Status'] == 'Empty':
            return {'Status': cert_data['Status']}

        return {
            "Serial": cert_data.get('Serial', None),
            "Subject": cert_data.get('Subject', None),
            "Issuer": cert_data.get('Issuer', None),
        }

    def _calc_diff(self):
        result = {}
        if self._remote != self._local:
            result['certfile'] = self.path
            result['pem'] = self.pem
            result['meta'] = self.local
        return result


def dict_from_yaml(path):
    with open(path, 'r') as yaml_file:
        data = yaml.load(yaml_file, Loader=yaml.SafeLoader)
    return data


def skip_frontend(frontend_id, frontend):
    filter_frontend_names = list(filter(None, args.frontends.split(",")))
    filter_frontend_ids = list(filter(None, args.frontend_ids.split(",")))

    if not filter_frontend_ids and not filter_frontend_names:
        return False

    if filter_frontend_ids and frontend_id in filter_frontend_ids:
        return False
    if filter_frontend_names and frontend['name'] in filter_frontend_names:
        return False

    return True


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
        'command',
        choices=['status', 'diff', 'sync', 'transactions', 'abort'],
        nargs='+',
        help="Execute one or more operations."
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
        '--frontend-ids',
        help='Attempt action on a list of frontend ids, specified as a comma separated list.',
        default=""
    )
    parser.add_argument(
        '--output',
        help='Specify output format.',
        choices=['json', 'raw'],
        default="raw"
    )
    return parser.parse_args()


def get_crt_lists_from_config(configfile):
    """ Get ssl crt-list with certificates from configfile"""
    config = dict_from_yaml(configfile)
    crt_lists = []
    for frontend_id, frontend in config['frontends'].items():
        if skip_frontend(frontend_id, frontend):
            continue

        certs = []
        default_cert = None
        for cert_id, cert_data in frontend['certs'].items():
            crt = base64_decode(cert_data['crt'])
            key = base64_decode(cert_data['key'])
            ca = base64_decode(cert_data['ca'])
            full_cert = crt + key + ca

            if cert_data['default']:
                default_cert = cert_data['path']

            certs.append(Cert(path=cert_data['path'], pem=full_cert, cert_id=cert_id))

        params = {
            'path': frontend['crt_list_path'],
            'frontend_id': frontend_id,
            'frontend_name': frontend['name'],
            'certs': certs,
            'default_cert': default_cert
        }
        crt_lists.append(CertList(**params))

    return crt_lists


args = get_args()
crt_lists = get_crt_lists_from_config(args.config)
diff = Diff(crt_lists=crt_lists)

""" Sync ssl certs from configfile to HaProxy """
if "status" in args.command:
    diff.show_status(args.output)
if "diff" in args.command:
    diff.show_diff(args.output)
if "abort" in args.command:
    diff.abort(args.output)
if "transactions" in args.command:
    diff.show_transactions(args.output)
if "sync" in args.command:
    diff.sync(args.output)
