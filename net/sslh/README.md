# sslh plugin for OPNsense

## Introduction

This is a plugin for OPNsense firewall which provides a front-end UI for managing `SSLH`.

This plugin is designed for v`1.21c`, but may function with later versions.

Here are some resources for `SSLH`

[SSLH Project Home Page](https://www.rutschle.net/tech/sslh/README.html)

[SSLH Project Source Repository](https://github.com/yrutschle/sslh)

[SSLH FAQ](https://www.rutschle.net/tech/sslh/doc/FAQ.html)

## Features

This plugin, currently support only basic features like:

* Setting multiple listen addresses
* Setting protocol targets
* Controlling some advanced settings

## Operation

The plugin can be managed from Services -> SSLH in the OPNsense UI.

To begin, enable SSLH, define at least one listen address, and define a desired target protocol.

### Settings

Most settings for `SSLH` are included here in the UI, but some were left out due to the advanced nature, or the need to investigate further to understand the best approach to bring them into the UI. Help for each setting is included in the UI, select the "i" button to the left of each setting to show the help text.

#### Listen Addresses

This is a list of ADDRESS:PORT combinations. The list is comma delimited, and supports both IPv4, and IPv6 addresses.

#### Protocol Targets

The protocol targets each support one ADDRESS:PORT combination.

### Advanced Settings

Several settings are hidden by default, select the "advanced mode" button in the top left to access these settings.

#### Mode

This is also called the sslh "variant", and makes the start up script execute a separate binary, `sslh-fork` or `sslh-select` depending on selection. Each behaves differently, and has different performance.

#### Timeout

This is a global timeout, and has a default value of 2 seconds.

#### On Timeout

This defines the protocol to which connections will be sent after the timeout period. The default is SSH.

#### Verbose

This will increase the verbosity of the log messages in `SSLH`.

#### Numeric

This will force no DNS lookup, and make the logs contain IP addresses instead of hostnames.

### License

[![License](https://img.shields.io/badge/License-BSD%202--Clause-orange.svg)](https://opensource.org/licenses/BSD-2-Clause)
