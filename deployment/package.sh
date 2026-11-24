#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
rm -rf dist
mkdir -p dist/aurum/public_html dist/aurum-private
cp -R frontend/out/. dist/aurum/public_html/
cp -R public_html/. dist/aurum/public_html/
cp -R backend database dist/aurum-private/
cp deployment/INSTALL.md dist/
(cd dist && zip -qr aurum-cloudways.zip aurum aurum-private INSTALL.md && sha256sum aurum-cloudways.zip > aurum-cloudways.zip.sha256)
