#!/usr/bin/env bash
# Gera o .zip de distribuição a partir do commit atual.
# Arquivos marcados com export-ignore no .gitattributes ficam de fora.
set -euo pipefail
cd "$(dirname "$0")/.."
VERSION="$(grep -m1 "define( 'JPXCC_VERSION'" jpx-cookies-lgpd.php | sed -E "s/.*'([0-9.]+)'.*/\1/")"
mkdir -p dist
git archive --format=zip --prefix=jpx-cookies-lgpd/ -o "dist/jpx-cookies-lgpd-$VERSION.zip" HEAD
echo "dist/jpx-cookies-lgpd-$VERSION.zip"
