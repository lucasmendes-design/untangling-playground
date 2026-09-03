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

- `untangling-prototype.php` — the prototype mu-plugin. Source of truth when
  you set up from this repo (see "Local setup").
- `untangling-seeder.php` — seeds demo content from a `seed-*.json`.
- `0-untangling-config.php` — demo identity (Free plan, standalone mode).
- `blueprint.json` — the Playground blueprint that assembles the site.
- `blueprint-quickstart.json` / `quickstart-config.php` — the wp-admin-only
  quick start (Prototype controls visible, standalone mode).
- `blueprint-<site>.json` / `config-<site>.php` / `seed-<site>.json` — the six
  per-site blueprints for the combined demo.
- `studio/<slug>/0-untangling-config.php` — local Studio identities for the
  six sites.
- `scripts/setup.sh` / `scripts/start.sh` — local setup and daily resume.
- `sync.sh` — commits and pushes the repo (optionally pulling the plugin and
  seeds from an external working copy, see `.untangling.env` in the script).
- `HANDOVER.md` — the living state doc. `CLAUDE.md` — conventions for
  Claude Code, plus the `/untangling-setup` and `/untangling-start` skills.

## Local setup (continue the work)

Requirements: the [Studio](https://developer.wordpress.com/studio/) app with
its CLI installed, Node + Yarn for Calypso, and Claude Code.

```
git clone https://github.com/lucasmendes-design/untangling-playground.git
cd untangling-playground && claude
```

Then type `/untangling-setup`. It creates and seeds the six Studio sites,
checks out the Calypso branch `prototype/untangling-ia`, reconciles the
Studio ports with the MSD mocks, boots everything, and recaps the state from
`HANDOVER.md`. From the next day on, `/untangling-start` resumes,
`/untangling-sync "message"` publishes, and `/untangling-start wrap` closes
the session. See "How to work" in `HANDOVER.md`.
