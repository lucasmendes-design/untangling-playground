#!/bin/bash
# Sync the latest prototype plugin into the public playground repo and push.
# The shared Playground links pick the new version up automatically
# (raw.githubusercontent.com caches for ~5 minutes).
set -euo pipefail
cd "$(dirname "$0")"

REPO_ACCOUNT=lucasmendes-design

# This repo belongs to the professional account. gh's credential helper only
# ever serves the *active* account, so a personal account left active pushes
# straight into a 403 — fail here with something readable instead.
ACTIVE=$(gh auth status --hostname github.com 2>&1 | grep -B1 "Active account: true" | grep -oE "account [^ ]+" | awk '{print $2}' || true)
if [ "$ACTIVE" != "$REPO_ACCOUNT" ]; then
	echo "Wrong GitHub account active: '${ACTIVE:-none}' (need $REPO_ACCOUNT)."
	echo "Run: gh auth switch --hostname github.com --user $REPO_ACCOUNT"
	exit 1
fi

cp ~/AI/A8C/untangling-prototype/untangling-prototype.php .
cp ~/AI/A8C/untangling-prototype/untangling-seeder.php .

# Per-site seeds live canonically in the Studio sites' mu-plugins.
cp ~/Studio/aperture-diaries/wp-content/mu-plugins/untangling-seed.json seed-aperture.json
cp ~/Studio/cast-iron-supply-co/wp-content/mu-plugins/untangling-seed.json seed-castiron.json
cp ~/Studio/core-coworking/wp-content/mu-plugins/untangling-seed.json seed-coworking.json
cp ~/Studio/open-ocean/wp-content/mu-plugins/untangling-seed.json seed-openocean.json
cp ~/Studio/paper-fox-prints/wp-content/mu-plugins/untangling-seed.json seed-paperfox.json
cp ~/Studio/slow-mornings/wp-content/mu-plugins/untangling-seed.json seed-slowmornings.json

# The demo is the plugin *plus* its blueprints and configs — syncing only the
# plugin used to strand config edits locally while the live link kept serving
# the old scenario.
if git diff --quiet -- . && git diff --cached --quiet -- .; then
	echo "Already in sync — nothing to push."
	exit 0
fi

# This repo is public: never publish internal hosts. Scan the payload only —
# this script is excluded because the pattern below would match itself.
INTERNAL=$(git diff -- . ':!sync.sh' | grep -inE "^\+.*(a8c\.com|\.a8c\b)" | head || true)
if [ -n "$INTERNAL" ]; then
	echo "Refusing to push: an internal a8c reference appears in the changes."
	echo "$INTERNAL"
	exit 1
fi

echo "Publishing:"
git status --porcelain -- .

git add -A
git commit -m "Sync prototype plugin and demo config ($(date +%Y-%m-%d))"
GH_HOST=github.com git push origin main
echo "Pushed. The Playground links serve the new version within ~5 minutes."
