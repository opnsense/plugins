#!/usr/local/bin/python3

# DNS BL script
# Copyright 2020 Petr Kejval <petr.kejval6@gmail.com>

# Downloads blacklisted domains from user specified URLs and "compile" them into unbound.conf compatible file

# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions
# are met:
#
# 1. Redistributions of source code must retain the above copyright
#    notice, this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in the
#    documentation and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
# ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
# IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
# ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
# FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
# DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
# OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
# HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
# LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
# OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
# SUCH DAMAGE.

import re, urllib3, threading, subprocess

re_pattern = re.compile(r'^127\.0\.0\.1\s|^0.0.0.0\s(.*)|^([a-z_.-]+$)', re.I)
re_whitelist = re.compile(r'$^') # default - match nothing
blacklist = set()
urls = set()

predefined_lists = {
    "aa": "https://adaway.org/hosts.txt",
    "ag": "https://justdomains.github.io/blocklists/lists/adguarddns-justdomains.txt",
    "bla": "https://blocklist.site/app/dl/ads",
    "blf": "https://blocklist.site/app/dl/fraud",
    "blp": "https://blocklist.site/app/dl/phishing",
    "ca": "http://sysctl.org/cameleon/hosts",
    "el": "https://justdomains.github.io/blocklists/lists/easylist-justdomains.txt",
    "ep": "https://justdomains.github.io/blocklists/lists/easyprivacy-justdomains.txt",
    "emd": "https://hosts-file.net/emd.txt",
    "hpa": "https://hosts-file.net/ad_servers.txt",
    "hpf": "https://hosts-file.net/fsa.txt",
    "hpp": "https://hosts-file.net/psh.txt",
    "hup": "https://hosts-file.net/pup.txt",
    "nc": "https://justdomains.github.io/blocklists/lists/nocoin-justdomains.txt",
    "rw": "https://ransomwaretracker.abuse.ch/downloads/RW_DOMBL.txt",
    "mw": "http://malwaredomains.lehigh.edu/files/justdomains",
    "pa": "https://raw.githubusercontent.com/chadmayfield/my-pihole-blocklists/master/lists/pi_blocklist_porn_all.list",
    "pt": "https://raw.githubusercontent.com/chadmayfield/pihole-blocklists/master/lists/pi_blocklist_porn_top1m.list",
    "sa": "https://s3.amazonaws.com/lists.disconnect.me/simple_ad.txt",
    "sb": "https://raw.githubusercontent.com/StevenBlack/hosts/master/hosts",
    "st": "https://s3.amazonaws.com/lists.disconnect.me/simple_tracking.txt",
    "ws": "https://raw.githubusercontent.com/crazy-max/WindowsSpyBlocker/master/data/hosts/spy.txt",
    "wsu": "https://raw.githubusercontent.com/crazy-max/WindowsSpyBlocker/master/data/hosts/update.txt",
    "wse": "https://raw.githubusercontent.com/crazy-max/WindowsSpyBlocker/master/data/hosts/extra.txt",
    "yy": "http://pgl.yoyo.org/adservers/serverlist.php?hostformat=hosts&mimetype=plaintext"
}

def AddToBL(domain):
    """ Checks if domain is present in whitelist. If not, domain is addded to BL set. """
    match = re_whitelist.match(domain)
    if not match:
        blacklist.add(domain)

def Parse(line):
    """ Check if line matches re_pattern. If so, tries add domain to BL set. """
    global blacklist
    match = re_pattern.match(line)
    if match:
        if match.group(1) != None:
            AddToBL(match.group(1))
        elif match.group(2) != None:
            AddToBL(match.group(2))

def Process(url):
    """ Reads and parses blacklisted domains from URL into BL set. """
    print(f"Processing BL items from: {url}") 
    
    try:
        http = urllib3.PoolManager()
        r = http.request('GET', url)

        for line in str(r.data).split('\\n'):
            Parse(line)
    except Exception as e:
        print(str(e))

def SaveConfigFile():
    """ Saves blacklist in unbound.conf format """
    print(f"Saving {len(blacklist)} blacklisted domains into dnsbl.conf")

    try:
        with open("/var/unbound/etc/dnsbl.conf", 'w') as file:
            # No domains found or DNSBL is disabled
            if (len(blacklist) == 0):
                file.write("")
            else:
                file.write('server:\n')
                for line in blacklist:
                    #file.write('local-zone: "' + str(line) + '" static\n')
                    file.write('local-data: "' + str(line) + ' A 0.0.0.0"\n')
    except Exception as e:
        print(str(e))
        exit(1)

def LoadList(path, separator=None):
    """ Reads file with specified path into set to ensure unique values. 
    Splits lines with defined separator. If sperator==None no split is performed. """
    result = set()
    
    try:
        with open(path, 'r') as file:
            for line in file.readlines():
                if not separator == None:
                    for element in line.split(separator):
                        result.add(element.replace('\n', ''))
                else:
                    result.add(line.replace('\n', ''))
    except Exception as e:
        print(str(e))

    return result

def LoadWhitelist():
    """ Loads user defined whitelist in regex format and compiles it. """
    print("Loading whitelist")
    global re_whitelist
    wl = LoadList('/var/unbound/etc/whitelist.inc', ',')
    print(f"Loaded {len(wl)} whitelist items")

    if len(wl) > 0:
        try:
            re_whitelist = re.compile('|'.join(wl))
        except Exception as e:
            print(f"Whitelist regex compile failed: {str(e)}")

def LoadBlacklists():
    """ Loads user defined blacklists URLs. """
    print("Loading blacklists URLs")
    global urls
    urls = LoadList('/var/unbound/etc/lists.inc', ',')
    print(f"Loaded {len(urls)} blacklists URLs")

def LoadPredefinedLists():
    """ Loads user chosen predefined lists """
    print("Loading predefined lists URLs")
    global urls
    lists = LoadList('/var/unbound/etc/dnsbl.inc')
    types = set()

    for first in lists:
        first = str(first).split('=')[1]
        first = str(first).replace('"', '').replace('\n', '')
        first = first.split(',')
        for type in first:
            types.add(type)
        break
    
    print(f"Loaded {len(types)} predefined blacklists URLs")

    for type in types:
        try:
            urls.add(predefined_lists[type])
        except KeyError:
            continue
        except Exception as e:
            print(str(e))

if __name__ == "__main__":
    # Prepare lists from config files
    LoadWhitelist()
    LoadBlacklists()
    LoadPredefinedLists()

    # Start processing BLs in threads
    threads = [threading.Thread(target=Process, args=(url,)) for url in urls]
    for t in threads:
        t.start()
    for t in threads:
        t.join()
    
    SaveConfigFile()

    print("Restarting unbound service")
    subprocess.Popen(["pluginctl", "-s", "unbound", "restart"])
    exit(0)