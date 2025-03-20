"""
    Copyright (c) 2024 Trevor MacPhail <trevor@macphail.net>
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
    Dynamic DNS updating with Dreamhost API: 
    https://help.dreamhost.com/hc/en-us/articles/217555707-DNS-API-commands

"""
import syslog
import requests
import uuid
import json
from . import BaseAccount

class DHException(Exception):
    def __init__(self,cmd: str ,cmd_args: dict,message: str):
        super().__init__(message)
        self.cmd = cmd
        self.cmd_args = cmd_args
        self.message = message

class DNSRecord:
    # record_exists -> does the record already exist at Dreamhost (set to true if originating from dns-list_records or otherwise known to exist)
    def __init__(self,record:str,type:str,value:str,comment:str = None, editable:bool = True, record_exists:bool = False,is_verbose:bool = False):
        self._record = record
        self.__orig_record = record if record_exists else None
        self._type = type
        self.__orig_type = type if record_exists else None
        self._value = value
        self.__orig_value = value if record_exists else None
        self._comment = comment
        self.__orig_comment = comment if record_exists else None
        self._editable = editable
        self.__orig_record_exists = record_exists
        self.is_verbose = is_verbose

    @property
    def Record(self):
        return self._record

    @Record.setter
    def Record(self,value:str):
        if not self._editable:
            raise Exception("Record is not editable.")
        self._record = value

    @property
    def Value(self):
        return self._value

    @Value.setter
    def Value(self,value:str):
        if not self._editable:
            raise Exception("Record is not editable.")
        self._value = value

    @property
    def Type(self):
        return self._type

    @Type.setter
    def Type(self,value:str):
        if not self._editable:
            raise Exception("Record is not editable.")
        self._type = value

    @property
    def Comment(self):
        return self._comment

    @Comment.setter
    def Comment(self,value:str):
        if not self._editable:
            raise Exception("Record is not editable.")
        self._comment = value

    @property
    def Editable(self):
        return self._editable
    
    def has_chanegs(self):
        return self._record  != self.__orig_record or \
               self._value   != self.__orig_value  or \
               self._type    != self.__orig_type   or \
               self._comment != self.__orig_comment
    
    def save(self, api):
        if not self.has_chanegs():
            return
        self.remove_original_record(api)
        api.add_record(record  = self._record, \
                       value   = self._value,  \
                       type    = self._type,   \
                       comment = self._comment)
        if self.is_verbose:
            syslog.syslog(syslog.LOG_INFO, "Added new '%s' record for '%s' with value '%s'." % (self._type, self._record, self._value))
        self.__orig_record  = self._record
        self.__orig_type    = self._type
        self.__orig_value   = self._value
        self.__orig_comment = self._comment
        self.__orig_record_exists = True

    def remove_original_record(self, api):
        if self.__orig_record_exists:
            api.remove_record(record  = self.__orig_record, \
                              value   = self.__orig_value,  \
                              type    = self.__orig_type)
            if self.is_verbose:
                syslog.syslog(syslog.LOG_INFO, "Deleted old '%s' record for '%s' with value '%s'." % (self.__orig_type, self.__orig_record, self.__orig_value))
            self.__orig_record_exists = False
        pass        

class DreamhostDNS:
    def __init__(self,key:str,is_verbose:bool):
        self.is_verbose = is_verbose
        self._baseurl = "https://api.dreamhost.com"
        self._key = key
        if not self._has_needed_methods():
            raise Exception("One or more needed dns-* API commands not accessible with the provided API Key")
     
    def _rawquery(self,cmd:str,args:dict={}):
        args.update({'cmd':cmd,'key':self._key,'unique_id':str(uuid.uuid4())})
        response = requests.get(self._baseurl,params=args)
        return response.text

    def JSONQuery(self,cmd:str,args:dict={}):
        sendArgs = args.copy()
        sendArgs.update({"format":"json"})
        response = self._rawquery(cmd,sendArgs)

        obfuscated = sendArgs.copy()
        obfuscated['key']='*'*len(obfuscated['key'])
        if self.is_verbose:
            syslog.syslog(syslog.LOG_DEBUG, "Sent request '%s' with args: '%s'." % (cmd,str(obfuscated)))

        j = json.loads(response)
        if j['result'] == 'success':
            return j['data']
        else:
            raise DHException(cmd,obfuscated,j['data'])
        
    def _has_needed_methods(self):
        methods = [method["cmd"] for method in self.JSONQuery("api-list_accessible_cmds")]
        neededMethods = ["dns-list_records","dns-add_record","dns-remove_record"]
        return all(needed in methods for needed in neededMethods)
    
    def list_records(self):
        records = self.JSONQuery("dns-list_records")
        return[DNSRecord(record = r['record'],type = r['type'],value = r['value'],comment = r['comment'],editable = r['editable']=="1",record_exists = True,is_verbose=self.is_verbose) for r in records]
    
    #Filter a list of records from list_records based on record contents
    @staticmethod
    def filter_records(recordList:list[DNSRecord], record:str = None,type:str = None,value:str = None, editable:bool = None):
        return [r for r in recordList\
                if ((record is None or record == r.Record) and\
                    (type is None or type == r.Type) and\
                    (value is None or value == r.Value) and\
                    (editable is None or editable == r.Editable)) ]
    
    def add_record(self,record:str,type:str,value:str,comment:str = None):
        args={"record":record,
              "type":type,
              "value":value}
        if comment:
             args["comment"] = comment
        return self.JSONQuery(cmd="dns-add_record", args=args)
    
    def remove_record(self,record:str,type:str,value:str):
        args={"record":record,
              "type":type,
              "value":value}
        return self.JSONQuery(cmd="dns-remove_record", args=args)

class DreamhostDDClient(BaseAccount):
    _services = ['dreamhost']

    def __init__(self, account: dict):
        super().__init__(account)

        # Consider making this configurable.
        # False => All records matching the hostname and target record type will be replaced with a new record.
        # True  => Only records matching the hostname and old IP will be replaced.
        self.match_old_ip = False

    @staticmethod
    def known_services():
        return DreamhostDDClient._services

    @staticmethod
    def match(account):
        return account.get('service') in DreamhostDDClient._services
    
    @staticmethod
    def record_type(ip:str):
        return 'AAAA' if ':' in ip else 'A'

    def execute(self):
        if super().execute():

            hostnames = [h.strip() for h in self.settings['hostnames'].split(',')]
            if self.is_verbose:
                syslog.syslog(syslog.LOG_INFO, "Updating records for hostnames: '%s' to '%s'" % (str(hostnames),self.current_address))
            if not self.current_address:
                return True

            api = DreamhostDNS(self.settings['password'],self.is_verbose)

            oldAddress = self._state.get('ip')
            newRecordType = DreamhostDDClient.record_type(self.current_address)

            # If not matching old IP, removing all records matching the target type
            oldRecordType = DreamhostDDClient.record_type(oldAddress) if self.match_old_ip else newRecordType
            
            #only deal with records matching the old type
            records = DreamhostDNS.filter_records(api.list_records(), type = oldRecordType, value = oldAddress if self.match_old_ip else None)
           
            success = True
            for h in hostnames:
                try:
                    # Filter for the specific hostname we're changing
                    specificRecord = DreamhostDNS.filter_records(records,record=h)
                    if len(specificRecord) == 0:
                        # No matching records, create a new one
                        r = DNSRecord(record = h, type = newRecordType, value = self.current_address, comment = "Dynamic IP from OPNsense Dynamic DNS")
                        r.save(api)
                    else:
                        if self.match_old_ip:
                            r = specificRecord[0]
                            if not r.Editable:
                                syslog.syslog(syslog.LOG_ERR, "'%s' record for '%s' with value '%s' not editable." % (r.Type, r.Record, r.Value))
                                continue
                            if len(specificRecord) > 1:
                                # Shouldn't happen
                                syslog.syslog(syslog.LOG_INFO, "Extra '%s' records for '%s' with value '%s' exist. Only only attempting to replace one." % (r.Type, r.Record, r.Value))

                            # Update just the one matching record
                            r.Type = newRecordType
                            r.Value = self.current_address
                            if not r.Comment:
                                r.Comment = "Dynamic IP from OPNsense Dynamic DNS" # Don't overwrite comment if one exists
                            r.save(api)
                        else:
                            # Remove all old records and create a new one
                            newComment = None
                            for r in specificRecord:
                                if not newComment:
                                    newComment = r.Comment # Preserve first returned old comment
                                r.remove_original_record(api)
                            if not newComment:
                                newComment = "Dynamic IP from OPNsense Dynamic DNS" # Don't overwrite comment if one exists
                            r = DNSRecord(record = h, type = newRecordType, value = self.current_address, comment = newComment)
                            r.save(api)

                except DHException as e:
                    syslog.syslog(syslog.LOG_ERR, "Error updating '%s' record for '%s'. Request: %s Response: %s" % (oldRecordType, h, e.cmd, str(e.cmd_args), e.message))
                    success = False
                except Exception as e:
                    syslog.syslog(syslog.LOG_ERR, "Error updating '%s' record for '%s'. Message: %s" % (oldRecordType, h, str(e)))
                    success = False
                if success:
                    self.update_state(address=self.current_address)
            return success
        return True

    