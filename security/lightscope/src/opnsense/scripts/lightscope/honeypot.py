#!/usr/local/bin/python3
"""
honeypot.py - Honeypot listener with PROXY protocol forwarding

This module opens sockets on configured ports and forwards connections
to a remote honeypot server using the PROXY protocol to preserve
the original attacker IP address.
"""

import socket
import select
import threading
import time
import sys
import subprocess
import re


class HoneypotListener:
    """
    Manages honeypot listener sockets and forwards connections
    to remote honeypot server using PROXY protocol.
    """

    def __init__(self, config, upload_pipe, database):
        """
        Initialize honeypot listener.

        Args:
            config: Dict with honeypot configuration
            upload_pipe: Pipe for sending honeypot connection data
            database: Database identifier for PROXY header
        """
        self.config = config
        self.upload_pipe = upload_pipe
        self.database = database

        self.honeypot_server = config.get('honeypot_server', '128.9.28.79')
        self.ssh_port = int(config.get('honeypot_ssh_port', 12345))
        self.telnet_port = int(config.get('honeypot_telnet_port', 12346))

        self.sockets = {}  # socket -> port mapping
        self.sockets_lock = threading.Lock()
        self.running = False

        # Connection limiting
        self.MAX_CONCURRENT_CONNECTIONS = 50
        self.connection_semaphore = threading.Semaphore(self.MAX_CONCURRENT_CONNECTIONS)
        self.active_connections = 0
        self.connection_lock = threading.Lock()

        # Firewall monitoring
        self.FIREWALL_CHECK_INTERVAL = 10  # seconds

    def parse_ports(self, ports_string):
        """Parse comma-separated port string into list of integers."""
        if not ports_string:
            return []

        ports = []
        for p in ports_string.split(','):
            p = p.strip()
            if p.isdigit():
                port = int(p)
                if 1 <= port <= 65535:
                    ports.append(port)
        return ports

    def get_firewall_allowed_ports(self):
        """
        Get list of ports that have PASS/ALLOW rules in the firewall.
        These are ports where legitimate services may be running.
        Returns a tuple of (set of ports, list of (start, end) range tuples).
        """
        allowed_ports = set()
        allowed_ranges = []

        try:
            result = subprocess.run(
                ["pfctl", "-sr"],
                capture_output=True,
                text=True,
                timeout=5
            )

            if result.returncode == 0:
                for line in result.stdout.split('\n'):
                    # Look for pass rules with port specifications
                    if line.startswith('pass') and 'proto tcp' in line:
                        # Match port ranges like "port 1:4343" or "port 80:443"
                        range_match = re.search(r'port\s*[=]?\s*(\d+):(\d+)', line)
                        if range_match:
                            start_port = int(range_match.group(1))
                            end_port = int(range_match.group(2))
                            allowed_ranges.append((start_port, end_port))
                            continue  # Don't also match as single port

                        # Match single port patterns like "port = 22" or "port 22"
                        port_match = re.search(r'port\s*[=]?\s*(\d+)(?![\d:])', line)
                        if port_match:
                            allowed_ports.add(int(port_match.group(1)))

                        # Match port lists in braces
                        brace_match = re.search(r'port\s*[=]?\s*\{([^}]+)\}', line)
                        if brace_match:
                            ports_str = brace_match.group(1)
                            for p in re.findall(r'\d+', ports_str):
                                allowed_ports.add(int(p))
        except Exception as e:
            print(f"honeypot: Error checking firewall rules: {e}", flush=True)

        return allowed_ports, allowed_ranges

    def is_port_allowed_by_firewall(self, port, allowed_ports, allowed_ranges):
        """Check if a port is allowed by firewall rules (including ranges)."""
        if port in allowed_ports:
            return True
        for start, end in allowed_ranges:
            if start <= port <= end:
                return True
        return False

    def is_port_in_use(self, port):
        """
        Check if a port is already in use by another service.
        Returns True if port is in use, False if available.
        """
        try:
            test_sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            test_sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
            test_sock.bind(("", port))
            test_sock.close()
            return False  # Port is available
        except OSError:
            return True  # Port is in use

    def _firewall_monitor_thread(self):
        """
        Monitor firewall rules and port usage every FIREWALL_CHECK_INTERVAL seconds.
        If a new ALLOW rule is detected or port becomes in use, close that honeypot port.
        """
        print("honeypot: Started firewall/port monitor (checking every 10s)", flush=True)

        while self.running:
            time.sleep(self.FIREWALL_CHECK_INTERVAL)
            if not self.running:
                break

            try:
                allowed_ports, allowed_ranges = self.get_firewall_allowed_ports()

                with self.sockets_lock:
                    sockets_to_close = []
                    for sock, port in list(self.sockets.items()):
                        if self.is_port_allowed_by_firewall(port, allowed_ports, allowed_ranges):
                            print(f"honeypot: WARNING - Firewall ALLOW rule detected for port {port}, closing honeypot on this port", flush=True)
                            sockets_to_close.append((sock, port, "firewall conflict"))

                    for sock, port, reason in sockets_to_close:
                        try:
                            sock.close()
                        except:
                            pass
                        del self.sockets[sock]
                        print(f"honeypot: Closed listener on port {port} due to {reason}", flush=True)

            except Exception as e:
                print(f"honeypot: Error in firewall monitor: {e}", flush=True)

    def open_port(self, port):
        """
        Try to bind and listen on a port.

        Returns:
            (socket, port) on success, (None, None) on failure
        """
        try:
            s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            s.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
            s.bind(("", port))
            s.listen(10)
            s.setblocking(False)
            return s, port
        except OSError as e:
            if e.errno == 48:  # EADDRINUSE on FreeBSD
                print(f"honeypot: Port {port} already in use", flush=True)
            elif e.errno == 13:  # EACCES
                print(f"honeypot: Permission denied for port {port}", flush=True)
            else:
                print(f"honeypot: Error binding port {port}: {e}", flush=True)
            return None, None
        except Exception as e:
            print(f"honeypot: Unexpected error binding port {port}: {e}", flush=True)
            return None, None

    def start(self, ports_string):
        """
        Start honeypot listeners on configured ports.

        Args:
            ports_string: Comma-separated list of ports
        """
        ports = self.parse_ports(ports_string)
        if not ports:
            print("honeypot: No valid ports configured", flush=True)
            return

        # Check for firewall conflicts and ports already in use
        allowed_ports, allowed_ranges = self.get_firewall_allowed_ports()
        safe_ports = []
        for port in ports:
            if self.is_port_allowed_by_firewall(port, allowed_ports, allowed_ranges):
                print(f"honeypot: WARNING - Port {port} has a firewall ALLOW rule, skipping (may conflict with legitimate service)", flush=True)
            elif self.is_port_in_use(port):
                print(f"honeypot: WARNING - Port {port} is already in use by another service, skipping", flush=True)
            else:
                safe_ports.append(port)

        if not safe_ports:
            print("honeypot: No ports available (all have conflicts or are in use)", flush=True)
            return

        print(f"honeypot: Opening ports: {safe_ports}", flush=True)

        for port in safe_ports:
            s, actual_port = self.open_port(port)
            if s and actual_port:
                with self.sockets_lock:
                    self.sockets[s] = actual_port
                print(f"honeypot: Listening on port {actual_port}", flush=True)

        if not self.sockets:
            print("honeypot: Failed to open any ports", flush=True)
            return

        self.running = True

        # Start firewall monitor thread
        monitor_thread = threading.Thread(target=self._firewall_monitor_thread, daemon=True)
        monitor_thread.start()

        self._run_accept_loop()

    def stop(self):
        """Stop all honeypot listeners."""
        self.running = False
        with self.sockets_lock:
            for s in list(self.sockets.keys()):
                try:
                    s.close()
                except:
                    pass
            self.sockets.clear()
        print("honeypot: Stopped all listeners", flush=True)

    def get_open_ports(self):
        """Return list of currently open honeypot ports."""
        with self.sockets_lock:
            return list(self.sockets.values())

    def _run_accept_loop(self):
        """Main accept loop for honeypot connections."""
        print("honeypot: Starting accept loop", flush=True)

        while self.running:
            with self.sockets_lock:
                sock_list = list(self.sockets.keys())

            if not sock_list:
                time.sleep(1.0)
                continue

            try:
                # Wait for incoming connections
                readable, _, _ = select.select(sock_list, [], [], 1.0)

                for sock in readable:
                    try:
                        self._handle_connection(sock)
                    except Exception as e:
                        print(f"honeypot: Error handling connection: {e}", flush=True)

            except Exception as e:
                if self.running:
                    print(f"honeypot: Error in accept loop: {e}", flush=True)
                    time.sleep(1.0)

    def _handle_connection(self, listen_sock):
        """Handle an incoming connection on a honeypot port."""
        try:
            local_conn, addr = listen_sock.accept()
            with self.sockets_lock:
                port = self.sockets.get(listen_sock)
            if port is None:
                local_conn.close()
                return
            attacker_ip = addr[0]
            attacker_port = addr[1]

            print(f"honeypot: Connection from {attacker_ip}:{attacker_port} to port {port}", flush=True)

            # Determine service type: even ports = SSH, odd ports = TELNET
            if port % 2 == 0:
                service = 'SSH'
                remote_port = self.ssh_port
            else:
                service = 'TELNET'
                remote_port = self.telnet_port

            # Check connection limit
            if not self.connection_semaphore.acquire(blocking=False):
                print(f"honeypot: Connection limit reached, rejecting {attacker_ip}", flush=True)
                local_conn.close()
                return

            with self.connection_lock:
                self.active_connections += 1

            # Start forwarding in a new thread
            threading.Thread(
                target=self._forward_connection,
                args=(local_conn, attacker_ip, attacker_port, port, service, remote_port),
                daemon=True
            ).start()

        except Exception as e:
            print(f"honeypot: Error accepting connection: {e}", flush=True)

    def _forward_connection(self, local_conn, attacker_ip, attacker_port, honeypot_port, service, remote_port):
        """
        Forward a connection to the remote honeypot server using PROXY protocol.
        """
        remote_conn = None
        connection_released = False

        try:
            # Connect to remote honeypot
            remote_conn = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            remote_conn.settimeout(30.0)

            try:
                remote_conn.connect((self.honeypot_server, remote_port))
            except Exception as e:
                print(f"honeypot: Failed to connect to remote server: {e}", flush=True)
                return

            # Send PROXY protocol header
            # Format: PROXY TCP4 src_ip dest_ip src_port dest_port\r\n
            proxy_header = f"PROXY TCP4 {attacker_ip} {self.database} {attacker_port} {honeypot_port}\r\n"
            print(f"honeypot: Sending PROXY header: {proxy_header.strip()}", flush=True)
            remote_conn.sendall(proxy_header.encode())

            # Set timeouts for idle connections (3 minutes)
            CONNECTION_TIMEOUT = 180
            local_conn.settimeout(CONNECTION_TIMEOUT)
            remote_conn.settimeout(CONNECTION_TIMEOUT)

            # Send honeypot connection data for logging
            self._send_connection_data(attacker_ip, attacker_port, honeypot_port, service)

            # Bidirectional forwarding
            def forward(src, dst, direction):
                try:
                    while True:
                        data = src.recv(4096)
                        if not data:
                            break
                        dst.sendall(data)
                except Exception:
                    pass
                finally:
                    try:
                        src.close()
                    except:
                        pass
                    try:
                        dst.close()
                    except:
                        pass

            # Start forwarding threads
            t1 = threading.Thread(target=forward, args=(local_conn, remote_conn, "client->server"))
            t2 = threading.Thread(target=forward, args=(remote_conn, local_conn, "server->client"))
            t1.daemon = True
            t2.daemon = True
            t1.start()
            t2.start()

            # Wait for both threads to complete
            t1.join()
            t2.join()

            print(f"honeypot: Connection from {attacker_ip} closed", flush=True)

        except Exception as e:
            print(f"honeypot: Forwarding error: {e}", flush=True)

        finally:
            # Clean up
            for conn in (local_conn, remote_conn):
                if conn:
                    try:
                        conn.close()
                    except:
                        pass

            # Release connection slot
            if not connection_released:
                self.connection_semaphore.release()
                with self.connection_lock:
                    self.active_connections -= 1

    def _send_connection_data(self, attacker_ip, attacker_port, honeypot_port, service):
        """Send honeypot connection data to upload pipe."""
        try:
            data = {
                "db_name": self.database,
                "system_time": str(time.time()),
                "ip_version": "HP",
                "ip_ihl": "HP",
                "ip_tos": "HP",
                "ip_len": "HP",
                "ip_id": "HP",
                "ip_flags": "HP",
                "ip_frag": "HP",
                "ip_ttl": "HP",
                "ip_proto": "HP",
                "ip_chksum": "HP",
                "ip_src": attacker_ip,
                "ip_dst_randomized": "HP",
                "ip_options": "HP",
                "tcp_sport": str(attacker_port),
                "tcp_dport": str(honeypot_port),
                "tcp_seq": 0,
                "tcp_ack": "HP",
                "tcp_dataofs": "HP",
                "tcp_reserved": "HP",
                "tcp_flags": "HP",
                "tcp_window": "HP",
                "tcp_chksum": "HP",
                "tcp_urgptr": "HP",
                "tcp_options": "HP",
                "ext_dst_ip_country": "HP",
                "type": "HP",
                "ASN": "HP",
                "domain": "HP",
                "city": "HP",
                "as_type": "HP",
                "ip_dst_is_private": "HP",
                "external_is_private": "HP",
                "open_ports": "",
                "previously_open_ports": "",
                "interface": "honeypot",
                "internal_ip_randomized": "HP",
                "external_ip_randomized": "HP",
                "System_info": "OPNsense",
                "Release_info": "HP",
                "Version_info": "HP",
                "Machine_info": "HP",
                "Total_Memory": "HP",
                "processor": "HP",
                "architecture": "HP",
                "honeypot_status": "True",
                "payload": service,
                "ls_version": "opnsense-1.0"
            }

            if self.upload_pipe:
                self.upload_pipe.send(data)

        except Exception as e:
            print(f"honeypot: Error sending connection data: {e}", flush=True)


def run_honeypot(config, upload_pipe, database):
    """
    Main entry point for honeypot process.

    Args:
        config: Configuration dict
        upload_pipe: Pipe for sending connection data
        database: Database identifier
    """
    listener = HoneypotListener(config, upload_pipe, database)
    ports = config.get('honeypot_ports', '')
    listener.start(ports)


if __name__ == "__main__":
    # Test mode
    print("honeypot: Running in test mode")

    test_config = {
        'honeypot_ports': '8080,2323',
        'honeypot_server': '128.9.28.79',
        'honeypot_ssh_port': 12345,
        'honeypot_telnet_port': 12346
    }

    listener = HoneypotListener(test_config, None, "test_database")
    try:
        listener.start(test_config['honeypot_ports'])
    except KeyboardInterrupt:
        listener.stop()
