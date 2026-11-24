#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TMP="$(mktemp -d)"
PORT="$(python3 - <<'PY'
import socket
s=socket.socket();s.bind(('127.0.0.1',0));print(s.getsockname()[1]);s.close()
PY
)"
mkdir -p "$TMP/private"
cp -R "$ROOT/backend" "$TMP/private/"
export APP_ENV=test STORAGE_DRIVER=json AURUM_PRIVATE_PATH="$TMP/private" AURUM_ADMIN_EMAILS=""
php -S "127.0.0.1:$PORT" -t "$ROOT/public_html" >"$TMP/php.log" 2>&1 &
PID=$!
cleanup(){ kill "$PID" 2>/dev/null || true; wait "$PID" 2>/dev/null || true; rm -rf "$TMP"; }
trap cleanup EXIT
for i in $(seq 1 40); do
  if curl -fsS "http://127.0.0.1:$PORT/api/trading.php" -c "$TMP/cookies" -o "$TMP/initial.json"; then break; fi
  sleep .25
done
CSRF="$(python3 -c 'import json,sys;print(json.load(open(sys.argv[1]))["csrf"])' "$TMP/initial.json")"
EMAIL="ci-$(date +%s)-$$@example.test"
REGISTER="$(curl -fsS "http://127.0.0.1:$PORT/api/trading.php" -b "$TMP/cookies" -c "$TMP/cookies" -H 'Content-Type: application/json' -H "X-CSRF-Token: $CSRF" --data "$(python3 -c 'import json,sys;print(json.dumps({"action":"register","email":sys.argv[1],"password":"Aurum-CI-Password-2026","size":5000}))' "$EMAIL")")"
python3 -c 'import json,sys;d=json.loads(sys.argv[1]);assert d["authenticated"] is True;assert d["role"]=="trader";assert d["csrf"]' "$REGISTER"
BAD="$(curl -sS -o "$TMP/bad.json" -w '%{http_code}' "http://127.0.0.1:$PORT/api/trading.php" -b "$TMP/cookies" -H 'Content-Type: application/json' -H 'X-CSRF-Token: wrong' --data '{"action":"open"}')"
test "$BAD" = "403"
ADMIN="$(curl -sS -o "$TMP/admin.json" -w '%{http_code}' "http://127.0.0.1:$PORT/api/admin.php" -b "$TMP/cookies")"
test "$ADMIN" = "403"
USER_HASH="$(python3 -c 'import hashlib,sys;print(hashlib.sha256(sys.argv[1].encode()).hexdigest())' "$EMAIL")"
python3 -c 'import json,sys;d=json.load(open(sys.argv[1]));assert d["user"]["email"]==sys.argv[2];assert d["account"]["initial"]==500000;assert d["account"]["rules"]["phaseTargets"]==[8.0,5.0]' "$TMP/private/private-storage/user-$USER_HASH.json" "$EMAIL"
STATE="$(curl -fsS "http://127.0.0.1:$PORT/api/trading.php" -b "$TMP/cookies")"
python3 -c 'import json,sys;d=json.loads(sys.argv[1]);assert d["account"]["initial"]==500000;assert d["feed"]=="offline"' "$STATE"
echo "PHP API registration, CSRF, admin isolation and JSON persistence passed"
