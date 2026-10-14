# Handover — untangling IA prototype

Updated: 2026-09-15. This is the living state doc for the prototype. Keep it
current when you finish a session (`/untangling-wrap` does that).

## What this is

A working prototype of the untangled WordPress.com experience: the
Multi-site Dashboard (MSD) as the one full management surface, and a slimmer
wp-admin that previews site health and hands off to the MSD for the deep
work. Two halves, linked both ways:

- **MSD half**: Calypso branch `prototype/untangling-ia`, served locally at
  http://my.localhost:3333. Six mock sites with live hydration from the Studio
  sites (name, icon, iframe preview). Two personas, sticky via localStorage:
  `?persona=blogger` (solo Aperture Diaries, Free) and `?persona=developer`
  (the other five).
- **wp-admin half**: six Studio sites sharing ONE mu-plugin
  (`untangling-prototype.php`), parameterized per site by
  `0-untangling-config.php`. Plan drives both surfaces consistently.

| Site | Plan | Persona | Notes |
|---|---|---|---|
| Aperture Diaries | Free | blogger | Simple site type, domain upsell |
| Open ocean | Free | developer | |
| Slow Mornings | Premium | developer | |
| Core Coworking | Business | developer | Atomic |
| Cast Iron Supply Co | Commerce | developer | WooCommerce, seeded products |
| Paper Fox Prints | Business | developer | WooCommerce, seeded products |

## The two wp-admin variants (the current version)

Switched by **Layout → Variant** at the top of the floating **Prototype
controls** panel (`?untangling_variant=dashboard|drawer`, persisted per site).
Switching navigates to that variant's home.

1. **`dashboard` (default) — the all-in Dashboard.** Core `index.php` carries
   the custom widgets, 3 columns by default (the reviewed layout as of
   2026-09-15): col 1 = Your site (identity + plan + the ONE promo slot) →
   Hosting (plan / WordPress / PHP / theme / content / storage + Developer
   tools); col 2 = Daily Writing Prompt → Quick Draft (core's widget, drafts
   read the seeds); col 3 = Stats (KPI pair + sparkline) → Newsletter → Log
   Activity. Layout map: `untangling_dw_layout()`, read by registration and
   the order-snap filter; Screen Options default = those seven checked, every
   core/Jetpack extra unchecked. **There is no Next steps widget**: setup is
   the **Site Setup** sidebar item directly below Dashboard (the production
   launchpad page, `admin.php?page=untangling-mysite&ms=setup`, rendered by
   `SiteSetupPage` from the existing `LaunchpadCard`; "0 of 6 completed" on a
   fresh site, hidden once the site is Established). This follows the
   product decision to keep Site Setup as its own page rather than a
   Dashboard widget. Established sites also show three foreign plugin
   widgets (WooCommerce Status, Yoast, Elementor) so layouts are judged with
   realistic clutter. Sidebar: Dashboard → Site Setup → Upgrades (opens
   `ms=plan`) … → Help & Learn; welcome panel suppressed. Persistence: the
   prompt is lifted out of older saved hide lists once (`untangling_pw_seeded`
   = 2) and core's `postboxes._pb_change` is neutralised on the Dashboard, so
   a phone/tablet visit no longer rewrites the column count. Only the user's
   own Screen Options / drag / Site state changes alter the view; Reset demo
   restores it. Every widget is a preview; the MSD stays the full surface.
   Upgrade flows exit "Back to Dashboard" (`untangling_plan_flow_home_url()`
   is variant-aware).
2. **`drawer` — the My Site drawer.** Top-level **My Site** menu item with
   Next steps / Plan & products / Hosting / Help & Learn, `?ms=` router on
   `admin.php?page=untangling-mysite`. Pages share an MSD PageLayout mimic
   (`untangling_ms_app_js()` / `untangling_ms_app_css()` at the end of the
   plugin). Next steps has Just created → confetti → Established. All drawer
   state is namespaced `untangling_ms_*`.

Other panel controls: Site state (Just created | Established), Hosting state
(All good | Needs attention), Site type (Atomic | Simple), Upsell placement
(None | Menu top | Menu foot | Omnibar), Plugins & themes version
(Fullscreen | Split | Tabs), Partner themes (Subscription | Included), Plan
filter (Included | Dropdown), "Copy link to this view", and "Reset demo"
(clears variant, options, theme subscriptions and the dashboard's Screen
Options / order user meta so the designed first look returns).

Designed defaults: Dashboard · Just created · All good · Atomic · Upsell None ·
Tabs · Subscription · Included.

Partner themes (the six priced third-party themes in the catalog) follow the
`Partner themes` toggle on Business and Commerce sites. `Subscription` is the
production behaviour: the card shows the monthly price and a `Subscribe`
action, the theme view shows `Available for US$X/month` + `Partner`, the
checkout carries only the theme (no plan row), the confirmation says "Congrats
on your new theme" with `Activate this design`, and afterwards the theme reads
`Subscribed` with an `Activate` action. `Included` is the earlier shortcut
where those themes come with the plan. On lower plans nothing changes: the
plan upsell checkout still carries the theme line and marks it subscribed.
Subscriptions persist in `untangling_mkt_subscribed` (reset clears it); the
single-theme checkout is `ustep=checkout&type=theme&slug=<slug>&flow=subscribe`.

Retired, never demo or link: `admin.php?page=untangling-hosting` (redirects),
the old `untangling_variant=submenu|plain` and `untangling_header=` switches.

## Where the code is

wp-admin (this repo, `untangling-prototype.php`):
- `untangling_get_variant()` — variant resolution (overridable by the
  `UNTANGLING_FORCE_VARIANT` constant for Playground builds).
- `untangling_dw_*` — dashboard widgets; `untangling_dw_layout()` is the
  column map read by registration and the order-snap filter.
- `untangling_ms_*` — My Site drawer app (React-ish closure, multi-mount).
  Widgets reuse the same components; `ctx=ms` + `back=` plumbing carries
  checkout round trips.
- Prototype controls panel + fab, REST route `untangling/v1/upsell` (serves
  the site's live offer to the MSD omnibar pill), CORS for the MSD origin,
  localhost auto-login as user 1.
- Marketplace: `admin.php?page=untangling-marketplace` (fullscreen) vs split
  vs tabs, pricing → checkout → confirmation persisting
  `untangling_plan_override`.

MSD (Calypso branch `prototype/untangling-ia`):
- `client/dashboard/sites/overview-blogger/mock-sites.ts` — the six mock
  sites, hardcoded Studio ports (`localUrl`), cache seeding so every sidebar
  page renders, `isRemoteMsd()` (off `*.localhost` → Playground blueprints +
  static previews).
- `client/dashboard/app-dotcom/prototype-controls.ts` — MSD-side panel
  (MSD style: Default | Hybrid | WP Admin), omnibar upsell pill injection,
  "My Site" row in the site-name dropdown.
- `client/dashboard/app-dotcom/wpadmin-sidebar.scss` + `wpadmin-style.scss`
  — the WP Admin style variant (modern admin color scheme values).
- `client/assets/images/untangling-previews/` — static previews for remote.

## Share links (no install needed)

- Whole flow, developer persona:
  https://calypso.live/sites?branch=prototype/untangling-ia&env=dashboard&persona=developer
- Whole flow, blogger persona: same URL with `persona=blogger`.
- wp-admin only, controls visible (Business/Atomic, all-in Dashboard):
  https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/lucasmendes-design/untangling-playground/main/blueprint-quickstart.json
- Same site, landing on Add Themes → Marketplace tab (partner theme
  subscription state):
  https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/lucasmendes-design/untangling-playground/main/blueprint-themes.json

## How to work

Three commands, all inside Claude Code. No terminal needed.

- /untangling-start opens the day. It boots the sites and the MSD, prints the links, and tells you where the work stopped.
- /untangling-sync "what changed" publishes the wp-admin side. It pulls first, then commits and pushes. The Playground links follow in about five minutes.
- /untangling-wrap closes the day. It updates this doc and publishes.

The wp-admin work lives in untangling-prototype.php. Your Studio sites point to this file, therefore every edit is live on reload. Seeds and per-site configs live next to it.

The MSD work lives in the Calypso branch. Commit there and push the branch, calypso.live rebuilds from it.

Obs: two people push to the same repo. Pull before you push. The sync skill does it for you.

Woo sites in Playground: the blueprints install WooCommerce and suppress the
activation redirect before seeding.

## Gotchas that cost time

- `client/document/index.jsx` SSR-renders the interim omnibar. Importing
  `mock-sites.ts` (or anything client-heavy) into it creates a circular import
  that 500s EVERY page. The omnibar keeps its own local port regex.
- The Calypso server bundle builds only at startup. Server-side changes
  (routes in `DOTCOM_DASHBOARD_SECTION_PATHS`, redirects) need a restart.
- The dashboard SPA intercepts same-origin anchors; links to `/setup` must
  force a full page load.
- `loading="lazy"` images never load on the chromeless marketplace page in
  Chrome; use eager + `decoding="async"`.
- `html.has-docked-help-center` makes the MSD sidebar transparent and wins on
  source order; wpadmin-sidebar.scss carries an explicit re-dark rule.
- Never navigate the Playground iframe programmatically (breaks its service
  worker); click through the UI.
- Studio assigns ports itself. If yours differ from the hardcoded `localUrl`
  values in mock-sites.ts, change those six lines (local change is fine).
- No system `php`/`wp` on a Mac: use `studio wp --path <site> ...` or the
  Studio bundled php for `-l`.

## Open next steps

- MSD `/plans` deep link has no logged-in landing yet.
- wp-admin workspace flyout is visual only, no bridge to the MSD persona.
- Notifications bell and Help in wp-admin are placeholders.
- Woo stores show the blog homepage in previews; a storefront-ish front page
  for Cast Iron / Paper Fox would read better.
- Site-card subtitles show `localhost:PORT`; could mask with the pretty domain.
- Playground quickstart mirrors the Studio defaults; re-sync after widget
  changes (Site Setup + the locked default layout are synced as of
  2026-09-15).
- Site Setup page: the "Tailored with AI" footer line under the list is a
  prototype leftover (production has none); remove once confirmed. A "Launch
  site" button under the site preview is under discussion.
- On a touch device, core's postbox script used to switch the Dashboard to 1–2
  columns and save it; that path is disabled. If a layout still "changes on
  its own", check `screen_layout_dashboard` / `meta-box-order_dashboard` user
  meta first, then Reset demo.
