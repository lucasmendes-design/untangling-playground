# Handover — untangling IA prototype

Updated: 2026-10-16. This is the living state doc for the prototype. Keep it
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
  `0-untangling-config.php`. Plan drives both surfaces consistently: the
  Prototype controls **Plan** segment (Free · Personal · Premium · Business ·
  Commerce) persists one plan per site, and the MSD mock site hydrates plan,
  hosting type, storage and domains from `GET /wp-json/untangling/v1/site`
  on its next load. Site type follows the plan (Free/Personal/Premium are
  Simple; Business/Commerce default Atomic with a Simple override).

| Site | Plan | Persona | Notes |
|---|---|---|---|
| Aperture Diaries | Free | blogger | Simple, domain upsell; flip to Personal to see the custom domain |
| Open ocean | Free | developer | |
| Slow Mornings | Premium | developer | |
| Core Coworking | Business | developer | Atomic |
| Cast Iron Supply Co | Commerce | developer | WooCommerce, seeded products |
| Paper Fox Prints | Business | developer | WooCommerce, seeded products |

## The wp-admin design (the current version)

One design: the **all-in Dashboard**. The old Variant switch (Dashboard |
My Site drawer, `?untangling_variant=`) was removed on 2026-10-22; the param
and the `UNTANGLING_FORCE_VARIANT` constant are ignored, old links carrying
them land on the Dashboard.

Core `index.php` carries
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
Upgrade flows exit "Back to Dashboard" (`untangling_plan_flow_home_url()`).
The sidebar pages (Site Setup `ms=setup`, Upgrades `ms=plan`, Help & Learn
`ms=help`) share an MSD PageLayout mimic (`untangling_ms_app_js()` /
`untangling_ms_app_css()` at the end of the plugin); the widgets reuse the
same components. Their state is namespaced `untangling_ms_*`.
`page=untangling-mysite` with any other `ms` (or none) redirects to
`index.php`.

Other panel controls: Plan (Free | Personal | Premium | Business | Commerce),
Site state (Just created | Established), Hosting state
(All good | Needs attention), Site type (Atomic | Simple, Business and
Commerce only), Upsell placement
(None | Menu top | Menu foot | Omnibar), Plugins & themes version
(Fullscreen | Split | Tabs), Partner themes (Subscription | Included), Plan
filter (Included | Dropdown), "Copy link to this view", and "Reset demo"
(clears options, theme subscriptions and the dashboard's Screen
Options / order user meta so the designed first look returns).

Designed defaults: Dashboard · Just created · All good · Atomic · Upsell None ·
Tabs · Subscription · Included.

Partner themes (the six priced third-party themes in the catalog) follow the
`Partner themes` toggle on Business and Commerce sites. `Subscription` is the
production behaviour: each partner theme is sold on its own, annual by
default. The card shows `US$7.20/mo, billed annually` and a `Subscribe`
action; the theme view shows `Available for US$7.20/month, billed annually` +
`Partner` and an Annually / Monthly radio (annual first with `Save 20%`,
monthly as the pricier way to pay); the checkout carries only the theme (no
plan row) with a `One year / One month` select, the twelve-month price struck
through next to the annual charge, and the total and Pay button following the
choice; the confirmation says "Congrats on your new theme" with the renewal
line (`Renews yearly at US$86.40` or `Renews monthly at US$9.00`) and
`Activate this design`; afterwards the theme reads `Subscribed` with an
`Activate` action and its renewal line. Price rule: the catalog carries the
monthly price, annual = twelve months with 20% off. `Included` is the earlier
shortcut where those themes come with the plan. On lower plans nothing
changes except the plan upsell checkout also carries the theme line (annual by
default, same select) and marks it subscribed. Subscriptions persist in
`untangling_mkt_subscribed` + `untangling_mkt_subscribed_terms` (reset clears
both); the single-theme checkout is
`ustep=checkout&type=theme&slug=<slug>&flow=subscribe&term=year|month`.

Retired, never demo or link: `admin.php?page=untangling-hosting` (redirects),
the old `untangling_variant=` (any value) and `untangling_header=` switches.

## Where the code is

wp-admin (this repo, `untangling-prototype.php`):
- `untangling_dw_*` — dashboard widgets; `untangling_dw_layout()` is the
  column map read by registration and the order-snap filter.
- `untangling_ms_*` — the sidebar pages app (React-ish closure, multi-mount).
  Widgets reuse the same components; `ctx=ms` + `back=` plumbing carries
  checkout round trips.
- Prototype controls panel + fab, REST routes `untangling/v1/upsell` (serves
  the site's live offer to the MSD omnibar pill) and `untangling/v1/site`
  (plan, tier, site type, storage, domains, renewal — the MSD mock sites
  hydrate from it), CORS for the MSD origin,
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

## Pricing page (2026-10-16)

The fullscreen pricing page (`admin.php?page=untangling-marketplace&ustep=pricing`,
every Upgrade / See plans / Compare plans CTA lands there) now mirrors the
pricing grid redesign that wp-calypso ships behind an experiment (the
`.plans-grid-next.is-plans-grid-redesign-experiment` rules in
`packages/plans-grid-next`, July 2026), without the Enterprise column:

- Data: `untangling_plan_grid()` — production copy per plan (tagline, `$price`
  + struck original, Save %, storage, CTA label and style, static badges,
  feature rows as `[ label, pill, tooltip ]`). `untangling_plan_pricing()`
  still feeds checkout prices.
- Render: the loop in `untangling_marketplace_pricing_step()`; the ladder
  filtering (current plan + the tiers that unlock the item or need), the
  hero and the checkout URLs are unchanged.
- Styles: the `.untangling-mkt-plan*` block in `untangling_marketplace_styles()`
  — one bordered row of 280px-max columns, 40px CTAs (filled on
  Premium/Business, outlined elsewhere), cloud storage row as the first
  feature row, green checks, "Most popular" / "Best value" badges on the top
  rule. Tooltips on the Save pill ("$48/year vs. $108 paying monthly") and on
  every feature (the production feature descriptions).
- Behaviour: item and need entries keep the contextual dark pill on the
  required column; the current plan shows a disabled "Current plan";
  "Add more" opens the storage add-on checkout (50 GB pack); "Compare plans"
  opens wordpress.com/pricing in a new tab.
- Gotcha: wp-admin core gives every `li` a 6px bottom margin; the grid
  resets it.

## Open next steps

- Pricing page: "Start Free" (design) vs "Start with Free" (Calypso) is
  undecided; the legacy "Choose a plan" modal and the two-column compare
  cards (Upgrades page, WordPress.com page) still carry the old look.
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
