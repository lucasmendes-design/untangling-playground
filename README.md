# Untangling IA — WP Admin prototype (Playground share)

A self-contained WordPress Playground demo of the WP Admin side of the
untangling prototype: Hosting menu (two variants), Simple/Atomic site type
mimic, Marketplace (fullscreen / split / tabs), theme details pages, and the
upgrade flow. Use the floating **Prototype controls** panel (bottom-right of
every wp-admin screen) to switch variants live.

**Try it:**
[Open in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/lucasmendes-design/untangling-playground/main/blueprint.json)

Notes:

- Everything runs in your browser. Each visit is a fresh, private instance;
  closing the tab discards it. Reload for a clean slate.
- First load takes a few seconds while WordPress boots.
- Links pointing to the Hosting Dashboard side of the prototype show a notice
  instead of navigating — that half is not part of this demo.

## Combined demo (MSD + WP Admin)

The six `blueprint-<site>.json` files back the combined demo: each stands in
for one of the prototype's demo sites, seeded with its own content and plan
identity (`config-<site>.php` + `seed-<site>.json`). Their MSD-bound links
point at the hosted Dashboard preview instead of showing a notice, so the two
halves of the prototype link to each other.

## Files

- `untangling-prototype.php` — the prototype mu-plugin (synced from the main
  working copy; do not edit here).
- `untangling-seeder.php` — seeds demo content from a `seed-*.json` (synced;
  do not edit here).
- `0-untangling-config.php` — demo identity (Free plan, standalone mode).
- `blueprint.json` — the Playground blueprint that assembles the site.
- `blueprint-quickstart.json` / `quickstart-config.php` — the wp-admin-only
  quick start (Prototype controls visible, standalone mode).
- `blueprint-<site>.json` / `config-<site>.php` / `seed-<site>.json` — the six
  per-site blueprints for the combined demo.
- `sync.sh` — copies the latest plugin/seeder/seeds from the working copies,
  commits, pushes.
