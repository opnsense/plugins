#!/bin/sh
# webfilter-reload.sh
# create the list of custom Blacklists and Whitelists in Categories DB dirname
# Copyright (C) 2018-2020 Cloudfence

CONFIG_XML="/conf/config.xml"
# Indexes / Files PATH
CUSTOMBLCKLIST_PATH="/usr/local/etc/squid/db/Blacklist"
BLACKLIST_PATH="/usr/local/etc/squid/db"
# Gen by template blacklist.index
BLACKLIST_INDEX="$CUSTOMBLCKLIST_PATH/blacklist.index"
BLACKLISTREGEX_INDEX="$CUSTOMBLCKLIST_PATH/blacklistregex.index"
BLACKLIST_DOMAINS="$CUSTOMBLCKLIST_PATH/domains"
BLACKLIST_URLS="$CUSTOMBLCKLIST_PATH/urls"
BLACKLIST_REGEX="$CUSTOMBLCKLIST_PATH/expressions"

WHITELIST_PATH="/usr/local/etc/squid/db/Whitelist"
# Gen by template whitelist.index
WHITELIST_INDEX="$WHITELIST_PATH/whitelist.index"
WHITELISTREGEX_INDEX="$WHITELIST_PATH/whitelistregex.index"
WHITELIST_DOMAINS="$WHITELIST_PATH/domains"
WHITELIST_URLS="$WHITELIST_PATH/urls"
WHITELIST_REGEX="$WHITELIST_PATH/expressions"

ARG="$1"

blacklist(){
# params
# $1 domains / urls / regex
local TYPE=$1

if [ ! -e $CUSTOMBLCKLIST_PATH ];then
    mkdir $CUSTOMBLCKLIST_PATH
fi

case "$TYPE" in

domains)
    # index sanitization
    local INDEX_CLEAN=$(cat $BLACKLIST_INDEX | grep -v ^$ > /tmp/blacklist.index.$$)
    mv /tmp/blacklist.index.$$ $BLACKLIST_INDEX

    # dir sanitization
    # Suspeita de derrubar o SquidGuard - ref. issue #144
    # rm -f $BLACKLIST_DOMAINS.*

    # create domains file to each rule
    for rule in $(cat $BLACKLIST_INDEX | cut -d: -f2); do
        #let's populate the file
        cat $BLACKLIST_INDEX | grep $rule | cut -d: -f1 > $BLACKLIST_DOMAINS.$rule
    done

    ;;

urls)
    ;;

regex)
    # index sanitization
    local INDEX_CLEAN=$(cat $BLACKLISTREGEX_INDEX | grep -v ^$ > /tmp/blacklistregex.index.$$)
    mv /tmp/blacklistregex.index.$$ $BLACKLISTREGEX_INDEX

    # dir sanitization
    # Suspeita de derrubar o SquidGuard - ref. issue #144
    # rm -f $BLACKLIST_REGEX.*

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

    # dir sanitization
    # Suspeita de derrubar o SquidGuard - ref. issue #144
    #rm -f $WHITELIST_DOMAINS.*

    # create domains file to each rule
    for rule in $(cat $WHITELIST_INDEX | cut -d: -f2); do
        #let's populate the file
        cat $WHITELIST_INDEX | grep $rule | cut -d: -f1 > $WHITELIST_DOMAINS.$rule
    done

    ;;

urls)
    ;;

regex)
    # index sanitization
    local INDEX_CLEAN=$(cat $WHITELISTREGEX_INDEX | grep -v ^$ > /tmp/whitelistregex.index.$$)
    mv /tmp/whitelistregex.index.$$ $WHITELISTREGEX_INDEX

    # dir sanitization
    # Suspeita de derrubar o SquidGuard - ref. issue #144
    #rm -f $WHITELIST_REGEX.*

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

categories_gen(){
XMLLINT=$(which xmllint)
local CATEGORIES=$($XMLLINT --nocdata --xpath "//webfilter//general//categories" $CONFIG_XML | cut -d">" -f2 | cut -d"<" -f1 | sed 's/\,/ /g')
local CATEG_INDEX="/usr/local/etc/squid/db/categories.index"
local TMP_CATEG_INDEX="/tmp/categories.index.$$"

local DEST_CHECK=$(xmllint --nocdata --xpath "//webfilter//rules//destination" /conf/config.xml | cut -d">" -f2 |
 cut -d"<" -f1 | sed 's/\,/ /g')
local ACTIVE_CATEG=$(xmllint --nocdata --xpath "//webfilter//general//categories" /conf/config.xml | cut -d">" -f2 | cut -d"<" -f1 | sed 's/\,/ /g')

#for category in $DEST_CHECK; do
#        CHK_IFEXIST=$(echo $ACTIVE_CATEG | grep $category )
#        if [ -z "$CHK_IFEXIST" ];then
#                echo "The category $category is in use and can't be removed!"
#                exit 0
#        fi
#done

# Build JSON index
echo -n "{" > $TMP_CATEG_INDEX
for category in $CATEGORIES; do
    echo -n "\"$category\": \"$category\"" >> $TMP_CATEG_INDEX
    echo -n "," >> $TMP_CATEG_INDEX
done
echo -n "}" >> $TMP_CATEG_INDEX
# wrap-up
cat $TMP_CATEG_INDEX | sed 's/,}/}/g' > $CATEG_INDEX
rm -f $TMP_CATEG_INDEX
}

# Call functions
service_reload(){
logger -t "WebFilter" "Reloading..."
# Reload WebFilter template
configctl template reload OPNsense/WebFilter
chown -R squid:squid $BLACKLIST_PATH
# Call squid to reload configuration
/usr/local/sbin/squid -k reconfigure
logger -t "WebFilter" "Reloaded!"
}

#MAIN
if [ "$ARG" == "reconfigure" ];then
    # Call functions
    RELOAD_NEEDED="1"
    logger -t "WebFilter" "Configuration changed, reconfiguring..."
    blacklist domains
    blacklist regex
    whitelist domains
    whitelist regex
    categories_gen
fi

# Needs reload?
if [ "$RELOAD_NEEDED" -eq 1 ];then
    service_reload
fi
# Exiting with OK - all my work is done!
exit 0
