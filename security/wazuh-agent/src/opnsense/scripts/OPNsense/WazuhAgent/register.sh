#!/bin/sh
#     Copyright (c) 2018-2023 Cloudfence - Julio Camargo
#    All rights reserved.
#
#    Redistribution and use in source and binary forms, with or without
#    modification, are permitted provided that the following conditions are met:
#
#    1. Redistributions of source code must retain the above copyright notice,
#     this list of conditions and the following disclaimer.
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
xmllint=$(which xmllint)
config_xml="/conf/config.xml"
wazuh_key=$($xmllint --nocdata --xpath "//wazuhagent//general//key" $config_xml | cut -d">" -f2 | cut -d"<" -f1)
wazuh_path="/var/ossec"

# log function
log(){
    message=$1
    logger -t "Wazuh Agent" "$message"
    #echo "$message"
}

check(){
    # check for wazuh agent registration status
    if [ -f "${wazuh_path}/var/run/wazuh-agentd.state" ];then
        wazuh_status=$(grep "status=" ${wazuh_path}/var/run/wazuh-agentd.state | cut -d= -f2 | tr -dc '[:alnum:]')
    elif [ -f "${wazuh_path}/var/run/wazuh-agentd.state.temp" ];then
        wazuh_status=$(grep "status=" ${wazuh_path}/var/run/wazuh-agentd.state.temp | cut -d= -f2 | tr -dc '[:alnum:]')
    else
        wazuh_status="pending"
    fi
    
    # return status
    echo -n "wazuh-agent is $wazuh_status"
    exit 0
}

#register agent
register(){
    #Check if it is a base64 (client.keys) format
    base64_chk=$(echo "$wazuh_key" | b64decode -rm | awk '{print $1}' | grep -E "^[0-9]")
    #Check if it is a base64 (client.keys) format
    if [ ! -z "$base64_chk" ];then
        if [ ! -e ${wazuh_path}/etc/client.keys ];then
            log "Wazuh Agent not configured, inserting client key"
            echo y | ${wazuh_path}/bin/manage_agents -i "$wazuh_key"
        else
            # check if the key differs 
            key_chk=$(grep "$wazuh_key" ${wazuh_path}/etc/client.keys)
            if [ -z "$key_chk" ];then
                log "Wazuh Agent not configured, inserting client key"
                echo y | ${wazuh_path}/bin/manage_agents -i "$wazuh_key"
            fi
        fi
    #Authentication Key (Password based)
    else
        log "Not registered, using password authentication method"
        echo "$wazuh_key" > ${wazuh_path}/etc/authd.pass
        chmod 640 ${wazuh_path}/etc/authd.pass
        chown root:wazuh ${wazuh_path}/etc/authd.pass
        # remove file not need
        if [ -f ${wazuh_path}/etc/client.keys ];then
            rm -f ${wazuh_path}/etc/client.keys
        fi
    fi
    # restart agent
    ${wazuh_path}/bin/wazuh-control restart

}


#main - call function by arg
$1