#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
for f in release/public/index.html release/public/.htaccess release/public/api/.htaccess release/public/api/_bootstrap.php release/public/api/health.php release/public/api/trading.php release/private/backend/bootstrap.php release/private/database/schema.sql release/RELEASE.json release/private/build/release.json; do
  test -f "$f" || { echo "Missing release entry: $f" >&2; exit 1; }
done
test -f release/private/backend/Domain/Engine.php
test -f release/private/backend/Application/TradeService.php
test -f release/private/backend/Infrastructure/JsonStorage.php
test -f release/private/backend/Http/trading.php
test ! -e release/public/backend
test ! -e release/public/database
test ! -e release/private/config/local.php
test ! -e release/private/private-storage
test -z "$(find release -type l -print)"
cmp release/RELEASE.json release/private/build/release.json
grep -q '/aurum/_next/' release/public/index.html
php -r '$m=json_decode(file_get_contents("release/RELEASE.json"),true,16,JSON_THROW_ON_ERROR); if($m["schema"]!=="DIGIOPS-BUILD/1" || $m["name"]!=="Aurum" || $m["public"]!=="public" || $m["private"]!=="private" || !preg_match("/^[a-f0-9]{40}$/D",$m["sourceSha"]) || (getenv("GITHUB_SHA") && $m["sourceSha"]!==getenv("GITHUB_SHA")))exit(1);'
echo "DigiOps release contract verified"
