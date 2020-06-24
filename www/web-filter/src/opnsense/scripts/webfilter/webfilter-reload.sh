#!/bin/sh
# webfilter-reload.sh
# create the list of custom Blacklists and Whitelists in Categories DB dirname
# Copyright (C) 2018 Julio Camargo
# Copyright (C) 2018 Cloudfence


# Indexes / Files PATH
BLACKLIST_PATH="/usr/local/etc/squid/db/Blacklist"
# Gen by template blacklist.index
BLACKLIST_INDEX="$BLACKLIST_PATH/blacklist.index"
BLACKLISTREGEX_INDEX="$BLACKLIST_PATH/blacklistregex.index"
BLACKLIST_DOMAINS="$BLACKLIST_PATH/domains"
BLACKLIST_URLS="$BLACKLIST_PATH/urls"
BLACKLIST_REGEX="$BLACKLIST_PATH/expressions"

WHITELIST_PATH="/usr/local/etc/squid/db/Whitelist"
# Gen by template whitelist.index
WHITELIST_INDEX="$WHITELIST_PATH/whitelist.index"
WHITELISTREGEX_INDEX="$WHITELIST_PATH/whitelistregex.index"
WHITELIST_DOMAINS="$WHITELIST_PATH/domains"
WHITELIST_URLS="$WHITELIST_PATH/urls"
WHITELIST_REGEX="$WHITELIST_PATH/expressions"

# Gen by template userlist.index
USERLIST_PATH="/usr/local/etc/squid/db"
USERLIST_INDEX="$USERLIST_PATH/userlist.index"

ARG="$1"

blacklist(){
# params
# $1 domains / urls / regex
local TYPE=$1

if [ ! -e $BLACKLIST_PATH ];then
    mkdir $BLACKLIST_PATH
fi

case "$TYPE" in

domains)
    # index sanitization
    local INDEX_CLEAN=$(cat $BLACKLIST_INDEX | grep -v ^$ > /tmp/blacklist.index.$$)
    mv /tmp/blacklist.index.$$ $BLACKLIST_INDEX

    # create domains file to each rule
    for rule in $(cat $BLACKLIST_INDEX | cut -d: -f2); do
        #let's populate the file
        cat $BLACKLIST_INDEX | grep $rule | cut -d: -f1 > $BLACKLIST_DOMAINS.$rule
    done
    ;;

urls)
    # WIP
    ;;

regex)
    # index sanitization
    local INDEX_CLEAN=$(cat $BLACKLISTREGEX_INDEX | grep -v ^$ > /tmp/blacklistregex.index.$$)
    mv /tmp/blacklistregex.index.$$ $BLACKLISTREGEX_INDEX

    # create domains file to each rule
    for rule in $(cat $BLACKLISTREGEX_INDEX | cut -d: -f2); do
        #let's populate the file
        cat $BLACKLISTREGEX_INDEX | grep $rule | cut -d: -f1 > $BLACKLIST_REGEX.$rule
    done
    ;;

*)
    ;;

esac

}

whitelist(){
# params
# $1 domains / urls / regex
local TYPE=$1

if [ ! -e $WHITELIST_PATH ];then
    mkdir $WHITELIST_PATH
fi

case "$TYPE" in

domains)
    # index sanitization
    local INDEX_CLEAN=$(cat $WHITELIST_INDEX | grep -v ^$ > /tmp/whitelist.index.$$)
    mv /tmp/whitelist.index.$$ $WHITELIST_INDEX

    # create domains file to each rule
    for rule in $(cat $WHITELIST_INDEX | cut -d: -f2); do
        #let's populate the file
        cat $WHITELIST_INDEX | grep $rule | cut -d: -f1 > $WHITELIST_DOMAINS.$rule
    done

    ;;

urls)
    # WIP
    ;;

regex)
    # index sanitization
    local INDEX_CLEAN=$(cat $WHITELISTREGEX_INDEX | grep -v ^$ > /tmp/whitelistregex.index.$$)
    mv /tmp/whitelistregex.index.$$ $WHITELISTREGEX_INDEX

    # create domains file to each rule
    for rule in $(cat $WHITELISTREGEX_INDEX | cut -d: -f2); do
        #let's populate the file
        cat $WHITELISTREGEX_INDEX | grep $rule | cut -d: -f1 > $WHITELIST_REGEX.$rule
    done
    ;;

*)
    ;;

esac

}


# Call functions
service_reload(){
logger -t "WebFilter" "Reloading..."
chown -R squid:squid $USERLIST_PATH
# Call squid to reload configuration
/usr/local/sbin/squid -k reconfigure
logger -t "WebFilter" "Reloaded!"
}

# Main
if [ "$ARG" == "reconfigure" ];then
    # Call functions
    RELOAD_NEEDED="1"
    logger -t "WebFilter" "Configuration changed, reconfiguring..."
    blacklist domains
    blacklist regex
    whitelist domains
    whitelist regex
fi

# Needs reload?
if [ "$RELOAD_NEEDED" -eq 1 ];then
    service_reload
fi
# Exiting with OK - all my work is done!
exit 0
