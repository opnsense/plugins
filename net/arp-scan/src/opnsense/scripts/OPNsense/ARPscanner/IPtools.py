import socket
import fcntl
import struct

# From linux/sockios.h
#~ SIOCGIFCONF = 0x8912
#~ SIOCGIFINDEX = 0x8933
SIOCGIFFLAGS =  0x8913
#~ SIOCSIFFLAGS =  0x8914
SIOCGIFHWADDR = 0x8927
SIOCSIFHWADDR = 0x8924
#~ SIOCGIFADDR = 0x8915
#~ SIOCSIFADDR = 0x8916
#~ SIOCGIFNETMASK = 0x891B
#~ SIOCSIFNETMASK = 0x891C
#~ SIOCETHTOOL = 0x8946

# From linux/if.h
IFF_UP       = 0x1


# From linux/socket.h
AF_UNIX      = 1
AF_INET      = 2

class IPtools(object):
    
    @staticmethod
    def get_ip_address(ifname):
        # python2 only
        ifname = str.encode(ifname)
        #
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        return socket.inet_ntoa(fcntl.ioctl(
            s.fileno(),
            0x8915,  # SIOCGIFADDR
            struct.pack('256s', ifname[:15])
        )[20:24])
    
    @staticmethod
    def get_netmask(ifname):
        # python2 only
        ifname = str.encode(ifname)
        #
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        return socket.inet_ntoa(fcntl.ioctl(
                s.fileno(), 
                35099, 
                struct.pack('256s', ifname))[20:24])
    
    @staticmethod
    def is_up(ifname):
        ''' Return True if the interface is up, False otherwise. '''
        # python2 only
        ifname = str.encode(ifname)
        #
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        # Get existing device flags
        ifreq = struct.pack('16sh', ifname, 0)
        flags = struct.unpack('16sh', 
            fcntl.ioctl(s.fileno(), 
                        SIOCGIFFLAGS, 
                        ifreq))[1]

        # Set new flags
        if flags & IFF_UP:
            return True
        else:
            return False
    
    @staticmethod
    def get_mac(ifname):
        ''' Obtain the device's mac address. '''
        # python2 only
        ifname = str.encode(ifname)
        #
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        ifreq = struct.pack('16sH14s', ifname, AF_UNIX, b'\x00'*14)
        res = fcntl.ioctl(s.fileno(), SIOCGIFHWADDR, ifreq)
        address = struct.unpack('16sH14s', res)[2]
        mac = struct.unpack('6B8x', address)

        return ":".join(['%02X' % i for i in mac])

    @staticmethod
    def set_mac(ifname, newmac):
        ''' Set the device's mac address. Device must be down for this to
            succeed. '''
        # python2 only
        ifname = str.encode(ifname)
        #
        macbytes = [int(i, 16) for i in newmac.split(':')]
        ifreq = struct.pack('16sH6B8x', ifname, AF_UNIX, *macbytes)
        fcntl.ioctl(s.fileno(), SIOCSIFHWADDR, ifreq)
