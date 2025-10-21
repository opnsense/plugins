"""
    Copyright (c) 2025 Deciso B.V.
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
from lib.api import Api
from lib.log import PFLogCrawler
from lib.file import LockedFile


class QFeedsActions:
    def __init__(self, target_dir, forced=False):
        self._target_dir = target_dir
        self._forced = forced

    @classmethod
    def list_actions(cls):
        return [
            'fetch_index',
            'fetch',
            'show_index',
            'firewall_load',
            'unbound_load',
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
        data = ujson.load(open(self.index_file)) or {}
        if type(data) is dict:
            for feed in data.get('feeds', []):
                feed['local_filename'] = "%s/%s.txt" % (self._target_dir, feed['feed_type'])
                feed['updated_at_dt'] = datetime.fromisoformat(feed['updated_at']).timestamp()
                feed['next_update_dt'] = datetime.fromisoformat(feed['next_update']).timestamp()

        return data

    def _file_stat(self, filename):
        if not os.path.exists(filename):
            return 0
        return os.stat(filename).st_mtime

    def fetch_index(self):
        if not os.path.isdir(self._target_dir):
            os.makedirs(self._target_dir)
        with LockedFile(self.index_file) as f:
            payload = Api().licenses()
            f.truncate()
            f.write(ujson.dumps(payload))
            yield 'downloaded index to %s' % f.filename

    def show_index(self):
        yield ujson.dumps(self.index)

    def fetch(self):
        for feed in self.index.get('feeds', []):
            if feed['licensed'] and feed['updated_at_dt'] != self._file_stat(feed['local_filename']):
                with LockedFile(feed['local_filename']) as f:
                    counter = 0
                    for entry in Api().fetch(feed['feed_type']):
                        if counter == 0:
                            f.truncate()
                        f.write("%s\n" % entry)
                        counter += 1
                os.utime(feed['local_filename'], (feed['updated_at_dt'], feed['updated_at_dt']))
                yield "downloaded %d entries into %s [%s]" % (counter, feed['local_filename'], feed['updated_at'])
            elif feed['licensed']:
                yield "skipped %s [%s]" % (feed['local_filename'], feed['updated_at'])

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
        if os.path.exists(bl_conf) and os.path.getsize(bl_conf) > 20:
            # when qfeeds-blocklists.conf is ~empty, skip updates
            subprocess.run(['/usr/local/sbin/configctl', 'unbound', 'dnsbl'])
            yield 'update unbound blocklist'

    def update(self):
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
                for action in ['fetch_index', 'fetch', 'firewall_load', 'unbound_load']:
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
