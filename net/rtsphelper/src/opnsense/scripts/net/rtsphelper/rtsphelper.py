#!/usr/bin/python
import socket
import select
import time
import sys
import os
import signal
import subprocess

buffer_size = 4096
delay = 0.0001

config_file = '/var/etc/rtsphelper.conf'
config = {}

FNULL = open(os.devnull, 'w')

class Forward:
    def __init__(self):
        self.forward = socket.socket(socket.AF_INET, socket.SOCK_STREAM)

    def start(self, host, port):
        try:
            self.forward.connect((host, port))
            return self.forward
        except Exception as e:
            print(e)
            return False

class ProxyServer:
    input_list = []
    channel = {}
    clients = []

    forward_to = []

    perms = []

    def __init__(self, remoteHost, remotePort, portManager, perms):
        self.pm = portManager
        self.forward_to = [remoteHost, remotePort]
        self.perms = perms
        self.server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        self.server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        self.server.bind(('127.0.0.1', 0))
        self.server.listen(200)
        self.pm.addLocalBinding(remoteHost, remotePort, self.server.getsockname()[1])
        self.input_list.append(self.server)

    def main_loop(self):
        ss = select.select
        inputready, outputready, exceptready = ss(self.input_list, [], [])
        for self.s in inputready:
            if self.s == self.server:
                self.on_accept()
                break
            
            try:
                self.data = self.s.recv(buffer_size)
                if len(self.data) == 0:
                    self.on_close()
                    break
                else:
                    self.on_recv()
            except socket.error as e:
                self.on_close()

    def on_accept(self):
        clientsock, clientaddr = self.server.accept()
        
        if allowedIP(clientaddr[0], self.perms):
            forward = Forward().start(self.forward_to[0], self.forward_to[1])
            if forward:
                self.clients.append([clientaddr,clientsock,forward])
                self.pm.addClient(clientaddr)
                self.input_list.append(clientsock)
                self.input_list.append(forward)
                self.channel[clientsock] = forward
                self.channel[forward] = clientsock
            else:
                print("Can't establish connection with remote server.")
                print("Closing connection with client side", clientaddr)
                clientsock.close()
        else:
            print("Forbidden client IP")
            clientsock.close()

    def on_close(self):
        #remove objects from input_list
        self.input_list.remove(self.s)
        self.input_list.remove(self.channel[self.s])
        out = self.channel[self.s]
        # close the connection with client
        self.channel[out].close()  # equivalent to do self.s.close()
        # close the connection with remote server
        self.channel[self.s].close()
        # delete both objects from channel dict
        del self.channel[out]
        del self.channel[self.s]

        for c in self.clients:
            if c[1] == self.s:
                break
        self.clients.remove(c)
        self.pm.removeClient(c[0])

    def on_recv(self):
        data = self.data
        # here we can parse and/or modify the data before send forward
        self.channel[self.s].send(data)
        for c in self.clients:
            if c[1] == self.s:
                self.parseData(data, c)
                break

    def parseData(self, data, client):
        for line in data.splitlines():
            lineSplit = line.decode().split(':', 1)
            if lineSplit[0] == "Transport":
                for transportOpt in lineSplit[1].split(';'):
                    if transportOpt.split('=')[0] == "client_port":
                        askedPorts = transportOpt.split('=')[1].split('-')
                        allowedPorts = []
                        for port in askedPorts:
                            if allowedPortForward(client[0][0], port, self.perms):
                                allowedPorts.append(port)
                        self.pm.updatePorts(client[0], allowedPorts)

class PortManager:
    forwardedPorts = {}
    localBindings = []
    allowedNets = []

    def __init__(self, perms):
        for perm in perms:
            network = perm[0]
            self.allowedNets.append(network)
        self.removeAll()
        self.applyRules()

    def addClient(self, client):
        self.forwardedPorts[client] = []

    def updatePorts(self, client, ports):
        print("Forwarding ports for client " + client[0] + ". New list of ports is: {0}".format(ports))
        self.forwardedPorts[client] = ports
        self.applyRules()


    def removeClient(self, client):
        print("Remove client: " + client[0])
        self.forwardedPorts.pop(client)
        self.applyRules()

    def removeAll(self):
        f = open('/tmp/rtsphelper.rules', 'w')
        f.close()
        subprocess.call(['pfctl', '-a', 'rtsphelper', '-F', 'nat'], stdout=FNULL, stderr=subprocess.STDOUT)
        subprocess.call(['pfctl', '-a', 'rtsphelper', '-F', 'rules'], stdout=FNULL, stderr=subprocess.STDOUT)
        subprocess.call(['pfctl', '-k', 'label', '-k', 'RTSP'], stdout=FNULL, stderr=subprocess.STDOUT)

    def addLocalBinding(self, ip, port, local_port):
        self.localBindings.append([ip, port, local_port])
        self.applyRules()

    def applyRules(self):
        config_rule_1 = 'rdr inet proto tcp from any to {} port {} -> {} port {}\n'
        config_rule_2 = 'block in quick on {} proto tcp from any to {} port {}\n'
        config_rule_3 = 'pass in quick proto tcp from {} to {} port {}\n'
        
        pass_rule = 'pass in quick on {} inet proto udp from any to {} port {} keep state label "{}"\n'
        rdr_rule  = 'rdr on {} inet proto udp from any to any port {} -> {}\n'

        f = open('/tmp/rtsphelper.rules', 'w')

        for localBinding in self.localBindings:
            f.write(config_rule_1.format(localBinding[0], localBinding[1], '127.0.0.1', localBinding[2]))

        for client,ports in self.forwardedPorts.items():
            ip = client[0]
            for port in ports:
                f.write(rdr_rule.format(config['ext_if'], port, ip))

        f.write('\n')
        for localBinding in self.localBindings:
            f.write(config_rule_2.format(config['ext_if'], '127.0.0.1', localBinding[2]))
            for network in self.allowedNets:
                f.write(config_rule_3.format(network, '127.0.0.1', localBinding[2]))

        for client,ports in self.forwardedPorts.items():
            ip = client[0]
            for port in ports:
                f.write(pass_rule.format(config['ext_if'], ip, port, 'RTSP'))

        f.close()
        subprocess.call(['pfctl', '-a', 'rtsphelper', '-f', '/tmp/rtsphelper.rules'], stdout=FNULL)


def writePidFile():
    pid = str(os.getpid())
    f = open('/var/run/rtsphelper.pid', 'w')
    f.write(pid)
    f.close()

def ip_to_u32(ip):
    return int(''.join('%02x' % int(d) for d in ip.split('.')), 16)

def allowedIP(ipstr, perms):
    ip = ip_to_u32(ipstr)
    for perm in perms:
        mask, net = perm[0]
        if ip & mask == net:
            return True
    return False

def allowedPortForward(ipstr, port, perms):
    if not allowedIP(ipstr, perms):
        return False
    else:
        ip = ip_to_u32(ipstr)
        for perm in perms:
            mask, net = perm[0]
            ports = perm[1]
            if ip & mask == net:
                if int(port) >= int(ports[0]) and int(port) <= int(ports[1]):
                    return True
        return False

def buildPerms(perms):
    masks = [ ]
    for perm in perms:
        cidr = perm[0]
        portRange = perm[1]
        if '/' in cidr:
            netstr, bits = cidr.split('/')
            mask = (0xffffffff << (32 - int(bits))) & 0xffffffff
            net = ip_to_u32(netstr) & mask
        else:
            mask = 0xffffffff
            net = ip_to_u32(cidr)
        masks.append(((mask, net), (min(portRange.split('-')[0],portRange.split('-')[1]),max(portRange.split('-')[0],portRange.split('-')[1]))))
    return masks

if __name__ == '__main__':
    writePidFile()

    config['forward_to'] = []
    config['perms'] = []

    with open(config_file, 'r') as cf:
        line = cf.readline()
        while line:
            key,value = line.strip().split('=')
            if key == 'ext_ifname':
                config['ext_if'] = value
            elif key == 'forward':
                config['forward_to'].append([value.split(':')[0],int(value.split(':')[1])])
            elif key == 'allow':
                config['perms'].append(value.split(' '))

            line = cf.readline()
    
    perms = buildPerms(config['perms'])
    servers = []

    pm = PortManager(config['perms'])

    for forward in config['forward_to']:
        servers.append(ProxyServer(forward[0], forward[1], pm, perms))

    def handle_exit_signal(sig, frame):
        handle_exit()

    def handle_exit():
        print("Exiting...")
        pm.removeAll()
        sys.exit(0)

    signal.signal(signal.SIGTERM, handle_exit_signal)
    try:
        while 1:
            time.sleep(delay)
            for server in servers:
                server.main_loop()
    except KeyboardInterrupt:
        print("Ctrl C - Stopping server")
        handle_exit()
sys.exit(1)