## Development Discussions

This plugin started out as the dnscrypt-proxy plugin written by @mimugmail. However, once I got started poking around, and digging, I found and thought of various ways to do things that were substantially different from the author's approach.  I understand the preferred approach is to make incremental, small changes to plugins to reduce the burden of code review, however, in this case the changes made are so drastic that it complicates the code review process due to the fact that the old code is replaced with entirely new code, rather than small corrections.  Thus, this plugin lives in a new directory in the repo to keep it separate from the original one. I hope that this readme, the PHP documentation, and the comments made throughout will help alleviate the burden of code review. If some of the changes/features I've included here are adopted in Core, then the footprint of this plugin will reduce significantly.

#### Views

The original plugin consisted of a single view (`general.volt`) and contained almost entirely static HTML, and Javascript code. A lot of the HTML was for drawing bootgrids on the various tabs, and the Javascript was for attaching to these bootgrid elements. The static nature of this approach makes it very cumbersome to make changes due to the number of things that need to change and the ways in which they need to change. There are references to objects which reside outside the template itself, which unless it's understood intimately what those things are, it is likely things will not line up, and end up broken, especially with bootgrids.

To mitigate these misalignment issues that I encountered, a lot of the core of this plugin has been "partialized" using the Phalcon templating engine, and is now driven/defined by a combination of Volt templates, and the controller forms. I think this is in the spirit of MVC and the intent to move away from PHP-based pages (or in this case, static HTML code in Volt templates). This allows for quick UI, and model changes to occur with minimal effort.

A lot of the partialized code was built on the foundation of existing Core `layout_partials`. I expanded upon their functionality, and with experimentation and more error than trial, I addressed several bugs I found in how the partials parse the arrays. These are the reasons why there are copies of them within this plugin, as I couldn't make these changes at will to Core. I also gleaned code and ideas from other plugins such as Redis and Proxy. Some of the Javascript function structures were written by those authors, and adapted for use here. I tried to include comments where this has occurred but I may have forgotten in some places.

Partializing all of the UI structure moves almost everything out of the static Volt templates and into the controller form definitions. As is possible already with several field types in Core, many of the features can be controlled through definition in the form. Thus to change how something looks, or the order in which it appears on the page it only takes changing values or moving the fields in the controller form.

Something new I've done with the partials is utilizing more macros, with one partial including exclusively macros. This isn't entirely necessary, but it does allow for recursion when parsing the tab arrays, which eliminates some duplicative code. Also new, is the concept of partializing the Javascript attachments. None of the current layout partials do this. This is a tremendous help for the bootgrids as everything is done automatically.

One of the main features which I had massive trouble with when I originally started on this plugin is the bootgrids. The tutorials for this functionality are written such that there is a lot of HTML/Javascript code to copy and paste, and then change names of various things to make the forms, and the models, and everything line up. This is a huge effort for folks who are new to MVC (like myself). So after close evaluation of the HTML structure, and underlying bootgrid functions, it was clear to me that the entire thing could be built dynamically, and only having to define a few things.

The bootgrid has now been entirely partialized and is treated (and appears on the page) as any other field, and abides by the same constraints. There is an exception in that the columns of the HTML table are differently defined since the grid spans the entire page. It's possible to control API calls, grid functionality, row definitions, and hiding/showing of the various command buttons all within the form definition. The HTML bootgrid structure is then built completely by the partial. It's even possible to have multiple bootgrids on the same page with ease. Moving the definition of the bootgrid into the controller form, it allows the partials to build the bootgrid (HTML and Javascript), and also keep all of the elements which need to have the same name aligned correctly with no effort from the developer.

After heavy partializing all of what is seen is controlled through the controller forms. Everything from the tabs, down to the little things like element state changes (hidden/visible/enabled/disabled) is built by the `layout_partials`. This includes Javascript attachments for the various elements, which is not something done by the Core `layout_partials`. There is one underlying Javascript attachment/function which I don't think is well suited for defining in the form data, and is a unique situation to this specific plugin.

The `layout_partials` contain DocBlock-style comments for reference, and comments throughout to attempt to explain their functions. Most of the new fields are elaborated on or explained to some degree. I added several validations, and fixed some other minor issues I found in some of the original fields as well. I also encountered the scope issue described in `base_form.volt` when I encountered a sticky variable being set when it wasn't supposed to be. I mitigated this issue entirely by never using the "root" scope, and always passing in a named array. That's why all of the partials utilize a "this_" prefixed array instead. I also adjusted all of the volt code to be uniform and utilize indentation inside the enclosing symbols to prevent the white space from getting into the final HTML. Managing white space with the the dashes ({%-) isn't really reasonable except in a handful of cases, and the dashes don't work on comment blocks like they do in Jinja.

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
Translating these into a field structure within a model given the existing constraints means that this section is rather verbose. It would be best if these could be arrays, but that won't work with the currently available field definitions (nested arrays are forbidden).

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

Ideally it would be an array for each day, with each array containing 4 values, having the fields treated as a group and having Phalcon build the UI to accommodate the group.

I decided to go with the separate fields approach because the dropdown boxes were resulting in these fields getting picked up on the save, and creating a separate array in the POST for the set API call. We'll also be able to do data validation with each field individually (hour, minute).

All of the values are padded with zeros because the "0" value here breaks selectpicker as in BaseListField/getNodeData() the "empty placeholder" will evaluate empty("0") to be true. If the selected value is 0, then it sets the empty placeholder to be selected, while also the 0 value is selected. Selectpicker doesn't like both being selected when only one is supposed to be. The extra zero padding on the rest of the numbers is so that the dropdown-select box will sort them nicely. We'll convert to integer when putting the values into the the config files for `dnscrypt-proxy`. This approach is not pretty, and not a good method of handling the data since it's getting modified on the way in and the way out, however, it was the best solution I could come up with working within the constraints of the bugs.

This whole approach results in a massive section for a relatively small amount of data. It would be cool if this could be done a different way, like defining an option list, and then referencing that option list instead of including the entire option list repeatedly (x7). It would also be nice to support nested arrays so we could have one array per-day per-schedule.

#### Controllers

The documentation for the Controllers is located here:

**[PHP Documentation](https://agh1467.github.io/dnscrypt-proxy-v2.0.45/packages/OPNsense-Dnscryptproxy.html)**

The Controller footprint is expanded a little due to the additional page definitions. An additional controller not related to a page is `ControllerBase.php` this is copied from Core and is used to parse form XMLs differently when using `getForm()`. This function walks deeper into the XML, parses element attributes, and supports arrays beyond the first and second levels. This allows for much more flexibility in the XML design to be able to create more complex `layout_partials` to dynamically create elements on a page.

The controllers here aren't overly complex. The only differences from the originals are utilizing the custom `getForm()`, utilizing setVars() to set the variables instead of the magic setter (purely aesthetic reasons), changing the variable convention, and absence of calls to parse edit dialog forms.

With respect to the variable names, the example variable names here were previously camel case, but given the convention it results in excessively long names. Looking at the coding standards (https://docs.opnsense.org/development/guidelines/psr1.html) camelCase is for methods, and these are just arrays, and properties (variables) are allowed to be anything. Since it was the only place camel case was used for a variable name I changed it to be consistent with the element prefixes used throughout.

Utilizing the custom `getForm()` there isn't a need to define the dialogs as separate forms or as separate files, and these definitions now live within the definition of the bootgrid field to which they should be associated. This approach further reduces the number of separate files to maintain.

##### Forms

The forms XMLs have reduced in number due to the lack of need to have separate form definitions for dialog boxes. I found little value in doing this if the definition of the dialog could be included in the page form itself. This helps keep everything in a single place and much easier to manage with fewer files to work on.

Due to the malleable nature of the `layout_partials` being within this plugin, it makes it much easier to add features and create new field type definitions. That being said, the XML design in the forms is mostly the same, with some deviations, and additions. New field types are included for radio, command, bootgrid, managefile, startstoptime. For some other field types additional elements have been added to support more features such as the placeholder (hint) attribute for textbox. A new concept of "field control" has been added to support state changes (enable/disable/show/hide) when specific events happen with a checkbox, or radio button.

The XML structure now utilizes the tab/subtab feature rather than everything being on individual tabs. Tabs/Subtabs are grouped together based on related features. These tabs are then drawn by `layout_partials` entirely instead of needing to be defined in the volt template. Along the same vein, the edit dialogs for bootgrids are contained within `<dialog>` elements and reside within the field definition for their respective bootgrid. API calls for bootgrids are also defined here instead of statically in the volt template for the page.

One of the challenges I ran into while working with the XMLs is how nested elements are interpreted. I'm not convinced this is the best method of handling the situation. The approach that I ended up going with is accommodating the discrepancy when the data is used. A much better approach would be to fix it or process it properly in the first place, so that the data is always predictable. There are two primary configurations which are being interpreted differently, a single nested element, and multiple nested elements.

A single nested element looks like this in the XML:
```
<options>
    <option>dnscrypt-proxy.toml</option>
</options>

```
This translates to an associative array with a single named string, "option":
```
array(1) {
  ["option"]=>
  string(19) "dnscrypt-proxy.toml"
}
```

Multiple nested elements looks like this in the XML:
```
<options>
    <option>dnscrypt-proxy.toml</option>
    <option>allowed-ips-internal.txt</option>
</options>
```
This translates to an associative array with a single named array, "option":

```
array(1) {
  ["option"]=>
  array(17) {
    [0]=>
    string(19) "dnscrypt-proxy.toml"
    [1]=>
    string(24) "allowed-ips-internal.txt"
    ...
  }
}
```

This means that any time that the object named "option" is evaluated it could be a string OR an array. The issue here is that any procedure designed to process this object must accommodate both scenarios. In most cases what I decided to do was evaluate if the object is a string, and wrap it in an array if it was. This is not ideal, but seems to work, but adds a bunch of code in various places. It's also easy to forget about this condition because most of the time when this functionality is used, it is a multi-selection situation.

##### Api

With respect to the topic of multiple files for bootgrids, the primary reason that I found that the multiple model/controllers/forms approach is used is due to how the tutorial/documentation is written. The example describes creating an additional API controller, using an additional model, uses hard coded paths, hard coded array definitions, and function names like 'setItemAction()'. This all leads developers to copying the code wholesale and changing the parts that are changing, like setForwardAction(), setCloakAction(), setServerAction(), etc. Which results in the duplication of all of it.

As partially elaborated on in the Models section, all of these repetitive objects/files aren't really necessary. Even with multiple functions of various names, they can still operate within the same model. There isn't even a real need to have multiple functions for the API end-points as is shown in the tutorial. This is where I originally began after bringing all of the settings within the single Settings model. Ultimately I ended with a *single* function, gridAction(), which supports *all* of the activities of the bootgrid, and replaces *all* of the repetitive functions shown in the tutorial.

The original plugin had a `general.php` which contained no functions, and several other PHP classes which contained functions related to each bootgrid. Now all of these functions are consolidated into the relevant PHP class, `SettingsController`. There are two additional classes, `DiagnosticsController`, and `FileController` which serve their own purposes. Each class has various DocBlocks and comments to describe the different parts.

#### Service Templates

Most of the changes for the templates happen within the core configuration file `dnscrypt-proxy.toml`. The original was very noisy, and contained a lot of the same data repeatedly with respect to conditional statements. Here a lot of the repetitive text is swapped out in lieu of variables. This makes the code more portable, is cleaner, and requires less effort to change something like the plugin name, or path in the config. I also included section headers similar to what is included in the default `dnscrypt-proxy.toml`. This isn't necessary, and is purely for aesthetic/convenience purposes.

Many of the more advanced settings are wrapped up in conditional statements which will include or exclude them from the configuration file entirely. Comments are included to help explain what's happening in these more complex structures. Tertiary statements are utilized in the case of boolean settings which eliminates the need to include a version of the setting for each condition. White space is tailored for presentation, both within the settings, and between settings definitions and headers. Cloaking, forwarding, and lists files have been either updated or added. Support for schedules, and comments have been added to the lists files.

For all of the files, I've added 'jinja' as a file extension because it makes it a simple matter for an IDE to understand that these files contain Jinja code. This deviates from the instructions provided in the OPNsense documentation, however, I could find no negative impact from doing this. The only impact I could find was updating the +TARGETS to reflect the new file name. The destination file name is defined there and is not affected by the template's file name. I couldn't find any other plugins using the jinja file extension, but I see more value in using it than not using it.

This is also described in the [Jinja documentation](https://github.com/pallets/jinja/pull/1083/files#diff-0f54a58b39617a700a0b750e7a8bf07eR60-R71) which was updated in 2019.

#### Service Scripts

There are several new scripts here for performing back-end type activities, mostly with files or interacting with `dnscrypt-proxy` itself.

There is one script for importing lists (allowed/blocked/cloaking), another for importing some certificates out of the OPNsense config into files. The others are for getting info out of `dnscrypt-proxy` using some command parameters. These scripts are executed dynamically or on-demand from the user depending on the activity.

The setup.sh script has been replaced with a `+POST_INSTALL.post` action. References to this script in the configd conf have been removed, as they're unnecessary.

#### Logging

With the migration to Phalcon4, it appears to now be "camelizing" arguments for at least `/api/diagnostics/log/` endpoint. If a dash is used in an argument, the dash is getting eaten and the following letter become capitalized. This results in calls like `/api/diagnostics/log/dnscrypt-proxy/main` looking at `/var/log/dnscryptProxy/main.log` instead. Since we can't fix that API from this plugin, we can only work around this issue, with options like using a different directory, or creating a symlink to the log directory. @mimugmail addressed this issue via the symlink approach in PR [#2467](https://github.com/opnsense/plugins/pull/2467). It doesn't really matter where the directory is that the logs are located or what its named, however, moving the directory does have an impact. For example, it results in changes necessary in configuration files, install scripts, menu XMLs, and API calls. Additionally if the directory is moved, the installation/upgrade scripts must deal with moving the files from the old log directory to the new one.

Instead of doing exactly what @mimugmail did, I've chosen to leave the log directory in place, and create a symlink, `/var/log/dnscryptProxy`, and then leaving the API calls the same. This is in the case that the camelizing functionality when making the Diagnostics/Log API call is changed in the future so that the call goes back to looking in `/var/log/dnscrypt-proxy.` The only work needed then is to clean up the symlink, and remove the creation of the symlink from the install scripts.

### Notes from the DNSCrypt Proxy installation

The installation of dnscrypt-proxy2 has some notes about functionality which may be useful. They're included here for reference.

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
