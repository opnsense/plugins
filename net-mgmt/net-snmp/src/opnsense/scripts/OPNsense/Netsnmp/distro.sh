#!/bin/sh
#
#   Observium License Version 1.0
#
#   Copyright (C) 2013-2019 Joe Holden, (C) 2014-2024 Observium Limited
#
#   The intent of this license is to establish the freedom to use, share and contribute to
#   the software regulated by this license.
#
#   This license applies to any software containing a notice placed by the copyright holder
#   saying that it may be distributed under the terms of this license. Such software is herein
#   referred to as the Software. This license covers modification and distribution of the
#   Software.
#
#   Granted Rights
#
#   1. You are granted the non-exclusive rights set forth in this license provided you agree to
#      and comply with any and all conditions in this license. Whole or partial distribution of the
#      Software, or software items that link with the Software, in any form signifies acceptance of
#      this license.
#
#   2. You may copy and distribute the Software in unmodified form provided that the entire package,
#      including - but not restricted to - copyright, trademark notices and disclaimers, as released
#      by the initial developer of the Software, is distributed.
#
#   3. You may make modifications to the Software and distribute your modifications, in a form that
#      is separate from the Software, such as patches. The following restrictions apply to modifications:
#
#      a. Modifications must not alter or remove any copyright notices in the Software.
#      b. When modifications to the Software are released under this license, a non-exclusive royalty-free
#         right is granted to the initial developer of the Software to distribute your modification in
#         future versions of the Software provided such versions remain available under these terms in
#         addition to any other license(s) of the initial developer.
#
#   Limitations of Liability
#
#   In no event shall the initial developers or copyright holders be liable for any damages whatsoever,
#   including - but not restricted to - lost revenue or profits or other direct, indirect, special,
#   incidental or consequential damages, even if they have been advised of the possibility of such damages,
#   except to the extent invariable law, if any, provides otherwise.
#
#   No Warranty
#
#   The Software and this license document are provided AS IS with NO WARRANTY OF ANY KIND, INCLUDING THE
#   WARRANTY OF DESIGN, MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE.
#
#   URL: [https://www.observium.org/files/distro]

DISTROSCRIPT="2.3.2"

if [ -z ${DISTROFORMAT} ]; then
    DISTROFORMAT="pipe"
fi

if [ -n "${AGENT_LIBDIR}" -o -n "${MK_LIBDIR}" ]; then
    DISTROFORMAT="export"
fi

getos() {
    OS=`uname -s`
    if [ "${OS}" = "SunOS" ]; then
        OS="Solaris"
    elif [ "${OS}" = "DragonFly" ]; then
        OS="DragonFlyBSD"
    fi
    export OS
    return 0
}

getkernel() {
    if [ "${OS}" = "FreeBSD" -o "${OS}" = "DragonFlyBSD" ]; then
        KERNEL=`uname -i`
    elif [ "${OS}" = "OpenBSD" -o "${OS}" = "NetBSD" ]; then
        KERNEL=`uname -v`
    elif [ "${OS}" = "AIX" ]; then
        KERNEL=`oslevel -s`
    else
        KERNEL=`uname -r`
    fi
    export KERNEL
    return 0
}

getdistro() {
    if [ "${OS}" = "Linux" ]; then
        if [ -f /etc/os-release ]; then
            if [ -f /etc/armbian-release ]; then
                # Armbian uses incorrect os-release
                DISTRO="Armbian"
            elif [ -f /etc/orangepi-release ]; then
                # Orange Pi uses incorrect os-release
                DISTRO="Orange OS"
            elif [ -f /boot/dietpi/.version ]; then
                # This is clone of Raspbian, but distro/version incorrect
                DISTRO="DietPi"
            else
                # note, this source include also set variable name $VERSION, ie on debian "10 (buster)"
                . /etc/os-release
                #DISTRO=`echo ${NAME} | awk '{print $1}'`
                DISTRO="${NAME% *}"
                if [ "${DISTRO}" = "Linux" ]; then
                    # ie: Linux Mint
                    DISTRO="${NAME#Linux *}"
                fi
            fi
        elif [ -x /usr/bin/lsb_release ]; then
            DISTRO=`/usr/bin/lsb_release -si 2>/dev/null`
        elif [ -f /etc/redhat-release ]; then
            DISTRO=`cat /etc/redhat-release | cut -d" " -f1`
        elif [ -f /etc/fedora-release ]; then
            DISTRO="Fedora"
        elif [ -f /etc/debian_version ]; then
            if [ -f /etc/mailcleaner/etc/mailcleaner/version.def ]; then
                DISTRO="MailCleaner"
            else
                DISTRO="Debian"
            fi
        elif [ -f /etc/arch-release ]; then
            DISTRO="ArchLinux"
        elif [ -f /etc/gentoo-release ]; then
            DISTRO="Gentoo"
        elif [ -f /etc/SuSE-release -o -f /etc/novell-release -o -f /etc/sles-release ]; then
            DISTRO="SuSE"
          if [ -f /etc/os-release ]; then
              . /etc/os-release
                if [ "${NAME}" = "openSUSE" ]; then
                    DISTRO="openSUSE"
                fi
            fi
        elif [ -f /etc/mandriva-release ]; then
            DISTRO="Mandriva"
        elif [ -f /etc/mandrake-release -o -f /etc/mandakelinux-release ]; then
            DISTRO="Mandrake"
        elif [ -f /etc/UnitedLinux-release ]; then
            DISTRO="UnitedLinux"
        elif [ -f /etc/openwrt_version ]; then
            DISTRO="OpenWRT"
        elif [ -f /etc/slackware-version -o -f /etc/slackware-release ]; then
            DISTRO="Slackware"
        elif [ -f /etc/annvix-release ]; then
            DISTRO="Annvix"
        elif [ -f /etc/arklinux-release ]; then
            DISTRO="Arklinux"
        elif [ -f /etc/aurox-release ]; then
            DISTRO="Aurox Linux"
        elif [ -f /etc/blackcat-release ]; then
            DISTRO="BlackCat"
        elif [ -f /etc/cobalt-release ]; then
            DISTRO="Cobalt"
        elif [ -f /etc/conectiva-release ]; then
            DISTRO="Conectiva"
        elif [ -f /etc/eos-version ]; then
            DISTRO="FreeEOS"
        elif [ -f /etc/hlfs-release -o -f /etc/hlfs_version ]; then
            DISTRO="HLFS"
        elif [ -f /etc/immunix-release ]; then
            DISTRO="Immunix"
        elif [ -f /knoppix_version ]; then
            DISTRO="Knoppix"
        elif [ -f /etc/lfs-release -o -f /etc/lfs_version ]; then
            DISTRO="Linux-From-Scratch"
        elif [ -f /etc/linuxppc-release ]; then
            DISTRO="Linux-PPC"
        elif [ -f /etc/mageia-release ]; then
            DISTRO="Mageia"
        elif [ -f /etc/mklinux-release ]; then
            DISTRO="MkLinux"
        elif [ -f /etc/nld-release ]; then
            DISTRO="Novell Linux Desktop"
        elif [ -f /etc/pld-release ]; then
            DISTRO="PLD"
        elif [ -f /etc/rubix-version ]; then
            DISTRO="Rubix"
        elif [ -f /etc/e-smith-release ]; then
            DISTRO="SME Server"
        elif [ -f /etc/synoinfo.conf ]; then
            DISTRO="Synology"
        elif [ -f /etc/tinysofa-release ]; then
            DISTRO="Tiny Sofa"
        elif [ -f /etc/trustix-release -o -f /etc/trustix-version ]; then
            DISTRO="Trustix"
        elif [ -f /etc/turbolinux-release ]; then
            DISTRO="TurboLinux"
        elif [ -f /etc/ultrapenguin-release ]; then
            DISTRO="UltraPenguin"
        elif [ -f /etc/va-release ]; then
            DISTRO="VA-Linux"
        elif [ -f /etc/yellowdog-release ]; then
            DISTRO="Yellow Dog"
        else
            DISTRO=
        fi

        # Additional Distro fixes
        if [ "${DISTRO}" = "Debian GNU/Linux" ]; then
            DISTRO="Debian"
        elif [ "${DISTRO}" = "Red" -o "${DISTRO}" = "RedHatEnterpriseServer" ]; then
            DISTRO="RedHat"
        elif [ "${DISTRO}" = "VMware Photon" ]; then
            DISTRO="Photon"
        elif [ "${DISTRO}" = "Arch" ]; then
            DISTRO="Arch Linux"
        elif [ "${DISTRO}" = "Orange" ]; then
            DISTRO="Orange OS"
        elif [ "${DISTRO}" = "Amazon" ]; then
            DISTRO="Amazon Linux"
        fi

    elif [ "${OS}" = "FreeBSD" ]; then
        if [ -f /etc/platform -a -f /etc/version ]; then
            DISTRO="pfSense"
        elif [ -f /etc/platform -a -f /etc/prd.name ]; then
            DISTRO=`cat /etc/prd.name`
        elif [ -x /usr/local/sbin/opnsense-version ]; then
            DISTRO="OPNsense"
        elif [ -f /usr/local/bin/pbreg ]; then
            DISTRO="PC-BSD"
        elif [ -f /usr/sbin/hbsd-update -o -f /etc/hbsd-update.conf ]; then
            DISTRO="HardenedBSD"
        elif [ -f /tmp/freenas_config.md5 ]; then
            DISTRO="FreeNAS"
        else
            DISTRO=
        fi
    elif [ "${OS}" = "Solaris" ]; then
        DISTRO=`head -n 1 /etc/release | awk '{print $1}'`
        if [ "${DISTRO}" = "Solaris" -o "${DISTRO}" = "Oracle" ]; then
            DISTRO=
        fi
    elif [ "${OS}" = "Darwin" ]; then
        case `uname -m` in
            AppleTV*)
                DISTRO="AppleTV"
            ;;
            iPad*)
                DISTRO="iPad"
                ;;
            iPhone*)
                DISTRO="iPhone"
                ;;
            iPod*)
                DISTRO="iPod"
                ;;
            *)
                DISTRO="macOS"
                ;;
        esac
    else
        DISTRO=
    fi
    export DISTRO
    return 0
}

getarch() {
    if [ "${OS}" = "Solaris" ]; then
        ARCH=`isainfo -k`
    elif [ "${OS}" = "Darwin" ]; then
        ARCH=`uname -m`
    elif [ "${OS}" = "AIX" ]; then
        ARCH=`uname -p`
    else
        ARCH=`uname -m`
    fi
    if [ "${ARCH}" = "x86_64" ]; then
        ARCH="amd64"
    elif [ "${ARCH}" = "i486" -o "${ARCH}" = "i586" -o "${ARCH}" = "i686" ]; then
        ARCH="i386"
    fi
    export ARCH
    return 0
}

getversion() {
    if [ "${OS}" = "FreeBSD" -o "${OS}" = "DragonFlyBSD" ]; then
        if [ "${DISTRO}" = "pfSense" ]; then
            VERSION=`cat /etc/version`
        elif [ "${DISTRO}" = "PC-BSD" ]; then
            VERSION=`pbreg get /PC-BSD/Version`
        #elif [ "${DISTRO}" = "HardenedBSD" ]; then
        #    tmp=`sysctl -n hardening.version 2>/dev/null`
        #    VERSION=``
        elif [ "${DISTRO}" = "OPNsense" ]; then
            VERSION=`/usr/local/sbin/opnsense-version | awk '{ print $2 }'`
        elif [ -f /etc/prd.version ]; then
            VERSION=`cat /etc/prd.version`
        elif [ -f /bin/freebsd-version ]; then
            VERSION=`/bin/freebsd-version -u`
        else
            VERSION=`uname -r`
        fi
    elif [ "${OS}" = "OpenBSD" -o "${OS}" = "NetBSD" ]; then
        VERSION=`uname -r`
    elif [ "${OS}" = "Linux" ]; then
        VERSION=none
        if [ "${DISTRO}" = "Debian GNU/Linux" ]; then
            DISTRO="Debian"
        fi
        if [ "${DISTRO}" = "OpenWRT" ]; then
            VERSION=`cat /etc/openwrt_version`
        elif [ "${DISTRO}" = "Slackware" ]; then
            VERSION=`cat /etc/slackware-version | cut -d" " -f2`
        elif [ "${DISTRO}" = "Armbian" ]; then
            #. /etc/armbian-release
            VERSION=`awk -F '=' '/VERSION=/ { print $2 }' /etc/armbian-release`
        elif [ "${DISTRO}" = "Orange OS" ]; then
            #. /etc/orangepi-release
            VERSION=`awk -F '=' '/VERSION=/ { print $2 }' /etc/orangepi-release`
        elif [ "${DISTRO}" = "DietPi" ]; then
            . /boot/dietpi/.version
            VERSION="${G_DIETPI_VERSION_CORE}.${G_DIETPI_VERSION_SUB}.${G_DIETPI_VERSION_RC}"
        elif [ -f /etc/orangepi-os-version ]; then
            VERSION=`awk -F ' - ' '{ print $NF }' /etc/orangepi-os-version | sed 's/-linux.*//'`
        elif [ -f /etc/os-release ]; then
            . /etc/os-release
            VERSION="${VERSION_ID}"
        elif [ -f /etc/redhat-release ]; then
            VERSION=`cat /etc/redhat-release | sed 's/.*release\ //' | cut -d" " -f1`
        elif [ -x /usr/bin/lsb_release ]; then
            VERSION=`lsb_release -sr 2>/dev/null`
        elif [ -f /etc/debian_version ]; then
            VERSION=`cat /etc/debian_version | cut -d"." -f1`
        fi

        # reset not numeric version string
        case $VERSION in 0*|1*|2*|3*|4*|5*|6*|7*|8*|9*)
            ;;
            *)
                VERSION=none
                ;;
        esac

        if [ "${VERSION}" = "none" -a -f /etc/os-release ]; then
            . /etc/os-release
            VERSION="${VERSION_ID}"
        fi

    elif [ "${OS}" = "Darwin" ]; then
        VERSION=`sw_vers -productVersion`
    elif [ "${OS}" = "Solaris" ]; then
        VERSION=`uname -v`
    elif [ "${OS}" = "AIX" ]; then
        # ie: 6.1.0.0
        VERSION=`oslevel`
    else
        VERSION=
    fi
    export VERSION
    return 0
}

detectvirt() {
    local type
    type=none
    case $1 in
        [Vv][Mm][Ww][Aa][Rr][Ee]*)
            type=vmware
            ;;
        HVM*domU*)
            type=xenhvm
            ;;
        [Xx][Ee][Nn]*)
            type=xenpv
            ;;
        [Mm][Ii][Cc][Rr][Oo][Ss][Oo][Ff][Tt]|[Vv][Ii][Rr][Tt][Uu][Aa][Ll]*)
            type=microsoft
            ;;
        [Bb][Hh][Yy][Vv][Ee]*)
            type=bhyve
            ;;
        Standard*PC*Q*ICH*|Standard*PC*i440FX*PIIX*)
            type=qemu
            ;;
        QEMU*Virtual*CPU*)
            type=qemu
            ;;
        KVM*|[Go][Oo][Oo][Gg][Ll][Ee]*)
            type=kvm
            ;;
        zvm|oracle|amazon|bochs|uml|parallels|kvm|qemu|qnx|acrn|powervm)
            type="${1}"
            ;;
        vm-other)
            type=other
            ;;
        *)
            type=none
            ;;
    esac

    echo $type
}

detectcont() {
    local type
    type=none
    case $1 in
        *)
            type="${1}"
            ;;
    esac

    echo $type
}

getcont() {
    CONT="none"
    if [ "${OS}" = "Linux" ]; then
		if [ "${CONT}" = "none" -a -f /proc/user_beancounters ]; then
            if [ "`awk '/envID:/ {print $2}' /proc/self/status 2>/dev/null`" != "0" ]; then
                CONT=openvz
            fi
		fi
        if [ "${CONT}" = "none" -a -f /usr/bin/systemd-detect-virt ]; then
            CONT=`/usr/bin/systemd-detect-virt -c`
        fi
        if [ "${CONT}" = "none" -a -f /proc/1/cgroup ]; then
            if grep -qa docker /proc/1/cgroup 2>/dev/null; then
                CONT=docker
            elif grep -qa lxc /proc/1/cgroup 2>/dev/null; then
                CONT=lxc
            fi
        fi
        if [ "${CONT}" = "none" -a -f /.dockerenv ]; then
            CONT=docker
        fi
    elif [ "${OS}" = "FreeBSD" -o "${OS}" = "OpenBSD" -o "${OS}" = "NetBSD" ]; then
        if [ "${OS}" = "FreeBSD" ]; then
            tmp=`sysctl -n security.jail.jailed 2>/dev/null`
            if [ "${tmp}" = "1" ]; then
                CONT=jail
            fi
        fi
    else
        CONT=""
    fi
    if [ "${CONT}" = "none" ]; then
        CONT=""
    fi
    export CONT
    return 0
}

getvirt() {
    VIRT="none"
    if [ "${OS}" = "Linux" ]; then
        if [ "${VIRT}" = "none" -a -f /usr/bin/systemd-detect-virt ]; then
            tmp=`/usr/bin/systemd-detect-virt -v`
            # systemd-detect-virt falsely detects "Microsoft" virtualisation
            # https://github.com/systemd/systemd/issues/21468
            if [ "${tmp}" = "microsoft" -a -f /sys/devices/virtual/dmi/id/product_name ]; then
                tmp2=`cat /sys/devices/virtual/dmi/id/product_name`
                if [ "${tmp2}" = "KVM" ]; then
                    tmp="${tmp2}"
                fi
            fi
            VIRT=`detectvirt "${tmp}"`
        fi
        if [ "${VIRT}" = "none" -a -f /sys/devices/virtual/dmi/id/product_name ]; then
            tmp=`cat /sys/devices/virtual/dmi/id/product_name`
            VIRT=`detectvirt "${tmp}"`
        fi
        if [ "${VIRT}" = "none" ]; then
            if [ -f `which dmidecode 2>/dev/null` ]; then
                tmp=`dmidecode -s system-product-name 2>/dev/null`
                VIRT=`detectvirt "${tmp}"`
            fi
        fi
        if [ "${VIRT}" = "none" ]; then
            if [ -f /proc/cpuinfo ]; then
                tmp=`grep 'model name' /proc/cpuinfo | cut -d\  -f3-`
                VIRT=`detectvirt "${tmp}"`
            fi
        fi
    elif [ "${OS}" = "FreeBSD" -o "${OS}" = "OpenBSD" -o "${OS}" = "NetBSD" ]; then
        if [ "${OS}" = "FreeBSD" ]; then
            tmp=`sysctl -n hw.hv_vendor 2>/dev/null`
            VIRT=`detectvirt "${tmp}"`
            if [ "${VIRT}" = "none" ]; then
                tmp=`sysctl -n machdep.dmi.system-vendor 2>/dev/null`
                VIRT=`detectvirt "${tmp}"`
            fi
            if [ "${VIRT}" = "none" ]; then
                tmp=`sysctl -n machdep.dmi.system-product 2>/dev/null`
                VIRT=`detectvirt "${tmp}"`
            fi
        fi
        if [ "${OS}" = "OpenBSD" ]; then
            tmp=`dmesg | grep pvbus0 | awk '{print $4}'`
            VIRT=`detectvirt "${tmp}"`
        fi
        if [ "${VIRT}" = "none" ]; then
            tmp=`sysctl -n hw.product 2>/dev/null`
            VIRT=`detectvirt "${tmp}"`
        fi
        if [ "${VIRT}" = "none" ]; then
            tmp=`sysctl -n hw.model 2>/dev/null`
            VIRT=`detectvirt "${tmp}"`
        fi
    else
        VIRT=""
    fi
    if [ "${VIRT}" = "none" ]; then
        VIRT=""
    fi
    export VIRT
    return 0
}

help() {
    cat << EOF
Usage: distro [-f format] [-o out] [-h] [-v]

Options:
  -f <format>       Output format: pipe (default), twopipe, json, ini, export
  -o <out>          Show specific parameter only: os, kernel, arch, distro,
                    version, virt, cont
  -h                Show help
  -v                Show version

See the distro homepage at https://docs.observium.org/distro_script
and source code at https://github.com/observium/distroscript
EOF
}

while getopts vho:f: flag
do
    case "${flag}" in
        v) echo $DISTROSCRIPT; exit;;
        h) help; exit;;
        o)
            getos
            getdistro
            if [ "${OPTARG}" = "os" ]; then
                #getos
                echo $OS
            elif [ "${OPTARG}" = "kernel" ]; then
                getkernel
                echo $KERNEL
            elif [ "${OPTARG}" = "arch" ]; then
                getarch
                echo $ARCH
            elif [ "${OPTARG}" = "distro" ]; then
                #getdistro
                echo $DISTRO
            elif [ "${OPTARG}" = "version" ]; then
                getversion
                echo $VERSION
            elif [ "${OPTARG}" = "virt" ]; then
                getvirt
                echo $VIRT
            elif [ "${OPTARG}" = "cont" ]; then
                getcont
                echo $CONT
            fi
            exit
        ;;
        f)  if [ "${OPTARG}" = "pipe" -o "${OPTARG}" = "twopipe" -o "${OPTARG}" = "json" -o "${OPTARG}" = "export" -o "${OPTARG}" = "ini" ]; then
                DISTROFORMAT=${OPTARG}
            fi
        ;;
    esac
done

if [ -z ${DISTROEXEC+x} ]; then

    getos
    getkernel
    getarch
    getdistro
    getversion
    getvirt
    getcont

    if [ "${AGENT_LIBDIR}" -o "${MK_LIBDIR}" ]; then
        echo "<<<distro>>>"
    fi
    if [ "${DISTROFORMAT}" = "twopipe" ]; then
        echo "${OS}||${KERNEL}||${ARCH}||${DISTRO}||${VERSION}||${VIRT}||${CONT}"
    elif [ "${DISTROFORMAT}" = "ini" ]; then
        echo "[distroscript]"
        echo "  OS = ${OS}"
        echo "  KERNEL = ${KERNEL}"
        echo "  ARCH = ${ARCH}"
        echo "  DISTRO = ${DISTRO}"
        echo "  VERSION = ${VERSION}"
        echo "  VIRT = ${VIRT}"
        echo "  CONT = ${CONT}"
        echo "  SCRIPTVER = ${DISTROSCRIPT}"
    elif [ "${DISTROFORMAT}" = "export" ]; then
        echo "OS=${OS}"
        echo "KERNEL=${KERNEL}"
        echo "ARCH=${ARCH}"
        echo "DISTRO=${DISTRO}"
        echo "VERSION=${VERSION}"
        echo "VIRT=${VIRT}"
        echo "CONT=${CONT}"
        echo "SCRIPTVER=${DISTROSCRIPT}"
    elif [ "${DISTROFORMAT}" = "json" ]; then
        echo "{\"os\":\"${OS}\",\"kernel\":\"${KERNEL}\",\"arch\":\"${ARCH}\",\"distro\":\"${DISTRO}\",\"version\":\"${VERSION}\",\"virt\":\"${VIRT}\",\"cont\":\"${CONT}\",\"scriptver\":\"${DISTROSCRIPT}\"}"
    else
        echo "${OS}|${KERNEL}|${ARCH}|${DISTRO}|${VERSION}|${VIRT}|${CONT}"
    fi
    exit 0
fi
