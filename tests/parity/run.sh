#!/usr/bin/env bash
# Compares the output of this library with @fylgja/fonts-provider, the
# Node version, on the same cases and recorded HTTP responses.
#
# Usage: tests/parity/run.sh [path to a js-fonts-provider checkout]
# Without a path it uses the version installed in tests/parity/node_modules.
set -euo pipefail

here="$(cd "$(dirname "$0")" && pwd)"
node_out="$(mktemp)"
php_out="$(mktemp)"
trap 'rm -f "$node_out" "$php_out"' EXIT

node "$here/run.mjs" "$@" > "$node_out"
php "$here/run.php" > "$php_out"

if diff -u --label node --label php "$node_out" "$php_out"; then
	echo "Parity OK: $(grep -c '^### ' "$node_out") cases match."
else
	exit 1
fi
