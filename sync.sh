#!/bin/bash
# Publish the current prototype to this public repo and push. The shared
# Playground links pick the new version up automatically
# (raw.githubusercontent.com caches for ~5 minutes).
#
# Two ways of working, chosen by .untangling.env (gitignored, per machine):
#   - Repo is the source of truth (default; what scripts/setup.sh wires up):
#     edit untangling-prototype.php / seeds here, run ./sync.sh to publish.
#   - External working copy: set UNTANGLING_SRC to the folder holding the
#     plugin + seeder, and UNTANGLING_SEEDS_FROM_STUDIO=1 to pull each site's
#     seed from ~/Studio/<slug>/wp-content/mu-plugins/untangling-seed.json.
#   - REPO_ACCOUNT (optional): the gh account that must be active to push.
set -euo pipefail
cd "$(dirname "$0")"
[ -f .untangling.env ] && source .untangling.env

if [ -n "${REPO_ACCOUNT:-}" ]; then
	# gh's credential helper only ever serves the *active* account, so a
	# different account left active pushes straight into a 403 — fail here
	# with something readable instead.
	ACTIVE=$(gh auth status --hostname github.com 2>&1 | grep -B1 "Active account: true" | grep -oE "account [^ ]+" | awk '{print $2}' || true)
	if [ "$ACTIVE" != "$REPO_ACCOUNT" ]; then
		echo "Wrong GitHub account active: '${ACTIVE:-none}' (need $REPO_ACCOUNT)."
		echo "Run: gh auth switch --hostname github.com --user $REPO_ACCOUNT"
		exit 1
	fi
fi

if [ -n "${UNTANGLING_SRC:-}" ]; then
	cp "$UNTANGLING_SRC/untangling-prototype.php" .
	cp "$UNTANGLING_SRC/untangling-seeder.php" .
fi
if [ "${UNTANGLING_SEEDS_FROM_STUDIO:-0}" = "1" ]; then
	STUDIO_ROOT="${STUDIO_ROOT:-$HOME/Studio}"
	cp "$STUDIO_ROOT/aperture-diaries/wp-content/mu-plugins/untangling-seed.json" seed-aperture.json
	cp "$STUDIO_ROOT/cast-iron-supply-co/wp-content/mu-plugins/untangling-seed.json" seed-castiron.json
	cp "$STUDIO_ROOT/core-coworking/wp-content/mu-plugins/untangling-seed.json" seed-coworking.json
	cp "$STUDIO_ROOT/open-ocean/wp-content/mu-plugins/untangling-seed.json" seed-openocean.json
	cp "$STUDIO_ROOT/paper-fox-prints/wp-content/mu-plugins/untangling-seed.json" seed-paperfox.json
	cp "$STUDIO_ROOT/slow-mornings/wp-content/mu-plugins/untangling-seed.json" seed-slowmornings.json
fi

# The demo is the plugin *plus* its blueprints and configs — syncing only the
# plugin used to strand config edits locally while the live link kept serving
# the old scenario.
if git diff --quiet -- . && git diff --cached --quiet -- . && [ -z "$(git ls-files --others --exclude-standard)" ]; then
	echo "Already in sync — nothing to push."
	exit 0
fi

# This repo is public: never publish internal hosts. Scan the payload only —
# this script is excluded because the pattern below would match itself.
git add -A
INTERNAL=$(git diff --cached -- . ':!sync.sh' | grep -inE "^\+.*(a8c\.com|\.a8c\b)" | head || true)
if [ -n "$INTERNAL" ]; then
	git reset -q
	echo "Refusing to push: an internal reference appears in the changes."
	echo "$INTERNAL"
	exit 1
fi

echo "Publishing:"
git status --porcelain -- .

git commit -m "${1:-Sync prototype plugin and demo config ($(date +%Y-%m-%d))}"
GH_HOST=github.com git push origin main
echo "Pushed. The Playground links serve the new version within ~5 minutes."
