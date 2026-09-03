---
name: untangling-wrap
description: Close a session on the untangling IA prototype — update HANDOVER.md with what changed, publish the wp-admin side, and commit Calypso changes locally. Use when the user says "wrap up", "close the day", "I'm done for today", or invokes /untangling-wrap.
---

# Wrap up the session

1. Update `HANDOVER.md`: the `Updated:` date, the current-version section if
   the design changed, and the "Open next steps" list. Keep it public-safe:
   no colleague names, no internal hosts or post links.
2. Publish the wp-admin side by following the `untangling-sync` skill (pull
   first, then `./sync.sh "<short message>"`).
3. In the Calypso checkout (`CALYPSO_DIR` in `.untangling.env`): if there are
   intentional changes on `prototype/untangling-ia`, commit them locally with
   a short message. Ask before pushing the branch, calypso.live rebuilds from
   it.
4. Confirm in two or three lines what was saved and pushed.
