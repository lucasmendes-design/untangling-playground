---
name: untangling-start
description: Resume the untangling IA prototype — boot the Calypso dev server and the six Studio sites if they are not running, print the live links, and recap where the work left off. Use whenever a session starts on this prototype. Argument `wrap` = end-of-session update of HANDOVER.md.
---

# Resume the untangling IA prototype

1. Run `scripts/start.sh` (idempotent, detection-first; up to 5 minutes when
   Calypso needs a cold start, so run it in the background and continue).
   Relay OK/WARN/FAIL lines. If it says the Calypso checkout is missing, run
   `/untangling-setup` instead. If it warns about uncommitted changes on
   another branch, show the user and ask; never stash or discard for them.
   A WARN for one Studio site is not fatal: the MSD falls back to static mock
   data for that site.

2. Recap where the work left off, in this order of source priority:
   `HANDOVER.md` (current version / open next steps), then
   `git log --oneline -8` in this repo, then `git log --oneline -8` on the
   Calypso branch. Keep it to 3–6 sentences, then list the live links from
   the script.

   Staleness check: compare the `Updated:` date in `HANDOVER.md` with the
   mtime of `untangling-prototype.php`. If the plugin is newer, say so and
   treat the doc as possibly behind. Offer to update it.

3. With the argument `wrap` (or when the user asks to wrap up): update
   `HANDOVER.md` (current version / next steps / date), then run
   `./sync.sh "<short message>"` to publish the wp-admin side and, if the
   Calypso branch has intentional changes, commit them locally and ask before
   pushing.

Follow `CLAUDE.md` for conventions during the session.
