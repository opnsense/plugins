#!/usr/bin/env python3
"""
Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
All rights reserved.

Failover Simulator for HCloudDNS
Allows testing failover logic without actual gateway failures
"""

import json
import sys
import os
import time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from gateway_health import write_state_file

STATE_FILE = '/var/run/hclouddns_state.json'
SIMULATION_FILE = '/var/run/hclouddns_simulation.json'


def load_state():
    """Load gateway state from file"""
    if os.path.exists(STATE_FILE):
        try:
            with open(STATE_FILE, 'r') as f:
                return json.load(f)
        except (json.JSONDecodeError, IOError):
            pass
    return {'gateways': {}, 'entries': {}, 'failoverHistory': [], 'lastUpdate': 0}


def load_simulation():
    """Load simulation settings"""
    if os.path.exists(SIMULATION_FILE):
        try:
            with open(SIMULATION_FILE, 'r') as f:
                return json.load(f)
        except (json.JSONDecodeError, IOError):
            pass
    return {'active': False, 'simulatedDown': []}


def save_simulation(sim):
    """Save simulation settings"""
    try:
        write_state_file(SIMULATION_FILE, sim)
    except IOError as e:
        sys.stderr.write(f"Error saving simulation: {e}\n")


def simulate_gateway_down(gateway_uuid):
    """Simulate a gateway going down"""
    sim = load_simulation()
    if gateway_uuid not in sim.get('simulatedDown', []):
        sim.setdefault('simulatedDown', []).append(gateway_uuid)
    sim['active'] = True
    save_simulation(sim)
    return {'status': 'ok', 'message': f'Gateway {gateway_uuid} simulated as DOWN', 'simulation': sim}


def simulate_gateway_up(gateway_uuid):
    """Simulate a gateway coming back up"""
    sim = load_simulation()
    if gateway_uuid in sim.get('simulatedDown', []):
        sim['simulatedDown'].remove(gateway_uuid)
    if not sim['simulatedDown']:
        sim['active'] = False
    save_simulation(sim)
    return {'status': 'ok', 'message': f'Gateway {gateway_uuid} simulated as UP', 'simulation': sim}


def clear_simulation():
    """Clear all simulations and reset gateway upSince for immediate failback"""
    sim = {'active': False, 'simulatedDown': []}
    save_simulation(sim)

    # Also update state file to allow immediate failback
    # by setting upSince to a time in the past for all gateways
    state = load_state()
    past_time = int(time.time()) - 3600  # 1 hour ago
    for uuid in state.get('gateways', {}):
        state['gateways'][uuid]['upSince'] = past_time
        state['gateways'][uuid]['status'] = 'up'
        state['gateways'][uuid]['simulated'] = False
    try:
        write_state_file(STATE_FILE, state)
    except IOError:
        pass

    return {'status': 'ok', 'message': 'Simulation cleared', 'simulation': sim}


def get_simulation_status():
    """Get current simulation status"""
    sim = load_simulation()
    state = load_state()

    result = {
        'status': 'ok',
        'simulation': sim,
        'gateways': {}
    }

    for uuid, gw_state in state.get('gateways', {}).items():
        is_simulated_down = uuid in sim.get('simulatedDown', [])
        result['gateways'][uuid] = {
            'realStatus': gw_state.get('status', 'unknown'),
            'simulatedDown': is_simulated_down,
            'effectiveStatus': 'down' if is_simulated_down else gw_state.get('status', 'unknown'),
            'ipv4': gw_state.get('ipv4'),
            'ipv6': gw_state.get('ipv6')
        }

    return result


def main():
    if len(sys.argv) < 2:
        print(json.dumps({'status': 'error', 'message': 'Usage: simulate_failover.py <action> [gateway_uuid]'}))
        sys.exit(1)

    action = sys.argv[1]

    if action == 'down':
        if len(sys.argv) < 3:
            print(json.dumps({'status': 'error', 'message': 'Gateway UUID required'}))
            sys.exit(1)
        result = simulate_gateway_down(sys.argv[2])

    elif action == 'up':
        if len(sys.argv) < 3:
            print(json.dumps({'status': 'error', 'message': 'Gateway UUID required'}))
            sys.exit(1)
        result = simulate_gateway_up(sys.argv[2])

    elif action == 'clear':
        result = clear_simulation()

    elif action == 'status':
        result = get_simulation_status()

    else:
        result = {'status': 'error', 'message': f'Unknown action: {action}'}

    print(json.dumps(result, indent=2))


if __name__ == '__main__':
    main()
