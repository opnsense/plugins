# dnscrypt-proxy plugin for OPNsense

# ![dnscrypt-proxy 2](https://raw.github.com/dnscrypt/dnscrypt-proxy/master/logo.png?3)

## Introduction

This is a plugin for OPNsense firewall which provides a front-end UI for managing `dnscrypt-proxy`. Original plugin written by @mimugmail.

This plugin is designed to function with v2.1.0+.

Here are some useful resources for `dnscrypt-proxy`:

* [DNSCrypt Proxy project source](https://github.com/DNSCrypt/dnscrypt-proxy)
* **[dnscrypt-proxy documentation](https://dnscrypt.info/doc) ← Start here**
* [DNSCrypt project home page](https://dnscrypt.info/)
* [Discussions](https://github.com/DNSCrypt/dnscrypt-proxy/discussions)
* [DNS-over-HTTPS and DNSCrypt resolvers](https://dnscrypt.info/public-servers)
* [Server and client implementations](https://dnscrypt.info/implementations)
* [DNS stamps](https://dnscrypt.info/stamps)
* [FAQ](https://dnscrypt.info/faq)

## Features

Here is a summary of the features of this plugin:

* All settings represented in the UI
* Enable/disable complex/advanced features
* Upload/Download of allowed/blocked lists
* External allowed/blocked names support
* Importing/Exporting of allowed/blocked lists and some others
* Diagnostic tools
* All logs supported
* Resolver list, displays all currently available resolvers.
* Anonymized DNS configuration and relay list

## Operation

There are three pages available on the OPNsense menu for operating this plugin.

### Settings

This is the primary page which most of the functionality of this plugin is available. It contains 6 primary tabs, and several other subtabs (accessible with the arrow to the right of the tab name).

The settings are organized for the most part in the order in which they appear in the configuration file (`dnscrypt-proxy.toml`). Further, sub-sections of the configuration file which are signified by square brackets ([]) have their own dedicated full tab, or a sub tab.

#### Global

The Global settings are organized into basic, and advanced settings, with advanced settings being on a subtab. Many settings include default configurations already, as defined in the example `dnscrypt-proxy.toml` configuration file.

* Where appropriate, features are grouped together visually with a header which itself has a help icon which explains that group's focus.
* All settings have a help description for user reference. The majority of these are verbatim from `dnscrypt-proxy`'s documentation, but some have additional explanations or examples or different phrasing.
* Where applicable, the first line of the help is the setting name in bold. This is for ease of use for matching a setting in the UI which often uses a label in plain English, with a setting in the configuration file which may not have the same name. This is especially useful when referencing documentation from `dnscrypt-proxy`.

##### Basic Settings

This is the default page for the plugin when selecting the settings page. It includes some of the basic settings to get the plugin started.

* Some settings preclude functionality of other settings. One such setting is `server_names`. When using this setting, the "require_" settings (`require_dnssec`, `require_nolog`, `require_nofilter`) do not apply. There is an option to enable or disable manual server selection (i.e. using `server_names` setting) which will then disable and enable the applicable settings.
* Dropdown menus for both `server_names`, and `disabled_server_names` are dynamically populated, and utilize multi-select picker with search function. These servers come from servers defined in sources files, and also statically defined servers in the configuration.

##### Advanced Settings

This tab includes many settings which most users will have no interest in changing. Many of these settings have the potential to break functionality of `dnscrypt-proxy` entirely. Where possible they're populated or hinted with default settings.

* The DoH TLS Cipher Suite is easily configurable with a multi-select picker dropdown for ease of use.
* Log level configuration is made easy with a single select dropdown, and each level is labeled appropriately.
* Several other general logging settings are included here such as for the Query, and Non-Existent domain logs.

#### Lists/Rules

This tab and subtabs include various settings for the available lists and rules configurations.  These are the most complex pages due to some additional features. Since the allowed and blocked lists have similar functionality, a brief description is included here, with a larger summary of the features included below.

##### Blocked Names

This is the default subtab for this tab and includes several features for managing blocked names.

##### Blocked IPs

Blocked IPs differs from Blocked Names only in that it exclusively supports IP-based expressions. Functionally, this tab works the same as the Blocked Names tab.

##### Allowed Names

Allowed Names is for defining domains which should be allowed, even if they would be blocked by Blocked Names. This is for more specific definitions where Blocked Names should be used for broader definitions.

##### Allowed IPs

Allowed IPs differs from Allowed Names only in that it exclusively supports IP-based expressions. Functionally, this tab works the same as the Allowed Names tab.

##### Lists/Rules Features

Since the features are generally the same across all of these pages a general description is included below:

* It's possible to enable and disable a list entirely for quick troubleshooting or re-configurations, for example, if the list is causing issues. When disabling a list, all of the settings on the page are disabled (grayed out).
* There are three list types available for selection, external, manual, and internal. Selecting each option will enable and disable the relevant settings on the page to prevent misconfiguration.
* *External* list allows referencing a file within the file system within OPNsense to support lists from other plugins. (This feature is not available on allow lists as it is unlikely to be useful).
* *Manual* list allows uploading a list. The file name of the list is saved for future reference. The list can also be downloaded from OPNsense in the case of desired updating or manipulation, or simply understanding what is in the list at a later time. This list can also be removed entirely with the remove button if it is no longer desired.
* *Internal* list allows managing the list within the OPNsense configuration. This allows for quick addition, deletion, editing, copying, searching, and toggling (enabling/disabling) for individual list entries utilizing the list on the page. It's also possible to export and import the entire list if there is a need to do so. This feature also has direct support for schedules. The option for schedules also utilizes a single select dropdown dynamically populated from the configuration on the Schedules tab.
* The option for logging each list is located on the respective tabs, it can be enabled and disabled here.

Some considerations when using each option:

* *External* list needs to accommodate the `dnscrypt-proxy` user reading the list where ever it is in the file system.
* *Manual* list will not be included in the OPNsense configuration when performing a backup. The file name will be included for reference but not the list itself.
* *Internal* list should only be utilized for a relatively small number of entries. Advertising block lists can easily number in the tens of thousands and it is not ideal to use the OPNsense configuration for this purpose. Manual list configuration is better suited for large lists.

##### Schedules

Schedules is a fairly basic tab. It is where the schedule definitions for the lists are defined. The list supports all of the command functions of add, delete, edit, copy, and enable/disable. Adding a schedule here will allow the schedule to be used on any allowed names/blocked names entry.

##### Cloaking Rules

This tab is where cloaking is enabled and configured. It supports several functions which are found on the other list tabs. However, since it's unlikely that there would be an external source for this feature, external has been excluded. Available for selection are internal, and manual cloaking rules types. Manual may be useful for a user that already has a list created for `dnscrypt-proxy`. They would then not have to enter each entry one at a time into the internal list. The `cloak_ttl` setting is located here as it's most relevant here rather than on the Global Advanced settings tab. This setting along with all of the others on this page will be disabled when appropriate based on the which other options are selected.

##### Forwarding Rules

The Forwarding Rules tab only has one setting, and a list. Forwarding can be enabled and disabled here, which will also enable and disable the list as appropriate. There aren't any manual or external list type options due to the unique nature of these settings, it's unlikely that these would be useful. Once entries are entered, it is possible to export/import the list though.

#### DoH Server/Client

This tab includes settings that are specifically related to DNS over HTTP (DoH), both the server, and client settings.

##### Local DoH Server

The DoH server can be enabled here. This feature is off by default, and when disabled, the other settings are also disabled on the page. A new feature here is the ghost text (aka hint/placeholder) which appears in the edit boxes. These show what's expected to be input in the box which should include the beginning and ending certificate headers.

##### DoH Client x509 Authentication

`dnscrypt-proxy` v2.1.0 only supports a single entry in this setting. Having more than a single entry enabled here will result in `dnscrypt-proxy` reporting the error `[FATAL] Only one tls_client_auth entry is currently supported`. `dnscrypt-proxy` may eventually allow multiple entries.

The DoH server/client settings tab only includes a list. The list allows addition, deletion, edit, copying, export/import features. The `server_name` property for the entries is dynamically populated from the currently configured servers in `dnscrypt-proxy`. Each entry can be individually enabled and disabled. The list itself utilizes a specific fixed-width font and size for better presentation and consistency. The dialog box includes edit boxes which support the new hint/placeholder feature.

#### Resolvers

These tabs include settings related to resolvers, including `sources`, and `static` configured servers.

##### Resolvers

This list is dynamically populated from `dnscrypt-proxy` and includes all the currently configured servers from both `sources` and `static` configured servers. This is here only for informational purposes allowing for quick searching and filtering of the server. The search function also supports filtering on the boolean attributes like nolog, and nofilter. Placing one of "ipv6", "dnssec", "nolog", or "nofilter" in the search box will list those servers which have those features (indicated by a check mark in that column). Only one at a time is supported at the moment.

##### Sources

The sources tab includes only a list which allows setting up `sources` to be used by `dnscrypt-proxy` to dynamically populate its server list. All of the options for each sources is able to be set from here, and individual sources can be enabled and disabled if desired. The entire list can be exported/imported. There is an additional button at the bottom of the page which will wipe the list clean, and populate it with the default sources which are defined in the `dnscrypt-proxy.toml` example configuration file.

##### Static

This tab has just a list as well, and allows configuration of static server entries. All of the commands are available for add/delete/edit/copy/export/import. Take care with the `server_name` as it is possible to have ambiguity between servers if a server of the same name exists in `sources` as well. Utilizing the prefix setting on `sources` will allow for mitigation of this.

##### Broken Implementations

This tab is for a one-off setting which is for a very specific issue with some servers. The `fragments_blocked` setting is a multi-select dropdown with search that populates dynamically from the currently configured servers in `dnscrypt-proxy`.

#### Anonymized DNS

This tab allows configuration of the `anonymized_dns` settings. The Routes list allows creating individual route rules. The `server_name` setting is a single-select dropdown populated dynamically from the currently configured servers in `dnscrypt-proxy`. There is an additional server at the top of the list which is an asterisk and it represents the "everything" setting meaning "all servers." The `via` setting is a multi-select dropdown with search dynamically populated by a script which parses the configured `sources` files and analyzes the SDNS stamps for all of the servers, and selects only the relays that are present. At the bottom of the tab is a list of the relays populated by the same script. This can be used to get more information about any particular relay than is presented in the `via` drop down.

#### DNS64

This tab includes all of the DNS64 settings. It is disabled by default and should only be enabled if the settings are fully understood and needed. Multiple prefixes can be defined, and multiple DNS64 resolvers can be defined here using the lists on this tab.

### Diagnostics

There are three tabs here for diagnostics/testing/etc.

#### Resolve Hostname

This tab uses a new field type. There is a text box for entering the hostname, a button to execute the command, and a text box below that to display the output of the command.

#### Show DoH Certificates

This tab uses the same new field type, but with the option for the text box disabled. There is only a run button, and a text box below that to display the command output.

#### Configuration Check

This tab is most useful in misconfiguration situations. When `dnscrypt-proxy` won't start, it's likely due to a syntax error in the configuration file. There isn't an interface currently for this type of information to get to the user, so this tab has a button which runs a check on the configuration file with `dnscrypt-proxy` and displays the output which will include the error found in the configuration.

#### Version

This tab can be used to show the version of `dnscrypt-proxy` that's being used on OPNsense. The version of `dnscrypt-proxy` may drift from the plugin, and this will allow to confirm the version that's being used. It uses the `dnscrypt-proxy` command's `-version` parameter and displays the output of the command on the page.

### Logs

There are several logs for `dnscrypt-proxy` which are focused on specific features. Depending on the enabled state of features, the tabs for each log will be visible when a given feature is enabled. These tabs utilize the bootgrid field with a special style configuration suited for displaying logs. It uses a fixed-width font and respects whitespace such as tabs and extra spaces to keep the output close to the original.

#### Main Log

This log is the main log for `dnscrypt-proxy` and shows information about startup, configuration errors, server lists, etc.

#### Query Log

This is the query log and shows queries as they pass through `dnscrypt-proxy`. The setting for enabling/disabling this log can be found on the Global/Advanced settings tab in the logging section.

#### NX Log

This is the non-existant (NX) log and shows queries which are dubbed "suspicious" in the configuration file. These queries are for domains which do not exist. The setting for enabling/disabling this log can be found on the Global/Advanced settings tab in the logging section.

#### Blocked Names Log

This is the Blocked Names log and shows queries which are blocked by Blocked Names. The setting for enabling/disabling this log can be found on the Blocked Names tab in Settings.

#### Blocked IPs Log

This is the Blocked IPs log and shows queries which are blocked by Blocked IPs. The setting for enabling/disabling this log can be found on the Blocked IPs tab in Settings.

#### Allowed Names Log

This is the Allowed Names log and shows queries which are allowed by Allowed Names. The setting for enabling/disabling this log can be found on the Allowed Names tab in Settings.

#### Allowed IPs Log

This is the Allowed IPs log and shows queries which are allowed by Allowed IPs. The setting for enabling/disabling this log can be found on the Allowed IPs tab in Settings.

### License

[![License](https://img.shields.io/badge/License-BSD%202--Clause-orange.svg)](https://opensource.org/licenses/BSD-2-Clause)
