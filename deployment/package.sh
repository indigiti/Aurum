#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
rm -rf dist release
mkdir -p release/public release/private/build dist/aurum/public_html dist/aurum-private
test -f frontend/out/index.html
cp -R frontend/out/. release/public/
cp -R public_html/. release/public/
cp -R backend database release/private/
php -r '
$sha=getenv("GITHUB_SHA")?:trim((string)shell_exec("git rev-parse HEAD"));
$manifest=["schema"=>"DIGIOPS-BUILD/1","name"=>"Aurum","version"=>"0.1.0","builtAt"=>gmdate(DATE_ATOM),"sourceSha"=>$sha,"branch"=>getenv("GITHUB_REF_NAME")?:"local","ciRunId"=>getenv("GITHUB_RUN_ID")?:"","ciRunNumber"=>getenv("GITHUB_RUN_NUMBER")?:"","ciRunAttempt"=>getenv("GITHUB_RUN_ATTEMPT")?:"","public"=>"public","private"=>"private"];
$data=json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
file_put_contents("release/RELEASE.json",$data);
file_put_contents("release/private/build/release.json",$data);
'
# Keep manual deployment ZIP available, but DigiOps consumes release/ directly.
cp -R release/public/. dist/aurum/public_html/
cp -R release/private/. dist/aurum-private/
cp deployment/INSTALL.md dist/
(cd dist && zip -qr aurum-cloudways.zip aurum aurum-private INSTALL.md && sha256sum aurum-cloudways.zip > aurum-cloudways.zip.sha256)
bash deployment/check-release.sh
