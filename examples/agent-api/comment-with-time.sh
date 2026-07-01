#!/usr/bin/env sh
set -eu
SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
. "$SCRIPT_DIR/_common.sh"

comment="${FOXDESK_COMMENT:-<p>Work update from the Agent API quickstart.</p>}"
is_internal="${FOXDESK_INTERNAL_COMMENT:-0}"
skip_notification="${FOXDESK_SKIP_NOTIFICATION:-1}"
is_billable="${FOXDESK_IS_BILLABLE:-1}"

if [ -n "${FOXDESK_STARTED_AT:-}" ] || [ -n "${FOXDESK_ENDED_AT:-}" ]; then
  : "${FOXDESK_STARTED_AT:?Set FOXDESK_STARTED_AT when using FOXDESK_ENDED_AT}"
  : "${FOXDESK_ENDED_AT:?Set FOXDESK_ENDED_AT when using FOXDESK_STARTED_AT}"
  started_json="\"started_at\":\"$(foxdesk_json_escape "$FOXDESK_STARTED_AT")\","
  ended_json="\"ended_at\":\"$(foxdesk_json_escape "$FOXDESK_ENDED_AT")\","
  manual_json=""
else
  : "${FOXDESK_MANUAL_DATE:?Set FOXDESK_MANUAL_DATE or FOXDESK_STARTED_AT}"
  : "${FOXDESK_MANUAL_START_TIME:?Set FOXDESK_MANUAL_START_TIME or FOXDESK_STARTED_AT}"
  : "${FOXDESK_MANUAL_END_TIME:?Set FOXDESK_MANUAL_END_TIME or FOXDESK_ENDED_AT}"
  started_json=""
  ended_json=""
  manual_json="\"manual_date\":\"$(foxdesk_json_escape "$FOXDESK_MANUAL_DATE")\","
  manual_json="${manual_json}\"manual_start_time\":\"$(foxdesk_json_escape "$FOXDESK_MANUAL_START_TIME")\","
  manual_json="${manual_json}\"manual_end_time\":\"$(foxdesk_json_escape "$FOXDESK_MANUAL_END_TIME")\","
fi

: "${FOXDESK_DURATION_MINUTES:?Set FOXDESK_DURATION_MINUTES}"

payload="{"
payload="${payload}$(foxdesk_ticket_selector_json),"
payload="${payload}\"content\":\"$(foxdesk_json_escape "$comment")\","
payload="${payload}\"is_internal\":${is_internal},"
payload="${payload}\"skip_notification\":${skip_notification},"
payload="${payload}${started_json}${ended_json}${manual_json}"
payload="${payload}\"duration_minutes\":${FOXDESK_DURATION_MINUTES},"
payload="${payload}\"is_billable\":${is_billable}"
payload="${payload}}"

foxdesk_post_json "app-add-comment-with-time" "$payload"
