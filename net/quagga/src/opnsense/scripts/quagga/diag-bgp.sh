#!/bin/sh

case "$1" in
  bgp)
    vtysh -d bgpd -c "show ip bgp"
    ;;
  summary)
    vtysh -d bgpd -c "show ip bgp summary"
    ;;
  neighbor)
    vtysh -d bgpd -c "show ip bgp neighbors $2"
    ;;
  neighbor-adv)
    vtysh -d bgpd -c "show ip bgp neighbors $2 advertised-routes"
    ;;
  *)
    echo "Usage: $0 bgp|summary|neighbor <ip>|neighbor-adv <ip>"
    exit 1
esac
exit 0
