# Configuration Sync
Configuration Sync is a tool designed to one-way synchronize the system 
configuration files from the OPNsense host to S3 compatible object data storage 
in close to real time. While the tool has the effect of being a great 
configuration backup tool the intent is to provide a tool that stores the 
OPNsense system configuration in a location that is readily addressable using 
DevOps automation tools such as Terraform.

The ability to start an OPNsense instance using automation tools means OPNsense 
becomes a first-class choice for building and managing network infrastructure 
within cloud-compute providers.

****

## Authors
Plugin managed by [Verb Networks Pty Ltd](https://verbnetworks.com)

## License
BSD-2-Clause - see LICENSE file for full details.
