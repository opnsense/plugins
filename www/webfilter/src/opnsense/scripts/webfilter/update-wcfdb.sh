#!/bin/sh
#    Copyright (C) 2020 Cloudfence - JCC
#    All rights reserved.
#
#    Redistribution and use in source and binary forms, with or without
#    modification, are permitted provided that the following conditions are met:
#
#    1. Redistributions of source code must retain the above copyright notice,
#     this list of conditions and the following disclaimer.#
#
#    2. Redistributions in binary form must reproduce the above copyright
#     notice, this list of conditions and the following disclaimer in the
#     documentation and/or other materials provided with the distribution.
#
#    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
#    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
#    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
#    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
#    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
#    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
#    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
#    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
#    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
#    POSSIBILITY OF SUCH DAMAGE.

# fetch Categories file
CONFIG_XML="/conf/config.xml"
WEBFILTER_CONF="/usr/local/etc/webfilter/webfilter.conf"
BLACKLIST_URL=$(grep categoriesurl $CONFIG_XML | cut -d">" -f2 | cut -d"<" -f1)
FETCH_BIN=$(which fetch)
FETCH_CMD="$FETCH_BIN -q"
BLACKLIST_PATH="/usr/local/etc/squid/db"
FORCE_DOWNLOAD=$(grep "ForceDownload=1" $WEBFILTER_CONF | cut -d"=" -f2)
PID_FILE="/var/run/update-wcfdb.pid"
SGUARD=$(which squidGuard)
SGCONF_TMP="/tmp/squidGuard.conf"
ARG="$1"

filetype() {
    local SUFIX=$1
    CHECK=$(basename -s ."$SUFIX" /tmp/$FILENAME | grep "\.")
}

find_path() {
    # blacklist.db
    FIND_PATH=$(find $BLACKLIST_PATH -type d)
    CATEGORIES=$(find $BLACKLIST_PATH -name "domains" | rev | cut -d"/" -f2 | rev)
    BLACKLIST_DIR=$(find $BLACKLIST_PATH -name "domains" | rev | cut -d"/" -f3 | rev | uniq)
}

updatedb(){
    find_path
cat > $SGCONF_TMP << EOF
#
# TEMP CONFIG FILE FOR SQUIDGUARD
#
logdir /var/log/squid/
dbhome /usr/local/etc/squid/db/
EOF
        for category in $(echo $CATEGORIES); do
                echo "dest $category {" >> $SGCONF_TMP
                echo "        domainlist categories/$category/domains" >> $SGCONF_TMP
                echo "}" >> $SGCONF_TMP
            done
cat >> $SGCONF_TMP << EOF

dest local {
}

acl {

        default {
                pass     local none
        }
}
EOF
        echo "running"
        $SGUARD -c $SGCONF_TMP -b -C all & >> /tmp/sguard.out
}

download() {
    local CATEG_URL=$(grep CategoriesURL $WEBFILTER_CONF | cut -d"=" -f2)
   
    if [ -f $PID_FILE ];then
        echo "running"
        exit 0
    fi 

    if [ -e "$BLACKLIST_PATH/categories" ];then
        exit 0
    else
        echo $$ > $PID_FILE
        echo "starting"
        # Download Blacklists and extract blacklist
        FILENAME=$(basename $BLACKLIST_URL)
        $FETCH_CMD $BLACKLIST_URL -o /tmp/$FILENAME

        for EXTENSION in "gz" "zip" "tgz" "tar.gz"; 
            do
                filetype $EXTENSION
                
                if [ -z "$CHECK" ];then
                    
                        case $EXTENSION in 
                        gz)
                            mv /tmp/$FILENAME $BLACKLIST_PATH
                            gzip -d $BLACKLIST_PATH/$FILENAME
                        ;; 
                        zip)
                            mv /tmp/$FILENAME $BLACKLIST_PATH
                            unzip $BLACKLIST_PATH/$FILENAM
                        ;;
                        tgz)
                            tar xvf /tmp/$FILENAME -C $BLACKLIST_PATH/
                        ;; 
                        tar.gz)
                            tar xvf /tmp/$FILENAME -C $BLACKLIST_PATH/
                        ;;
                        esac 
                    break  
                fi
            done

        find_path

        mv $BLACKLIST_PATH/$BLACKLIST_DIR $BLACKLIST_PATH/categories

        BLACKLIST_INDEX="$BLACKLIST_PATH/blacklist.db"
        TMP_BLACKLIST_INDEX="/tmp/blacklist.db.$$"

        # JSONify
        echo -n "{" > $TMP_BLACKLIST_INDEX
        for blacklist in $CATEGORIES; do
            echo -n "\"$blacklist\": \"$blacklist\"" >> $TMP_BLACKLIST_INDEX
            echo -n "," >> $TMP_BLACKLIST_INDEX
        done
        echo -n "}" >> $TMP_BLACKLIST_INDEX
        # wrap-up
        cat $TMP_BLACKLIST_INDEX | sed 's/,}/}/g' > $BLACKLIST_INDEX
        updatedb
        rm -f $PID_FILE
    fi
}

check_running() {
    CHK_SGBUILD=$(pgrep -f "$SGUARD -c $SGCONF_TMP -b -C all")
    CHK_DB=$(find $BLACKLIST_PATH -name "domains.db")

    if [ -e /tmp/update-wcfdb.force ];then
        echo "running"
        exit 0
    elif [ ! -z "$CHK_SGBUILD" ];then
        echo "running"
        exit 0
    elif [ ! -z "$CHK_DB" ] && [ -z "$CHK_SGBUILD" ] ;then
        echo "done"
        rm -f $TMP_BLACKLIST_INDEX
        rm -f $PID_FILE
        rm -f /tmp/update-wcfdb.force
    fi
}
   
   check_running
if [ ! -z "$ARG" ];then
    download
else
    CATEG_URL=$(grep CategoriesURL $WEBFILTER_CONF | cut -d"=" -f2)
    if [ -z "$CATEG_URL" ];then 
        echo "done"
        exit 0
    fi 
    
    exit 0

fi

 

