<?php

/*
 * Copyright (C) 2026 Henry Stern <henry@stern.ca>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */
/* GENERATED from knobs.yaml by tools/gen_knobs.py — do not edit by hand. */
return [
    'pki_disconnect_invalid' => ['yaml' => 'pki.disconnect_invalid', 'type' => 'bool'],
    'pki_initiating_version' => ['yaml' => 'pki.initiating_version', 'type' => 'int'],
    'static_map_cadence' => ['yaml' => 'static_map.cadence', 'type' => 'duration'],
    'static_map_network' => ['yaml' => 'static_map.network', 'type' => 'enum'],
    'static_map_lookup_timeout' => ['yaml' => 'static_map.lookup_timeout', 'type' => 'duration'],
    'am_lighthouse' => ['yaml' => 'lighthouse.am_lighthouse', 'type' => 'bool'],
    'lighthouse_serve_dns' => ['yaml' => 'lighthouse.serve_dns', 'type' => 'bool'],
    'lighthouse_interval' => ['yaml' => 'lighthouse.interval', 'type' => 'int'],
    'lighthouse_dns_host' => ['yaml' => 'lighthouse.dns.host', 'type' => 'host'],
    'lighthouse_dns_port' => ['yaml' => 'lighthouse.dns.port', 'type' => 'int'],
    'lighthouse_hosts' => ['yaml' => 'lighthouse.hosts', 'type' => 'list'],
    'lighthouse_advertise_addrs' => ['yaml' => 'lighthouse.advertise_addrs', 'type' => 'list'],
    'listen_host' => ['yaml' => 'listen.host', 'type' => 'host'],
    'listen_port' => ['yaml' => 'listen.port', 'type' => 'int'],
    'listen_batch' => ['yaml' => 'listen.batch', 'type' => 'int'],
    'listen_read_buffer' => ['yaml' => 'listen.read_buffer', 'type' => 'int'],
    'listen_write_buffer' => ['yaml' => 'listen.write_buffer', 'type' => 'int'],
    'listen_send_recv_error' => ['yaml' => 'listen.send_recv_error', 'type' => 'enum'],
    'listen_accept_recv_error' => ['yaml' => 'listen.accept_recv_error', 'type' => 'enum'],
    'listen_so_mark' => ['yaml' => 'listen.so_mark', 'type' => 'int'],
    'punchy_punch' => ['yaml' => 'punchy.punch', 'type' => 'bool'],
    'punchy_respond' => ['yaml' => 'punchy.respond', 'type' => 'bool'],
    'punchy_delay' => ['yaml' => 'punchy.delay', 'type' => 'duration'],
    'punchy_respond_delay' => ['yaml' => 'punchy.respond_delay', 'type' => 'duration'],
    'cipher' => ['yaml' => 'cipher', 'type' => 'enum'],
    'preferred_ranges' => ['yaml' => 'preferred_ranges', 'type' => 'list'],
    'relay_am_relay' => ['yaml' => 'relay.am_relay', 'type' => 'bool'],
    'relay_use_relays' => ['yaml' => 'relay.use_relays', 'type' => 'bool'],
    'relay_relays' => ['yaml' => 'relay.relays', 'type' => 'list'],
    'tun_name' => ['yaml' => 'tun.dev', 'type' => 'text'],
    'tun_disabled' => ['yaml' => 'tun.disabled', 'type' => 'bool'],
    'tun_drop_local_broadcast' => ['yaml' => 'tun.drop_local_broadcast', 'type' => 'bool'],
    'tun_drop_multicast' => ['yaml' => 'tun.drop_multicast', 'type' => 'bool'],
    'tun_tx_queue' => ['yaml' => 'tun.tx_queue', 'type' => 'int'],
    'tun_mtu' => ['yaml' => 'tun.mtu', 'type' => 'int'],
    'tun_use_system_route_table' => ['yaml' => 'tun.use_system_route_table', 'type' => 'bool'],
    'tun_use_system_route_table_buffer_size' => ['yaml' => 'tun.use_system_route_table_buffer_size', 'type' => 'int'],
    'tunnels_drop_inactive' => ['yaml' => 'tunnels.drop_inactive', 'type' => 'bool'],
    'tunnels_inactivity_timeout' => ['yaml' => 'tunnels.inactivity_timeout', 'type' => 'duration'],
    'logging_level' => ['yaml' => 'logging.level', 'type' => 'enum'],
    'logging_format' => ['yaml' => 'logging.format', 'type' => 'enum'],
    'logging_disable_timestamp' => ['yaml' => 'logging.disable_timestamp', 'type' => 'bool'],
    'logging_timestamp_format' => ['yaml' => 'logging.timestamp_format', 'type' => 'text'],
    'firewall_outbound_action' => ['yaml' => 'firewall.outbound_action', 'type' => 'enum'],
    'firewall_inbound_action' => ['yaml' => 'firewall.inbound_action', 'type' => 'enum'],
    'firewall_default_local_cidr_any' => ['yaml' => 'firewall.default_local_cidr_any', 'type' => 'bool'],
    'firewall_conntrack_tcp_timeout' => ['yaml' => 'firewall.conntrack.tcp_timeout', 'type' => 'duration'],
    'firewall_conntrack_udp_timeout' => ['yaml' => 'firewall.conntrack.udp_timeout', 'type' => 'duration'],
    'firewall_conntrack_default_timeout' => ['yaml' => 'firewall.conntrack.default_timeout', 'type' => 'duration'],
    'routines' => ['yaml' => 'routines', 'type' => 'int'],
    'handshakes_try_interval' => ['yaml' => 'handshakes.try_interval', 'type' => 'duration'],
    'handshakes_retries' => ['yaml' => 'handshakes.retries', 'type' => 'int'],
    'handshakes_query_buffer' => ['yaml' => 'handshakes.query_buffer', 'type' => 'int'],
    'handshakes_trigger_buffer' => ['yaml' => 'handshakes.trigger_buffer', 'type' => 'int'],
];
