#!/usr/bin/env sh

#This file name is "dns_freedns.sh"
#So, here must be a method dns_freedns_add()
#Which will be called by acme.sh to add the txt record to your api system.
#returns 0 means success, otherwise error.
#
#Author: David Kerr
#Report Bugs here: https://github.com/dkerr64/acme.sh
#
########  Public functions #####################

# Export FreeDNS userid and password in folowing variables...
#  FREEDNS_User=username
#  FREEDNS_Password=password
# login cookie is saved in acme account config file so userid / pw
# need to be set only when changed.

#Usage: dns_freedns_add   _acme-challenge.www.domain.com   "XKrxpRBosdIKFzxW_CT3KLZNf6q0HG9i01zxXp5CPBs"
dns_freedns_add() {
  fulldomain="$1"
  txtvalue="$2"

  _info "Add TXT record using FreeDNS"
  _debug "fulldomain: $fulldomain"
  _debug "txtvalue: $txtvalue"

  if [ -z "$FREEDNS_User" ] || [ -z "$FREEDNS_Password" ]; then
    FREEDNS_User=""
    FREEDNS_Password=""
    if [ -z "$FREEDNS_COOKIE" ]; then
      _err "You did not specify the FreeDNS username and password yet."
      _err "Please export as FREEDNS_User / FREEDNS_Password and try again."
      return 1
    fi
    using_cached_cookies="true"
  else
    FREEDNS_COOKIE="$(_freedns_login "$FREEDNS_User" "$FREEDNS_Password")"
    if [ -z "$FREEDNS_COOKIE" ]; then
      return 1
    fi
    using_cached_cookies="false"
  fi

  _debug "FreeDNS login cookies: $FREEDNS_COOKIE (cached = $using_cached_cookies)"

  _saveaccountconf FREEDNS_COOKIE "$FREEDNS_COOKIE"

  # split our full domain name into two parts...
  i="$(echo "$fulldomain" | tr '.' ' ' | wc -w)"
  i="$(_math "$i" - 1)"
  top_domain="$(echo "$fulldomain" | cut -d. -f "$i"-100)"
  i="$(_math "$i" - 1)"
  sub_domain="$(echo "$fulldomain" | cut -d. -f -"$i")"

  # Sometimes FreeDNS does not reurn the subdomain page but rather 
  # returns a page regarding becoming a premium member.  This usually
  # happens after a period of inactivity.  Immediately trying again
  # returns the correct subdomain page.  So, we will try twice to
  # load the page and obtain our domain ID
  attempts=2
  while [ "$attempts" -gt "0" ]; do
    attempts="$(_math "$attempts" - 1)"

    htmlpage="$(_freedns_retrieve_subdomain_page "$FREEDNS_COOKIE")"
    if [ "$?" != "0" ]; then
      if [ "$using_cached_cookies" = "true" ]; then
        _err "Has your FreeDNS username and password channged?  If so..."
        _err "Please export as FREEDNS_User / FREEDNS_Password and try again."
      fi
      return 1
    fi

    # Now convert the tables in the HTML to CSV.  This litte gem from
    # http://stackoverflow.com/questions/1403087/how-can-i-convert-an-html-table-to-csv    
    subdomain_csv="$(echo "$htmlpage" \
      | grep -i -e '</\?TABLE\|</\?TD\|</\?TR\|</\?TH' \
      | sed 's/^[\ \t]*//g' \
      | tr -d '\n' \
      | sed 's/<\/TR[^>]*>/\n/Ig' \
      | sed 's/<\/\?\(TABLE\|TR\)[^>]*>//Ig' \
      | sed 's/^<T[DH][^>]*>\|<\/\?T[DH][^>]*>$//Ig' \
      | sed 's/<\/T[DH][^>]*><T[DH][^>]*>/,/Ig' \
      | grep 'edit.php?' \
      | grep "$top_domain")"
    # The above beauty ends with striping out rows that do not have an
    # href to edit.php and do not have the top domain we are looking for.
    # So all we should be left with is CSV of table of subdomains we are
    # interested in.

    # Now we have to read through this table and extract the data we need
    lines="$(echo "$subdomain_csv" | wc -l)"
    nl='
'
    i=0
    found=0
    while [ "$i" -lt "$lines" ]; do
      i="$(_math "$i" + 1)"
      line="$(echo "$subdomain_csv" | cut -d "$nl" -f "$i")"
      tmp="$(echo "$line" | cut -d ',' -f 1)"
      if [ $found = 0 ] && _startswith "$tmp" "<td>$top_domain"; then
        # this line will contain DNSdomainid for the top_domain
        DNSdomainid="$(echo "$line" | cut -d ',' -f 2 | sed 's/^.*domain_id=//;s/>.*//')"
        found=1
      else
        # lines contain DNS records for all subdomains
        DNSname="$(echo "$line" | cut -d ',' -f 2 | sed 's/^[^>]*>//;s/<\/a>.*//')"
        DNStype="$(echo "$line" | cut -d ',' -f 3)"
        if [ "$DNSname" = "$fulldomain" ] && [ "$DNStype" = "TXT" ]; then
          DNSdataid="$(echo "$line" | cut -d ',' -f 2 | sed 's/^.*data_id=//;s/>.*//')"
          # Now get current value for the TXT record.  This method may
          # not produce accurate results as the value field is truncated
          # on this webpage. To get full value we would need to load
          # another page. However we don't really need this so long as
          # there is only one TXT record for the acme chalenge subdomain.
          DNSvalue="$(echo "$line" | cut -d ',' -f 4 | sed 's/^[^&quot;]*&quot;//;s/&quot;.*//;s/<\/td>.*//')"
          if [ $found != 0 ]; then
            break
            # we are breaking out of the loop at the first match of DNS name
            # and DNS type (if we are past finding the domainid). This assumes
            # that there is only ever one TXT record for the LetsEncrypt/acme
            # challenge subdomain.  This seems to be a reasonable assumption
            # as the acme client deletes the TXT record on successful validation.
          fi
        else
          DNSname=""
          DNStype=""
        fi
      fi
    done

    _debug "DNSname: $DNSname DNStype: $DNStype DNSdomainid: $DNSdomainid DNSdataid: $DNSdataid"
    _debug "DNSvalue: $DNSvalue"

    if [ -z "$DNSdomainid" ]; then
      # If domain ID is empty then something went wrong (top level
      # domain not found at FreeDNS).
      if [ "$attempts" = "0" ]; then
        # exhausted maximum retry attempts
        _debug "$htmlpage"
        _debug "$subdomain_csv"
        _err "Domain $top_domain not found at FreeDNS"
        return 1
      fi
    else
      # break out of the 'retry' loop... we have found our domain ID
      break
    fi
    _info "Domain $top_domain not found at FreeDNS"
    _info "Retry loading subdomain page ($attempts attempts remaining)"
  done

  if [ -z "$DNSdataid" ]; then
    # If data ID is empty then specific subdomain does not exist yet, need
    # to create it this should always be the case as the acme client
    # deletes the entry after domain is validated.
    _freedns_add_txt_record "$FREEDNS_COOKIE" "$DNSdomainid" "$sub_domain" "$txtvalue"
    return $?
  else
    if [ "$txtvalue" = "$DNSvalue" ]; then
      # if value in TXT record matches value requested then DNS record
      # does not need to be updated. But...
      # Testing value match fails.  Website is truncating the value field.
      # So for now we will always go down the else path.  Though in theory
      # should never come here anyway as the acme client deletes
      # the TXT record on successful validation, so we should not even
      # have found a TXT record !!
      _info "No update necessary for $fulldomain at FreeDNS"
      return 0
    else
      # Delete the old TXT record (with the wrong value)
      _freedns_delete_txt_record "$FREEDNS_COOKIE" "$DNSdataid"
      if [ "$?" = "0" ]; then
        # And add in new TXT record with the value provided
        _freedns_add_txt_record "$FREEDNS_COOKIE" "$DNSdomainid" "$sub_domain" "$txtvalue"
      fi
      return $?
    fi
  fi
  return 0
}

#Usage: fulldomain txtvalue
#Remove the txt record after validation.
dns_freedns_rm() {
  fulldomain="$1"
  txtvalue="$2"

  _info "Delete TXT record using FreeDNS"
  _debug "fulldomain: $fulldomain"
  _debug "txtvalue: $txtvalue"

  # Need to read cookie from conf file again in case new value set
  # during login to FreeDNS when TXT record was created.
  # acme.sh does not have a _readaccountconf() fuction
  FREEDNS_COOKIE="$(_read_conf "$ACCOUNT_CONF_PATH" "FREEDNS_COOKIE")"
  _debug "FreeDNS login cookies: $FREEDNS_COOKIE"

  # Sometimes FreeDNS does not reurn the subdomain page but rather 
  # returns a page regarding becoming a premium member.  This usually
  # happens after a period of inactivity.  Immediately trying again
  # returns the correct subdomain page.  So, we will try twice to
  # load the page and obtain our TXT record.
  attempts=2
  while [ "$attempts" -gt "0" ]; do
    attempts="$(_math "$attempts" - 1)"

    htmlpage="$(_freedns_retrieve_subdomain_page "$FREEDNS_COOKIE")"
    if [ "$?" != "0" ]; then
      return 1
    fi

    # Now convert the tables in the HTML to CSV.  This litte gem from
    # http://stackoverflow.com/questions/1403087/how-can-i-convert-an-html-table-to-csv
    subdomain_csv="$(echo "$htmlpage" \
      | grep -i -e '</\?TABLE\|</\?TD\|</\?TR\|</\?TH' \
      | sed 's/^[\ \t]*//g' \
      | tr -d '\n' \
      | sed 's/<\/TR[^>]*>/\n/Ig' \
      | sed 's/<\/\?\(TABLE\|TR\)[^>]*>//Ig' \
      | sed 's/^<T[DH][^>]*>\|<\/\?T[DH][^>]*>$//Ig' \
      | sed 's/<\/T[DH][^>]*><T[DH][^>]*>/,/Ig' \
      | grep 'edit.php?' \
      | grep "$fulldomain")"
    # The above beauty ends with striping out rows that do not have an
    # href to edit.php and do not have the domain name we are looking for.
    # So all we should be left with is CSV of table of subdomains we are
    # interested in.

    # Now we have to read through this table and extract the data we need
    lines="$(echo "$subdomain_csv" | wc -l)"
    nl='
'
    i=0
    found=0
    while [ "$i" -lt "$lines" ]; do
      i="$(_math "$i" + 1)"
      line="$(echo "$subdomain_csv" | cut -d "$nl" -f "$i")"
      DNSname="$(echo "$line" | cut -d ',' -f 2 | sed 's/^[^>]*>//;s/<\/a>.*//')"
      DNStype="$(echo "$line" | cut -d ',' -f 3)"
      if [ "$DNSname" = "$fulldomain" ] && [ "$DNStype" = "TXT" ]; then
        DNSdataid="$(echo "$line" | cut -d ',' -f 2 | sed 's/^.*data_id=//;s/>.*//')"
        DNSvalue="$(echo "$line" | cut -d ',' -f 4 | sed 's/^[^&quot;]*&quot;//;s/&quot;.*//;s/<\/td>.*//')"
        _debug "DNSvalue: $DNSvalue"
        #     if [ "$DNSvalue" = "$txtvalue" ]; then
        # Testing value match fails.  Website is truncating the value
        # field. So for now we will assume that there is only one TXT
        # field for the sub domain and just delete it. Currently this
        # is a safe assumption.
        _freedns_delete_txt_record "$FREEDNS_COOKIE" "$DNSdataid"
        return $?
        #     fi
      fi
    done
  done

  # If we get this far we did not find a match (after two attempts)
  # Not necessarily an error, but log anyway.
  _debug2 "$subdomain_csv"
  _info "Cannot delete TXT record for $fulldomain/$txtvalue. Does not exist at FreeDNS"
  return 0
}

####################  Private functions below ##################################

# usage: _freedns_login username password
# print string "cookie=value" etc.
# returns 0 success
_freedns_login() {
  export _H1="Accept-Language:en-US"
  username="$1"
  password="$2"
  url="https://freedns.afraid.org/zc.php?step=2"

  _debug "Login to FreeDNS as user $username"

  htmlpage="$(_post "username=$(printf '%s' "$username" | _url_encode)&password=$(printf '%s' "$password" | _url_encode)&submit=Login&action=auth" "$url")"

  if [ "$?" != "0" ]; then
    _err "FreeDNS login failed for user $username bad RC from _post"
    return 1
  fi

  cookies="$(grep -i '^Set-Cookie.*dns_cookie.*$' "$HTTP_HEADER" | _head_n 1 | tr -d "\r\n" | cut -d " " -f 2)"

  # if cookies is not empty then logon successful
  if [ -z "$cookies" ]; then
    _debug "$htmlpage"
    _err "FreeDNS login failed for user $username. Check $HTTP_HEADER file"
    return 1
  fi

  printf "%s" "$cookies"
  return 0
}

# usage _freedns_retrieve_subdomain_page login_cookies
# echo page retrieved (html)
# returns 0 success
_freedns_retrieve_subdomain_page() {
  export _H1="Cookie:$1"
  export _H2="Accept-Language:en-US"
  url="https://freedns.afraid.org/subdomain/"

  _debug "Retrieve subdmoain page from FreeDNS"

  htmlpage="$(_get "$url")"

  if [ "$?" != "0" ]; then
    _err "FreeDNS retrieve subdomins failed bad RC from _get"
    return 1
  elif [ -z "$htmlpage" ]; then
    _err "FreeDNS returned empty subdomain page"
    return 1
  fi

  _debug2 "$htmlpage"

  printf "%s" "$htmlpage"
  return 0
}

# usage _freedns_add_txt_record login_cookies domain_id subdomain value
# returns 0 success
_freedns_add_txt_record() {
  export _H1="Cookie:$1"
  export _H2="Accept-Language:en-US"
  domain_id="$2"
  subdomain="$3"
  value="$(printf '%s' "$4" | _url_encode)"
  url="http://freedns.afraid.org/subdomain/save.php?step=2"

  htmlpage="$(_post "type=TXT&domain_id=$domain_id&subdomain=$subdomain&address=%22$value%22&send=Save%21" "$url")"

  if [ "$?" != "0" ]; then
    _err "FreeDNS failed to add TXT record for $subdomain bad RC from _post"
    return 1
  elif ! grep "200 OK" "$HTTP_HEADER" >/dev/null; then
    _debug "$htmlpage"
    _err "FreeDNS failed to add TXT record for $subdomain. Check $HTTP_HEADER file"
    return 1
  elif _contains "$htmlpage" "security code was incorrect"; then
    _debug "$htmlpage"
    _err "FreeDNS failed to add TXT record for $subdomain as FreeDNS requested seurity code"
    _err "Note that you cannot use automatic DNS validation for FreeDNS public domains"
    return 1
  fi

  _debug2 "$htmlpage"
  _info "Added acme challenge TXT record for $fulldomain at FreeDNS"
  return 0
}

# usage _freedns_delete_txt_record login_cookies data_id
# returns 0 success
_freedns_delete_txt_record() {
  export _H1="Cookie:$1"
  export _H2="Accept-Language:en-US"
  data_id="$2"
  url="https://freedns.afraid.org/subdomain/delete2.php"

  htmlheader="$(_get "$url?data_id%5B%5D=$data_id&submit=delete+selected" "onlyheader")"

  if [ "$?" != "0" ]; then
    _err "FreeDNS failed to delete TXT record for $data_id bad RC from _get"
    return 1
  elif ! _contains "$htmlheader" "200 OK"; then
    _debug "$htmlheader"
    _err "FreeDNS failed to delete TXT record $data_id"
    return 1
  fi

  _info "Deleted acme challenge TXT record for $fulldomain at FreeDNS"
  return 0
}
