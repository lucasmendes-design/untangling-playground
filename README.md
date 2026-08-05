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

## Files

- `untangling-prototype.php` — the prototype mu-plugin (synced from the main
  working copy; do not edit here).
- `0-untangling-config.php` — demo identity (Free plan, standalone mode).
- `blueprint.json` — the Playground blueprint that assembles the site.
- `sync.sh` — copies the latest plugin from the working copy, commits, pushes.
