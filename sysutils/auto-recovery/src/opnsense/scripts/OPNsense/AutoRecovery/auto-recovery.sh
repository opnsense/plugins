#!/bin/sh

# Copyright (c) 2023 Frank Wall
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions
# are met:
#
# 1. Redistributions of source code must retain the above copyright
#    notice, this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in the
#    documentation and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
# ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
# IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
# ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
# FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
# DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
# OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
# HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
# LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
# OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
# SUCH DAMAGE.

# Configuration variables.
BASE_DIR='/var/etc/auto-recovery'
ABORT_FILE="${BASE_DIR}/autorecover.abort"
CONFIG_FILE='/conf/config.xml'
CONFIG_BACKUP_FILE="${BASE_DIR}/config.xml_recover"
CONFIG_ORIGINAL_FILE="${BASE_DIR}/config.xml_orig"
COUNTDOWN=0
SCRIPT_NAME='AutoRecovery'
START_TIME="`date +%s`"
STATE_FILE="${BASE_DIR}/countdown.state"
VERBOSE=0

# Possible recovery actions.
DO_RESTORE=0
DO_REBOOT=0
DO_RELOAD=0
DO_CONFIGD=0

# Recovery commands.
RESTORE_CMD='configctl template reload \*'
REBOOT_CMD='configctl system reboot'
RELOAD_CMD='configctl service reload all'
CONFIGD_CMD='configctl'

# Print usage information.
usage() {
  echo "usage: `basename $0` COUNTDOWN_IN_SECONDS" 
}

# Print fatal errors on STDERR and exit.
fatal() {
  1>&2 echo "[ERROR] $@"
  syslog $@
  cleanup
  exit 1
}

# Print errors on STDERR.
error() {
  1>&2 echo "[ERROR] $@"
  syslog $@
}

# Print message on STDOUT.
log() {
  1>&2 echo "$@"
  syslog $@
}

# Print debug messages.
verbose() {
  [ "$VERBOSE" = "1" ] && echo $@
}

# Send message to syslog.
syslog() {
  logger "${SCRIPT_NAME}: $@"
}

# Remove state information.
cleanup() {
  rm -f $ABORT_FILE $STATE_FILE
}

# Check args.
if [ "$#" -lt "1" ]; then
  usage
  exit 1
fi

# Get user input.
while [ "$1" != "" ]; do
  case "$1" in
    --action*)
      RECOVERY_ACTION="`echo $1 | cut -d= -f2`"
      shift
      ;;
    --configd*)
      _tmp_cmd="${CONFIGD_CMD} `echo $1 | cut -d= -f2`"
      # Replace colons with spaces to restore correct configd command.
      CONFIGD_CMD="`echo ${_tmp_cmd} | sed -e 's/:/ /'`"
      shift
      ;;
    -v*)
      VERBOSE=1
      shift
      ;;
    *)
      COUNTDOWN=$1
      shift
      ;;
  esac
done

# Enable selected recovery actions.
case "$RECOVERY_ACTION" in
  restore_reboot)
    DO_RESTORE=1
    DO_REBOOT=1
    ;;
  restore_reload)
    DO_RESTORE=1
    DO_RELOAD=1
    ;;
  restore_configd)
    DO_RESTORE=1
    DO_CONFIGD=1
    ;;
  restore)
    DO_RESTORE=1
    ;;
  reboot)
    DO_REBOOT=1
    ;;
  configd)
    DO_CONFIGD=1
    ;;
  noop)
    log "test mode enabled"
    ;;
  *)
    usage
    exit 1
    ;;
esac

# Verify args
if [ "${COUNTDOWN}" -le 1 ]; then
  usage
  exit 1
fi

mkdir -p $BASE_DIR || fatal "Unable to create ${BASE_DIR}"

# Save the current configuration.
if [ "${DO_RESTORE}" == "1" ]; then
  cp $CONFIG_FILE $CONFIG_BACKUP_FILE
  if [ "$?" -gt "0" ]; then
    fatal "Unable to create a backup of the config file"
  fi
fi

# Calculate target time.
TARGET_TIME="`expr $START_TIME + $COUNTDOWN`"

# Start countdown.
echo $TARGET_TIME > $STATE_FILE
if [ "$?" -gt "0" ]; then
  fatal "Unable to create state file"
fi
log "Countdown initiated: ${COUNTDOWN} seconds remaining"

# Main loop
while [ 1 ]; do
  # Check if the countdown must be aborted.
  if [ -f "$ABORT_FILE" ]; then
    verbose "Found file: $ABORT_FILE"
    log "Countdown aborted on user request"
    cleanup
    exit 0
  fi

  # Check if target time is reached.
  cur_time="`date +%s`"
  if [ "$cur_time" -ge "$TARGET_TIME" ]; then
    log "Performing system recovery... (${RECOVERY_ACTION})"

    # Restore configuration backup.
    if [ "${DO_RESTORE}" == "1" ]; then
      log "Restoring configuration backup"

      # Perform an additional backup of the currently running configuration.
      # Just for extra safekeeping if the recovery process goes horribly wrong.
      cp $CONFIG_FILE $CONFIG_ORIGINAL_FILE

      # Restore the backup configuration.
      cp $CONFIG_BACKUP_FILE $CONFIG_FILE
      if [ "$?" -gt "0" ]; then
        fatal "Unable to restore config file"
      fi

      # Reload all templates.
      eval $RESTORE_CMD
      if [ "$?" -gt "0" ]; then
        error "Reload templates command exited with non-zero exit code"
      fi
    fi

    # Restart all services.
    if [ "${DO_RELOAD}" == "1" ]; then
      log "Restarting all services"
      eval $RELOAD_CMD
      if [ "$?" -gt "0" ]; then
        error "Restart services command exited with non-zero exit code"
      fi
    fi

    # Run configd command.
    if [ "${DO_CONFIGD}" == "1" ]; then
      log "Running system command: ${CONFIGD_CMD}"
      eval $CONFIGD_CMD
      if [ "$?" -gt "0" ]; then
        error "System command exited with non-zero exit code"
      fi
    fi

    # Reboot the system.
    if [ "${DO_REBOOT}" == "1" ]; then
      # Need to cleanup before issuing the reboot command, otherwise
      # the state file would not get removed.
      cleanup
      log "Rebooting the system"
      eval $REBOOT_CMD
      if [ "$?" -gt "0" ]; then
        error "Reboot command exited with non-zero exit code"
      fi
    fi

    cleanup
    exit 0
  fi

  # Print status information.
  remaining_time="`expr $TARGET_TIME - $cur_time`"
  verbose "Countdown: $remaining_time seconds"
  sleep 1
done

exit 0
