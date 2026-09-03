---
name: untangling-sync
description: Publish the wp-admin side of the untangling IA prototype — pull first, then commit and push this repo so the Playground links and other clones get the new version. Use when the user says "sync", "publish", "push the playground", or invokes /untangling-sync, optionally with a commit message.
---

# Publish the wp-admin side

This repo is the source of truth for the plugin, seeder, seeds, and configs.
The argument, if any, is the commit message.

1. `git fetch origin`. If `origin/main` is ahead of `HEAD`, run
   `git pull --rebase origin main` first. If the rebase conflicts, stop, show
   the conflicting files, and ask before touching them. Never force-push.
2. Run `./sync.sh "<message>"` from the repo root. No message = the dated
   default. If it refuses for an internal reference, show the lines and fix
   them first. Never bypass that check.
3. Report in two or three lines: commit hash, what changed
   (`git show --stat HEAD`), and that the Playground links pick it up within
   about five minutes.

If `.untangling.env` sets `UNTANGLING_SRC`, this machine keeps the plugin in
an external folder and `sync.sh` copies it in. In that case, after a pull,
copy the pulled `untangling-prototype.php` / `untangling-seeder.php` back
into that folder before editing, so the two copies do not drift.
