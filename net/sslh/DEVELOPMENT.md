# Development Notes

The initial version of this plugin is going to cover only the most basic of settings. A first pass will include primarily the settings which are available through the command line, and included in documentation.

## Configuration

For configuration, this plugin utilizes the configuration file to set settings. It has more options, and parity for all settings at the command line that are needed to be used in OPNsense. I didn't want to go as far as manipulating the startup script's command execution to inject command line options.

Most settings are available through the configuration file. A sample is provided through the FreeBSD ports installation:

/usr/local/etc/sslh.conf.sample:
```
# This is a basic configuration file that should provide
# sensible values for "standard" setup.

verbose:         0;
foreground:      false;
inetd:           false;
numeric:         false;
transparent:     false;
timeout:         2;
user:            "nobody";
pidfile:         "/var/run/sslh.pid";
chroot:          "/var/empty";


# Change hostname with your external address name.
listen:
(
    { host: "thelonious"; port: "443"; }
);

protocols:
(
     { name: "ssh";     service: "ssh"; host: "localhost"; port: "22";        fork: true; },
     { name: "openvpn";                 host: "localhost"; port: "1194"; },
     { name: "xmpp";                    host: "localhost"; port: "5222"; },
     { name: "http";                    host: "localhost"; port: "80"; },
     { name: "tls";                     host: "localhost"; port: "443";       log_level: 0; },
     { name: "anyprot";                 host: "localhost"; port: "443"; }
);
```

Here is a table which maps the configurations to command line options, and includes the model data types, default values, and if a hint is defined.

| Configuration Key  | Command line Option | Model Field Type | Field Default | Hint          |
| ------------------ | ------------------- | ---------------- | ------------- | ------------- |
| verbose            | -v, --verbose       | BooleanField     | N/A           |               |
| foreground         | -f, --foreground    | N/A              | N/A           |               |
| inetd              | -i, --inetd         | N/A              | N/A           |               |
| numeric            | -n, --numeric       | BooleanField     | N/A           | 2             |
| timeout            | -t, --timeout       | IntegerField     | N/A           |               |
| user               | -u, --user          | N/A              | N/A           |               |
| pidfile            | -P, --pidfile       | N/A              | N/A           |               |
| chroot             | -C, --chroot        | N/A              | N/A           |               |
| on_timeout         | --on-timeout        | OptionField      | ssh           |               |
| listen             | -p, --listen        | CSVListField     | localhost:443 |               |
| protocols          | --ssl, --tls<br>--ssh<br>--openvpn<br>--http<br>--xmpp<br>--tinc<br>--anyprot | TextField | N/A  | localhost:443<br>localhost:22<br>localhost:1194<br>localhost:80<br>localhost:5222<br>localhost:655 |

Here are some non-configuration options which have representation in the model:

| Model Node         | Model Field Type | Field Default | Hint          |
| ------------------ | ---------------- | ------------- | ------------- |
| mode               | OptionField      | fork          |               |

Most settings have defaults set within the application itself, so setting defaults in the model would be redundant. Hints were provided where this occurred, and it happened that the HTML element supports a hint. The only settings with field defaults are the dropdown `OptionFields` which are set to required because selecting nothing for these would require some additional code in the Jinja template to accommodate a blank value. Since they're required, setting a default is best so the user doesn't have to interact with them. Especially since these fields are advanced and would be hidden.

The configuration file has some functionality which isn't explained in the main documentation pages, but is included in some example configuration files (see `fork`, and `log_level` in sample above), and some in the source code. These settings are excluded for now, and need to be investigated further to see what OPNsense model data types would be best to use and how to visualize these settings in the UI.

Some advanced settings can be seen in the test configuration file in the source:

https://github.com/yrutschle/sslh/blob/master/test.cfg

There are also more settings for each protocol (maybe individually?), and it's also possible to define multiple entries for some (all?) settings:

```
protocols: (
    {   name: "tls";
        host: "localhost";
        port: "993";
        sni_hostnames: [ "mail.rutschle.net" ];
    },
    {   name: "tls";
        host: "localhost";
        port: "xmpp-client";
        sni_hostnames: [ "im.rutschle.net" ];
    },
    {   name: "tls";
        host: "localhost";
        port: "4443";
        sni_hostnames: [ "www.rutschle.net" ];
    }
);
```

There is also a more advanced "regex" protocol which can be defined multiple times:
```
protocols: (
    {   name: "regex";
        host: "192.168.0.2";
        port: "80";
        regex_patterns:
                ["^(GET|POST|PUT|OPTIONS|DELETE|HEADER) [^ ]* HTTP/[0-9.]*[\r\n]*Host: host_A.acme"] },
    {   name: "regex";
        host: "192.168.0.3";
        port: "80";
        regex_patterns:
                ["^(GET|POST|PUT|OPTIONS|DELETE|HEADER) [^ ]* HTTP/[0-9.]*[\r\n]*Host: host_B.acme"] }
);
```

To support multiple entries, an `ArrayField` type will have to be used, and bootgrids utilized to control the entries within each field. For that it may be best to split out each protocol onto a separate tab, rather than have them displayed on the same tab (may look cluttered).

The style of the entries here is different than those provided in the sample which would require some additional care in the Jinja template if that style is to be used.

## Command Line Options

For reference, here are the command line options:

Command line options:
```
sslh
	[-Fconfig file]
	[-t num]
	[--transparent]
	[-p listening address [-p listening address ...]
	[--ssl target address for SSL]
	[--tls target address for TLS]
	[--ssh target address for SSH]
	[--openvpn target address for OpenVPN]
	[--http target address for HTTP]
	[--xmpp target address for XMPP]
	[--tinc target address for TINC]
	[--anyprot default target address] (use this for SSLv2 connections)
	[--on-timeout protocol name]
	[-u username]
	[-C chroot] [-P pidfile] [-v] [-i] [-V] [-f] [-n]
```

## Logging

This application may not have a function to output to a log file. Documentation indicates that `sslh` should be started manually, and run with the `foreground` option to get log messages clearly. Otherwise, logs are sent to the `syslog` facility. There is a configuration setting: `syslog_facility: "auth";` which might be used to change this behavior.

On OPNsense 22.1 the logging goes to the `audit` facility, and looks something like this:
```
2021-12-29T23:41:07+00:00 OPNsense.localdomain sslh-fork[62839] 62839 - [meta sequenceId="43"] sslh-fork 1.21c started
```

It's a bit noisy with the "meta sequence" part, and the PID being displayed twice. Hopefully that will get cleaned up eventually. It should be possible to utilize the built-in log API to display these log entries, and utilize a hard coded filter to display only messages for `sslh`.

A more crude option is to set the `foreground` option in the configuration file, start `sslh` via configd, and redirect the output to a file located in `/var/log/`. `configd` might not do well with this since commands that it runs are expected to exit. Maybe the `&` operator can be used to background the process, and maybe `configd` could deal with that better. It would definitely need more testing to confidently use as a solution though.

## Jinja Templates

The protocols section can probably be reduced to a single for loop which iterates through each variable, and appends the line to the list. The extra settings described above would have to be taken into consideration if some settings only apply to specific protocols. The alternative multi-line style would also have to be considered to make sure everything looks nice in the file.

## Protocol Order

The `anyprot` documentation mentions that `sslh` will try protocols in the order specified (at the command line). This probably also means that it applies for the configuration file as well. Being able to change the order, will eventually be necessary for full functionality. As for a standard order, the `man` page command line reference, the order in which the commands are detailed, and the sample configuration files all use different ordering.

This could possibly be done with a single `ArrayField` containing all protocol entries. With the protocol name being one of the fields in each entry. That would probably be better than the multi-field approach as described earlier. This would put all of the entries in a single bootgrid which could be displayed on a single page. A number field (`AutoNumberField`?) could be added to indicate the appropriate order. Without a UI function to perform this ordering it could get a bit complicated for new users. It's definitely more complicated than just a list of static boxes. Changing the order is not a function of the bootgrid at the moment.

There is also the need to consider that some options may only be available/function for specific protocols. There may be the possibility to employ constraints/requirements for field usage if a specific field only works with a specific protocol, but it will have to be investigated further. This would tie in also to the Jinja template because the protocols section could use for loop as described above, but would definitely have to display all of the additional options for each protocol.

## Service Status

The fact that this service can run as different binaries means that under specific circumstances, sometimes the service status can return a status of "not running" even when the service is running. The UI won't offer to stop the service because it thinks it's not running. If the service is configured to run with the one variant, and then the configuration is changed to use the another variant without a service restart (though some error occurring), then the service status will be looking for the new variant when looking at the status, and it will say "not running," but the previous variant will still be running. If there are no errors with saving the configuration, and the service API restarts the service successfully after saving the configuration, then it should be relatively rare occurrence.

## Transparent mode

This function is available using the command line option "-t, --transparent" or through the configuration file using the "transparent" keyword. The documentation describes this as a "Linux only" feature, and the documentation demonstrates using this feature in conjunction with `iptables`. Since FreeBSD doesn't have `iptables` it's probably that this feature won't work. Since there is no provisions for using this feature on FreeBSD, it's been excluded from the this plugin.
