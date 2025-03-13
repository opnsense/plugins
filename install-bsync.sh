#! /usr/bin/env bash

for package in "rsync"; do
    if [[ ! $(which "$package") ]] ; then
        echo "Installing $package..."
        if [[ $(which dnf) ]] ; then
            dnf install -y "$package"
        elif [[ $(which pkg) ]] ; then
            pkg install -y "$package"
        else
            echo "Neither dnf nor pkg not found"
            exit 1
        fi
    fi
done

out_file="/usr/local/bin/bsync"
uri="https://raw.githubusercontent.com/dooblem/bsync/refs/heads/master/bsync"
curl -fs "$uri" -o "$out_file"
chmod +x "$out_file"
