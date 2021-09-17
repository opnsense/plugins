## Manifest

This a manifest of the relevant files in this plugin, and a description of what the file is, or its purpose.

```
|-- +POST_DEINSTALL.post --------------------------------------- script executed by pkg when plugin is uninstalled
|-- +POST_INSTALL.post ----------------------------------------- script executed by pkg when plugin is installed
|-- CHANGELOG.md ----------------------------------------------- high-level change log used by OPNsense UI
|-- DEVELOPMENT.md --------------------------------------------- development discussions about this plugin
|-- Makefile --------------------------------------------------- build file, includes dependencies, and version information, used to make a pkg
|-- README.md -------------------------------------------------- description of plugin
|-- MANIFEST.md ------------------------------------------------ file manifest of all relevant files of this plugin, and descriptions for each
|-- pkg-descr -------------------------------------------------- package description used by OPNsense UI
`-- src -------------------------------------------------------- everything in this directory maps to /usr/local on OPNsense
    |-- etc ---------------------------------------------------- config directory for application configurations
    |   `-- inc ------------------------------------------------ php files, to be included by php scripts
    |       `-- plugins.inc.d ---------------------------------- inc files placed here are automatically processed by services
    |           `-- dnscryptproxy.inc -------------------------- adds entry to services in OPNsense UI
    `-- opnsense ----------------------------------------------- base directory for OPNsense's new Phalcon MVC implementation
        |-- mvc ------------------------------------------------ base directory for the Phalcon MVC
        |   `-- app -------------------------------------------- base directory for the MVC app structure
        |       |-- controllers/OPNsense/Dnscryptproxy --------- base directory for MVC controllers for this plugin
        |       |           |-- Api ---------------------------- API interfaces for this plugin
        |       |           |   |-- DiagnosticsController.php -- OPNsense API endpoint: /api/dnscryptproxy/diagnostics
        |       |           |   |-- FileController.php --------- OPNsense API endpoint: /api/dnscryptproxy/file
        |       |           |   |-- ServiceController.php ------ OPNsense API endpoint: /api/dnscryptproxy/service/
        |       |           |   `-- SettingsController.php ----- OPNsense API endpoint: /api/dnscryptproxy/settings
        |       |           |-- ControllerBase.php ------------- custom implementation of ControllerBase from Core
        |       |           |-- DiagnosticsController.php ------ OPNsense UI endpoint: /ui/dnscryptproxy/diagnostics
        |       |           |-- LogsController.php ------------- OPNsense UI endpoint: /ui/dnscryptproxy/logs
        |       |           |-- SettingsController.php --------- OPNsense UI endpoint: /ui/dnscryptproxy/settings
        |       |           `-- forms -------------------------- form definitions consumed by the UI endpoint php's defined above
        |       |               |-- diagnostics.xml ------------ form definition for DiagnosticsController
        |       |               |-- logs.xml ------------------- form definition for LogController
        |       |               `-- settings.xml --------------- form definition for SettingsController
        |       |-- models/OPNsense/Dnscryptproxy -------------- base directory for MVC models for this plugin
        |       |           |-- ACL ---------------------------- ACL definitions
        |       |           |   `-- ACL.xml -------------------- ACL definition for ths plugin
        |       |           |-- Menu --------------------------- directory to store OPNsense menu definitions
        |       |           |   `-- Menu.xml ------------------- OPNsense menu definition which includes an entry for this plugin
        |       |           |-- Settings.php ------------------- class definition for Settings, has the plugin model, and variable definitions
        |       |           `-- Settings.xml ------------------- model definition for Settings
        |       `-- views/OPNsense/Dnscryptproxy --------------- base directory for MVC views for this plugin (volt templates for phalcon to consume)
        |                   |-- +macros.volt ------------------- volt macros used by multiple volt templates
        |                   |-- layout_partials ---------------- custom layout_partials copied from Core and modified as necessary
        |                   |   |-- base_dialog.volt ----------- dialog template for displaying a dialog (used by bootgrids)
        |                   |   |-- base_form.volt ------------- main form template use to build the main page of the plugin
        |                   |   |-- base_script_content.volt --- template used to populate the contents of javascript <script> tags
        |                   |   |-- form_bootgrid_tr.volt ------ template used to draw a bootgrid
        |                   |   `-- form_input_tr.volt --------- template used to display row for a setting within a tab form
        |                   |-- diagnostics.volt --------------- volt template used for the DiagnosticsController (/ui/dnscryptproxy/diagnostics)
        |                   |-- logs.volt ---------------------- volt template used for the LogsController (/ui/dnscryptproxy/logs)
        |                   `-- settings.volt ------------------ volt template used for the SettingsController (/ui/dnscryptproxy/settings)
        |-- scripts/OPNsense/Dnscryptproxy --------------------- base scripts directory for this plugin
        |           |-- get_relays.py -------------------------- gets the current relays from dnscrypt-proxy
        |           |-- get_resolvers.py ----------------------- gets the current resolvers from dnscrypt-proxy
        |           |-- import_doh_client_certs.py ------------- imports the doh client certificates in OPNsense into files for dnscrypt-proxy
        |           |-- import_list.py ------------------------- imports an allowed/blocked list into dnscrypt-proxy's configuration files
        |           `-- tests ---------------------------------  base directory for tests for python scripts for this plugin
        `-- service -------------------------------------------- base directory for OPNsense's configd configurations
            |-- conf ------------------------------------------- configuration directory to include actions
            |   |-- actions.d ---------------------------------- actions directory to include config files for plugins
            |   |   `-- actions_dnscryptproxy.conf ------------- actions definitions for this plugin
            |   `-- dnscrypt-proxy.conf ------------------------ plugin definition for configd with some variable definitions
            `-- templates/OPNsense/Dnscryptproxy --------------- template definitions for this plugin for OPNsense configd to use
                        |-- +TARGETS --------------------------- mapping definition for template->configuration file names
                        |-- allowed-ips-internal.txt.jinja ----- builds allowed IPs list
                        |-- allowed-names-internal.txt.jinja --- builds allowed DNS names list
                        |-- blocked-ips-internal.txt.jinja ----- builds blocked IPs list
                        |-- blocked-names-internal.txt.jinja --- builds blocked DNS names list
                        |-- captive-portals.txt.jinja ---------- builds captive portals list
                        |-- cloaking-internal.txt.jinja -------- builds clocked domains list
                        |-- dnscrypt-proxy.toml.jinja ---------- builds dnscrypt-proxy configuration file
                        |-- dnscrypt_proxy.jinja --------------- sets dnscrypt_proxy_enable="YES" in /etc/rc.conf.d/dnscrypt_proxy
                        |-- forwarding-rules.txt.jinja --------- builds forwarding rules list
                        |-- local_doh-cert.pem.jinja ----------- builds a file which contains local doh certificate
                        `-- local_doh-cert_key.pem.jinja ------- builds a file which contains local doh certificate key
```
