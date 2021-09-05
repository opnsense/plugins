# dnscrypt-proxy plugin for OPNsense

# ![dnscrypt-proxy 2](https://raw.github.com/dnscrypt/dnscrypt-proxy/master/logo.png?3)

## Introduction

This is a plugin for OPNsense firewall which provides a front-end UI for managing `dnscrypt-proxy`. Original plugin written by <original author>

It helps to understand how `dnscrypt-proxy` functions in order to really take
advantage of the features of the plugin. This plugin is designed to function with v2.1.0+. Here are some useful resources for dnscrypt-proxy:

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
* External allowed names/blocked names support
* Importing/Exporting of allowed/blocked lists and some others
* Diagnostics tools
* All logs supported
* Resolver list, displays all currently available resolvers.
* Anonymized DNS Relay list, displays all currently available relays.

## Manifest
```
|-- +POST_DEINSTALL.post -- script executed by pkg when plugin is uninstalled
|-- +POST_INSTALL.post -- script executed by pkg when plugin is installed
|-- CHANGELOG.md -- high-level change log used by OPNsense UI.
|-- Makefile -- build file, includes dependencies, and version information.
|-- README.md -- You are here.
|-- pkg-descr -- package description used by OPNsense UI.
`-- src
    |-- etc
    |   `-- inc
    |       `-- plugins.inc.d
    |           `-- dnscryptproxy.inc -- adds entry to services in OPNsense UI
    `-- opnsense
        |-- mvc
        |   `-- app
        |       |-- controllers
        |       |   `-- OPNsense
        |       |       `-- Dnscryptproxy
        |       |           |-- Api
        |       |           |   |-- DiagnosticsController.php
        |       |           |   |-- FileController.php
        |       |           |   |-- ServiceController.php
        |       |           |   `-- SettingsController.php
        |       |           |-- ControllerBase.php
        |       |           |-- DiagnosticsController.php
        |       |           |-- LogsController.php
        |       |           |-- SettingsController.php
        |       |           `-- forms
        |       |               |-- diagnostics.xml
        |       |               |-- logs.xml
        |       |               `-- settings.xml
        |       |-- models
        |       |   `-- OPNsense
        |       |       `-- Dnscryptproxy
        |       |           |-- ACL
        |       |           |   `-- ACL.xml
        |       |           |-- Menu
        |       |           |   `-- Menu.xml
        |       |           |-- Settings.php
        |       |           `-- Settings.xml
        |       `-- views
        |           `-- OPNsense
        |               `-- Dnscryptproxy
        |                   |-- +macros.volt
        |                   |-- diagnostics.volt
        |                   |-- layout_partials
        |                   |   |-- base_dialog.volt
        |                   |   |-- base_form.volt
        |                   |   |-- base_script_content.volt
        |                   |   |-- form_bootgrid_tr.volt
        |                   |   `-- form_input_tr.volt
        |                   |-- logs.volt
        |                   `-- settings.volt
        |-- scripts
        |   `-- OPNsense
        |       `-- Dnscryptproxy
        |           |-- get_relays.py
        |           |-- get_resolvers.py
        |           |-- import_doh_client_certs.py
        |           |-- import_list.py
        |           `-- tests
        |               |-- fixtures
        |               |   `-- doh_client_certs
        |               |       `-- one_enabled_of_two.xml
        |               |-- output
        |               |   `-- doh_client_certs
        |               |       |-- 57cddf7c-f383-4b0d-8d2c-e95e4efee5ea-client_cert.pem
        |               |       |-- 57cddf7c-f383-4b0d-8d2c-e95e4efee5ea-client_cert_key.pem
        |               |       `-- 57cddf7c-f383-4b0d-8d2c-e95e4efee5ea-root_ca_cert.pem
        |               |-- references
        |               |   `-- doh_client_certs
        |               |       |-- 57cddf7c-f383-4b0d-8d2c-e95e4efee5ea-client_cert.pem
        |               |       |-- 57cddf7c-f383-4b0d-8d2c-e95e4efee5ea-client_cert_key.pem
        |               |       `-- 57cddf7c-f383-4b0d-8d2c-e95e4efee5ea-root_ca.pem
        |               `-- run_tests.py
        `-- service
            |-- conf
            |   |-- actions.d
            |   |   `-- actions_dnscryptproxy.conf
            |   `-- dnscrypt-proxy.conf
            `-- templates
                `-- OPNsense
                    `-- Dnscryptproxy
                        |-- +TARGETS
                        |-- allowed-ips-internal.txt.jinja
                        |-- allowed-names-internal.txt.jinja
                        |-- blocked-ips-internal.txt.jinja
                        |-- blocked-names-internal.txt.jinja
                        |-- captive-portals.txt.jinja
                        |-- cloaking-internal.txt.jinja
                        |-- dnscrypt-proxy.toml.jinja
                        |-- dnscrypt_proxy.jinja
                        |-- forwarding-rules.txt.jinja
                        |-- local_doh-cert.pem.jinja
                        `-- local_doh-cert_key.pem.jinja
```
## Operation

There are three pages available on the OPNsense menu for operating this plugin.

### Settings

This is the primary page which most of the functionality of this plugin is available. It contains 6 primary tabs, and several other subtabs (accessible with the arrow to the right of the tab name).

The settings are organized for the most part in the order in which they appear in the configuration file (`dnscrypt-proxy.toml`). Further, sub-sections of the configuration file which are signified by square brackets ([]) have their own dedicated full tab, or a sub tab.

#### Global

The Global settings are organized into basic, and advanced settings, with advanced settings being on a subtab. Many settings include default configurations already, as defined in the example `dnscrypt-proxy.toml` configuration file.

* Where appropriate, features are grouped together visually with a header which itself has a help icon which explains that feature.
* All settings have a help description for user reference.
* Where applicable, the first line of the help is the setting name in bold. This is for ease of use for matching a setting in the UI which often uses a label in plain English, with a setting in the configuration file which may not have the same name. This is especially useful when referencing documentation from the application author.

##### Basic Settings

This is the default page for the plugin when selecting the settings page. It includes some of the basic settings to get the plugin started.

* Some settings preclude functionality of other settings. One such setting is `server_names`. When using this setting, the "require" settings (`require_dnssec`, `require_nolog`, `require_nofilter`) do not apply. There is an option to enable or disable manual server selection (i.e. using `server_names` setting) which will then disable and enable the applicable settings.
* Servers for both `server_names`, and `disabled_server_names` are dynamically populated, and utilize multi-select picker dropdowns with search function. These servers come from servers defined in sources files, and also statically defined servers in the configuration.

##### Advanced Settings

This tab includes many settings which most users will have no interest in changing. Many of these settings have the potential to break functionality of `dnscrypt-proxy` entirely. Where possible they're populated with default settings.

* The DoH TLS Cipher Suite is easily configurable with a multi-select picker dropdown for ease of use.
* Log level configuration is made easy with a single select dropdown, and each level is labeled appropriately.
* Several other general logging settings are included here such as for the Query, and Non-Existant domain logs.

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

##### Features

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

The Forwarding Rules tab only has one setting, and a list. Fowarding can be enabled and disabled here, which will also enable and disable the list as appropriate. There aren't any manual or external list type options due to the unique nature of these settings, it's unlikely that these would be useful. Once entries are entered, it is possible to export/import the list though.

#### DoH Server/Client

This tab includes settings that are specifically related to DNS over HTTP (DoH), both the server, and client settings.

##### Local DoH Server

The DoH server can be enabled here. This feature is off by default, and when disabled, the other settings are also disabled on the page. A new feature here is the ghost text (aka hint/placeholder) which appears in the edit boxes. These show what's expected to be input in the box which should include the beginning and ending certificate headers.

##### DoH Client x509 Authentication

The DoH server/client settings tab only includes a list. The list allows addition, deletion, edit, copying, export/import features. The `server_name` property for the entries is dynamically populated from the currently configured servers in `dnscrypt-proxy`. Each entry and be individually enabled and disabled. The list itself utilizes a specific fixed-width font and size for better presentation and consistency. The dialog box includes edit boxes which support the new hint/placeholder feature.

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

This tab is most useful in mis-configuration situations. When `dnscrypt-proxy` won't start, it's likely due to a syntax error in the configuration file. There isn't an interface currently for this type of information to get to the user, so this tab has a button which runs a check on the configuration file with `dnscrypt-proxy` and displays the output which will include the error found in the configuration.

#### Version

This tab can be used to show the version of dnscrypt-proxy that's being used on OPNsense. The version of dnscrypt-proxy may drift from the plugin, and this will allow to confirm the version that's being used. It uses the dnscrypt-proxy command's `-version` parameter and displays the output of the command on the page.

### Logs

There are several logs for `dnscrypt-proxy` which are focused on specific features. Depending on the enabled state of features, the tabs for each log will be visible when a given feature is enabled. These tabs utilize the bootgrid field with a special style configuration suited for displaying logs. It uses a fixed-width font and respects whitespace such as tabs and extra spaces to keep the output close to the original.

#### Main Log

This log is the main log for `dnscrypt-proxy` and shows information about startup, configuration errors, server lists, etc.

#### Query Log

This is the query log and shows queries as they pass through `dnscrypt-proxy`. The setting for enabling/disabling this log can be found on the Global/Advanced settings tab in the logging section.

#### NX Log

This is the non-existant (NX) log and shows queries which are dubbed "suspicious" in the configuration file. These queries are for domains which do not exist. The setting for enabling/disabling this log can be found on the Global/Advanced settings tab in the logging section.

#### Blocked Names Log

This is the Blocked Names log and shows queries which are blocked by Blocked Names. The setting for enabling/disabling this log can be found on the Blocked Names tab.

#### Blocked IPs Log

This is the Blocked IPs log and shows queries which are blocked by Blocked IPs. The setting for enabling/disabling this log can be found on the Blocked IPs tab.

#### Allowed Names Log

This is the Allowed Names log and shows queries which are allowed by Allowed Names. The setting for enabling/disabling this log can be found on the Allowed Names tab.

#### Allowed IPs Log

This is the Allowed IPs log and shows queries which are allowed by Allowed IPs. The setting for enabling/disabling this log can be found on the Allowed IPs tab.

### Design Decisions & Issues

This plugin started out as the dnscrypt-proxy plugin written by @mimugmail. However, once I got started poking around, and digging, I found and thought of various ways to do things that were substantially different from the author's approach.

A side note, the DNSBL functionality has been moved out of this plugin into its own. It's been rebuilt, with some minor changes, overall functionality remains the same. The DNS Blacklist created by that plugin can be utilized in this plugin via the External blocked names list feature.

I understand the preferred approach is to make incremental, small changes to plugins to reduce the burden of code review. I hope that this readme, the PHP documentation, and the comments made throughout will help alleviate that burden. I also hope that some of changes/features I've made will be adopted upstream in Core. At a high level there are five areas to discuss: Views, Models, Controllers, Service Templates, and Service Scripts.

#### Views

The original plugin consisted of a single view (`general.volt`) and contained almost entirely static HTML, and Javascript code. A lot of the HTML was for drawing bootgrids on the various tabs, and the Javascript was for attaching to these bootgrid elements. The static nature of this approach makes it very cumbersome to make changes due to the number of things that need to change and the ways in which they need to change. There are references to objects which reside outside the template itself, which unless it's understood intimately what those things are, it is likely things will not line up, and end up broken, especially with bootgrids.

To mitigate these misalignment issues that I encountered, a lot of the core of this plugin has been "partialized" using the Volt template engine, and is now driven/defined via the controller forms. I think this is in the spirit of MVC and the intent to move away from static PHP pages (or in this case, static HTML code in Volt templates). This allows for quick UI, and model changes to occur with minimal effort.

A lot of the partialized code was built on the foundation of existing Core layout_partials. I expanded upon their functionality, and with experimentation and more error than trial, I addressed several bugs I found in how the partials parse the arrays. These are the reasons why there are copies of them within this plugin, as I couldn't make these changes at will to Core. I also gleaned code and ideas from other plugins such as Redis and Proxy. Some of the Javascript function structures were written by those authors, and adapted for use here. I tried to include comments where this has occurred but I may have forgotten in some places.

Partializing all of the UI structure moves almost everything out of the static Volt templates and into the controller form definitions. As is possible already with several field types in Core, many of the features can be controlled through definition in the form. Thus to change how something looks, or the order in which it appears on the page it only takes changing values or moving the fields in the controller form.

Something new I've done with the partials is utilizing more macros, with one partial including exclusively macros. This isn't entirely necessary, but it does allow for recursion when parsing the tab arrays, which eliminates some duplicative code. Also new, is the concept of partializing the Javascript attachments. None of the current layout partials do this. This is a tremendous help for the bootgrids as everything is done automatically.

One of the main features which I had massive trouble with when I originally started on this plugin is the bootgrids. The tutorials for this functionality are written such that there is a lot of HTML/Javascript code to copy and paste, and then change names of various things to make the forms, and the models, and everything line up. This is a huge effort for folks who are new to MVC (like myself). So after close evaluation of the HTML structure, and underlying bootgrid functions, it was clear to me that the entire thing could be built dynamically, and only having to define a few things.

The bootgrid has now been entirely partialized and is treated (and appears on the page) as any other field, and abides by the same constraints. There is an exception in that the columns of the HTML table are differently defined since the grid spans the entire page. It's possible to control API calls, grid functionality, row definitions, and hiding/showing of the various command buttons all within the form definition. The HTML bootgrid structure is then built completely by the partial. It's even possible to have multiple bootgrids on the same page with ease. Moving the definition of the bootgrid into the controller form, it allows the partials to build the bootgrid (HTML and Javascript), and also keep all of the elements which need to have the same name aligned correctly with no effort from the developer.

After heavy partializing all of what is seen is controlled through the controller forms. Everything from the tabs, down to the little things like element state changes (hidden/visible/enabled/disabled) is built by the layout_partials. This includes Javascript attachments for the various elements, which is not something done by the Core layout_partials. There is one underlying Javascript attachment/function which I don't think is well suited for defining in the form data, and is a unique situation to this specific plugin.

The layout_partials contain DocBlock-style comments for reference, and comments throughout to attempt to explain their functions. Most of the new fields are elaborated on or explained to some degree. I added several validations, and fixed some other minor issues I found in some of the original fields as well. I also encountered the scope issue described in `base_form.volt` when I encountered a sticky variable being set when it wasn't supposed to be. I mitigated this issue entirely by never using the "root" scope, and always passing in a named array. That's why all of the partials utilize a "this_" prefixed array instead. I also adjusted all of the volt code to be uniform and utilize indentation inside the enclosing symbols to prevent the white space from getting into the final HTML. Managing white space with the the dashes ({%-) isn't really reasonable except in a handful of cases, and the dashes don't work on comment blocks like they do in Jinja.

#### Models

The original plugin included several models, which mounted to several sub-nodes within the core dnscrypt-proxy node within the config. This approach stemmed from the tutorial/documentation for bootgrids, and is understandable. However, this approach results in multiple instances of paths in the config like `//OPNsense/dnscryptproxy/cloak/cloaks/cloak`, `//OPNsense/dnscryptproxy/server/servers/server`, in addition to the model definition itself consisting of only a few fields, with two files per-model. Multiple models means multiple controllers will be necessary to accomplish the API calls for bootgrid.

All of these models aren't technically necessary though. I had a hard time justifying the redundant path definitions in the config, the additional model definitions for only a few fields, and additional controllers for something that can be done all within a single model. This reduces the plugin footprint substantially, reduces the number of files to edit for making changes, and reduces the complexity of the plugin overall. There is now only a single model, Settings. It contains all of the settings for this plugin.

The main menu has been changed to reflect the new three pages included in this plugin. The original had definitions of three log pages utilizing the Core Diagnostics UI to display these. These logs are included still, but are all on a single page with tabs instead.

There several sections within the Settings model which will benefit from some elaboration.

##### JsonKeyValueStoreField

This field type is utilized in several places to pull data from `dnscrypt-proxy` directly mostly for a list of resolvers. This approach may or may not be the best of approach for this data. Of the several methods I tried this was the most reliable, while also not requiring storing volatile data in the config or jumping through hoops with UUIDs.

##### OptionValues

Anywhere that OptionValues are used, I used the element name "option" instead of a named element as is often found in documentation. The reason for this is that the element name is not relevant, and is not used at all. Using the name "option," though truly arbitrary, it makes it easier to read, and understand what is being represented by the data. It also implies by virtue of being present the value attribute, and the value of the element itself as being important. The value attribute being what is used as the data stored in the config, and the value of the element being the text displayed to the user on the dropdown.

##### ModelRelationField

The schedules employ the ModelRelationField to reference the schedules settings within the model itself. This is not a well documented feature. There are comments that I left in the XML for reference when reading to better understand what's happening without having to dig through the functions which process these elements.

##### Schedules

These schedules are used by the allowed/blocked lists. A schedule can be associated with an entry to indicate a time period during which that entry should be active. In the `dnscrypt-proxy.toml` these settings look like this:
```
[schedules]
  [schedules.'time-to-sleep']
  mon = [{after='21:00', before='7:00'}]
  tue = [{after='21:00', before='7:00'}]
  wed = [{after='21:00', before='7:00'}]
  thu = [{after='21:00', before='7:00'}]
  fri = [{after='23:00', before='7:00'}]
  sat = [{after='23:00', before='7:00'}]
  sun = [{after='21:00', before='7:00'}]
```
Translating these into a field structure within a model given the existing constraints means that this section is rather verbose. It would be best if these could be arrays, but that won't work with the currently available field definitions.

The best option would be something like a TimeField type with specific validators designed to handle time entries.

In considering the options, I found that this data could be represent in several ways:
* CSV (each array element)
  * Can't limit to two elements
  * Validation would be complicated
* Separate fields x4 (each time segment)
  * Volt templates, and mapDataToFormUI() only supports single field assignment
  * Validation would be simple
* Text (whole string in {})
  * Easiest (LOE), largest LOE for users, ask them to enter the value as a whole
  * Validation would be complicated

Ideally it would be an array for each day, with each array containing 4 values, having the fields treated as a group and having phalcon build the UI to accommodate the group.

I decided to go with the separate fields approach because the dropdown boxes were resulting in these fields getting picked up on the save, and creating a separate array in the POST for the set API call. We'll also be able to do data validation with each field individually (hour, minute).

We pad all of the values here with zeros because the "0" value here breaks selectpicker as in BaseListField/getNodeData() the "empty placeholder" will evaluate empty("0") to be true. If the selected value is 0, then it sets the empty placeholder to be selected, while also the 0 value is selected. Selectpicker doesn't like both being selected when only one is supposed to be. The extra zero padding on the rest of the numbers is so that the dropdown-select box will sort them nicely. We'll convert to integer when putting the values into the the config files for `dnscrypt-proxy`.

This whole approach results in a massive section for a relatively small amount of data. It would be cool if this could be done a different way, like defining an option list, and then referencing that option list instead of including the entire option list repeatedly (x7). It would also be nice to support nested arrays so we could have one array per-day.

#### Controllers

The documentation for the Controllers is located here:

**[PHP Documentation](https://agh1467.github.io/dnscrypt-proxy-v2.0.45/packages/OPNsense-Dnscryptproxy.html)**

The Controller footprint is expanded a little due to the additional page definitions. An additional controller not related to a pages is `ControllerBase.php` this is copied from Core and is used to parse form XMLs differently when using `getForm()`. This function walks deeper into the XML, parses element attributes, and supports arrays beyond the first and second levels. This allows for much more flexibility in the XML design to be able to create more complex layout_partials to dynamically create elements on a page.

The controllers here aren't overly complex. The only differences from the originals are utilizing the custom `getForm()`, utilizing setVars() to set the variables instead of the magic setter (purely aesthetic reasons), changing the variable convention, and absence of calls to parse edit dialog forms.

With respect to the variable names, the example variable names here were previously camel case, but given the convention it results in excessively long names. Looking at the coding standards (https://docs.opnsense.org/development/guidelines/psr1.html) camelCase is for methods, and these are just arrays, and properties (variables) are allowed to be anything. Since it was the only place camel case was used for a variable name I changed it to be consistent with the element prefixes used throughout.

Utilizing the custom `getForm()` there isn't a need to define the dialogs as separate forms or as separate files, and these definitions now live within the definition of the bootgrid field to which they should be associated. This approach further reduces the number of separate files to maintain.

##### Forms

The forms XMLs have reduced in number due to the lack of need to have separate form definitions for dialog boxes. I found little value in doing this if the definition of the dialog could be included in the page form itself. This helps keep everything in a single place and much easier to manage with fewer files to work on.

Due to the malleable nature of the layout_partials being within this plugin, it makes it much easier to add features and create new field type definitions. That being said, the XML design in the forms is mostly the same, with some deviations, and additions. New field types are included for radio, command, bootgrid, managefile, startstoptime. For some other field types additional elements have been added to support more features such as the placeholder (hint) attribute for textbox. A new concept of "field control" has been added to support state changes (enable/disable/show/hide) when specific events happen with a checkbox, or radio button.

The XML structure now utilizes the tab/subtab feature rather than everything being on tabs. Tabs/Subtabs are grouped together based on related features. These tabs are then drawn by layout_partials entirely instead of needing to be defined in the volt template. Along the same vein, the edit dialogs for bootgris are contained within `<dialog>` elements and reside within the field definition for their respective bootgrid. API calls for bootgrids are also defined here instead of in the volt template for the page.

##### Api

With respect to the topic of multiple files for bootgrids, the primary reason that I found that the multiple model/controllers/forms approach is used is due to how the tutorial/documentation is written. The example describes creating an additional API controller, using an additional model, uses hard coded paths, hard coded array definitions, and function names like 'setItemAction()'. This all leads developers to copying the code wholesale and changing the parts that are changing, like setForwardAction(), setCloakAction(), setServerAction(), etc. Which results in the duplication of all of it.

As partially elaborated on in the Models section, all of these repetitive objects/files aren't really necessary. Even with multiple functions of various names, they can still operate within the same model. There isn't even a real need to have multiple functions for the API end-points as is shown in the tutorial. This is where I originally began after bringing all of the settings within the single Settings model. Ultimately I ended with a single function, gridAction(), which supports all of the activities of the bootgrid, and replaces all of the repetitive functions shown in the tutorial.

The original plugin had a `general.php` which contained no functions, and several other PHP classes which contained functions related to each bootgrid. Now all of these functions are consolidated into the relevant PHP class, `SettingsController`. There are two additional classes, `DiagnosticsController`, and `FileController` which serve their own purposes. Each class has various DocBlocks and comments to describe the different parts.

#### Service Templates

Most of the changes for the templates happen within the core configuration file `dnscrypt-proxy.toml`. The original was very noisy, and contained a lot of the same data repeatedly with respect to conditional statements. Here a lot of the repetitive text is swapped out in lieu of variables. This makes the code more portable, is cleaner, and requires less effort to change something like the plugin name, or path in the config. I also included headers similar to what is included in the default `dnscrypt-proxy.toml`. This isn't necessary, and is purely for aesthetic/convenience purposes.

Many of the more advanced settings are wrapped up in conditional statements which will include or exclude them from the configuration file entierly upon reload action. Comments are included to help explain what's happening in these more complex structures. Tertiary statements are utilized in the case of boolean settings which eliminates the need to include a version of the setting for each condition. White space is tailored for presentation, both within the settings, and between settings definitions and headers. Cloaking, forwarding, and lists files have been either updated or added. Support for schedules, and comments have been added to the lists files.

For all of the files, I've added 'jinja' as a file extension because it makes it a simple matter for an IDE to understand that these files contain Jinja code. This deviates from the instructions provided in the OPNsense documentation, however, I could find no negative impact from doing this. The only impact I could find was updating the +TARGETS to reflect the new file name. The destination file name is defined there and is not affected by the template's file name. I couldn't find any other plugins using the jinja file extension, but I see more value in using it than not using it.

#### Service Scripts

There are several new scripts here for performing back-end type activities, mostly with files or interacting with `dnscrypt-proxy` itself.

There is one script for importing lists (allowed/blocked/cloaking), another for importing some certificates out of the config. The others are for getting info out of `dnscrypt-proxy` using some command parameters. These scripts are executed dynamically or on-demand from the user depending on the activity.

The setup.sh script has been replaced with a +POST_INSTALL.post action. References to this script in the configd conf have been removed, as they're unnecessary.

### Notes from the DNSCrypt Proxy installation

The installation of dnscrypt-proxy in FreeBSD has some notes about functionality which may be useful. They're included here for reference.

=====
Message from dnscrypt-proxy2-2.0.45:

```
Version 2 of dnscrypt-proxy is written in Go and therefore isn't capable
of dropping privileges after binding to a low port on FreeBSD.

By default, this port's daemon will listen on port 5353 (TCP/UDP) as the
_dnscrypt-proxy user.

It's possible to bind it and listen on port 53 (TCP/UDP) with mac_portacl(4)
kernel module (network port access control policy). For this add
dnscrypt_proxy_mac_portacl_enable=YES in your rc.conf. The dnscrypt-proxy
startup script will load mac_portacl and add a rule where _dnscrypt-proxy user will
be able to bind on port 53 (TCP/UDP). This port can be changed by
dnscrypt_proxy_mac_portacl_port variable in your rc.conf. You also need to
change dnscrypt-proxy config file to use port 53.

Below are a few examples on how to redirect local connections from port
5353 to 53.

[ipfw]

  ipfw nat 1 config if lo0 reset same_ports \
    redirect_port tcp 127.0.0.1:5353 53 \
    redirect_port udp 127.0.0.1:5353 53
  ipfw add nat 1 ip from any to 127.0.0.1 via lo0

  /etc/rc.conf:
    firewall_enable="YES"
    firewall_nat_enable="YES"

  /etc/sysctl.conf:
    net.inet.ip.fw.one_pass=0

[pf]

  set skip on lo0
  rdr pass on lo0 proto { tcp udp } from any to port 53 -> 127.0.0.1 port 5353

  /etc/rc.conf:
    pf_enable="YES"

[unbound]

  /etc/rc.conf:
    local_unbound_enable="YES"

  /var/unbound/unbound.conf:
    server:
      interface: 127.0.0.1
      do-not-query-localhost: no

  /var/unbound/forward.conf:
    forward-zone:
      name: "."
      forward-addr: 127.0.0.1@5353

  If you are using local_unbound, DNSSEC is enabled by default. You should
  comment the "auto-trust-anchor-file" line or change dnscrypt-proxy to use
  servers with DNSSEC support only.
```
### License

[![License](https://img.shields.io/badge/License-BSD%202--Clause-orange.svg)](https://opensource.org/licenses/BSD-2-Clause)
