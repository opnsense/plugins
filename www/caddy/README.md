# Caddy Plugin for OPNsense

- This project provides a simple yet powerful plugin for [OPNsense](https://github.com/opnsense) to enable support for [Caddy](https://github.com/caddyserver/caddy).
- The scope is the reverse proxy features.
- The main goal is an easy to configure plugin. Most options that aren't generally needed are hidden behind the advanced mode for this reason.
- The feature set is complete for now.

## Main Features

- Modern and fast Reverse Proxy based on [Caddy](https://caddyserver.com/)
- Automatic Let's Encrypt and ZeroSSL Certificates without configuration with HTTP-01 and TLS-ALPN-01
- ACME DNS-01 challenge with configuration (requires supported DNS Provider)
- Dynamic DNS (DynDns) with configuration (requires supported DNS Provider)
- Supported DNS Providers in GUI: ```cloudflare, duckdns, digitalocean, dnspod, hetzner, godaddy, gandi, ionos, desec, porkbun, route53, acmedns, alidns, googleclouddns, azure, openstack-designate, ovh, namecheap, netlify, namesilo, powerdns, vercel, ddnss, njalla, metaname, linode, tencentcloud, dinahosting, hexonet, mailinabox```
- Use custom certificates from OPNsense certificate store
- Normal domains, wildcard domains and subdomains
- Access Lists to restrict access based on static networks
- Basic Auth to restrict access by username and password
- Syslog-ng integration and HTTP Access Log
- NTLM Transport for Exchange Server

## License

- This project is licensed under the BSD 2-Clause "Simplified" license. See the LICENSE file for details.
- Caddy is licensed under the Apache License, Version 2.0.
- OPNsense is licensed under the BSD 2-Clause “Simplified” license.

## Acknowledgments

- Thanks to the Caddy community/developers for creating a fantastic open source web server.
- Thanks to the OPNsense community/developers for creating a powerful and flexible open source firewall and routing platform.
- Additional big **Thank You** in no particular order: [AdShellevis](https://github.com/Adschellevis), [mimugmail](https://forum.opnsense.org/index.php?action=profile;u=15464), [gspannu](https://github.com/gspannu), [francislavoie](https://caddy.community/u/francislavoie/summary), [matt](https://caddy.community/u/matt/summary), [fichtner](https://github.com/fichtner)

# How to install

- Install "os-caddy" from the OPNsense Plugins.

## Prepare Caddy for use after the installation

**Attention**, additional preparation of OPNsense needed:
- Make sure that port `80` and `443` aren't occupied. You have to change the default listen port to `8443` for example. Go to `System: Settings: Administration` to change the `TCP Port`. Then also enable `HTTP Redirect - Disable web GUI redirect rule`.
- If you have other reverse proxy or webserver plugins installed, make sure they don't use the same ports as Caddy
- Create Firewall rules that allow 80 and 443 TCP to "This Firewall" on WAN and (optionally) LAN, OPT1 etc...
- There is a lot of input validation. If you read all the hints, help texts and error messages, its unlikely that you create a configuration that won't work.
- **Attention**: If you use this in HA (High Availability), only use your own custom certificates. Caddy needs a shared storage for the ACME challenges to work on two or more firewalls in HA at the same time. This is out of scope, since offering shared storage on firewalls where one can potentially fail, would leave the other without storage for Caddy to work with.

# Available Settings in "Services - Caddy Web Server"
**Please note that some options are hidden in advanced mode.**
## General Settings - General
- `Enable` or `disable` Caddy
- `ACME Email`: e.g. `info@example.com`, it's optional.
- `Auto HTTPS`: `On (default)` creates automatic Let's Encrypt Certificates for all Domains that don't have more specific options set, like custom certificates.
- `Trusted Proxies`: Leave empty if you don't use a CDN in front of your OPNsense. If you use Cloudflare or another CDN provider, create an access list with the IP addresses of that CDN and add it here. Add the same Access List to the domain this CDN tries to reach.
- `Abort Connections`: This option, when enabled, aborts all connections to the Reverse Proxy Domain that don't match any specified handler or access list. This setting doesn't affect Let's Encrypt's ability to issue certificates, ensuring secure connections regardless of the option's status. If unchecked, the Reverse Proxy Domain remains accessible even without a matching handler, allowing for connectivity and certificate checks, even in the absence of a configured Backend Server. When using Access Lists, enabling this option is recommended to reject unauthorized connections outright. Without this option, unmatched IP addresses will encounter an empty page instead of an explicit rejection, though the Access Lists continue to function and restrict access.

## General Settings - DNS Provider
- `DNS Provider`: Select the DNS provider for the DNS-01 Challenge and Dynamic DNS. This is optional, since certificates will be requested from Let's Encrypt via HTTP-01 or TLS-ALPN-01 Challenge when this option is unset. You mostly need this for Wildcard Certificates, and for Dynamic DNS. To use the DNS-01 Challenge and Dynamic DNS, enable the checkbox in a Reverse Proxy Domain or Subdomain. For more information: https://github.com/caddy-dns
- `DNS API Standard Field`: This is the standard field for the API Key. Field can be left empty if optional: Cloudflare "api_token", Duckdns "api_token", DigitalOcean "auth_token", DNSPod "auth_token", Hetzner "api_token", Godaddy "api_token", Gandi "bearer_token", IONOS "api_token", deSEC "token", Route53 "access_key_id", Porkbun "api_key", ACME-DNS "username", Netlify "personal_access_token", Namesilo "api_token", Njalla "api_token", Vercel "api_token",  Google Cloud DNS "gcp_project", Alidns "access_key_id", Azure "tenant_id", OpenStack Designate "region_name", OVH "endpoint", Namecheap "api_key", PowerDNS "server_url", DDNSS "api_token", Metaname "api_key", Linode "api_token", Tencent Cloud "secret_id", Dinahosting "username", Hexonet "username", Mail-in-a-Box "api_url".
- `DNS API Additional Field 1`: Leave empty if your DNS Provider isn't specified here. Field can be left empty if optional: Duckdns "override_domain", Route53 "secret_access_key", Porkbun "api_secret_key", ACME-DNS "password", Alidns "access_key_secret", Azure "client_id", OpenStack Designate "tenant_id", OVH "application_key", Namecheap "user", PowerDNS "api_token", DDNSS "username", Metaname "account_reference", Linode "api_url", Tencent Cloud "secret_key", Dinahosting "password", Hexonet "password", Mail-in-a-Box "email_address".
- `DNS API Additional Field 2`: Leave empty if your DNS Provider isn't specified here. Field can be left empty if optional: Route53 "max_retries", ACME-DNS "subdomain", Azure "client_secret", OpenStack Designate "identity_api_version", OVH "application_secret", Namecheap "api_endpoint", DDNSS "password", Linode "api_version", Mail-in-a-Box "password".
- `DNS API Additional Field 3`: Leave empty if your DNS Provider isn't specified here. Field can be left empty if optional: Route53 "aws_profile", ACME-DNS "server_url", Azure "subscription_id", OpenStack Designate "password", OVH "consumer_key", Namecheap "client_ip", DDNS "password".
- `DNS API Additional Field 4`: Leave empty if your DNS Provider isn't specified here. Field can be left empty if optional: Route53 "region", Azure "resource_group_name", OpenStack Designate "username".
- `DNS API Additional Field 5`: Leave empty if your DNS Provider isn't specified here. Field can be left empty if optional: Route53 "token", OpenStack Designate "tenant_name".
- `DNS API Additional Field 6`: Leave empty if your DNS Provider isn't specified here. Field can be left empty if optional: OpenStack Designate "auth_url".
- `DNS API Additional Field 7`: Leave empty if your DNS Provider isn't specified here. Field can be left empty if optional: OpenStack Designate "endpoint_type".

## General Settings - Dynamic DNS
- `DynDns Check Http`: Optionally, enter an URL to test the current IP address of the firewall via HTTP procotol. Generally, this is not needed. Caddy uses default providers to test the current IP addresses. If you rather use your own, enter the https:// link to an IP address testing website.
- `DynDns Check Interface`: Optionally, select an interface to extract the current IP address of the firewall. Attention, all IP addresses will be read from this interface. Only choose this option if you know the implications.
- `DynDns Check Interval`: Interval to poll for changes of the IP address. The default is 5 minutes. Can be a number between 1 to 1440 minutes.
- `DynDns IP Version`: Leave on None to set IPv4 A-Records and IPv6 AAAA-Records. Select "Ipv4 only" for setting A-Records. Select "IPv6 only" for setting AAAA-Records.
- `DynDns TTL`: Set the TTL (time to live) for DNS Records. The default is 1 hour. Can be a number between 1 to 24 hours.

## General Settings - Log Settings
- `Log Credentials`: Log all Cookies and Authorization in HTTP request logging. Use combined with HTTP Access Log in the Reverse Proxy Domain. Enable this option only for troubleshooting.
- `Log Access in Plain Format`: Don't send HTTP(S) access logs to the central OPNsense logging facility but save them in plain Caddy JSON format in a subdirectory instead. Only effective for Reverse Proxy Domains that have HTTP Access Log enabled. The feature is intended to have access log files processed by e.g. CrowdSec. They can be found in `/var/log/caddy/access`.
- `Keep Plain Access Logs for (days)`: How many days until the plain format log files are deleted.

## Reverse Proxy - Domains
- Press `+` to create a new Reverse Proxy Domain
- `Enable` this new entry
- `Reverse Proxy Domain`: Can either be a domain name or an IP address. If a domain name is chosen, Caddy will automatically try to get an ACME certificate, and the header will be automatically passed to the Server in the backend.
- `Reverse Proxy Port`: Should be the port the OPNsense will listen on. Don't forget to create Firewall rules that allow traffic to this port on `WAN` or `LAN` to `This Firewall`. You can leave this empty if you want to use the default ports of Caddy (`80` and `443`) with automatic redirection from HTTP to HTTPS.
- `Access List`: Restrict the access to this domain to a list of IP addresses you define in the `Access` Tab. This doesn't influence the Let's Encrypt certificate generation, so you can be as restrictive as you want here.
- `Basic Auth`: Restrict the access to this domain to one or multiple users you define in the `Access` Tab. This doesn't influence the Let's Encrypt certificate generation, so you can be as restrictive as you want here.
- `DNS-01 challenge`: Enable this if you want to use the `DNS-01` ACME challenge instead of HTTP challenge. This can be set per entry, so you can have both types of challenges at the same time for different entries. This option needs the `General Settings` - `DNS Provider` and `API KEY` set.
- `Dynamic DNS`: Enable Dynamic DNS, please configure DNS Provider and API Key in General Settings. The DNS Records of this domain will be automatically updated with your DNS Provider.
- `Custom Certificate`: Use a Certificate you imported or generated in `System - Trust - Certificates`. The chain is generated automatically. `Certificate + Intermediate CA + Root CA`, `Certificate + Root CA` and `self signed Certificate` are all fully supported.
- `HTTP Access Log`: Enable the HTTP request logging for this domain and its subdomains. This option is mostly for troubleshooting since it will log every single request.
- `Description`: The description is mandatory. Create descriptions for each domain. Since there could be multiples of the same domain with different ports, do it like this: `foo.example.com` and `foo.example.com.8443`.

## Reverse Proxy - Subdomains
- Press `+` to create a new Reverse Proxy Subdomain
- `Reverse Proxy Domain` - Choose a wildcard domain you prepared in "Reverse Proxy - Domains", it has to be formatted like `*.example.com`
- `Reverse Proxy Subdomain` - Create a name that is seated under the Wildcard domain, for example `foo.example.com` and `bar.example.com`.
- For the other options refer to Domains.

## Reverse Proxy - Handler
Please note that the order that handlers are saved in the scope of each domain or domain/subdomain can influence functionality - The first matching handler wins. So if you put /ui* in front of a more specific handler like /ui/opnsense, the /ui* will match first and /ui/opnsense won't ever match (in the scope of their domain). Right now there isn't an easy way to move the position of handlers in the grid, so you have to clone them if you want to change their order, and delete the old entries afterwards. Most of the time, creating just one empty catch-all handler is the best choice. The template logic makes sure that catch-all handlers are always placed last, after all other handlers.
- Press `+` to create a new `Handler`. A Handler is like a location in nginx.
- `Enable` this new entry.
- `Reverse Proxy Domain`: Select the domain you have created in `Reverse Proxy Domains`.
- `Reverse Proxy Subdomain`: Leave this on `None`. It is not needed without having a wildcard certificate, or a `*.example.com` Domain.
- `Handle Type`: `Handle` or `Handle Path` can be chosen. If in doubt, always use `Handle`, the most common option. `Handle Path` is used to strip the path from the URI. For example if you have example.com/opnsense internally, but want to call it with just example.com externally.
- `Handle Path`: Leave this empty if you want to create a catch all location. You can create multiple Handler entries, and have each of them point at different locations like `/foo/*` or `/foo/bar/*` or `/foo*`.
- `Backend Server Domain`: Should be an internal domain name or an IP Address of the Backend Server that should receive the traffic of the `Reverse Proxy Domain`.
- `Backend Server Port`: Should be the port the Backend Server listens on. This can be left empty to use Caddy default ports 80 and 443.
- `Backend Server Path`: In case the backend application resides in a sub-path of the web root and you don't want this path visible in the frontend URL you can use this setting to prepend an initial path starting with '/' to every backend request. Java applications running in a servlet container like Tomcat are known to behave this way, so you can set it to e.g. '/guacamole' to access Apache Guacamole at the frontend root URL without needing a redirect.
- `TLS`: If your Backend Server only accepts HTTPS, enable this option. If the Backend Server has a globally trusted certificate, this is all you need.
- `TLS Trusted CA Certificates`: Choose a CA certificate to trust for the Backend Server connection. Import your self-signed certificate or your CA certificate into the OPNsense "System - Trust - Authorities" store, and select it here.
- `TLS Server Name`: If the SAN (Subject Alternative Names) of the offered trusted CA certificate or self-signed certificate doesn't match with the IP address or hostname of the `Backend Server Domain`, you can enter it here. This will change the SNI (Server Name Identification) of Caddy to the `TLS Server Name`. IP address e.g. `192.168.1.1` or hostname e.g. `localhost` or `opnsense.local` are all valid choices. Only if the SAN and SNI match, the TLS connection will work, otherwise an error is logged that can be used to troubleshoot.
- `NTLM`: If your Backend Server needs NTLM authentication, enable this option together with `TLS`. For example, Exchange Server.

**Attention**: The GUI doesn't allow "tls_insecure_skip_verify" due to safety reasons, as the Caddy documentation states not to use it. Use the `TLS Trusted CA Certificates` and `TLS Server Name` options instead to get a **secure TLS connection** to your Backend Server. Otherwise, use HTTP. If you really need to use "tls_insecure_skip_verify" and know the implications, use the import statements of custom configuration files.

## Reverse Proxy - Access - Access Lists
- Press `+` to create a new Access List
- `Access List name`: Choose a name for the Access List, for example `private_ips`.
- `Client IP Addresses`: Enter any number of IPv4 and IPv6 addresses or networks that this access list should contain. For example for matching only internal networks, add `192.168.0.0/16` `172.16.0.0/12` `10.0.0.0/8` `127.0.0.1/8` `fd00::/8` `::1`.
- `Invert List`: Invert the logic of the access list. If unchecked, the Client IP Addresses will be ALLOWED, all other IP addresses will be blocked. When checked, the Client IP Addresses will be BLOCKED, all other IP addresses will be allowed.
- Afterwards, go back to Domains or Subdomains and add the Access List you have created to them (advanced mode). All handlers created under these Domains will get an additional matcher. That means, the requests still reach Caddy, but if the IP Addresses don't match with the Access List logic, the request doesn't match any handler and will be dropped before being reverse proxied to any Backend Server. If you are using a CDN, make sure the Access List in General - Trusted Proxies and on each Domain used for that CDN are the same.

## Reverse Proxy - Access - Basic Auth
- Press `+` to create a new User for Basic Auth
- `User`: Enter a username. Afterwards, you can select it in Reverse Proxy Domains or Subdomains to restrict access with basic auth. Usernames are only allowed to have alphanumeric characters.
- `Password`: Enter a password. Write it down. It will be hashed with bcrypt. It can only be set and changed but won't be visible anymore. The hash can't be turned back into the original password.
- Afterwards, go back to Domains or Subdomains and add the one or multiple basic auth users you have created to them (advanced mode). The basic auth matches after access lists, so you can set both to first restrict access by IP address, and then additionally by username and password. Please note that if you delete a user before deselecting it in a domain, the basic auth will stay with no user. If that happens you have to select the "clear all" in the domain or subdomain and save. Don't set basic auth on top of a wildcard domain directly, always set it on the subdomains instead.

# HOW TO Section:

## HOW TO: Create an easy reverse proxy
**Services - Caddy Web Server - General Settings:**
- `Enable` Caddy and press `Apply`

**Services - Caddy Web Server - Reverse Proxy - Domain:**
- Press `+` to create a new Reverse Proxy Domain
- `Reverse Proxy Domain` - `foo.example.com`
- `Description` - `foo.example.com`
- `Save`

**Services - Caddy Web Server - Reverse Proxy - Handler:**
- Press `+` to create a new Handler
- `Reverse Proxy Domain` - `foo.example.com`
- `Backend Server Domain` - `192.168.10.1`
- `Save`
- `Apply`

Done, leave all other fields to default or empty. You don't need the advanced mode options. After just a few seconds the Let's Encrypt Certificate will be installed and everything just works. Check the Logfile for that.
Now you have a "Internet <-- HTTPS --> OPNsense (Caddy) <-- HTTP --> Backend Server" Reverse Proxy.

## HOW TO: Create a wildcard subdomain reverse proxy
- Do everything the same as above, but create your Reverse Proxy Domain like this `*.example.com` and activate the `DNS-01` challenge checkbox.
- OR - `Custom Certificate` - Use a Certificate you imported or generated in `System - Trust - Certificates`. It has to be a wildcard certificate.
- Go to the `Reverse Proxy Subdomain` Tab and create all subdomains that you need in relation to the `*.example.com` domain. So for example `foo.example.com` and `bar.example.com`.
- Create descriptions for each subdomain. Since there could be multiples of the same subdomain with different ports, do it like this: `foo.example.com` and `foo.example.com.8443`.
- In the `Handler` Tab you can now select your `*.example.com` `Reverse Proxy Domain`, and if `Reverse Proxy Subdomain` is `None`, the Handlers are added to the base `Reverse Proxy Domain`. For example, if you want a catch all Handler for all non referenced subdomains.
- If you create a Handler with `*.example.com` as `Reverse Proxy Domain` and `foo.example.com` as `Reverse Proxy Subdomain`, a nested Handler will be generated. You can do all the same configurations as if the subdomain is a normal domain, with multiple Handlers and Handler paths.

## HOW TO: Create a Handle with TLS and a trusted self-signed Certificate
**Example: Reverse Proxy the OPNsense Configuration GUI Website with Caddy**
- Open your OPNsense GUI in a Browser (e.g. Chrome or Firefox). Inspect the certificate. Copy the SAN for later use, for example `OPNsense.localdomain`.
- Save the certificate in your Browser as PEM file. Open it up with a text editor, and copy the contents into a new entry in `System - Trust - Authorities`. Name the certificate e.g. `opnsense-selfsigned`.
- Add a new Reverse Proxy Domain, for example `opn.example.com`. Make sure the name is externally resolvable to the IP of your OPNsense Firewall with Caddy.
- Add a new Handler with the following options (enable advanced mode):
- `Reverse Proxy Domain`: `opn.example.com`
- `Backend Server Domain`: `127.0.0.1`
- `Backend Server Port`: `8443` (Enter the port of your OPNsense GUI. You have changed it from 443 to a different port, since Caddy needs port 443.)
- `TLS`: `X`
- `TLS Trusted CA Certificates`: `opnsense-selfsigned` (The certificate you have saved in `System - Trust - Authorities`)
- `TLS Server Name`: `OPNsense.localdomain` (The SAN of the certificate)
- Save
- Apply
- Open `https://opn.example.com` and it should serve the reverse proxied OPNsense Configuration GUI Website. Check the log file for errors if it doesn't work, most of the time the `TLS Server Name` doesn't match the SAN of the `TLS Trusted CA Certificates`. Please note that Caddy doesn't support CN (Common Name) in certificate since it's been deprecated since many years.
- Additionally, you can create an access list to limit access to the GUI only from trusted IP addresses (recommended). Add that access list to the domain `opn.example.com` in advanced mode. Also, enable `Abort Connections` in the `General` Settings to abort all connections immediately that don't match the access list or the handler.

# Troubleshooting
- You can always test if your current Caddyfile is valid by invoking `/api/caddy/service/validate` - This is also done automatically each time `Apply` is pressed. If you have an invalid configuration, Caddy will refuse to start and show the exact error message.
- Check `/var/log/caddy/caddy.log` or `@latest.log` to find errors. There is also a Caddy Log File in the GUI.
- A good indicator that Caddy is indeed running is this log entry: `serving initial configuration`
- Check the Service Widget and the "General Settings" Service Control buttons. If everything works they should show a green "Play" sign. If Caddy is stopped there is a red "Stop" sign. If Caddy is disabled, there is no widget and no control buttons.

# Build caddy and os-caddy from source
- As build system use a FreeBSD 13.2 - https://github.com/opnsense/tools
- Use xcaddy to build your own caddy binary. Additonal Caddy plugins can be compiled in, here is an example: [Additional Plugins](https://github.com/opnsense/tools/blob/a555d25b11486835460a136af0b8ad2e517ae96b/config/24.1/make.conf#L94)
- Check the +MANIFEST file and put all dependant files into the right paths on your build system. Make sure to check your own file hashes with ```sha256 /path/to/file```.
- Use ```pkg create -M ./+MANIFEST``` in the folder of the ```+MANIFEST``` file.
- For os-caddy.pkg make sure you have the OPNsense tools build system properly set up.
- Build the os-caddy.pkg by going into /usr/plugins/devel/caddy/ and invoking ```make package```

# Custom configuration files
- The Caddyfile has an additional import from the path ```/usr/local/etc/caddy/caddy.d/```. You can place your own custom configuration files inside that adhere to the Caddyfile syntax.
- ```*.global``` will be imported into the global block of the Caddyfile. Global options can be found here: [Global Options Block](https://caddyserver.com/docs/caddyfile/options)
- ```*.conf``` will be imported at the end of the Caddyfile, you can put your own reverse_proxy or other settings there. Don't forget to test your custom configuration with `caddy run --config /usr/local/etc/caddy/Caddyfile`.

# Using the REST API to control the plugin:
The Rest API is now fully integreated with the OPNsense syntax.
https://docs.opnsense.org/development/api.html

All API Actions can be found in the API Controller files ```/usr/local/opnsense/mvc/app/controllers/Pischem/Caddy/Api```

Examples:
- /api/caddy/ReverseProxy/get
- /api/caddy/General/get
- /api/caddy/service/status
- /api/caddy/service/validate
