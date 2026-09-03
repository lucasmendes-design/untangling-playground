#!/bin/zsh
# Idempotent startup for the untangling IA prototype (MSD + WP Admin bridge).
# Detection-first: reuses anything already running. Prints STATUS lines the
# /untangling-start skill relays to the user. Safe to run any number of times.
set -u

REPO="$(cd "$(dirname "$0")/.." && pwd)"
[[ -f "$REPO/.untangling.env" ]] && source "$REPO/.untangling.env"
CALYPSO_DIR="${CALYPSO_DIR:-$HOME/wp-calypso}"
STUDIO_ROOT="${STUDIO_ROOT:-$HOME/Studio}"
BRANCH="prototype/untangling-ia"
LOG_FILE="$REPO/calypso-dev.log"

# slug | display name | fallback port (Lucas's mapping)
SITES=(
	"aperture-diaries|Aperture Diaries|8883"
	"cast-iron-supply-co|Cast Iron Supply Co|8885"
	"core-coworking|Core Coworking|8886"
	"open-ocean|Open ocean|8882"
	"paper-fox-prints|Paper Fox Prints|8887"
	"slow-mornings|Slow Mornings|8888"
)

ok()   { echo "OK   $1"; }
warn() { echo "WARN $1"; }
fail() { echo "FAIL $1"; }
http_code() { curl -s -o /dev/null -w "%{http_code}" --max-time 5 "$1" 2>/dev/null || echo "000"; }
site_port() { studio status --path "$1" 2>/dev/null | grep -oE "localhost:[0-9]+" | head -1 | cut -d: -f2; }

# --- 1. Git branch ---------------------------------------------------------
if [[ ! -d "$CALYPSO_DIR/.git" ]]; then
	fail "calypso checkout not found at $CALYPSO_DIR — run /untangling-setup first (or set CALYPSO_DIR in .untangling.env)"
else
	cd "$CALYPSO_DIR"
	current_branch=$(git rev-parse --abbrev-ref HEAD)
	if [[ "$current_branch" == "$BRANCH" ]]; then
		ok "branch: already on $BRANCH"
	elif [[ -z "$(git status --porcelain)" ]]; then
		git checkout "$BRANCH" >/dev/null 2>&1 && ok "branch: switched from $current_branch to $BRANCH" \
			|| fail "branch: could not checkout $BRANCH (from $current_branch)"
	else
		warn "branch: on $current_branch with uncommitted changes — NOT switching. Commit/stash first, then rerun."
	fi

	# --- 2. Calypso dev server (port 3333 via .env) -------------------------
	grep -q "^PORT=3333" .env 2>/dev/null || warn "calypso: .env does not pin PORT=3333 — the mu-plugin expects http://my.localhost:3333"
	if [[ "$(http_code "http://my.localhost:3333/sites")" == "200" ]]; then
		ok "calypso: already serving http://my.localhost:3333"
	else
		stale_pid=$(lsof -tiTCP:3333 -sTCP:LISTEN 2>/dev/null | head -1)
		if [[ -n "${stale_pid:-}" ]]; then
			stale_cwd=$(lsof -a -p "$stale_pid" -d cwd -Fn 2>/dev/null | sed -n 's/^n//p')
			if [[ "$stale_cwd" == "$CALYPSO_DIR" ]]; then
				warn "calypso: replacing unresponsive server (pid $stale_pid)"
				kill "$stale_pid" 2>/dev/null; sleep 2
			else
				fail "calypso: port 3333 is owned by another process (pid $stale_pid, cwd $stale_cwd) — resolve manually"
				stale_conflict=1
			fi
		fi
		if [[ -z "${stale_conflict:-}" ]]; then
			echo "INFO calypso: starting dev server (1-3 min cold, log: $LOG_FILE)"
			NODE_OPTIONS="--max-old-space-size=8192" nohup yarn start > "$LOG_FILE" 2>&1 &
			disown
			waited=0
			until [[ "$(http_code "http://my.localhost:3333/sites")" == "200" ]]; do
				sleep 5; waited=$(( waited + 5 ))
				if (( waited >= 300 )); then fail "calypso: not responding after ${waited}s — check $LOG_FILE"; break; fi
			done
			(( waited < 300 )) && ok "calypso: up after ~${waited}s at http://my.localhost:3333"
		fi
	fi
fi

# --- 3. Studio sites --------------------------------------------------------
typeset -A PORT_OF
for entry in "${SITES[@]}"; do
	IFS='|' read -r slug name fallback <<< "$entry"
	dir="$STUDIO_ROOT/$slug"
	mu="$dir/wp-content/mu-plugins"
	if [[ ! -d "$dir/wp-content" ]]; then
		fail "studio: site missing for $name ($dir) — run scripts/setup.sh"
		continue
	fi
	[[ -e "$mu/untangling-prototype.php" ]] || { ln -s "$REPO/untangling-prototype.php" "$mu/untangling-prototype.php" && warn "plugin: re-created symlink for $name"; }
	[[ -e "$mu/zz-untangling-seeder.php" ]] || ln -s "$REPO/untangling-seeder.php" "$mu/zz-untangling-seeder.php"

	port="$(site_port "$dir")"; port="${port:-$fallback}"
	code=$(http_code "http://localhost:$port/wp-admin/")
	if [[ "$code" == "200" || "$code" == "302" ]]; then
		ok "studio: $name serving on :$port"
	else
		echo "INFO studio: starting $name"
		studio start --path "$dir" --skip-browser --skip-log-details >/dev/null 2>&1
		sleep 3
		port="$(site_port "$dir")"; port="${port:-$fallback}"
		code=$(http_code "http://localhost:$port/wp-admin/")
		[[ "$code" == "200" || "$code" == "302" ]] && ok "studio: $name up on :$port" \
			|| warn "studio: $name returned $code after start — open the Studio app to start it (MSD falls back to static mock data)"
	fi
	PORT_OF[$slug]=$port
done

# --- 4. Port drift vs the Calypso mock ---------------------------------------
MOCK="$CALYPSO_DIR/client/dashboard/sites/overview-blogger/mock-sites.ts"
if [[ -f "$MOCK" ]]; then
	for slug in "${(@k)PORT_OF}"; do
		grep -q "localhost:${PORT_OF[$slug]}'" "$MOCK" || warn "ports: $slug runs on :${PORT_OF[$slug]} but mock-sites.ts has no localUrl with that port — update the six localUrl lines"
	done
fi

# --- 5. Bridge probes -------------------------------------------------------
[[ "$(http_code "http://my.localhost:3333/discover")" == "200" ]] && ok "bridge: MSD Discover responds" || warn "bridge: MSD /discover not responding"
ap="${PORT_OF[aperture-diaries]:-8883}"
code=$(http_code "http://localhost:$ap/wp-admin/")
[[ "$code" == "200" || "$code" == "302" ]] && ok "bridge: WP Admin responds on :$ap (auto-login active)" || warn "bridge: WP Admin on :$ap not responding"

echo ""
echo "LINKS"
echo "  MSD sites:      http://my.localhost:3333/sites"
echo "  MSD personas:   http://my.localhost:3333/sites?persona=blogger   (solo — Aperture Diaries, Free)"
echo "                  http://my.localhost:3333/sites?persona=developer (Cast Iron, Core Coworking, Open ocean, Paper Fox, Slow Mornings)"
echo "  WP Admin (blogger site, lands on the all-in Dashboard):"
echo "                  http://localhost:$ap/wp-admin/"
for slug in "${(@k)PORT_OF}"; do
	[[ "$slug" == "aperture-diaries" ]] || echo "  WP Admin $slug: http://localhost:${PORT_OF[$slug]}/wp-admin/"
done
echo ""
echo "  Every wp-admin toggle lives in the floating PROTOTYPE CONTROLS panel:"
echo "  Variant (Dashboard | My Site) · Site state · Hosting state · Site type · Upsell · Plugins & themes version · Plan filter."
echo "  Never link admin.php?page=untangling-hosting (retired; it redirects)."
