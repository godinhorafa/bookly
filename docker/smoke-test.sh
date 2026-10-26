#!/usr/bin/env bash
# Checks the reviewer path end to end against `docker compose up`:
# every service answers, the demo account works through the dashboard's proxy,
# and a booking reaches the realtime service through the outbox and a webhook.
set -euo pipefail

wait_for() {
  for _ in $(seq 1 120); do
    if curl -fsS "$1" > /dev/null 2>&1; then echo "up: $1"; return 0; fi
    sleep 5
  done
  echo "never came up: $1" >&2
  return 1
}

wait_for http://localhost:8000/up
wait_for http://localhost:3000/health
wait_for http://localhost:5173

# Prints the body, or the status and body on an HTTP error so CI logs say what went wrong.
api() {
  local response status
  response=$(curl -sS -w '\n%{http_code}' -H 'Accept: application/json' -H 'Content-Type: application/json' "$@")
  status=${response##*$'\n'}
  if [ "$status" -ge 400 ]; then
    echo "HTTP ${status} from $*: ${response%$'\n'*}" >&2
    docker compose logs --tail=30 app >&2 || true
    return 1
  fi
  printf '%s' "${response%$'\n'*}"
}

token=$(api -X POST http://localhost:5173/api/tokens \
  -d '{"email":"demo@bookly.test","password":"password","device_name":"smoke-test"}' | jq -r .token)
api http://localhost:5173/api/spaces -H "Authorization: Bearer $token" | jq -e '.data | length == 3' > /dev/null
echo "ok: logged in through the dashboard proxy and listed the seeded spaces"

# 15:00 two days ahead in São Paulo (UTC-3, no DST): never peak time, always bookable.
day=$(TZ=America/Sao_Paulo date -d '+2 days' +%F)
booking=$(api -X POST http://localhost:5173/api/bookings \
  -H "Authorization: Bearer $token" -H "Idempotency-Key: smoke-$(date +%s)" \
  -d "{\"space_id\":\"0b9e7e4c-2f4b-4c1a-9d3e-7a1b2c3d4e02\",\"start\":\"${day}T15:00:00-03:00\",\"end\":\"${day}T16:00:00-03:00\"}" | jq -r .data.id)
echo "ok: booked ${booking}"

for _ in $(seq 1 30); do
  if docker compose logs realtime 2>/dev/null | grep -q "Pushed booking.requested for booking ${booking}"; then
    echo "ok: the realtime service pushed the booking to the dashboards"
    exit 0
  fi
  sleep 1
done

echo "the booking never reached the realtime service" >&2
docker compose logs relay realtime >&2
exit 1
