#!/usr/local/bin/python2.7

"""
    Copyright (c) 2018 Verb Networks Pty Ltd <contact@verbnetworks.com>
    Copyright (c) 2018 Nicholas de Jong <me@nicholasdejong.com>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without modification,
    are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice, this
       list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright notice,
       this list of conditions and the following disclaimer in the documentation
       and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
    ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
    WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
    DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
    ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
    (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
    LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
    ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
    (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
    SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
"""

import os
import boto3
import argparse
from StorageInterface import StorageInterface


class StorageInterfaceS3CompatibleException(Exception):
    pass


class StorageInterfaceS3Compatible(StorageInterface):

    storage_use_gzip_encoding=True
    storage_use_local_cache=True

    __aws_key_id = None
    __aws_key_secret = None
    __endpoint_url = None

    def main(self, config_section):

        parser = argparse.ArgumentParser(description='S3 compatible storage interface for ConfigSync')
        parser.add_argument('action',
            type=str,
            choices=['test_parameters', 'sync_current', 'sync_all', 'get_synced'],
            help='Interface action requested'
        )
        parser.add_argument('--key_id', type=str, help='Overrides "provider_key" configsync.conf parameter')
        parser.add_argument('--key_secret', type=str, help='Overrides "provider_secret" configsync.conf parameter')
        parser.add_argument('--bucket', type=str, help='Overrides "storage_bucket" configsync.conf parameter')
        parser.add_argument('--path_prefix', type=str, help='Overrides "storage_path_prefix" configsync.conf parameter')
        parser.add_argument('--filter', type=str, help='Filter expression applied to get_synced action')
        parser.add_argument('--no_cache', action='store_true', help='Prevent use of local cache files')

        args = parser.parse_args()

        if config_section == 'aws_s3':
            self.__endpoint_url = 'https://s3.amazonaws.com'
        elif config_section == 'do_spaces':
            self.__endpoint_url = 'https://nyc3.digitaloceanspaces.com'
        elif config_section == 'gc_storage':
            self.__endpoint_url = 'https://storage.googleapis.com'
        else:
            return {'status': 'fail', 'message': 'Unsupported storage provider', 'data': config_section}

        if not os.path.isfile(self.system_config_current_file):
            return {'status': 'fail', 'message': 'Unable to locate system configuration file',
                    'data': self.system_config_current_file}

        if args.action == 'test_parameters':
            # test parameters are only possible via args, not from configsync.conf
            self.__aws_key_id = args.key_id
            self.__aws_key_secret = args.key_secret
            return self.test_parameters(bucket=args.bucket, path_prefix=args.path_prefix)

        if not os.path.isdir(self.system_config_backups_path):
            return {'status': 'fail', 'message': 'Unable to locate configuration backups path',
                    'data': self.system_config_backups_path}

        config = self.read_config(config_section)
        if config is None:
            return {'status': 'fail', 'message': 'No configuration for {} available'.format(config_section) }

        if args.key_id is not None:
            self.__aws_key_id = args.key_id
        else:
            self.__aws_key_id = config['provider_key']

        if args.key_secret is not None:
            self.__aws_key_secret = args.key_secret
        else:
            self.__aws_key_secret = config['provider_secret']

        if args.bucket is not None:
            storage_bucket = args.bucket
        else:
            storage_bucket = config['storage_bucket']

        if args.path_prefix is not None:
            storage_path_prefix = args.path_prefix
        else:
            storage_path_prefix = config['storage_path_prefix']

        if args.no_cache is True:
            self.storage_use_local_cache = False

        if args.action == 'sync_current':
            return self.sync_current(bucket=storage_bucket, path_prefix=storage_path_prefix)
        elif args.action == 'sync_all':
            return self.sync_all(bucket=storage_bucket, path_prefix=storage_path_prefix)
        elif args.action == 'get_synced':
            return self.get_synced(bucket=storage_bucket, path_prefix=storage_path_prefix, string_filter=args.filter)

        return {'status': 'fail', 'message': 'Unable to invoke StorageInterfaceS3Compatible'}

    def test_parameters(self, bucket, path_prefix):

        if self.__aws_key_id is None or len(self.__aws_key_id) == 0:
            return {'status': 'fail', 'message': 'Parameter key_id cannot be empty'}
        if self.__aws_key_secret is None or len(self.__aws_key_secret) == 0:
            return {'status': 'fail', 'message': 'Parameter key_secret cannot be empty'}
        if bucket is None or len(bucket) == 0:
            return {'status': 'fail', 'message': 'Parameter bucket cannot be empty'}
        if path_prefix is None or len(path_prefix) == 0:
            return {'status': 'fail', 'message': 'Parameter path_prefix cannot be empty'}

        try:
            content, meta = self.read_consistent(self.system_config_current_file, return_meta=['mtime', 'md5', 'bytes'])
        except(Exception) as e:
            return {
                'status': 'fail',
                'message': 'Unable to obtain consistent read of {}'.format(self.system_config_current_file),
                'data': str(e)
            }

        return self.__put_object(
            bucket=bucket,
            path=os.path.join(path_prefix, 'config-current.xml'),
            content=content if self.storage_use_gzip_encoding is not True else self.gzip_content(content),
            content_encoding=None if self.storage_use_gzip_encoding is not True else 'gzip',
            content_type='application/xml',
            object_tags={
                'filetype': 'opnsense-config',
                'mtime': str(meta['mtime']),
                'bytes': str(meta['bytes']),
                'md5': str(meta['md5']),
                'hostid': self.get_system_hostid(),
                'hostname': self.get_system_hostname(),
            }
        )

    def sync_current(self, bucket, path_prefix):
        configs = [(self.system_config_current_file, 'config-current.xml')]
        return self.__sync_configs(bucket, path_prefix, configs, overwrite_existing=True)

    def sync_all(self, bucket, path_prefix):
        configs = [(self.system_config_current_file, 'config-current.xml')]
        for backup_file in os.listdir(self.system_config_backups_path):
            if backup_file.endswith('.xml'):
                configs.append((os.path.join(self.system_config_backups_path, backup_file), backup_file))
        return self.__sync_configs(bucket, path_prefix, configs, overwrite_existing=False)

    def get_synced(self, bucket, path_prefix, string_filter=None):
        response = self.__list_objects(bucket=bucket, path_prefix=path_prefix, use_cached=self.storage_use_local_cache)
        if response['status'] != 'success':
            return response
        sorted_items = []
        for filename_key in sorted(response['data'].keys(), reverse=True):
            if string_filter is None or len(string_filter) == 0:
                sorted_items.append(response['data'][filename_key])
            else:
                for item_k, item_v in response['data'][filename_key].items():
                    if string_filter in item_v:
                        sorted_items.append(response['data'][filename_key])
                        break
        response['data'] = sorted_items
        return response

    def __sync_configs(self, bucket, path_prefix, configs, overwrite_existing=False):
        existing_configs = {}
        if overwrite_existing is False:
            list_response = self.__list_objects(bucket=bucket, path_prefix=path_prefix, use_cached=self.storage_use_local_cache)
            if list_response['status'] != 'success':
                return list_response
            existing_configs = list_response['data']

        target_paths = []
        for config_item in configs:
            source_path, target_filename = config_item

            if overwrite_existing is False:
                if target_filename in existing_configs.keys():
                    continue

            try:
                content, meta = self.read_consistent(source_path, return_meta=['mtime', 'md5', 'bytes'])
            except(Exception) as e:
                return {
                    'status': 'fail',
                    'message': 'Unable to obtain consistent read of {}'.format(source_path),
                    'data': str(e)
                }

            target_path = os.path.join(path_prefix, target_filename)
            target_paths.append(target_path)

            response = self.__put_object(
                bucket=bucket,
                path=target_path,
                content=content if self.storage_use_gzip_encoding is not True else self.gzip_content(content),
                content_encoding=None if self.storage_use_gzip_encoding is not True else 'gzip',
                content_type='application/xml',
                object_tags={
                    'filetype': 'opnsense-config',
                    'mtime': str(meta['mtime']),
                    'bytes': str(meta['bytes']),
                    'md5': str(meta['md5']),
                    'hostid': self.get_system_hostid(),
                    'hostname': self.get_system_hostname(),
                }
            )

            if response['status'] != 'success':
                return response

        if len(target_paths) > 0:
            self.__list_objects(bucket=bucket, path_prefix=path_prefix, use_cached=False)  # causes a cache update
            return {'status': 'success', 'message': 'Successfully PUT {} S3 objects'.format(
                len(target_paths)
            ), 'data': target_paths}

        return {'status': 'success', 'message': 'Zero files were required to be PUT as S3 objects'}

    def __put_object(self, bucket, path, content, content_type=None, content_encoding=None, object_tags=None, acl='private'):

        client_params = {
            'ACL': acl,
            'Body': content,
            'Bucket': bucket,
            'Key': path,
            'ContentLength': len(content),
            'ContentMD5': self.content_digest(content, digest_method='md5', output_type='base64'),
        }

        if content_type is not None:
            client_params['ContentType'] = content_type

        if content_encoding is not None:
            client_params['ContentEncoding'] = content_encoding

        if object_tags is not None and type(object_tags) is dict:
            client_params['Metadata'] = {}
            for tag_key, tag_value in object_tags.items():
                client_params['Metadata'][tag_key] = tag_value

        client = boto3.client(
            's3',
            aws_access_key_id=self.__aws_key_id,
            aws_secret_access_key=self.__aws_key_secret,
            endpoint_url=self.__endpoint_url
        )

        try:
            response = client.put_object(**client_params)
        except(Exception) as e:
            return {
                'status': 'fail',
                'message': 'Exception',
                'data': str(e)
            }

        if 'VersionId' in response:
            return {
                'status': 'success',
                'message': 'Successful S3 object PUT',
                'data': path
            }
        return {
            'status': 'fail',
            'message': 'Response data not in an expected format',
            'data': str(response)
        }

    def __list_objects(self, bucket, path_prefix, continuation_token=None, max_keys_per_request=1000, use_cached=False):

        client_params = {
            'Bucket': bucket,
            'MaxKeys': max_keys_per_request,
            'Prefix': path_prefix,
        }
        if continuation_token is not None:
            client_params['ContinuationToken'] = continuation_token

        if use_cached is True:
            cached = self.get_cache(keydata=client_params, prefix='list_objects')
            if cached is not None:
                return {
                    'status': 'success',
                    'message': 'Successful S3 object list loaded from cache',
                    'data': cached
                }

        client = boto3.client(
            's3',
            aws_access_key_id=self.__aws_key_id,
            aws_secret_access_key=self.__aws_key_secret,
            endpoint_url=self.__endpoint_url
        )

        try:
            response = client.list_objects_v2(**client_params)
        except(Exception) as e:
            return {
                'status': 'fail',
                'message': 'Exception',
                'data': str(e)
            }

        if 'Contents' in response:
            contents = {}
            for item in response['Contents']:
                item['ETag'] = item['ETag'].strip('"')
                item['LastModified'] = self.normalize_timestamp(item['LastModified'])

                # a messy yet simple hack indeed
                filename = os.path.basename(item['Key'])
                if filename.startswith('config-') and filename.endswith('.xml'):
                    timestamp = filename.lower().replace('config-','').replace('.xml','')
                    item['Created'] = self.normalize_timestamp(timestamp)
                else:
                    item['Created'] = filename
                contents[filename] = item

            if 'NextContinuationToken' in response:
                next_token = response['NextContinuationToken']
                next_data = self.__list_objects(bucket, path_prefix, continuation_token=next_token, max_keys_per_request=max_keys_per_request)
                if next_data['status'] != 'success':
                    return {'status': 'fail', 'message': next_data['message'], 'data': next_data['data']}
                contents.update(next_data['data'])

            if continuation_token is None:
                self.set_cache(data=contents, keydata=client_params, prefix='list_objects')
            return {
                'status': 'success',
                'message': 'Successful S3 object list GET',
                'data': contents
            }

        if 'KeyCount' in response and response['KeyCount'] == 0:
            return {
                'status': 'fail',
                'message': 'No S3 objects found using the provided path_prefix value',
                'data': path_prefix
            }

        return {
            'status': 'fail',
            'message': 'Response data not in an expected format',
            'data': str(response)
        }
