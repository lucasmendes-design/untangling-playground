---
name: untangling-setup
description: One-time machine setup for the untangling IA prototype — creates and seeds the six Studio sites, checks out the Calypso branch, reconciles ports, boots everything, and recaps the state. Use on a new machine or when sites are missing.
---

# Set up the untangling IA prototype on this machine

Everything runs from this repo. Be idempotent: rerunning must only fill gaps.
Tell the user what you are about to do in one line, then go.

## 1. WP Admin half (Studio sites)

Run `scripts/setup.sh` with a long timeout (up to 10 minutes; creating six
sites and installing WooCommerce twice takes a while). Relay the OK/WARN/FAIL
lines briefly. If it says the `studio` CLI is missing, stop and tell the user
to install it from the Studio app (Settings → command line tool), then rerun.
The script ends with a `PORTS` table; keep it, you need it in step 3.

## 2. MSD half (Calypso)

1. Find a wp-calypso checkout. Order: `CALYPSO_DIR` in `.untangling.env`
   (repo root, gitignored); then common paths (`~/dev/wp-calypso`,
   `~/wp-calypso`, `~/Projects/wp-calypso`, `~/code/wp-calypso`); then
   `find ~ -maxdepth 4 -type d -name wp-calypso -not -path '*/node_modules/*'`.
   If several, ask which one. If none, ask before cloning
   `https://github.com/Automattic/wp-calypso.git` (it is large); default
   location `~/wp-calypso`.
2. In that checkout: if the tree is dirty, show the user and ask before
   switching. Otherwise `git fetch origin prototype/untangling-ia` and
   `git checkout prototype/untangling-ia`.
3. Ensure `.env` in the checkout has `PORT=3333` (add the line if missing;
   `.env` is gitignored there). The mu-plugin points at
   `http://my.localhost:3333`, so this port is not optional.
4. `yarn install` if `node_modules` is missing or older than `yarn.lock`.
   Use the Node version from the repo's `.nvmrc`.
5. Write `CALYPSO_DIR="<path>"` into `.untangling.env` (create the file if
   needed; keep other lines).

## 3. Reconcile ports

Studio assigns ports itself. Compare the `PORTS` table from step 1 with the
six `localUrl` lines in
`client/dashboard/sites/overview-blogger/mock-sites.ts` (slugs match the site
names: aperture-diaries, cast-iron-supply-co, core-coworking, open-ocean,
paper-fox-prints, slow-mornings). If any differ, edit those lines to the real
ports and tell the user it is a local change they can commit on the branch or
keep uncommitted. Nothing else depends on the ports.

## 4. Boot and recap

Run `scripts/start.sh` (background is fine; a cold Calypso start takes 1–3
minutes). Then read `HANDOVER.md` and give a short recap: what the prototype
is, the two wp-admin variants and the Prototype controls panel, where the code
lives, how to publish. End with the live links the script printed and a
reminder that `/untangling-start` is the daily resume command.

## Conventions

Follow `CLAUDE.md` in the repo root for all prototype work.
