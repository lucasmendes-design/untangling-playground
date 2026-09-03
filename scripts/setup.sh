#!/bin/zsh
# One-time local setup for the WP Admin half of the untangling prototype.
# Creates the six Studio sites, wires the shared mu-plugin into each one,
# installs WooCommerce where the seed needs it, seeds demo content, and prints
# the slug → port table. Idempotent and non-destructive: rerun it anytime, it
# only fills in what is missing and never replaces files that already exist.
#
# Requires the Studio CLI (`studio`). Install it from the Studio app:
# Settings → "Install command line tool", or see developer.wordpress.com/studio.
set -u

REPO="$(cd "$(dirname "$0")/.." && pwd)"
STUDIO_ROOT="${STUDIO_ROOT:-$HOME/Studio}"

# slug | display name | seed key | needs woo
SITES=(
	"aperture-diaries|Aperture Diaries|aperture|"
	"cast-iron-supply-co|Cast Iron Supply Co|castiron|woo"
	"core-coworking|Core Coworking|coworking|"
	"open-ocean|Open ocean|openocean|"
	"paper-fox-prints|Paper Fox Prints|paperfox|woo"
	"slow-mornings|Slow Mornings|slowmornings|"
)

ok()   { echo "OK   $1"; }
warn() { echo "WARN $1"; }
fail() { echo "FAIL $1"; }
http_code() { curl -s -o /dev/null -w "%{http_code}" --max-time 8 "$1" 2>/dev/null || echo "000"; }
site_port() { studio status --path "$1" 2>/dev/null | grep -oE "localhost:[0-9]+" | head -1 | cut -d: -f2; }

if ! command -v studio >/dev/null 2>&1; then
	fail "the 'studio' CLI is not installed. Open the Studio app → Settings → install the command line tool, then rerun."
	exit 1
fi
if ! command -v curl >/dev/null 2>&1; then
	fail "curl is required"; exit 1
fi

mkdir -p "$STUDIO_ROOT"
PORTS_FILE="$REPO/.untangling-ports"
: > "$PORTS_FILE"

for entry in "${SITES[@]}"; do
	IFS='|' read -r slug name key woo <<< "$entry"
	dir="$STUDIO_ROOT/$slug"
	mu="$dir/wp-content/mu-plugins"
	echo ""
	echo "== $name ($slug)"

	# 1. Site
	if [[ -d "$dir/wp-content" ]]; then
		ok "site exists at $dir"
	else
		echo "INFO creating Studio site (30-90s)"
		if studio create --path "$dir" --name "$name" --skip-browser --skip-log-details >/dev/null 2>&1; then
			ok "site created"
		else
			fail "studio create failed for $name — open the Studio app, create a site named \"$name\" at $dir, then rerun"
			continue
		fi
	fi

	# 2. mu-plugins (never overwrite what is already there)
	mkdir -p "$mu"
	if [[ -e "$mu/0-untangling-config.php" ]]; then
		ok "config present"
	else
		cp "$REPO/studio/$slug/0-untangling-config.php" "$mu/0-untangling-config.php" && ok "config installed"
	fi
	if [[ -e "$mu/untangling-prototype.php" ]]; then
		ok "plugin present ($(readlink "$mu/untangling-prototype.php" 2>/dev/null || echo 'regular file'))"
	else
		ln -s "$REPO/untangling-prototype.php" "$mu/untangling-prototype.php" && ok "plugin symlinked from repo"
	fi
	if [[ -e "$mu/zz-untangling-seeder.php" ]]; then
		ok "seeder present"
	else
		ln -s "$REPO/untangling-seeder.php" "$mu/zz-untangling-seeder.php" && ok "seeder symlinked from repo"
	fi
	if [[ -e "$mu/untangling-seed.json" ]]; then
		ok "seed present"
	else
		ln -s "$REPO/seed-$key.json" "$mu/untangling-seed.json" && ok "seed symlinked from repo (seed-$key.json)"
	fi

	# 3. Running?
	port="$(site_port "$dir")"
	if [[ -z "$port" ]] || [[ "$(http_code "http://localhost:$port/wp-admin/")" == "000" ]]; then
		echo "INFO starting site"
		studio start --path "$dir" --skip-browser --skip-log-details >/dev/null 2>&1
		sleep 3
		port="$(site_port "$dir")"
	fi
	if [[ -z "$port" ]]; then
		fail "could not detect the port for $name — start it in the Studio app and rerun"
		continue
	fi
	ok "serving on http://localhost:$port"
	echo "$slug=$port" >> "$PORTS_FILE"

	# 4. WooCommerce for the store sites (seed products need it)
	if [[ "$woo" == "woo" ]]; then
		if studio wp --path "$dir" plugin is-active woocommerce >/dev/null 2>&1; then
			ok "WooCommerce active"
		else
			echo "INFO installing WooCommerce (1-2 min)"
			if studio wp --path "$dir" plugin install woocommerce --activate >/dev/null 2>&1; then
				studio wp --path "$dir" transient delete _wc_activation_redirect >/dev/null 2>&1
				studio wp --path "$dir" option update woocommerce_onboarding_profile '{"skipped":true}' --format=json >/dev/null 2>&1
				ok "WooCommerce installed and activated"
			else
				warn "WooCommerce install failed — install it from wp-admin → Plugins, then rerun to seed products"
			fi
		fi
	fi

	# 5. Seed (idempotent by seed version)
	result="$(curl -s --max-time 120 "http://localhost:$port/?untangling_seed=run" | head -c 400)"
	if [[ "$result" == *'"ok":true'* ]]; then
		ok "seed: $result"
	else
		warn "seed: unexpected response: ${result:-<empty>}"
	fi
done

echo ""
echo "PORTS (also saved to $PORTS_FILE)"
cat "$PORTS_FILE" | sed 's/^/  /'
echo ""
echo "Next: the MSD side (Calypso) hardcodes each site's port in"
echo "  client/dashboard/sites/overview-blogger/mock-sites.ts (localUrl)."
echo "  Lucas's mapping is open-ocean 8882 · aperture-diaries 8883 · cast-iron-supply-co 8885"
echo "  core-coworking 8886 · paper-fox-prints 8887 · slow-mornings 8888."
echo "  If your ports differ, update those six lines to match the table above."
