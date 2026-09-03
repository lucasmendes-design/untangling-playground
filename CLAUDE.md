# Untangling IA prototype — repo guide for Claude Code

Read `HANDOVER.md` first. It is the current picture of the prototype: what the
two wp-admin variants are, where every piece lives, how to publish, and the
open next steps.

## Skills in this repo

- `/untangling-setup` — one-time machine setup: six Studio sites + the Calypso
  branch, then boots everything and recaps. Run it once on a new machine.
- `/untangling-start` — daily resume: boots what is not running, prints the
  live links, recaps the state.
- `/untangling-wrap` — close the session: updates HANDOVER.md and publishes.
- `/untangling-sync "message"` — publish the wp-admin side: pull first, then
  commit and push. Playground links follow within ~5 minutes.

## What lives where

- `untangling-prototype.php` — THE mu-plugin (all wp-admin work, ~13k lines).
  When this repo was set up with `scripts/setup.sh`, the Studio sites symlink
  to this file, so edits here are live on reload.
- `untangling-seeder.php` + `seed-<site>.json` — demo content per site,
  idempotent by the seed `version` field (bump it to reseed; `&force=1` too).
- `studio/<slug>/0-untangling-config.php` — per-site identity for local
  Studio sites (plan, slug, domains).
- `config-<site>.php` / `blueprint-<site>.json` — Playground editions of the
  same six sites (they point MSD links at the hosted preview). `blueprint.json`
  is the locked walkthrough; `blueprint-quickstart.json` is the open share.
- The MSD half is NOT here: it is branch `prototype/untangling-ia` of
  github.com/Automattic/wp-calypso (see HANDOVER.md for the key files).

## Working rules

- **Two live variants, one panel.** `untangling_variant=dashboard|drawer`.
  Changes to one variant must never leak into the other. Same for the MSD
  styles (Default / Hybrid / WP Admin): variant CSS stays scoped.
- **Never link `admin.php?page=untangling-hosting`.** Retired; it redirects.
  Use the Prototype controls panel instead of hand-written `?untangling_*` URLs.
- **Copy**: WordPress.com voice. Sentence case, short, no "simple/easy", no
  exclamation marks. If the `brand-os-assistant:wpcom-brand-ai-kit` skill is
  installed, load it before writing user-facing text.
- **UI**: WordPress Design System. If the `wordpress-design-system` MCP is
  available, use `get_components` → `get_component_details` and
  `get_design_tokens` (`--wpds-*`) instead of guessing props or hardcoding.
  Applies to both the Calypso side and the mu-plugin's React surfaces.
- **This repo is public.** No internal hostnames, ticket links, internal post
  links, or colleague names in committed files. `sync.sh` refuses pushes that
  add internal hosts, but it only catches the obvious ones.
- **PHP lint** without a system PHP: the Studio app ships one at
  `/Applications/Studio.app/Contents/Resources/php-bin/*/php -l <file>`.

## Publishing

- wp-admin side: `/untangling-sync "message"` (or `./sync.sh "message"` in a
  terminal) commits everything and pushes `main`. Playground links serve the
  new version within ~5 minutes. Pull before you push; the skill does it.
- Calypso side: commit on `prototype/untangling-ia` and push the branch.
  calypso.live rebuilds the shared preview from the same link.
