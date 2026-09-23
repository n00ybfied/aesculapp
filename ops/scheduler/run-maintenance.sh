#!/usr/bin/env bash

# Run AesculApp maintenance on the demo VPS and the pharmacy test host.
# Install this file on the VPS; cron invokes one job per run.
set -uo pipefail
umask 077

PATH=/usr/local/bin:/usr/bin:/bin
export PATH

DEMO_ROOT=/var/www/html/aesculapp/server
PHARMACY_ROOT=/home/.sites/95/site1646665/web/aesculapp-api
PHARMACY_HOST=ftp1646665@www21.world4you.com
PHARMACY_KEY="${HOME}/.ssh/aesculapp_scheduler"
STATE_DIR="${HOME}/.local/state/aesculapp-scheduler"

usage() {
    printf 'Usage: %s {check|birthday|reminders|push}\n' "$0" >&2
    exit 2
}

log() {
    printf '%s %s\n' "$(date '+%Y-%m-%d %H:%M:%S %z')" "$*"
}

job="${1:-}"
case "$job" in
    check)
        command_name=''
        ;;
    birthday)
        command_name=app:award-birthday-bonuses
        ;;
    reminders)
        command_name=app:appointments:send-reminders
        ;;
    push)
        command_name=app:push:send
        ;;
    *)
        usage
        ;;
esac

if ! command -v flock >/dev/null 2>&1; then
    log 'ERROR: flock is required on the VPS.'
    exit 1
fi

ssh_options=(
    -T
    -i "$PHARMACY_KEY"
    -o BatchMode=yes
    -o IdentitiesOnly=yes
    -o StrictHostKeyChecking=yes
    -o ConnectTimeout=10
    -o ServerAliveInterval=15
    -o ServerAliveCountMax=2
    -o MACs=hmac-sha2-256
)

if [[ "$job" == check ]]; then
    if [[ -f "$DEMO_ROOT/bin/console" ]] && command -v php >/dev/null 2>&1; then
        log 'Demo: PHP and Symfony console found.'
    else
        log "ERROR: Demo console or PHP missing at $DEMO_ROOT"
        exit 1
    fi

    if [[ ! -r "$PHARMACY_KEY" ]]; then
        log "ERROR: SSH key not readable: $PHARMACY_KEY"
        exit 1
    fi

    if ssh "${ssh_options[@]}" "$PHARMACY_HOST" \
        "test -f '$PHARMACY_ROOT/bin/console' && command -v php84 >/dev/null"; then
        log 'Pharmacy: SSH, PHP 8.4 and Symfony console found.'
    else
        log 'ERROR: Pharmacy connection, PHP 8.4 or Symfony console unavailable.'
        exit 1
    fi
    exit 0
fi

mkdir -p -- "$STATE_DIR"
exec 9>"$STATE_DIR/$job.lock"
if ! flock -n 9; then
    log "SKIP: $job is already running."
    exit 0
fi

failed=0

log "START: $job on demo"
if (cd "$DEMO_ROOT" && APP_ENV=prod APP_DEBUG=0 php bin/console "$command_name" --no-interaction); then
    log "OK: $job on demo"
else
    status=$?
    log "ERROR: $job on demo (exit $status)"
    failed=1
fi

log "START: $job on pharmacy"
if [[ ! -r "$PHARMACY_KEY" ]]; then
    log "ERROR: SSH key not readable: $PHARMACY_KEY"
    failed=1
elif ssh "${ssh_options[@]}" "$PHARMACY_HOST" \
    "cd '$PHARMACY_ROOT' && APP_ENV=prod APP_DEBUG=0 php84 bin/console '$command_name' --no-interaction"; then
    log "OK: $job on pharmacy"
else
    status=$?
    log "ERROR: $job on pharmacy (exit $status)"
    failed=1
fi

exit "$failed"
