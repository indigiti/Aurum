#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
rm -rf dist
mkdir -p dist/aurum/public_html
cp -R frontend/out/. dist/aurum/public_html/
cp -R public_html/. dist/aurum/public_html/
cp -R backend database dist/aurum/
cp deployment/INSTALL.md dist/aurum/
(cd dist && zip -qr aurum-cloudways.zip aurum && sha256sum aurum-cloudways.zip > aurum-cloudways.zip.sha256)
