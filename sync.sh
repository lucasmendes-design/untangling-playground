#!/bin/bash
# Sync the latest prototype plugin into the public playground repo and push.
# The shared Playground link picks the new version up automatically
# (raw.githubusercontent.com caches for ~5 minutes).
set -euo pipefail
cd "$(dirname "$0")"

cp ~/AI/A8C/untangling-prototype/untangling-prototype.php .

if git diff --quiet -- untangling-prototype.php; then
	echo "Already in sync — nothing to push."
	exit 0
fi

git add untangling-prototype.php
git commit -m "Sync prototype plugin ($(date +%Y-%m-%d))"
GH_HOST=github.com git push origin main
echo "Pushed. The Playground link serves the new version within ~5 minutes."
