#!/usr/local/bin/python3

"""
OPNsense iperf3 manager service

Original concept (Ruby implementation):
Copyright (C) 2017 Fabian Franz

Python implementation and optimisations:
Copyright (C) 2025 Sheridan Computers

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

OPNsense iperf3 manager service
Handles multiple concurrent iperf3 instances with firewall rule management
and socket-based IPC for control.
"""

import subprocess
import json
import xml.etree.ElementTree as ET
import socket
import threading
from concurrent.futures import ThreadPoolExecutor
import time
import os
import re
from typing import Dict, List, Any, Set, Optional
from random import randint
from functools import lru_cache

# Configuration constants
SOCKET_FILE = '/var/run/iperf-manager.sock'
ONE_HOUR = 3600
KEY_START_TIME = 'start_time'
KEY_PORT = 'port'
PORT_RANGE = range(1024, 65001)
IPERF_TIMEOUT = 600  # 10 minutes
CLEANUP_INTERVAL = 10
MAX_WORKERS = 4  # Maximum concurrent connection handlers

# Global state management
instances: Dict[threading.Thread, dict] = {}  # Active iperf instances
_port_cache: Dict[int, float] = {}            # Recently used ports with timestamps
_port_cache_lock = threading.Lock()           # Synchronize port cache access
_firewall_lock = threading.Lock()             # Synchronize firewall rule updates
_active_interfaces: Dict[str, threading.Lock] = {}
_interfaces_lock = threading.Lock()

class IperfError(Exception):
    """Custom exception for iperf-related errors"""
    pass

def acquire_interface(interface: str) -> bool:
    """Try to acquire lock for an interface. Returns True if successful."""
    with _interfaces_lock:
        if interface not in _active_interfaces:
            _active_interfaces[interface] = threading.Lock()
        
        # Try to acquire the lock without blocking
        return _active_interfaces[interface].acquire(blocking=False)

def release_interface(interface: str) -> None:
    """Release the lock for an interface."""
    with _interfaces_lock:
        if interface in _active_interfaces:
            try:
                _active_interfaces[interface].release()
            except RuntimeError:
                pass  # Lock was already released

def execute_firewall_command(cmd: List[str], input_data: str = None) -> None:
    """Execute pfctl command with thread safety"""
    with _firewall_lock:
        process = subprocess.Popen(
            cmd,
            stdin=subprocess.PIPE if input_data else None,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True
        )
        
        if input_data:
            stdout, stderr = process.communicate(input=input_data)
        else:
            stdout, stderr = process.communicate()
        
        if process.returncode != 0:
            raise IperfError(f"Firewall command failed: {stderr}")

def get_current_rules() -> str:
    """Get current rules from the iperf anchor"""
    process = subprocess.run(
        ['pfctl', '-a', 'iperf', '-sr'],
        capture_output=True,
        text=True
    )
    return process.stdout

def execute_firewall_port(rule: str) -> None:
    """Apply firewall rules to the iperf anchor while preserving existing rules"""
    try:
        # Get existing rules
        current_rules = get_current_rules()
        
        # Create new ruleset
        rules_list = []
        
        # Add existing rules, excluding empty lines and anchors
        if current_rules:
            for line in current_rules.splitlines():
                if line.strip() and not line.startswith('@'):
                    # Clean up the rule format
                    cleaned_line = re.sub(r'^\[\d+\]\s*', '', line.strip())
                    if 'pass' in cleaned_line:
                        rules_list.append(cleaned_line)
        
        # Add new rule
        rules_list.append(rule)
        
        # Combine rules with proper formatting
        combined_rules = "\n".join(rules_list) + "\n"
        
        # Apply all rules at once
        execute_firewall_command(['pfctl', '-a', 'iperf', '-f', '-'], input_data=combined_rules)
        
    except Exception as e:
        raise IperfError(f"Failed to apply firewall rules: {e}")

def create_firewall_rule(interface: str, port: int, label: str = None, log: str = 'log') -> str:
    """Generate pf rule for iperf traffic with optional label"""
    # Prefix the label with the interface name
    if label:
        label = f"{interface}-{label}"
    label_str = f' label "{label}"' if label else ''
    return f"pass in {log} quick on {interface} inet proto tcp from any to (self) port {port} keep state{label_str}"

def flush_firewall_rules() -> None:
    """Clear all rules from the iperf anchor"""
    try:
        print("\nFlushing iperf anchor rules")
        execute_firewall_command(['pfctl', '-a', 'iperf', '-F', 'rules'])
    except Exception as e:
        print(f"Failed to flush rules: {e}")

def flush_firewall_rules() -> None:
    """Clear all rules from the iperf anchor"""
    execute_firewall_command(['pfctl', '-a', 'iperf', '-F', 'rules'])

def remove_firewall_rule_by_label(label: str) -> None:
    """Remove firewall rules with the specified label"""
    try:
        # Get all current rules
        current_rules = get_current_rules()
        print(f"\nAttempting to remove rule with label: {label}")
        print(f"Current rules:\n{current_rules}")
        
        if not current_rules.strip():
            print("No current rules found")
            return
        
        # Parse and clean rules
        new_rules = []
        found_rule = False
        for line in current_rules.splitlines():
            # Skip empty lines, comments, and anchors
            if not line.strip() or line.startswith('#') or line.startswith('@'):
                continue
            
            # Clean up the rule format
            cleaned_line = re.sub(r'^\[\d+\]\s*', '', line.strip())
            
            # Check if this is the rule we want to remove
            if f'label "{label}"' in cleaned_line:
                print(f"Found rule to remove: {cleaned_line}")
                found_rule = True
                continue
                
            if 'pass' in cleaned_line:
                new_rules.append(cleaned_line)
        
        if not found_rule:
            print(f"Warning: Rule with label '{label}' not found")
            return
            
        # Apply updated rules
        if new_rules:
            updated_rules = "\n".join(new_rules) + "\n"
            print(f"Applying updated rules:\n{updated_rules}")
            execute_firewall_command(['pfctl', '-a', 'iperf', '-f', '-'], input_data=updated_rules)
        else:
            print("No rules remaining, flushing iperf anchor")
            execute_firewall_command(['pfctl', '-a', 'iperf', '-F', 'rules'])
            
        # Verify removal
        final_rules = get_current_rules()
        print(f"Rules after removal:\n{final_rules}")
        
    except Exception as e:
        print(f"Failed to remove rule with label {label}: {str(e)}") 

@lru_cache(maxsize=1)
def get_forwarded_ports() -> Set[int]:
    """Parse OPNsense config for NAT forwarded ports
    Cached to reduce XML parsing overhead"""
    try:
        tree = ET.parse('/conf/config.xml')
        return {
            int(port.text)
            for port in tree.findall('.//opnsense/nat/rule/local-port')
            if port.text and port.text.isdigit()
        }
    except Exception:
        return set()

def get_open_ports() -> Set[int]:
    """Query system for currently listening ports"""
    try:
        result = subprocess.run(['sockstat', '-l'], capture_output=True, text=True, timeout=5)
        return {
            int(match.group(1))
            for line in result.stdout.splitlines()
            if (match := re.search(r':(\d+)', line))
        }
    except Exception:
        return set()

def find_available_port() -> int:
    """Find an available port with cache management
    Uses a combination of system state and recent allocation cache"""
    current_time = time.time()
    
    # Clean expired cache entries
    with _port_cache_lock:
        for port in list(_port_cache.keys()):
            if current_time - _port_cache[port] > 300:  # 5 minute cache lifetime
                del _port_cache[port]
    
    used_ports = get_open_ports() | get_forwarded_ports() | set(_port_cache.keys())
    available_ports = list(set(PORT_RANGE) - used_ports)
    
    if not available_ports:
        raise IperfError("No available ports")
    
    port = available_ports[randint(0, len(available_ports) - 1)]
    with _port_cache_lock:
        _port_cache[port] = current_time
    return port

def run_iperf3(port: int) -> dict:
    """Execute iperf3 server instance with proper process management"""
    cmd = ['iperf3', '-J', '-f', 'M', '-s', '-1', '-p', str(port)]
    process = None
    
    try:
        process = subprocess.Popen(
            cmd,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True
        )
        
        stdout, stderr = process.communicate(timeout=IPERF_TIMEOUT)
        if process.returncode == 0:
            return json.loads(stdout)
        raise IperfError(f'iperf3 failed: {stderr}')
        
    except subprocess.TimeoutExpired:
        print(f"Iperf3 timeout on port {port}")
        raise IperfError('timeout')
    except Exception as e:
        print(f"Iperf3 error on port {port}: {str(e)}")
        raise IperfError(str(e))
    finally:
        if process:
            try:
                process.kill()
                process.wait(timeout=5)
            except Exception:
                pass

def run_test(data: dict, interface: str = 'any') -> Optional[dict]:
    """Execute complete iperf test with firewall management"""
    test_label = None
    port = None
    
    try:
        port = find_available_port()
        data[KEY_PORT] = port

        # Generate a unique label with interface prefix
        test_label = f"iperf-rule-{port}"  # The interface prefix will be added in create_firewall_rule
        rule = create_firewall_rule(interface, port, label=test_label)
        data['firewall_label'] = f"{interface}-{test_label}"  # Store the full label
        execute_firewall_port(rule)
       
        result = run_iperf3(port)
        data['result'] = result
        return result
    except Exception as e:
        data['result'] = {'error': str(e)}
        return None
    finally:
        if test_label:
            try:
                # Use the full label with interface prefix for removal
                remove_firewall_rule_by_label(f"{interface}-{test_label}")
            except Exception as e:
                print(f"Failed to remove firewall rule with label {interface}-{test_label}: {e}")

def handle_connection(conn: socket.socket) -> None:
    """Handle individual client connections and commands"""
    while not conn._closed:
        try:
            command = conn.recv(1024).decode().strip().split()
            if not command:
                continue
            
            if command[0] == 'start':
                interface = 'any'
                if len(command) > 1 and re.match(r'^[a-z0-9_-]+$', command[1]):
                    interface = command[1]
                
                data = {'start_time': time.time(), 'interface': interface}
                thread = threading.Thread(
                    target=lambda: run_test(data, interface),
                    daemon=True
                )
                thread.start()
                instances[thread] = data
                conn.send(b'{"status": "queued job"}\n')
            
            elif command[0] == 'query':
                conn.send(json.dumps(list(instances.values())).encode() + b'\n')
            
            elif command[0] == 'bye':
                conn.send(b'{"status": "disconnecting"}\n')
                conn.close()
                break
            
            else:
                conn.send(b'{"status": "unknown command"}\n')
        
        except Exception:
            break

def cleanup_worker() -> None:
    """Background worker to clean up expired test instances"""
    while True:
        current_time = time.time()
        for thread, value in list(instances.items()):
            if current_time - value[KEY_START_TIME] > ONE_HOUR:
                if thread.is_alive():
                    thread.join(1)
                instances.pop(thread, None)
        time.sleep(CLEANUP_INTERVAL)

def main() -> None:
    """Main service entry point"""
    if os.path.exists(SOCKET_FILE):
        os.unlink(SOCKET_FILE)
    
    cleanup_thread = threading.Thread(target=cleanup_worker, daemon=True)
    cleanup_thread.start()
    
    server = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
    server.bind(SOCKET_FILE)
    server.listen(1)
    
    with ThreadPoolExecutor(max_workers=MAX_WORKERS) as executor:
        try:
            while True:
                conn, _ = server.accept()
                executor.submit(handle_connection, conn)
        except KeyboardInterrupt:
            pass
        finally:
            server.close()
            if os.path.exists(SOCKET_FILE):
                os.unlink(SOCKET_FILE)

if __name__ == "__main__":
    main()
    # Clean up any remaining firewall rules in the iperf anchor ONLY
    try:
        # Changed from ['pfctl', '-F', 'rules'] which would flush ALL rules!
        subprocess.run(['pfctl', '-a', 'iperf', '-F', 'rules'], check=True)
    except subprocess.CalledProcessError:
        pass
