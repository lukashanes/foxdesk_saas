#!/usr/bin/env sh
set -eu
SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
. "$SCRIPT_DIR/_common.sh"

duration="${FOXDESK_DURATION_MINUTES:-30}"
summary="${FOXDESK_TIME_SUMMARY:-API quickstart work log}"
is_billable="${FOXDESK_IS_BILLABLE:-1}"

case "$duration" in
  ''|*[!0-9]*) echo "FOXDESK_DURATION_MINUTES must be an integer from 1 to 1440." >&2; exit 2 ;;
esac
if [ "$duration" -lt 1 ] || [ "$duration" -gt 1440 ]; then
  echo "FOXDESK_DURATION_MINUTES must be an integer from 1 to 1440." >&2
  exit 2
fi

payload="{"
payload="${payload}$(foxdesk_ticket_selector_json),"
payload="${payload}\"duration_minutes\":${duration},"
payload="${payload}\"summary\":\"$(foxdesk_json_escape "$summary")\","
payload="${payload}\"is_billable\":${is_billable}"
payload="${payload}}"

foxdesk_post_json "agent-log-time" "$payload"
