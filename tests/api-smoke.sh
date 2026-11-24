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
export APP_ENV=test STORAGE_DRIVER=json AURUM_PRIVATE_PATH="$TMP/private" AURUM_ADMIN_EMAILS="admin@example.test"
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
ADMIN_CSRF="$(curl -fsS "http://127.0.0.1:$PORT/api/trading.php" -c "$TMP/admin-cookies" | python3 -c 'import json,sys;print(json.load(sys.stdin)["csrf"])')"
ADMIN_REGISTER="$(curl -fsS "http://127.0.0.1:$PORT/api/trading.php" -b "$TMP/admin-cookies" -c "$TMP/admin-cookies" -H 'Content-Type: application/json' -H "X-CSRF-Token: $ADMIN_CSRF" --data '{"action":"register","email":"admin@example.test","password":"Aurum-Admin-Password-2026","size":5000}')"
python3 -c 'import json,sys;assert json.loads(sys.argv[1])["role"]=="admin"' "$ADMIN_REGISTER"
ADMIN_DATA="$(curl -fsS "http://127.0.0.1:$PORT/api/admin.php" -b "$TMP/admin-cookies")"
python3 -c 'import json,sys;d=json.loads(sys.argv[1]);assert d["counts"]["users"]==2;assert d["system"]["storageDriver"]=="json";assert d["rules"]["dailyLossPct"]==5.0' "$ADMIN_DATA"
RULES="$(python3 -c 'import json;print(json.dumps({"action":"update-rules","rules":{"accountSizes":[5000,10000,25000,50000,100000,200000],"phaseTargets":[6,4],"dailyLossPct":4,"maxLossPct":8,"minTradingDays":3,"leverage":20,"maxUnits":1000,"maxPending":50,"profitSplitPct":80}}))')"
curl -fsS "http://127.0.0.1:$PORT/api/admin.php" -b "$TMP/admin-cookies" -H 'Content-Type: application/json' -H "X-CSRF-Token: $(python3 -c 'import json,sys;print(json.loads(sys.argv[1])["csrf"])' "$ADMIN_REGISTER")" --data "$RULES" > "$TMP/rules.json"
python3 -c 'import json,sys;d=json.load(open(sys.argv[1]));assert d["rules"]["phaseTargets"]==[6.0,4.0]' "$TMP/rules.json"
echo "PHP API auth, CSRF, admin isolation/rules, and JSON persistence passed"

# Demo has its own session, no user row, and cannot access privileged APIs.
BASE="http://127.0.0.1:$PORT/api"
for n in 1 2; do
  TOKEN="$(curl -fsS "$BASE/trading.php" -c "$TMP/demo$n" | python3 -c 'import json,sys;print(json.load(sys.stdin)["csrf"])')"
  curl -fsS "$BASE/trading.php" -b "$TMP/demo$n" -c "$TMP/demo$n" -H 'Content-Type: application/json' -H "X-CSRF-Token: $TOKEN" --data '{"action":"start-demo"}' > "$TMP/demo$n.json"
  python3 -c 'import json,sys;d=json.load(open(sys.argv[1]));assert d["demo"] is True and d["role"]=="demo" and d["account"]["initial"]==10000000' "$TMP/demo$n.json"
done
DEMO_TOKEN="$(python3 -c 'import json,sys;print(json.load(open(sys.argv[1]))["csrf"])' "$TMP/demo1.json")"
curl -fsS "$BASE/trading.php" -b "$TMP/demo1" -H 'Content-Type: application/json' -H "X-CSRF-Token: $DEMO_TOKEN" --data '{"action":"open","side":"buy","units":1,"requestId":"demo-test-order-12345678"}' > "$TMP/demo-order.json"
python3 -c 'import json,sys;d=json.load(open(sys.argv[1]));assert len(d["account"]["positions"])==1' "$TMP/demo-order.json"
curl -fsS "$BASE/trading.php" -b "$TMP/demo2" | python3 -c 'import json,sys;assert not json.load(sys.stdin)["account"]["positions"]'
curl -fsS "$BASE/analytics.php" -b "$TMP/demo1" | python3 -c 'import json,sys;assert json.load(sys.stdin)["analytics"]["openPositions"]==1'
test "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/admin.php" -b "$TMP/demo1")" = 401
test "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/review.php" -b "$TMP/demo1")" = 401
for act in create-account request-simulated-payout; do
  test "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/trading.php" -b "$TMP/demo1" -H 'Content-Type: application/json' -H "X-CSRF-Token: $DEMO_TOKEN" --data "{\"action\":\"$act\"}")" = 403
done
test "$(find "$TMP/private/private-storage" -name 'user-*.json' | wc -l)" -eq 2
curl -fsS "$BASE/trading.php" -b "$TMP/demo1" -c "$TMP/demo1" -H 'Content-Type: application/json' -H "X-CSRF-Token: $DEMO_TOKEN" --data '{"action":"reset-demo"}' | python3 -c 'import json,sys;d=json.load(sys.stdin);assert not d["account"]["positions"] and d["account"]["balance"]==10000000'
echo "Demo trading, session isolation, reset and privileged endpoint restrictions passed"
