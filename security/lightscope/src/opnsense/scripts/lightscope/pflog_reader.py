#!/usr/local/bin/python3
"""
pflog_reader.py - Captures blocked TCP SYN packets from pflog0 interface

This module reads packets from the pflog0 interface (pf firewall log)
and extracts TCP SYN packets that were blocked by the firewall.
"""

import struct
import socket
import datetime
import time
import sys
from collections import namedtuple, deque
import threading

# Try to import pcap library (python-libpcap on FreeBSD)
try:
    import pcap
except ImportError:
    print("Error: pypcap not found. Install with: pkg install py311-pypcap", file=sys.stderr)
    sys.exit(1)

try:
    import dpkt
except ImportError:
    print("Error: dpkt not found. Install with: pkg install py311-dpkt", file=sys.stderr)
    sys.exit(1)


# pflog header structure (FreeBSD)
# See /usr/include/net/if_pflog.h
# Structure size: 1+1+1+1+16+16+4+4+4+4+4+4+1+3+4+1+3 = 72 bytes
PFLOG_HDRLEN = 72  # FreeBSD pflog header length

# pflog action values (from /usr/include/netpfil/pf/pf.h)
PF_PASS = 0
PF_DROP = 1

# PacketInfo namedtuple - compatible with lightscope_core.py
PacketInfo = namedtuple("PacketInfo", [
    "packet_num", "proto", "packet_time",
    "ip_version", "ip_ihl", "ip_tos", "ip_len", "ip_id", "ip_flags", "ip_frag",
    "ip_ttl", "ip_proto", "ip_chksum", "ip_src", "ip_dst", "ip_options",
    "tcp_sport", "tcp_dport", "tcp_seq", "tcp_ack", "tcp_dataofs",
    "tcp_reserved", "tcp_flags", "tcp_window", "tcp_chksum", "tcp_urgptr", "tcp_options"
])


def tcp_flags_to_str(flags_value):
    """Convert dpkt TCP flags value to a comma-separated string."""
    flag_names = []
    if flags_value & dpkt.tcp.TH_FIN:
        flag_names.append("FIN")
    if flags_value & dpkt.tcp.TH_SYN:
        flag_names.append("SYN")
    if flags_value & dpkt.tcp.TH_RST:
        flag_names.append("RST")
    if flags_value & dpkt.tcp.TH_PUSH:
        flag_names.append("PSH")
    if flags_value & dpkt.tcp.TH_ACK:
        flag_names.append("ACK")
    if flags_value & dpkt.tcp.TH_URG:
        flag_names.append("URG")
    return ",".join(flag_names) if flag_names else ""


def parse_pflog_packet(buf, packet_num):
    """
    Parse a pflog packet and extract TCP SYN information.

    pflog packets have a pflog header followed by the IP packet.
    We only care about blocked TCP SYN packets.

    Returns PacketInfo namedtuple or None if not a blocked TCP SYN.
    """
    if len(buf) < PFLOG_HDRLEN:
        return None

    # Extract action from pflog header (byte offset 2)
    # Only process blocked packets (PF_DROP)
    action = buf[2]
    if action != PF_DROP:
        return None

    # Skip pflog header to get to IP packet
    ip_data = buf[PFLOG_HDRLEN:]

    if len(ip_data) < 20:  # Minimum IP header length
        return None

    # Check IP version
    ip_version = (ip_data[0] >> 4) & 0xF

    if ip_version == 4:
        try:
            ip = dpkt.ip.IP(ip_data)
        except Exception:
            return None

        # Only process TCP
        if ip.p != dpkt.ip.IP_PROTO_TCP:
            return None

        try:
            tcp = ip.data
            if not isinstance(tcp, dpkt.tcp.TCP):
                tcp = dpkt.tcp.TCP(ip.data)
        except Exception:
            return None

        # Only capture SYN packets (SYN flag set, ACK flag not set)
        if not (tcp.flags & dpkt.tcp.TH_SYN) or (tcp.flags & dpkt.tcp.TH_ACK):
            return None

        # Extract IP flags
        flags = []
        if ip.df:
            flags.append("DF")
        if ip.mf:
            flags.append("MF")
        ip_flags_str = ",".join(flags) if flags else ""

        ip_opts = ip.opts.hex() if ip.opts else ""

        return PacketInfo(
            packet_num=packet_num,
            proto="TCP",
            packet_time=datetime.datetime.now().timestamp(),
            ip_version=ip.v,
            ip_ihl=ip.hl,
            ip_tos=ip.tos,
            ip_len=ip.len,
            ip_id=ip.id,
            ip_flags=ip_flags_str,
            ip_frag=ip.offset >> 3,
            ip_ttl=ip.ttl,
            ip_proto=ip.p,
            ip_chksum=ip.sum,
            ip_src=socket.inet_ntoa(ip.src),
            ip_dst=socket.inet_ntoa(ip.dst),
            ip_options=ip_opts,
            tcp_sport=tcp.sport,
            tcp_dport=tcp.dport,
            tcp_seq=tcp.seq,
            tcp_ack=tcp.ack,
            tcp_dataofs=tcp.off * 4,
            tcp_reserved=0,
            tcp_flags=tcp_flags_to_str(tcp.flags),
            tcp_window=tcp.win,
            tcp_chksum=tcp.sum,
            tcp_urgptr=tcp.urp,
            tcp_options=tcp.opts
        )

    elif ip_version == 6:
        try:
            ip6 = dpkt.ip6.IP6(ip_data)
        except Exception:
            return None

        # Only process TCP
        if ip6.nxt != dpkt.ip.IP_PROTO_TCP:
            return None

        try:
            tcp = ip6.data
            if not isinstance(tcp, dpkt.tcp.TCP):
                tcp = dpkt.tcp.TCP(ip6.data)
        except Exception:
            return None

        # Only capture SYN packets
        if not (tcp.flags & dpkt.tcp.TH_SYN) or (tcp.flags & dpkt.tcp.TH_ACK):
            return None

        return PacketInfo(
            packet_num=packet_num,
            proto="TCP",
            packet_time=datetime.datetime.now().timestamp(),
            ip_version=6,
            ip_ihl=None,
            ip_tos=None,
            ip_len=ip6.plen,
            ip_id=None,
            ip_flags="",
            ip_frag=0,
            ip_ttl=ip6.hlim,
            ip_proto=ip6.nxt,
            ip_chksum=None,
            ip_src=socket.inet_ntop(socket.AF_INET6, ip6.src),
            ip_dst=socket.inet_ntop(socket.AF_INET6, ip6.dst),
            ip_options="",
            tcp_sport=tcp.sport,
            tcp_dport=tcp.dport,
            tcp_seq=tcp.seq,
            tcp_ack=tcp.ack,
            tcp_dataofs=tcp.off * 4,
            tcp_reserved=0,
            tcp_flags=tcp_flags_to_str(tcp.flags),
            tcp_window=tcp.win,
            tcp_chksum=tcp.sum,
            tcp_urgptr=tcp.urp,
            tcp_options=tcp.opts
        )

    return None


def read_pflog(output_pipe, interface="pflog0"):
    """
    Main pflog reader function.

    Captures packets from pflog0 interface, parses TCP SYNs,
    and sends them to output_pipe in batches.

    Args:
        output_pipe: multiprocessing.Pipe connection for sending packet batches
        interface: Network interface to capture from (default: pflog0)
    """
    BATCH_SIZE = 100
    IDLE_FLUSH_SECS = 1.0
    MAX_QUEUE_SIZE = 10000

    send_deque = deque(maxlen=MAX_QUEUE_SIZE)
    last_activity = time.monotonic()

    def sender_thread():
        """Thread to batch and send packets."""
        nonlocal last_activity
        prior_time = time.monotonic()
        packets_sent = 0

        while True:
            now = time.monotonic()
            to_send = 0

            # Log stats every second
            if (now - prior_time) >= 1.0:
                if packets_sent > 0:
                    print(f"pflog_reader: sent {packets_sent} packets, queue={len(send_deque)}", flush=True)
                packets_sent = 0
                prior_time = now

            # Check if we should send a batch
            if len(send_deque) >= BATCH_SIZE:
                to_send = BATCH_SIZE
            elif send_deque and (now - last_activity) >= IDLE_FLUSH_SECS:
                to_send = len(send_deque)
            else:
                time.sleep(0.01)
                continue

            # Build and send batch
            batch = [send_deque.popleft() for _ in range(to_send)]
            try:
                output_pipe.send(batch)
                packets_sent += len(batch)
            except Exception as e:
                print(f"pflog_reader: pipe send error: {e}", file=sys.stderr)
                return

    # Start sender thread
    threading.Thread(target=sender_thread, daemon=True).start()

    # Open pflog0 for capture
    try:
        # DLT_PFLOG = 117 on FreeBSD
        sniffer = pcap.pcap(
            name=interface,
            snaplen=65535,
            promisc=False,
            immediate=True,
            timeout_ms=100
        )
        # Filter for TCP only
        sniffer.setfilter("tcp")
    except Exception as e:
        print(f"pflog_reader: Failed to open {interface}: {e}", file=sys.stderr)
        sys.exit(1)

    print(f"pflog_reader: Capturing on {interface}", flush=True)

    packet_num = 0
    for ts, buf in sniffer:
        packet_num += 1

        try:
            pkt_info = parse_pflog_packet(buf, packet_num)
            if pkt_info:
                send_deque.append(pkt_info)
                last_activity = time.monotonic()
        except Exception as e:
            # Skip malformed packets
            continue


if __name__ == "__main__":
    # Test mode - print captured packets
    print("pflog_reader: Running in test mode (printing to stdout)")

    try:
        sniffer = pcap.pcap(
            name="pflog0",
            snaplen=65535,
            promisc=False,
            immediate=True,
            timeout_ms=1000
        )
        sniffer.setfilter("tcp")
    except Exception as e:
        print(f"Failed to open pflog0: {e}")
        sys.exit(1)

    packet_num = 0
    for ts, buf in sniffer:
        packet_num += 1
        pkt_info = parse_pflog_packet(buf, packet_num)
        if pkt_info:
            print(f"SYN: {pkt_info.ip_src}:{pkt_info.tcp_sport} -> {pkt_info.ip_dst}:{pkt_info.tcp_dport}")
