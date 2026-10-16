"""Isolated readiness, deadline and native-start compatibility regressions."""
import importlib.util
import json
from pathlib import Path
import socket
import struct
import subprocess
import tempfile
import threading
import unittest
from unittest.mock import patch

PLUGIN = Path(__file__).resolve().parents[1]
SCRIPTS = PLUGIN / 'src/opnsense/scripts/OPNsense/Nut'
spec = importlib.util.spec_from_file_location('startup', SCRIPTS / 'startup.py')
startup = importlib.util.module_from_spec(spec)
spec.loader.exec_module(startup)


def configuration(host='127.0.0.1', mode='standalone', enabled='1', managed='1', port='3551',
                  nis='1', bind='127.0.0.1', extra=''):
    return (f'<opnsense><OPNsense><Nut><general><enable>{enabled}</enable><mode>{mode}</mode>'
            f'</general><apcupsd><enable>1</enable><hostname>{host}</hostname><port>{port}</port>'
            f'</apcupsd>{extra}</Nut><apcupsd><general><Enabled>{managed}</Enabled>'
            f'<Netserver>{nis}</Netserver><NetserverAddress>{bind}</NetserverAddress>'
            f'<NetserverPort>{port}</NetserverPort></general></apcupsd></OPNsense></opnsense>')


class SelectionTests(unittest.TestCase):
    def selected(self, **values):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'config.xml'
            path.write_text(configuration(**values))
            return startup.selected(path)

    def test_explicit_local_custom_ports(self):
        for host in ('localhost', '127.0.0.1', '::1', '[::1]'):
            self.assertEqual(self.selected(host=host, port='13553')[:2], (host.strip('[]'), 13553))

    def test_remote_alias_and_non_loopback_local_addresses_are_unchanged(self):
        for host in ('192.0.2.1', 'ups.example.test', '127.0.0.2', 'LOCALHOST'):
            self.assertIsNone(self.selected(host=host))

    def test_disabled_unmanaged_and_netclient_are_unchanged(self):
        for values in ({'enabled':'0'}, {'managed':'0'}, {'mode':'netclient'}):
            self.assertIsNone(self.selected(**values))

    def test_missing_apcupsd_model_is_unchanged(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'config.xml'
            path.write_text('<opnsense><OPNsense><Nut/></OPNsense></opnsense>')
            self.assertIsNone(startup.selected(path))

    def test_multiple_drivers_are_not_managed_speculatively(self):
        with self.assertRaisesRegex(startup.NotReady, 'multiple_local_drivers'):
            self.selected(extra='<snmp><enable>1</enable></snmp>')

    def test_nis_disabled_and_invalid_ports_are_rejected(self):
        for values in ({'nis':'0'}, {'port':'0'}, {'port':'65536'}):
            with self.assertRaises(startup.NotReady):self.selected(**values)


class EndpointTests(unittest.TestCase):
    def test_literal_ipv4_and_ipv6_preserve_ports(self):
        end = startup.time.monotonic() + 2
        self.assertEqual(startup.endpoint(('127.0.0.1', 13551, '0.0.0.0'), end),
                         (socket.AF_INET, ('127.0.0.1',13551)))
        self.assertEqual(startup.endpoint(('::1',13553,'::'), end),
                         (socket.AF_INET6,('::1',13553,0,0)))

    def test_bind_mismatch_does_not_start_service(self):
        for host,bind in [('::1','127.0.0.1'),('127.0.0.1','127.0.0.2')]:
            with self.assertRaisesRegex(startup.NotReady,'bind_mismatch'):
                startup.endpoint((host,3551,bind),startup.time.monotonic()+2)

    def test_localhost_uses_same_first_address_as_native_driver(self):
        rows=[[socket.AF_INET6,1,6,'',['::1',3551,0,0]], [socket.AF_INET,1,6,'',['127.0.0.1',3551]]]
        with patch.object(startup.subprocess,'run',return_value=subprocess.CompletedProcess([],0,json.dumps(rows))) as run:
            self.assertEqual(startup.endpoint(('localhost',3551,'::'),startup.time.monotonic()+2)[1],('::1',3551,0,0))
        self.assertLessEqual(run.call_args.kwargs['timeout'],2)

    def test_mixed_localhost_resolution_is_rejected(self):
        rows=[[socket.AF_INET,1,6,'',['127.0.0.1',3551]], [socket.AF_INET,1,6,'',['192.0.2.1',3551]]]
        with patch.object(startup.subprocess,'run',return_value=subprocess.CompletedProcess([],0,json.dumps(rows))):
            with self.assertRaisesRegex(startup.NotReady,'not_loopback'):
                startup.endpoint(('localhost',3551,'0.0.0.0'),startup.time.monotonic()+2)

    def test_dns_timeout_cannot_reach_service_start(self):
        with patch.object(startup.subprocess,'run',side_effect=subprocess.TimeoutExpired('resolver',1)) as run:
            with self.assertRaises(subprocess.TimeoutExpired):startup.wait_ready(('localhost',3551,'127.0.0.1'))
            self.assertEqual(run.call_count,1)


class ProtocolTests(unittest.TestCase):
    def probe(self, text, complete=True, fragmented=False, announced=None):
        listener=socket.socket();listener.bind(('127.0.0.1',0));listener.listen()
        requests=[]
        def server():
            with listener:
                connection,_=listener.accept()
                with connection:
                    request=connection.recv(8)
                    while len(request)<8:request+=connection.recv(8-len(request))
                    requests.append(request)
                    data=text.encode();frame=struct.pack('!H',len(data) if announced is None else announced)+data
                    if complete:frame+=b'\0\0'
                    try:
                        if fragmented:
                            for byte in frame:connection.sendall(bytes([byte]))
                        else:connection.sendall(frame)
                    except OSError:pass
        worker=threading.Thread(target=server);worker.start()
        try:startup.probe((socket.AF_INET,listener.getsockname()),startup.time.monotonic()+2)
        finally:worker.join(3)
        self.assertEqual(requests,[b'\0\6status'])

    def test_fragmented_complete_response(self):
        self.probe('APC : header\nSTATUS : ONLINE\nEND APC : date\n',fragmented=True)

    def test_valid_battery_state_preserves_native_monitor_policy(self):
        self.probe('APC : header\nSTATUS : ONBATT LOWBATT\nEND APC : date\n')

    def test_commlost_with_and_without_spaces_is_not_ready(self):
        for flags in ('COMMLOST','COMMLOST ','ONLINE COMMLOST'):
            with self.assertRaisesRegex(startup.NotReady,'communication_lost'):
                self.probe('APC : header\nSTATUS : '+flags+'\nEND APC : date\n')

    def test_tcp_or_http_response_is_not_readiness(self):
        with self.assertRaises(startup.NotReady):self.probe('HTTP/1.0 200 OK\n')

    def test_empty_or_unknown_status_is_rejected(self):
        for flags in ('','BOGUS'):
            with self.assertRaisesRegex(startup.NotReady,'invalid_nis_status'):
                self.probe('APC : header\nSTATUS : '+flags+'\nEND APC : date\n')

    def test_truncated_payload_and_missing_terminator_are_rejected(self):
        for announced in (None,200):
            with self.assertRaisesRegex(startup.NotReady,'incomplete_nis_response'):
                self.probe('APC : header\nSTATUS : ONLINE\n',complete=False,announced=announced)


class LifecycleTests(unittest.TestCase):
    def test_running_apcupsd_is_not_started_or_restarted(self):
        with patch.object(startup.subprocess,'run',return_value=subprocess.CompletedProcess([],0)) as run, \
                patch.object(startup,'probe'):
            startup.wait_ready(('127.0.0.1',3551,'127.0.0.1'))
        self.assertEqual([x.args[0][-1] for x in run.call_args_list],['status'])

    def test_absent_enabled_service_uses_only_normal_rc_start(self):
        outcomes=[subprocess.CompletedProcess([],1),subprocess.CompletedProcess([],0)]
        with patch.object(startup.subprocess,'run',side_effect=outcomes) as run,patch.object(startup,'probe'):
            startup.wait_ready(('127.0.0.1',3551,'127.0.0.1'))
        self.assertEqual([x.args[0][-1] for x in run.call_args_list],['status','start'])

    def test_delay_is_bounded_without_restarting_service(self):
        clock=[0]
        def probe(target,end):
            startup.left(end)
            raise startup.NotReady('apcupsd_communication_lost')
        with patch.object(startup.time,'monotonic',side_effect=lambda:clock[0]), \
                patch.object(startup.time,'sleep',side_effect=lambda n:clock.__setitem__(0,clock[0]+n)), \
                patch.object(startup.subprocess,'run',return_value=subprocess.CompletedProcess([],0)) as run, \
                patch.object(startup,'probe',side_effect=probe):
            with self.assertRaisesRegex(startup.NotReady,'readiness_timeout'):
                startup.wait_ready(('127.0.0.1',3551,'127.0.0.1'))
        self.assertEqual(clock[0],30)
        self.assertEqual(run.call_count,1)

    def test_late_readiness_within_budget_succeeds(self):
        with patch.object(startup.subprocess,'run',return_value=subprocess.CompletedProcess([],0)), \
                patch.object(startup,'probe',side_effect=[OSError(),None]) as probe, \
                patch.object(startup.time,'sleep'):
            startup.wait_ready(('127.0.0.1',3551,'127.0.0.1'))
        self.assertEqual(probe.call_count,2)

    def test_managed_start_propagates_failure_without_starting_monitor(self):
        with patch.object(startup.subprocess,'call',return_value=7) as call:
            self.assertEqual(startup.start_services(True),7)
        self.assertEqual(call.call_count,1)

    def test_non_target_start_preserves_legacy_sequence_and_return(self):
        with patch.object(startup.subprocess,'call',side_effect=[7,0]) as call:
            self.assertEqual(startup.start_services(False),0)
        self.assertEqual([x.args[0][0] for x in call.call_args_list],
                         ['/usr/local/etc/rc.d/nut','/usr/local/etc/rc.d/nut_upsmon'])

    def test_non_target_readiness_has_no_service_or_network_actions(self):
        with patch.object(startup,'selected',return_value=None),patch.object(startup,'wait_ready') as wait:
            self.assertEqual(startup.main([]),0)
        wait.assert_not_called()

    def test_parent_shell_exit_prevents_native_driver_precmd(self):
        with tempfile.TemporaryDirectory() as directory:
            root=Path(directory);setup=root/'setup';ready=root/'ready';hook=root/'hook'
            setup.write_text('#!/bin/sh\nexit 0\n');ready.write_text('#!/bin/sh\nexit 1\n')
            setup.chmod(0o755);ready.chmod(0o755)
            text=(SCRIPTS/'rc_setup.sh').read_text().replace('/usr/local/opnsense/scripts/OPNsense/Nut/setup.sh',str(setup)).replace('/usr/local/opnsense/scripts/OPNsense/Nut/startup.py',str(ready))
            hook.write_text(text)
            result=subprocess.run(['/bin/sh','-c','rc_arg=start; . "$1"; echo DRIVER_STARTED','sh',str(hook)],capture_output=True,text=True)
            self.assertEqual(result.returncode,1)
            self.assertNotIn('DRIVER_STARTED',result.stdout)

    def test_shutdown_templates_and_model_are_original(self):
        text=(PLUGIN/'src/opnsense/service/templates/OPNsense/Nut/upsmon.conf').read_text()
        self.assertIn('SHUTDOWNCMD "/usr/local/etc/rc.halt"',text)
        self.assertNotIn('shutdown.sh',text)
        self.assertFalse((SCRIPTS/'nut_control.py').exists())
        model=(PLUGIN/'src/opnsense/mvc/app/models/OPNsense/Nut/Nut.xml').read_text()
        self.assertIn('<version>1.0.4</version>',model)
        self.assertNotIn('Recovery',model)



try:
    import jinja2
except ImportError:
    jinja2=None


@unittest.skipIf(jinja2 is None,'Jinja2 is required for template execution')
class TemplateTests(unittest.TestCase):
    def render(self,name,host='127.0.0.1',enabled='1',managed='1',mode='standalone',driver='apcupsd',port=''):
        nut={'general':{'enable':enabled,'mode':mode,'name':'LabUPS'},driver:{'enable':'1','hostname':host,'port':port}}
        config={'OPNsense':{'Nut':nut,'apcupsd':{'general':{'Enabled':managed}}}}
        class Helpers:
            def get(self,path):
                value=config
                for part in path.split('.'):value=value.get(part,{}) if isinstance(value,dict) else {}
                return value if not isinstance(value,dict) else ''
            def exists(self,path):return self.get(path)!=''
            def host_with_port(self,host_tag,port_tag,brackets_on_bare_ipv6=False):
                host,port=self.get(host_tag),self.get(port_tag)
                if ':' in host and not host.startswith('[') and (port or brackets_on_bare_ipv6):host='['+host+']'
                return host+(':'+port if port else '')
            def host_for_port(self,host_tag):return self.host_with_port(host_tag,'',True)
        env=jinja2.Environment(loader=jinja2.FileSystemLoader(PLUGIN/'src/opnsense/service/templates/OPNsense/Nut'))
        return env.get_template(name).render(config,helpers=Helpers())

    def test_defer_only_for_enabled_explicit_local_dependency(self):
        for name in ('nut','nut_upsmon'):
            for host in ('localhost','127.0.0.1','::1','[::1]'):
                self.assertIn(name+'_defer="YES"',self.render(name,host=host))

    def test_remote_disabled_unmanaged_and_netclient_have_no_defer(self):
        for name in ('nut','nut_upsmon'):
            for values in ({'host':'192.0.2.1'},{'host':'alias.example.test'},{'managed':'0'},{'enabled':'0'},{'mode':'netclient'}):
                self.assertNotIn('_defer=',self.render(name,**values))

    def test_other_drivers_have_no_defer(self):
        for driver in ('usbhid','snmp','apcsmart'):
            for name in ('nut','nut_upsmon'):self.assertNotIn('_defer=',self.render(name,driver=driver))

    def test_ipv6_and_custom_port_reach_native_driver(self):
        for host in ('::1','[::1]'):
            self.assertIn('port=[::1]:13553',self.render('ups.conf',host=host,port='13553'))
            self.assertIn('port=[::1]',self.render('ups.conf',host=host))

    def test_setup_hook_is_sourced_only_for_server(self):
        self.assertIn('nut_setup=". ',self.render('nut'))
        self.assertNotIn('_setup=',self.render('nut_upsmon'))


if __name__=='__main__':unittest.main()
