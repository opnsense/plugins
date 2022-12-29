#!/bin/sh
# Proxy for smartctl and configctl parameter issue
# We added single quotes for inseparable device string

# Parse all parameter from the actions.d file and add them to A, B or C.
case $1 in
    -l) A="-l"
        shift 
        A="$A $1"
        shift 
        ;;
    --json=c)  B="--json=c"
        shift
        ;;
    -t) A="-t"
        shift 
        A="$A $1"
        shift
        ;;
    -X) A="-X"
        shift
        ;; 
    -i|-H|-a|-c|-A) A=$1
        shift
        ;; 
    *)
      ;; 
esac

# remove the single quotes for regular paramter strings
C=$(echo $1 | /usr/bin/tr -d "'")
# execute the command
/usr/local/sbin/smartctl $A $B $C