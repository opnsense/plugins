#!/usr/local/bin/python3

"""

Copyright (C) 2025 github.com/mr-manuel
All rights reserved.

License: BSD 2-Clause

"""


import os
import subprocess
import time
import shutil
import logging
import socket
import configparser
import sqlite3
import requests
import csv
import gc
from io import StringIO
from datetime import datetime


CONFIG_FILE = "/usr/local/etc/arpndplogging.conf"
DB_FILE = "/var/db/arpndplogging/arpndplogging.db"
LOG_FILE = "/var/log/arpndplogging.log"
MAC_VENDOR_FILE = "/var/db/arpndplogging/oui.csv"

# Create directories
os.makedirs(os.path.dirname(DB_FILE), exist_ok=True)


# Set up logging to match RFC 5424
class CustomLogRecord(logging.LogRecord):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.hostname = socket.gethostname()
        self.appname = "arpndplogging"
        self.procid = os.getpid()


logging.basicConfig(
    filename=LOG_FILE,
    level=logging.INFO,
    format="<134>1 %(asctime)s %(hostname)s %(appname)s %(procid)s - [meta] %(message)s",
    datefmt="%Y-%m-%dT%H:%M:%S%z",
)


# Add hostname, appname, procid to the log records
logging.Formatter.converter = time.localtime  # Use local time
logging.Formatter.default_msec_format = "%s.%03d"

logging.setLogRecordFactory(CustomLogRecord)

# Create database
conn = sqlite3.connect(DB_FILE)
cursor = conn.cursor()
cursor.execute(
    """
    CREATE TABLE IF NOT EXISTS arp_entries (
        mac TEXT,
        ipv4 TEXT,
        ipv6 TEXT,
        interface TEXT,
        hostname TEXT,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
    """
)


# Read configuration file
config = configparser.ConfigParser()
with open(CONFIG_FILE) as f:
    config.read_file(StringIO("[default]\n" + f.read()))

protocols = config["default"].get("protocols")
interfaces = (
    config["default"].get("interfaces").replace("_", ".").split(" ")
    if config["default"].get("interfaces") is not None
    else []
)
suppress_mac = (
    config["default"].get("suppress_mac").split(" ")
    if config["default"].get("suppress_mac") is not None
    else []
)
log_new_entries = config["default"].getboolean("log_new_entries")
log_mac_changes = config["default"].getboolean("log_mac_changes")
log_ipv4_changes = config["default"].getboolean("log_ipv4_changes")
log_ipv6_changes = config["default"].getboolean("log_ipv6_changes")
log_hostname_changes = config["default"].getboolean("log_hostname_changes")
log_interface_changes = config["default"].getboolean("log_interface_changes")
retention_days = config["default"].getint("retention_days")

# add firewall MAC addresses to suppress_mac
device_mac_addresses = (
    subprocess.run(
        "ifconfig -a | grep ether  | awk '{print $2}' | sort | uniq",
        shell=True,
        capture_output=True,
        text=True,
    )
    .stdout.strip()
    .split("\n")
)

suppress_mac.extend(device_mac_addresses)


def main():
    while True:
        time_now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

        # create a dictionary with the current entries
        current_entries_dict = {}

        # Check ARP table
        if protocols == "all" or protocols == "ipv4_only":
            # $2 = IPv4, $4 = MAC, $6 = Interface
            filter = ' | grep -v \'incomplete\' | awk \'{gsub(/[()]/, "", $2); print $2 " " $4 " " $6}\''
            if len(interfaces) == 0:
                result = subprocess.run(
                    "arp -an" + filter,
                    shell=True,
                    capture_output=True,
                    text=True,
                )
                current_ipv4_entries = result.stdout.strip().split("\n")
            else:
                current_ipv4_entries = []
                for interface in interfaces:
                    result = subprocess.run(
                        "arp -i " + interface + " -an" + filter,
                        shell=True,
                        capture_output=True,
                        text=True,
                    )
                    current_ipv4_entries.extend(result.stdout.strip().split("\n"))

            # Filter out empty strings
            current_ipv4_entries = [
                entries for entries in current_ipv4_entries if entries
            ]

            # populate the current_entries_dict
            if len(current_ipv4_entries) > 0:
                for entry in current_ipv4_entries:
                    ipv4, mac, interface = entry.split()
                    current_entries_dict[mac] = {
                        "ipv4": ipv4,
                        "ipv6": "<unknown>",
                        "interface": interface,
                    }

        # Check NDP table
        if protocols == "all" or protocols == "ipv6_only":
            # $1 = IPv6, $2 = MAC, $3 = Interface
            filter = ' | grep -v "incomplete" | grep -v "Neighbor" | awk \'{gsub(/%.*/, "", $1); print $1 " " $2 " " $3}\''
            if len(interfaces) == 0:
                result = subprocess.run(
                    "ndp -an" + filter,
                    shell=True,
                    capture_output=True,
                    text=True,
                )
                current_ipv6_entries = result.stdout.strip().split("\n")
            else:
                current_ipv6_entries = []
                for interface in interfaces:
                    result = subprocess.run(
                        "ndp -an | grep " + interface + filter,
                        shell=True,
                        capture_output=True,
                        text=True,
                    )
                    current_ipv6_entries.extend(result.stdout.strip().split("\n"))

            # Filter out empty strings
            current_ipv6_entries = [
                entries for entries in current_ipv6_entries if entries
            ]

            # populate the current_entries_dict
            if len(current_ipv6_entries) > 0:
                for entry in current_ipv6_entries:
                    ipv6, mac, interface = entry.split()
                    if mac in current_entries_dict:
                        current_entries_dict[mac]["ipv6"] = ipv6
                    else:
                        current_entries_dict[mac] = {
                            "ipv4": "<unknown>",
                            "ipv6": ipv6,
                            "interface": interface,
                        }

        # Delete expired ARP entries
        cursor.execute(
            f"DELETE FROM arp_entries WHERE timestamp < datetime('now', '-{retention_days} day')"
        )

        # Select all current ARP entries
        cursor.execute(
            "SELECT mac, ipv4, ipv6, interface, hostname, timestamp FROM arp_entries"
        )
        saved_ipv4_entries = set(cursor.fetchall())

        # Save entries in a dictionary with MAC as the key
        saved_entries_dict_by_mac = {
            entry[0]: {
                "ipv4": entry[1],
                "ipv6": entry[2],
                "interface": entry[3],
                "hostname": entry[4],
                "timestamp": entry[5],
            }
            for entry in saved_ipv4_entries
        }

        # Save entries in a dictionary with MAC as the key
        saved_ipv4_entries_dict_by_ip = {
            entry[1]: {
                "mac": entry[0],
                "interface": entry[3],
                "hostname": entry[4],
                "timestamp": entry[5],
            }
            for entry in saved_ipv4_entries
        }

        # Save entries in a dictionary with MAC as the key
        saved_ipv6_entries_dict_by_ip = {
            entry[2]: {
                "mac": entry[0],
                "interface": entry[3],
                "hostname": entry[4],
                "timestamp": entry[5],
            }
            for entry in saved_ipv4_entries
        }

        # check if current_ipv4_entries_dict is empty
        if len(current_entries_dict) > 0:
            for mac, entry in current_entries_dict.items():

                ipv4 = entry["ipv4"]
                ipv6 = entry["ipv6"]
                interface = entry["interface"]

                # Get hostname from IPv4
                hostname = (
                    subprocess.run(
                        "host "
                        + ipv4
                        + " | grep -v 'not found' | awk '{print $5}' | sed 's/\\.$//'",
                        shell=True,
                        capture_output=True,
                        text=True,
                    )
                    .stdout.strip()
                    .split("\n")
                )
                # Get hostname from IPv6
                hostname += (
                    subprocess.run(
                        "host "
                        + ipv6
                        + " | grep -v 'not found' | awk '{print $5}' | sed 's/\\.$//'",
                        shell=True,
                        capture_output=True,
                        text=True,
                    )
                    .stdout.strip()
                    .split("\n")
                )
                # Remove duplicates and sort the hostname list
                hostname = sorted(set(hostname))
                # Filter out empty strings
                hostname = [name for name in hostname if name]

                if not hostname:
                    hostname = "<unknown>"
                else:
                    # split by newline, sort and join with ;
                    hostname = "; ".join(sorted(hostname))

                # Skip suppressed MAC addresses
                if mac in suppress_mac:
                    continue

                # Check if the MAC entry already exists
                if mac in saved_entries_dict_by_mac:

                    changes = False
                    changes_message = []

                    # Check if the IPv4 address has changed
                    if (
                        (protocols == "all" or protocols == "ipv4_only")
                        and log_ipv4_changes
                        and ipv4 != saved_entries_dict_by_mac[mac]["ipv4"]
                    ):
                        changes = True
                        changes_message.append(
                            f"OLD IPv4: {saved_entries_dict_by_mac[mac]['ipv4']}"
                        )

                    # Check if the IPv6 address has changed
                    if (
                        (protocols == "all" or protocols == "ipv6_only")
                        and log_ipv6_changes
                        and ipv6 != saved_entries_dict_by_mac[mac]["ipv6"]
                    ):
                        changes = True
                        changes_message.append(
                            f"OLD IPv6: {saved_entries_dict_by_mac[mac]['ipv6']}"
                        )

                    # Check if the hostname has changed
                    if (
                        log_hostname_changes
                        and hostname != saved_entries_dict_by_mac[mac]["hostname"]
                    ):
                        changes = True
                        changes_message.append(
                            f"OLD Hostname: {saved_entries_dict_by_mac[mac]['hostname']}"
                        )

                    # Check if the interface has changed
                    if (
                        log_interface_changes
                        and interface != saved_entries_dict_by_mac[mac]["interface"]
                    ):
                        changes = True
                        changes_message.append(
                            f"OLD Interface: {saved_entries_dict_by_mac[mac]['interface']}"
                        )

                    # Update the entry
                    if changes:
                        cursor.execute(
                            "UPDATE arp_entries SET ipv4 = ?, ipv6 = ?, interface = ?, hostname = ?, timestamp = ? WHERE mac = ?",
                            (ipv4, ipv6, interface, hostname, time_now, mac),
                        )
                        logging.info(
                            "ARP - Changes detected! "
                            + (
                                f"IPv4: {ipv4} | "
                                if protocols == "all" or protocols == "ipv4_only"
                                else ""
                            )
                            + (
                                f"IPv6: {ipv6} | "
                                if protocols == "all" or protocols == "ipv6_only"
                                else ""
                            )
                            + f"Hostname: {hostname} | MAC: {mac} | Vendor: {mac_vendor_check(mac)} | Interface: {interface}"
                            + (
                                " | " + " | ".join(changes_message)
                                if len(changes_message) > 0
                                else ""
                            )
                        )
                    # Update the timestamp
                    else:
                        cursor.execute(
                            "UPDATE arp_entries SET timestamp = ? WHERE ipv4 = ?",
                            (time_now, ipv4),
                        )

                # Check if the IPv4 entry already exists
                # This check allows to see, if an address is spoofed or multiple devices have the same IP
                elif ipv4 != "<unknown>" and ipv4 in saved_ipv4_entries_dict_by_ip:

                    changes = False
                    changes_message = []

                    # Check if the MAC address has changed
                    if (
                        log_mac_changes
                        and mac != saved_ipv4_entries_dict_by_ip[ipv4]["mac"]
                    ):
                        changes = True
                        changes_message.append(
                            f"OLD MAC: {saved_ipv4_entries_dict_by_ip[ipv4]['mac']} | OLD vendor: {mac_vendor_check(saved_ipv4_entries_dict_by_ip[ipv4]['mac'])}"
                        )

                    # Check if the hostname has changed
                    if (
                        log_hostname_changes
                        and hostname != saved_ipv4_entries_dict_by_ip[ipv4]["hostname"]
                    ):
                        changes = True
                        changes_message.append(
                            f"OLD Hostname: {saved_ipv4_entries_dict_by_ip[ipv4]['hostname']}"
                        )

                    # Check if the interface has changed
                    if (
                        log_interface_changes
                        and interface
                        != saved_ipv4_entries_dict_by_ip[ipv4]["interface"]
                    ):
                        changes = True
                        changes_message.append(
                            f"OLD Interface: {saved_ipv4_entries_dict_by_ip[ipv4]['interface']}"
                        )

                    # Update the entry
                    if changes:
                        cursor.execute(
                            "UPDATE arp_entries SET mac = ?, ipv6 = ?, interface = ?, hostname = ?, timestamp = ? WHERE ipv4 = ?",
                            (mac, ipv6, interface, hostname, time_now, ipv4),
                        )
                        logging.info(
                            "ARP - Changes detected! "
                            + (
                                f"IPv4: {ipv4} | "
                                if protocols == "all" or protocols == "ipv4_only"
                                else ""
                            )
                            + (
                                f"IPv6: {ipv6} | "
                                if protocols == "all" or protocols == "ipv6_only"
                                else ""
                            )
                            + f"Hostname: {hostname} | MAC: {mac} | Vendor: {mac_vendor_check(mac)} | Interface: {interface}"
                            + (
                                " | " + " | ".join(changes_message)
                                if len(changes_message) > 0
                                else ""
                            )
                        )
                    # Update the timestamp
                    else:
                        cursor.execute(
                            "UPDATE arp_entries SET timestamp = ? WHERE ipv4 = ?",
                            (time_now, ipv4),
                        )

                # Check if the IPv6 entry already exists
                # This check allows to see, if an address is spoofed or multiple devices have the same IP
                elif ipv6 != "<unknown>" and ipv6 in saved_ipv6_entries_dict_by_ip:

                    changes = False
                    changes_message = []

                    # Check if the MAC address has changed
                    if (
                        log_mac_changes
                        and mac != saved_ipv6_entries_dict_by_ip[ipv6]["mac"]
                    ):
                        changes = True
                        changes_message.append(
                            f"OLD MAC: {saved_ipv6_entries_dict_by_ip[ipv6]['mac']} | OLD vendor: {mac_vendor_check(saved_ipv6_entries_dict_by_ip[ipv6]['mac'])}"
                        )

                    # Check if the hostname has changed
                    if (
                        log_hostname_changes
                        and hostname != saved_ipv6_entries_dict_by_ip[ipv6]["hostname"]
                    ):
                        changes = True
                        changes_message.append(
                            f"OLD Hostname: {saved_ipv6_entries_dict_by_ip[ipv6]['hostname']}"
                        )

                    # Check if the interface has changed
                    if (
                        log_interface_changes
                        and interface
                        != saved_ipv6_entries_dict_by_ip[ipv6]["interface"]
                    ):
                        changes = True
                        changes_message.append(
                            f"OLD Interface: {saved_ipv6_entries_dict_by_ip[ipv6]['interface']}"
                        )

                    # Update the entry
                    if changes:
                        cursor.execute(
                            "UPDATE arp_entries SET mac = ?, ipv4 = ?, interface = ?, hostname = ?, timestamp = ? WHERE ipv6 = ?",
                            (mac, ipv4, interface, hostname, time_now, ipv6),
                        )
                        logging.info(
                            "ARP - Changes detected! "
                            + (
                                f"IPv4: {ipv4} | "
                                if protocols == "all" or protocols == "ipv4_only"
                                else ""
                            )
                            + (
                                f"IPv6: {ipv6} | "
                                if protocols == "all" or protocols == "ipv6_only"
                                else ""
                            )
                            + f"Hostname: {hostname} | MAC: {mac} | Vendor: {mac_vendor_check(mac)} | Interface: {interface}"
                            + (
                                " | " + " | ".join(changes_message)
                                if len(changes_message) > 0
                                else ""
                            )
                        )
                    # Update the timestamp
                    else:
                        cursor.execute(
                            "UPDATE arp_entries SET timestamp = ? WHERE ipv6 = ?",
                            (time_now, ipv6),
                        )

                elif log_new_entries:
                    logging.info(
                        "ARP - New entry detected! "
                        + (
                            f"IPv4: {ipv4} | "
                            if protocols == "all" or protocols == "ipv4_only"
                            else ""
                        )
                        + (
                            f"IPv6: {ipv6} | "
                            if protocols == "all" or protocols == "ipv6_only"
                            else ""
                        )
                        + f"Hostname: {hostname} | MAC: {mac} | Vendor: {mac_vendor_check(mac)} | Interface: {interface}"
                    )
                    cursor.execute(
                        "INSERT INTO arp_entries (mac, ipv4, ipv6, interface, hostname, timestamp) VALUES (?, ?, ?, ?, ?, ?)",
                        (mac, ipv4, ipv6, interface, hostname, time_now),
                    )

        conn.commit()

        # check if the log need to be rotated
        rotate_log()

        # Wait before next check
        time.sleep(60)


def rotate_log():
    # Rotate log file if necessary
    if os.path.exists(LOG_FILE) and os.path.getsize(LOG_FILE) > 102400:
        if os.path.exists(LOG_FILE + ".1"):
            os.remove(LOG_FILE + ".1")
        shutil.move(LOG_FILE, LOG_FILE + ".1")
        open(LOG_FILE, "a").close()
        logging.info("Rotated log file")


def mac_vendor_list_download():
    # Download MAC address vendor list if older than 365 days
    if (
        not os.path.exists(MAC_VENDOR_FILE)
        or (time.time() - os.path.getmtime(MAC_VENDOR_FILE)) > 365 * 86400
    ):
        url = "https://maclookup.app/downloads/csv-database/get-db"
        response = requests.get(url)
        if response.status_code == 200:
            with open(MAC_VENDOR_FILE, "wb") as f:
                f.write(response.content)
            logging.info("Downloaded MAC vendor list")
        else:
            logging.error("Failed to download MAC vendor list")


def mac_vendor_check(mac):
    # Load MAC vendor list into a dictionary
    mac_vendor = {}
    with open(MAC_VENDOR_FILE, "r") as f:
        reader = csv.reader(f)
        for row in reader:
            if len(row) >= 2:
                mac_prefix = row[0].strip().replace(":", "").lower()
                vendor = row[1].strip()
                mac_vendor[mac_prefix] = vendor

    # Search for the vendor by progressively increasing the length of the MAC prefix
    for length in range(6, 9):
        matches = [
            vendor
            for prefix, vendor in mac_vendor.items()
            if mac.replace(":", "").lower().startswith(prefix[:length])
        ]
        if len(matches) == 1:
            # Release memory
            del mac_vendor
            gc.collect()
            return matches[0]

    # Release memory
    del mac_vendor
    gc.collect()
    return "<unknown>"


if __name__ == "__main__":
    # check if the log need to be rotated
    rotate_log()

    logging.info("*** Starting ARP/NDP Logging ***")

    logging.info(f"protocols: {protocols}")
    logging.info(f"interfaces: {interfaces}")
    logging.info(f"suppress_mac: {suppress_mac}")

    mac_vendor_list_download()

    main()
