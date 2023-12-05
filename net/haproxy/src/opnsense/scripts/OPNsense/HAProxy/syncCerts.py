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

from haproxy.conn import HaPConn
from haproxy import cmds


class SyncWithTarget:
    """ Base class for sync objects to a target """

    def __init__(self, socket='/var/run/haproxy.socket', **kwargs):
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

    def __init__(self, crt_lists=None, **kwargs):
        super().__init__()
        if crt_lists is None:
            crt_lists = []
        self._crt_lists = crt_lists
        self._diff = self._calc_diff()
        self._status = self._get_status()
        self._transactions = self._get_transactions()

        self.output_format = kwargs['output']
        self.page = kwargs['page']
        self.page_rows = kwargs['page_rows']
        self.search = kwargs['search']
        self.sort_col = kwargs['sort_col']
        self.sort_dir = kwargs['sort_dir']

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

    def _get_bootgrid_output(self, rows):
        """ Returns jquery bootgrid output """
        args = {
            "rows": rows,
            "page": int(self.page) if self.page != None else 1,
            "page_rows": int(self.page_rows) if self.page_rows != None else len(rows),
            "search": self.search,
            "sort_col": self.sort_col if self.sort_col else 'id',
            "sort_dir": self.sort_dir,
        }

        # search
        if args['search']:
            filtered_rows = []
            for row in rows:
                def inner(row):
                    for k, v in row.items():
                        if args['search'] in v:
                            return row
                    return None

                match = inner(row)
                if match:
                    filtered_rows.append(match)
            rows = filtered_rows

        # sort
        rows.sort(key=lambda k: k[args['sort_col']], reverse=True if args['sort_dir'] == 'desc' else False)

        # pager
        total = len(rows)
        pages = [rows[i:i + args['page_rows']] for i in range(0, total, args['page_rows'])]
        if pages and (args['page'] > len(pages) or args['page'] < 1):
            raise KeyError(f"Current page {args['page']} does not exist. Available pages: {len(pages)}")
        page = pages[args['page'] - 1] if pages else []

        return json.dumps({
            "rows": page,
            "total": total,
            "rowCount": args['page_rows'],
            "current": args['page']
        })

    def _calc_diff(self):
        return [crt_list.diff for crt_list in self if crt_list.diff['total_count'] > 0]

    def abort(self):
        """ Abort transactions"""
        aborted = []
        for certfile in self.transactions:
            certfile = certfile.replace('*/', "/")

            output = self._execute_remote_cmd(cmds.abortSslCrt, certfile=certfile)
            aborted.append({
                "cert": certfile,
                "output": output,
            })

        if self.output_format == 'json':
            print(json.dumps({'abort': aborted}))

        if self.output_format == 'raw':
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

    def show_diff(self):
        """ Shows current local and remote state """
        if self.output_format == 'json':
            print(json.dumps(self.status))

        if self.output_format == 'raw':
            for frontend_id, crt_list in self.status.items():
                print(f"FRONTEND NAME: {crt_list['frontend_name']}")
                print(f"  CONFIG:")
                for cert_id, cert in crt_list['certs'].items():
                    if cert['path'] == crt_list['local_default']:
                        print(f"    CERT (Default):")
                    else:
                        print(f"    CERT:")
                    print(f"      Serial:  {cert['local']['Serial']}")
                    print(f"      Issuer:  {cert['local']['Issuer']}")
                    print(f"      Subject: {cert['local']['Subject']}")
                print(f"  ACTIVE:")
                for cert in crt_list['remote_certs']:
                    meta = self._execute_remote_cmd(cmds.showSslCert, certfile=cert.split(":")[0])
                    print(f"    CERT:")
                    print(f"      Serial:  {meta['Serial']}")
                    print(f"      Issuer:  {meta['Issuer']}")
                    print(f"      Subject: {meta['Subject']}")

    def show_actions(self):
        """ Shows what will be synced to target """
        if self.output_format == 'json':
            print(json.dumps(self.diff))

        if self.output_format == 'bootgrid':
            print(self._get_bootgrid_output(self.diff))

        if self.output_format == 'raw':
            for diff in self.diff:
                print(f"FRONTEND: {diff['frontend_name']}")

                print(f"  CRT-LIST: {diff['path']}")
                for update in diff['update']:
                    print(f"  CERT NEW / UPDATE:")
                    print(f"     Cert:    {update['certfile']}")
                    print(f"     Serial:  {update['meta'].get('Serial', None)}")
                    print(f"     Issuer:  {update['meta'].get('Issuer', None)}")
                    print(f"     Subject: {update['meta'].get('Subject', None)}")
                    print()
                else:
                    if not diff['update']:
                        print(f"  CERT NEW / UPDATE: []")

                for add in diff['add']:
                    print(f"  CERT ADD:")
                    print(f"     Cert:    {add['certfile']}")
                    print(f"     Serial:  {add['meta'].get('Serial', None)}")
                    print(f"     Issuer:  {add['meta'].get('Issuer', None)}")
                    print(f"     Subject: {add['meta'].get('Subject', None)}")
                    print()
                else:
                    if not diff['add']:
                        print(f"  CERT ADD: []")

                for remove in diff['remove']:
                    print(f"  CERT REMOVE:")
                    print(f"     Cert:    {remove['certfile']}")
                    print(f"     Serial:  {remove['meta'].get('Serial', None)}")
                    print(f"     Issuer:  {remove['meta'].get('Issuer', None)}")
                    print(f"     Subject: {remove['meta'].get('Subject', None)}")
                    print()
                else:
                    if not diff['remove']:
                        print(f"  CERT REMOVE: []")
                        print()


    def show_transactions(self):
        if self.output_format == 'json':
            print(json.dumps({'transactions': self.transactions}))

        if self.output_format == 'raw':
            print("## OPEN TRANSACTIONS ##")
            for cert in self.transactions:
                print(cert)

    def sync(self):
        """ Sync to target """
        sync = {
            'modified': [],
            'deleted': [],
            'add_count': 0,
            'remove_count': 0,
            'update_count': 0,
            'del_count': 0,
        }
        certs_to_delete = []
        for diff in self.diff:
            sync_item = {
                'frontend_name': diff['frontend_name'],
                'frontend_id': diff['frontend_id'],
                'path': diff['path'],
                'add': [],
                'remove': [],
                'update': [],
                'add_count': 0,
                'remove_count': 0,
                'update_count': 0,
            }

            # new cert / update cert
            for cert in diff['update']:
                messages = []
                if any(add_cert['certfile'] == cert['certfile'] for add_cert in diff['add']):
                    output = self._execute_remote_cmd(cmds.newSslCrt, certfile=cert['certfile'])
                    messages.append(output)

                output = self._execute_remote_cmd(cmds.updateSslCrt, certfile=cert['certfile'], payload=cert['pem'])
                messages.append(output)

                output = self._execute_remote_cmd(cmds.commitSslCrt, certfile=cert['certfile'])
                messages.append(output)
                sync_item['update'].append({
                    'cert': cert['certfile'],
                    'messages': messages
                })

                if "Success!" in output:
                    sync['update_count'] += 1
                    sync_item['update_count'] += 1

            # add to crt-list
            for cert in diff['add']:
                messages = []
                output = self._execute_remote_cmd(cmds.addToSslCrtList, crt_list=diff['path'], certfile=cert['certfile'])
                messages.append(output)
                sync_item['add'].append({
                    'cert': cert['certfile'],
                    'messages': messages
                })

                if "Success!" in output:
                    sync['add_count'] += 1
                    sync_item['add_count'] += 1

            # remove from crt-list
            for cert in diff['remove']:
                messages = []
                output = self._execute_remote_cmd(cmds.delFromSslCrtList, crt_list=diff['path'], certfile=cert['certfile'])
                messages.append(output)
                certs_to_delete.append(cert['certfile'].split(":")[0])
                sync_item['remove'].append({
                    'cert': cert['certfile'],
                    'messages': messages
                })

                if "deleted in crtlist" in output:
                    sync['remove_count'] += 1
                    sync_item['remove_count'] += 1

            sync['modified'].append(sync_item)

        # delete unused certs operation - haproxy does not allow to delete certs in use
        for cert in certs_to_delete:
            messages = []
            output = self._execute_remote_cmd(cmds.delSslCrt, certfile=cert)
            messages.append(output)
            cert_item = {
                'cert': cert,
                'messages': messages
            }
            sync['del_count'] += 1
            sync['deleted'].append(cert_item)

        if self.output_format == 'json':
            print(json.dumps(sync))

        if self.output_format == 'raw':
            for crt_list in sync['modified']:
                print(f"CRT-LIST: {crt_list['path']}")
                print(f"  FRONTEND NAME: {crt_list['frontend_name']}")
                print(f"  FRONTEND ID: {crt_list['frontend_id']}")
                for cert in crt_list['update']:
                    print(f"  NEW / UPDATE: {cert['cert']}")
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

            for cert in sync['deleted']:
                print(f"\n  DEL: {cert['cert']}")
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
        self._remote = [cert_ln.split(":")[0] for cert_ln in self._remote_ln] if self._remote_ln else []
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
        return self._remote_ln if self._remote_ln else []

    @property
    def remote(self):
        """
            if default certs are different return remote certs with line numbers, so they are deleted in the crt list.
            This ensures that the default cert is always on top.
        """
        if self._local_default is not None and self.local_default != self.remote_default:
            return self.remote_ln
        return self._remote

    @property
    def diff(self):
        return self._diff

    def _calc_diff(self):
        """ return needed operations to get remote object in sync """
        diff = {
            'id': self.frontend_id,
            'frontend_name': self.frontend_name,
            'frontend_id': self.frontend_id,
            'path': self.path,
            'add': [],
            'add_count': 0,
            'remove': [],
            'remove_count': 0,
            'update': [],
            'update_count': 0,
            'total_count': 0
        }
        # skip when there is no remote crt list
        if self.remote is None:
            return diff

        # add
        for cert_path in self.diff_list(self.local, self.remote):
            cert_obj = self._get_local_cert_by_path(cert_path)
            diff['add'].append({
                "certfile": cert_path,
                "meta": cert_obj.local
            })
        diff['add_count'] = len(diff['add'])

        # remove
        for certpath in self.diff_list(self.remote, self.local):
            diff['remove'].append({
                "certfile": certpath,
                "meta": self._execute_remote_cmd(cmds.showSslCert, certfile=certpath.split(":")[0])
            })
        diff['remove_count'] = len(diff['remove'])

        # update
        diff['update'] = [cert.diff for cert in self.certs if cert.diff]
        diff['update_count'] = len(diff['update'])

        diff['total_count'] = diff['add_count'] + diff['remove_count'] + diff['update_count']

        return diff

    def _get_local_cert_by_path(self, path):
        for cert in self._certs:
            if cert.path == path:
                return cert

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
        serial = cert_obj.get_serial_number()
        serial_hex = "%X" % serial

        if len(serial_hex) % 2 != 0:
            padding = len(serial_hex) + 1
            serial_hex = f"%.{padding}X" % serial

        return {
            "Serial": serial_hex,
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
        choices=['diff', 'actions', 'sync', 'transactions', 'abort'],
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
        '-o',
        help='Specify output format.',
        choices=['json', 'raw', 'bootgrid'],
        default="raw"
    )
    parser.add_argument(
        '--page-rows',
        help='Limit output to the specified numbers of rows per page.',
        default=None
    )
    parser.add_argument(
        '--page',
        help='Output page number.',
        default=None
    )
    parser.add_argument(
        '--search',
        help='Search for string.',
        default=None
    )
    parser.add_argument(
        '--sort-col',
        help='Sort output on this column.',
        default=None
    )
    parser.add_argument(
        '--sort-dir',
        help='Sort output in this direction.',
        default=None
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
diff = Diff(crt_lists=crt_lists, **vars(args))

""" Sync ssl certs from configfile to HaProxy """
if "diff" in args.command:
    diff.show_diff()
if "actions" in args.command:
    diff.show_actions()
if "abort" in args.command:
    diff.abort()
if "transactions" in args.command:
    diff.show_transactions()
if "sync" in args.command:
    diff.sync()
