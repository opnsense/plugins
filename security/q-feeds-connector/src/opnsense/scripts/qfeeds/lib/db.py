"""
    Copyright (c) 2026 Deciso B.V.
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
import glob
import sqlite3
import os
import hashlib
import ujson
from datetime import datetime


class DB:

    def __init__(self, dirname, readonly=True):
        self._dirname = dirname
        self._readonly = readonly
        self.open()

    def open(self):
        self.filename = "%s/qfeeds.db" % self._dirname
        if self._readonly:
            if os.path.exists(self.filename):
                self._connection = sqlite3.connect("file:%s?mode=ro" % self.filename, uri=True)
            else:
                self._connection =  sqlite3.connect(':memory:')
                # setup empty in-memory structure
                self.init()
        else:
            self._connection = sqlite3.connect(self.filename)
            self.init()

    def init(self):
        init_scriptfilename = '%s/../sql/setup.sql' % os.path.dirname(os.path.abspath(__file__))
        self._connection.executescript(open(init_scriptfilename, 'r').read())

    def load(self):
        for filename in glob.glob('%s/*.json' % self._dirname):
            if os.path.basename(filename) in ['index.json']:
                continue
            with open(filename, 'r') as f_in:
                updated_at = os.stat(filename).st_mtime
                try:
                    payload = ujson.load(f_in)
                except ujson.JSONDecodeError:
                    print('skip %s [invalid json]' % filename)
                    payload = None

                if type(payload) is not dict or payload.get('kind') not in ['ip', 'domain']:
                    # skip all but domain and ip types
                    continue
                meta = []
                meta_ids = {}
                if type(payload) is dict and type(payload.get('meta')) is dict:
                    for meta_class, metadata in payload.get('meta').items():
                        for meta_name, content in metadata.items():
                            meta_id = hashlib.md5(meta_name.encode(), usedforsecurity=False).digest()
                            meta_ids[content.get('id')] = meta_id
                            meta.append({
                                 'id': meta_id,
                                 'code': meta_name,
                                 'main_code': meta_name.split('.')[0],
                                 'category': meta_class,
                                 'payload': ujson.dumps(content),
                            })

                self._connection.executemany("""
                    insert into meta(id, code, category, payload)
                    values(:id,:code,:category,:payload)
                    on conflict(id) do update
                        set category = excluded.category,
                            payload = excluded.payload
                    """,
                    meta
                )

                if type(payload) is dict and payload.get('iocs'):
                    epoch = datetime.fromisoformat(payload.get('generated_at')).timestamp()
                    delivery_id = hashlib.md5(payload.get('feed').encode("utf-8"), usedforsecurity=False).digest()

                    known_keys = {
                        md5
                        for (md5, ) in self._connection.execute(
                            'select md5 from iocs where delivery_id = :1',
                            [delivery_id]
                        )
                    }

                    # enrich payload to ease executemany
                    ioc_meta = []
                    ioc_todo = []
                    for ioc, data in payload['iocs'].items():
                        this_ioc_meta = []
                        hash_payload = [ioc, str(data.get('t', ''))]
                        data['ioc'] = ioc
                        data['ioc_id'] =  hashlib.md5(ioc.encode("utf-8")).digest()
                        data['delivery_id'] = delivery_id
                        for this_id in data.get('m', []):
                            if this_id not in meta_ids:
                                continue # shouldn't happen, inconsitent data
                            this_ioc_meta.append({
                                'id': meta_ids[this_id],
                                'ioc_id': data['ioc_id']
                            })
                            hash_payload.append(meta_ids[this_id].hex())
                        data['md5'] = hashlib.md5(
                            ('|'.join(sorted(hash_payload))).encode('utf-8')
                        ).digest()
                        if data['md5'] in known_keys:
                            known_keys.discard(data['md5'])
                            continue

                        ioc_meta.extend(this_ioc_meta)
                        ioc_todo.append(data)

                    if len(known_keys) > 0:
                        # cleanup altered or deleted iocs
                        self._connection.execute('create temp table if not exists tmp_iocs_drop(md5 blob primary key)')
                        self._connection.execute('delete from tmp_iocs_drop')
                        self._connection.executemany(
                            'insert into tmp_iocs_drop(md5) values(?)',
                            ((key,) for key in known_keys)
                        )
                        self._connection.execute('delete from iocs where md5 in (select md5 from tmp_iocs_drop)')

                    self._connection.execute("""
                        insert into delivery(id, feed, generated_at, updated_at, kind)
                        values (:1,:2,:3,:4,:5)
                        on conflict(id) do update
                            set generated_at = excluded.generated_at,
                                updated_at = excluded.updated_at,
                                kind = excluded.kind
                        """,
                        [delivery_id, payload.get('feed'), epoch, updated_at, payload.get('kind')]
                    )
                    if len(ioc_todo) > 0:
                        self._connection.executemany("""
                            insert into iocs(id, delivery_id, ioc, ts, md5)
                            values(:ioc_id,:delivery_id,:ioc,:t,:md5)
                            on conflict(id) do update
                                set delivery_id = excluded.delivery_id,
                                    ts = excluded.ts,
                                    md5 = excluded.md5
                            """,
                            ioc_todo
                        )

                    if len(ioc_meta) > 0:
                        self._connection.executemany(
                            'insert or ignore into iocs_meta(id, ioc_id) values(:id,:ioc_id)',
                            ioc_meta
                        )

            self._connection.commit()
            # vacuum when > 10% can be reclaimed
            page_count = self._connection.execute("PRAGMA page_count").fetchone()[0]
            free_pages = self._connection.execute("PRAGMA freelist_count").fetchone()[0]
            if (free_pages / page_count if page_count else 0) >= 0.1:
                self._connection.execute('vacuum')

    def last_updated(self):
        return self._connection.execute('select max(updated_at) from delivery').fetchone()[0] or 0
