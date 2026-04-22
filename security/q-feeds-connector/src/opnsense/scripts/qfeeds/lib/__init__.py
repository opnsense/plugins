"""
    Copyright (c) 2025-2026 Deciso B.V.
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
import os
import subprocess
import time
import ujson
from datetime import datetime
from requests.exceptions import HTTPError
from lib.api import Api, QFeedsConfig
from lib.log import PFLogCrawler
from lib.file import LockedFile


class QFeedsActions:
    _AUTH_FAIL_FILE = '.auth_failed'
    _AUTH_FAIL_COOLDOWN = 3600

    def __init__(self, target_dir, forced=False):
        self._target_dir = target_dir
        self._forced = forced

    def _auth_fail_path(self):
        return os.path.join(self._target_dir, self._AUTH_FAIL_FILE)

    def _auth_blocked(self):
        p = self._auth_fail_path()
        if not os.path.exists(p):
            return False
        try:
            stamp = int(os.stat(p).st_mtime)
        except OSError:
            return False
        conf = '/usr/local/etc/qfeeds.conf'
        if os.path.exists(conf) and os.stat(conf).st_mtime > stamp:
            try:
                os.remove(p)
            except OSError:
                pass
            return False
        return (time.time() - stamp) < self._AUTH_FAIL_COOLDOWN

    def _mark_auth_failed(self):
        if not os.path.isdir(self._target_dir):
            os.makedirs(self._target_dir)
        p = self._auth_fail_path()
        open(p, 'w').close()
        os.utime(p, None)

    def _clear_auth_failed(self):
        p = self._auth_fail_path()
        if os.path.exists(p):
            try:
                os.remove(p)
            except OSError:
                pass

    @classmethod
    def list_actions(cls):
        return [
            'fetch_index',
            'fetch',
            'show_index',
            'firewall_load',
            'unbound_load',
            'dnscryptproxy_load',
            'update',
            'stats',
            'logs'
        ]

    @property
    def index_file(self):
        return "%s/index.json" % self._target_dir

    @property
    def index(self):
        if not os.path.exists(self.index_file) and self._forced:
            # require index file to get feeds
            list(self.fetch_index())
        elif not os.path.exists(self.index_file):
            return {}
        with open(self.index_file, 'r') as fp:
            data = ujson.load(fp) or {}
        if isinstance(data, dict):
            for feed in data.get('feeds', []):
                feed['local_filename'] = "%s/%s.txt" % (self._target_dir, feed['feed_type'])
                feed['updated_at_dt'] = (
                    int(datetime.fromisoformat(feed['updated_at'].strip()).timestamp())
                    if feed.get('updated_at') else 0
                )
                feed['next_update_dt'] = (
                    int(datetime.fromisoformat(feed['next_update'].strip()).timestamp())
                    if feed.get('next_update') else 0
                )

        return data

    def fetch_index(self):
        if not os.path.isdir(self._target_dir):
            os.makedirs(self._target_dir)
        try:
            payload = Api().licenses()
        except HTTPError as exc:
            if exc.response is not None and exc.response.status_code == 401:
                self._mark_auth_failed()
                yield 'auth failed (401); backing off'
                return
            raise
        with LockedFile(self.index_file) as f:
            f.truncate()
            f.write(ujson.dumps(payload))
            yield 'downloaded index to %s' % f.filename
        self._clear_auth_failed()

    def show_index(self):
        data = self.index or {}
        if not isinstance(data, dict):
            data = {}
        if not QFeedsConfig().api_key:
            data['auth_status'] = 'no_key'
        elif self._auth_blocked():
            data['auth_status'] = 'failed'
        else:
            data['auth_status'] = 'ok'
        yield ujson.dumps(data)

    def fetch(self):
        if not os.path.exists(self.index_file):
            return
        with open(self.index_file, 'r') as fp:
            data = ujson.load(fp) or {}
        feeds = data.get('feeds', [])
        if not feeds:
            return

        index_dirty = False
        for feed in feeds:
            if not feed.get('licensed'):
                continue
            local_filename = "%s/%s.txt" % (self._target_dir, feed['feed_type'])
            updated_at_dt = (
                int(datetime.fromisoformat(feed['updated_at'].strip()).timestamp())
                if feed.get('updated_at') else 0
            )
            file_mtime = int(os.stat(local_filename).st_mtime) if os.path.exists(local_filename) else 0
            if updated_at_dt != file_mtime:
                with LockedFile(local_filename) as f:
                    counter = 0
                    for entry in Api().fetch(feed['feed_type']):
                        if counter == 0:
                            f.truncate()
                        f.write("%s\n" % entry)
                        counter += 1
                if counter > 0:
                    now_ts = int(time.time())
                    os.utime(local_filename, (now_ts, now_ts))
                    feed['updated_at'] = time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime(now_ts))
                    index_dirty = True
                    yield "downloaded %d entries into %s [%s]" % (counter, local_filename, feed['updated_at'])
                else:
                    yield "skipped %s [%s]" % (local_filename, feed.get('updated_at', 'n/a'))
            else:
                yield "skipped %s [%s]" % (local_filename, feed.get('updated_at', 'n/a'))

        if index_dirty:
            with LockedFile(self.index_file) as f:
                f.truncate()
                f.write(ujson.dumps(data))

    def firewall_load(self):
        for feed in self.index.get('feeds', []):
            if feed['licensed'] and os.path.exists(feed['local_filename']) and feed['type'] == 'ip':
                table_name = '__qfeeds_%s' % feed['feed_type']
                sp = subprocess.run(
                    ['/sbin/pfctl', '-t', table_name, '-T', 'replace', '-f', feed['local_filename']],
                    capture_output=True,
                    text=True
                )
                yield 'load feed %s [%s]' % (feed['feed_type'], sp.stderr.strip().replace("\n", " "))

    def unbound_load(self):
        bl_conf = '/usr/local/etc/unbound/qfeeds-blocklists.conf'
        bl_configured = os.path.exists(bl_conf) and os.path.getsize(bl_conf) > 20
        bl_stat = '/tmp/qfeeds-unbound-bl.stat'
        if bl_configured or os.path.exists(bl_stat):
            # when de-configuring domain lists, we need to reconfigure unbound on deselect, track an empty file to
            # detect that event (written by the unbound helper).
            if os.path.exists(bl_stat):
                os.remove(bl_stat)

            # when qfeeds-blocklists.conf is ~empty, skip updates
            subprocess.run(['/usr/local/sbin/configctl', 'unbound', 'dnsbl'])
            yield 'update unbound blocklist'

    def dnscryptproxy_load(self):
        script_path = '/usr/local/opnsense/scripts/dnscryptproxy/blocklists/qfeeds_bl.py'
        dnscrypt_proxy_dir = '/usr/local/etc/dnscrypt-proxy'
        if os.path.exists(script_path) and os.path.isdir(dnscrypt_proxy_dir):
            subprocess.run([script_path], capture_output=True, text=True)
            # Trigger dnscrypt-proxy DNSBL update to merge blacklist-qfeeds.txt
            # Only if DNSCrypt-proxy is installed (directory exists)
            result = subprocess.run(['/usr/local/sbin/configctl', 'dnscryptproxy', 'dnsbl'],
                                  capture_output=True, text=True)
            if result.returncode == 0:
                yield 'update dnscrypt-proxy blocklist'
            else:
                yield 'dnscrypt-proxy not available'
        elif not os.path.isdir(dnscrypt_proxy_dir):
            yield 'dnscrypt-proxy not installed'
        else:
            yield 'dnscrypt-proxy blocklist script not found'

    def update(self):
        if not QFeedsConfig().api_key:
            yield 'no api key configured; skipping'
            return
        if self._auth_blocked():
            yield 'auth cooldown active; skipping'
            return
        update_sleep = 99999
        try:
            index_payload = self.index
        except TypeError:
            # when the index can't be parsed, assume we have none while updating
            index_payload = {}
        do_update = len(index_payload.get('feeds', [])) == 0
        for feed in index_payload.get('feeds', []):
            update_sleep = min(feed['next_update_dt'] - time.time(), update_sleep)
            if feed['licensed'] and update_sleep <= 300: # 5 minute cron interval
                do_update = True
        if do_update:
                if 0 < update_sleep <= 300:
                    time.sleep(update_sleep)
                for action in ['fetch_index', 'fetch', 'firewall_load', 'unbound_load', 'dnscryptproxy_load']:
                    yield from getattr(self, action)()

    def stats(self):
        result = {'feeds': []}
        for feed in self.index.get('feeds', []):
            if feed['licensed'] and os.path.exists(feed['local_filename']) and feed['type'] == 'ip':
                table_name = '__qfeeds_%s' % feed['feed_type']
                sp = subprocess.Popen(
                    ['/sbin/pfctl', '-t', table_name, '-vT', 'show'],
                    stdout=subprocess.PIPE,
                    stderr=subprocess.DEVNULL,
                    text=True
                )
                record = {
                    'name': feed['feed_type'],
                    'total_entries': 0,
                    'packets_blocked': 0,
                    'bytes_blocked': 0,
                    'addresses_blocked': 0
                }
                while (line := sp.stdout.readline()):
                    if line.startswith(' '):
                        record['total_entries'] += 1
                    elif 'Packets:' in line and 'Packets: 0 ' not in line:
                        parts = line.split()
                        if parts[3].isdigit() and parts[5].isdigit() and parts[0].lower().find('block') > 0:
                            record['packets_blocked'] += int(parts[3])
                            record['bytes_blocked'] += int(parts[5])
                            record['addresses_blocked'] += 1

                result['feeds'].append(record)
        result['totals'] = {
            'entries': sum(r['total_entries'] for r in result['feeds']),
            # assumes no overlaps in datafeeds
            'addresses_blocked': sum(r['addresses_blocked'] for r in result['feeds']),
            'packets_blocked': sum(r['packets_blocked'] for r in result['feeds']),
            'bytes_blocked': sum(r['bytes_blocked'] for r in result['feeds']),
        }

        yield  ujson.dumps(result)

    def logs(self):
        feeds = []
        for feed in self.index.get('feeds', []):
            if feed['type'] == 'ip':
                feeds.append('__qfeeds_%s' % feed['feed_type'])

        yield ujson.dumps({'rows': PFLogCrawler(feeds).find()})
