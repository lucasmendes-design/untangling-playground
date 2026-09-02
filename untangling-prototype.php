<?php
/**
 * Plugin Name: Untangling IA Prototype
 * Description: Prototype for the untangled WP Admin ↔ MSD experience: Hosting top-level menu (two variants), Marketplace tab on the Plugins screen, Theme Showcase discovery banner, and Omnibar bridge links back to the MSD.
 * Version: 0.1.0
 * Author: Lucas Mendes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Where the MSD prototype lives (Calypso dashboard). The per-site config can
// define it first — remote demos point it at a hosted MSD (calypso.live).
if ( ! defined( 'UNTANGLING_MSD_URL' ) ) {
	define( 'UNTANGLING_MSD_URL', 'http://my.localhost:3333' );
}

// Standalone demos (WordPress Playground shares) have no MSD running.
// The per-site config defines UNTANGLING_STANDALONE; MSD-bound links then
// show a toast instead of navigating to a dead host.
function untangling_is_standalone() {
	return defined( 'UNTANGLING_STANDALONE' ) && UNTANGLING_STANDALONE;
}

// Locked demos pin one scenario: Prototype controls are hidden and the
// untangling_* URL switches are ignored. Optional UNTANGLING_FORCE_*
// constants (per toggle, in the site config) override the persisted options.
function untangling_is_locked_demo() {
	return defined( 'UNTANGLING_LOCKED_DEMO' ) && UNTANGLING_LOCKED_DEMO;
}

// Playground and Studio both ship an older WP image, so core's "WordPress x.y
// is available! Please update now." nag shows on every screen. Nobody can
// update these demo instances — hide core update notices everywhere.
add_filter( 'pre_site_transient_update_core', '__return_null' );
add_action( 'admin_menu', function () {
	remove_action( 'admin_notices', 'update_nag', 3 );
	remove_action( 'network_admin_notices', 'update_nag', 3 );
} );

function untangling_standalone_link_guard() {
	if ( ! untangling_is_standalone() ) {
		return;
	}
	?>
	<script>
	( function () {
		var msd = <?php echo wp_json_encode( UNTANGLING_MSD_URL ); ?>;
		var toast;
		document.addEventListener( 'click', function ( event ) {
			var link = event.target.closest ? event.target.closest( 'a[href]' ) : null;
			if ( ! link || 0 !== link.href.indexOf( msd ) ) {
				return;
			}
			event.preventDefault();
			event.stopPropagation();
			if ( ! toast ) {
				toast = document.createElement( 'div' );
				toast.setAttribute( 'role', 'status' );
				// Top-center: Playground's own toolbar overlays the bottom of the viewport.
				toast.style.cssText = 'position:fixed;top:48px;left:50%;transform:translateX(-50%);z-index:1000000;background:#1e1e1e;color:#fff;padding:12px 20px;border-radius:4px;font:13px/1.5 -apple-system,BlinkMacSystemFont,sans-serif;box-shadow:0 4px 12px rgba(0,0,0,.25);transition:opacity .3s;max-width:90vw;text-align:center;';
				document.body.appendChild( toast );
			}
			toast.textContent = <?php echo wp_json_encode( __( 'This links to the Hosting Dashboard side of the prototype, which is not part of this demo.' ) ); ?>;
			toast.style.opacity = '1';
			clearTimeout( toast._hide );
			toast._hide = setTimeout( function () {
				toast.style.opacity = '0';
			}, 3200 );
		}, true );
	} )();
	</script>
	<?php
}
add_action( 'admin_footer', 'untangling_standalone_link_guard' );
add_action( 'wp_footer', 'untangling_standalone_link_guard' );

// Remote demos reach the MSD through the calypso.live redirector, which needs
// branch/env (and persona) args on every link. The per-site config defines
// UNTANGLING_MSD_QUERY (e.g. 'branch=prototype/untangling-ia&env=dashboard');
// links are rewritten at click time so every MSD_URL concatenation is covered.
function untangling_msd_query_rewriter() {
	if ( ! defined( 'UNTANGLING_MSD_QUERY' ) || ! UNTANGLING_MSD_QUERY || untangling_is_standalone() ) {
		return;
	}
	?>
	<script>
	( function () {
		var msd = <?php echo wp_json_encode( UNTANGLING_MSD_URL ); ?>;
		var msdQuery = <?php echo wp_json_encode( UNTANGLING_MSD_QUERY ); ?>;
		document.addEventListener( 'click', function ( event ) {
			var link = event.target.closest ? event.target.closest( 'a[href]' ) : null;
			if ( ! link || 0 !== link.href.indexOf( msd ) ) {
				return;
			}
			var url = new URL( link.href );
			new URLSearchParams( msdQuery ).forEach( function ( value, key ) {
				url.searchParams.set( key, value );
			} );
			link.href = url.toString();
		}, true );
	} )();
	</script>
	<?php
}
add_action( 'admin_footer', 'untangling_msd_query_rewriter' );
add_action( 'wp_footer', 'untangling_msd_query_rewriter' );

/* -------------------------------------------------------------------------
 * Per-site demo identity. Each Studio site carries a tiny
 * `0-untangling-config.php` mu-plugin (loads first alphabetically) defining
 * UNTANGLING_PLAN / UNTANGLING_SITE_SLUG / UNTANGLING_PRIMARY_DOMAIN /
 * UNTANGLING_DOMAIN_UPSELL; this shared plugin is symlinked into all sites.
 * ---------------------------------------------------------------------- */

function untangling_get_plan() {
	// The Marketplace checkout mimic persists upgrades; Prototype controls reset them.
	$plan = get_option( 'untangling_plan_override' );
	if ( ! $plan ) {
		$plan = defined( 'UNTANGLING_PLAN' ) ? UNTANGLING_PLAN : 'Business';
	}
	return in_array( $plan, array( 'Free', 'Personal', 'Premium', 'Business', 'Commerce' ), true ) ? $plan : 'Business';
}

function untangling_plan_rank( $plan ) {
	$rank = array( 'Free' => 0, 'Personal' => 1, 'Premium' => 2, 'Business' => 3, 'Commerce' => 4 );
	return isset( $rank[ $plan ] ) ? $rank[ $plan ] : 0;
}

/**
 * Contextual upgrade entries, keyed by the promise the visitor clicked.
 *
 * Every gated surface on the Hosting page needs the same plan, so the ladder
 * is not what changes between them — the reason is. `need=` carries that
 * reason to the pricing page so it opens on the promise that was clicked,
 * drops the tiers that cannot deliver it, and bolds the rows that answer it.
 * A visitor who asked “why did that page break” and one who asked “how do I
 * get my site back” are looking at the same plan for different reasons.
 *
 * tier  — lowest plan that delivers the need; the pricing floor and the
 *         highlighted column. Business for the hosting features; activity
 *         history really does start at Personal, so it says so.
 * pill  — badge on that column ('' keeps the generic “Recommended”).
 * title — pricing hero, continuing the card’s own promise rather than
 *         repeating its description back.
 * lede  — one sentence under it; the current-plan sentence is appended.
 * rows  — untangling_plan_pricing() row keys to bold.
 */
function untangling_hosting_needs() {
	return array(
		'hosting'     => array(
			'tier'  => 'Business',
			'pill'  => '',
			'title' => __( 'The rest of your hosting' ),
			'lede'  => __( 'Backups, scans, staging, plugins, logs, and server access all come with Business.' ),
			'rows'  => array( 'plugins', 'access', 'backups', 'scans', 'staging', 'logs' ),
		),
		'backups'     => array(
			'tier'  => 'Business',
			'pill'  => __( 'Unlocks backups' ),
			'title' => __( 'Restore any moment in one click' ),
			'lede'  => __( 'Real-time backups come with Business, so a change that goes wrong is never permanent.' ),
			'rows'  => array( 'backups' ),
		),
		'security'    => array(
			'tier'  => 'Business',
			'pill'  => __( 'Unlocks security scans' ),
			'title' => __( 'Keep threats off your site' ),
			'lede'  => __( 'Daily malware scans come with Business, and most fixes run on their own.' ),
			'rows'  => array( 'scans' ),
		),
		'performance' => array(
			'tier'  => 'Business',
			'pill'  => __( 'Unlocks performance' ),
			'title' => __( 'Track speed and traffic' ),
			'lede'  => __( 'Performance metrics come with Business — requests per minute and response times, over any range.' ),
			'rows'  => array( 'logs' ),
		),
		'logs-php'    => array(
			'tier'  => 'Business',
			'pill'  => __( 'Unlocks PHP logs' ),
			'title' => __( 'Find what broke a page' ),
			'lede'  => __( 'PHP error logs come with Business — every fatal, warning, and notice, with the file behind it.' ),
			'rows'  => array( 'logs' ),
		),
		'logs-server' => array(
			'tier'  => 'Business',
			'pill'  => __( 'Unlocks server logs' ),
			'title' => __( 'See every request to your site' ),
			'lede'  => __( 'Web server logs come with Business — status codes and response times for every page a visitor loads.' ),
			'rows'  => array( 'logs' ),
		),
		'advanced'    => array(
			'tier'  => 'Business',
			'pill'  => __( 'Unlocks server access' ),
			'title' => __( 'Reach your files and database' ),
			'lede'  => __( 'SFTP, SSH, phpMyAdmin, and PHP version controls come with Business.' ),
			'rows'  => array( 'access' ),
		),
		// Not a hosting feature and not Business: the activity log is on every
		// plan, and what a paid plan adds is history and filtering. Pointing this
		// one at Business would be selling the wrong thing.
		'activity'    => array(
			'tier'  => 'Personal',
			'pill'  => __( 'Unlocks history' ),
			'title' => __( 'Keep more of your history' ),
			'lede'  => __( 'Filters, date ranges, and 30 days of activity come with every paid plan.' ),
			'rows'  => array(),
		),
	);
}

function untangling_get_need() {
	if ( empty( $_GET['need'] ) ) {
		return '';
	}
	$need  = sanitize_key( wp_unslash( $_GET['need'] ) );
	$needs = untangling_hosting_needs();
	return isset( $needs[ $need ] ) ? $need : '';
}

// MSD site-overview slug for "Go to Site Overview" links.
function untangling_get_site_slug() {
	return defined( 'UNTANGLING_SITE_SLUG' ) ? UNTANGLING_SITE_SLUG : 'aperture-diaries.com';
}

// Real site status, in MSD's site-visibility terms + Badge intents
// (settings-site-visibility/summary.tsx): Public → success, Private →
// neutral. Coming soon (warning) has no core equivalent on Studio sites.
// "Add a domain" reuses the production flow: the dashboard's AddDomainButton
// (client/dashboard/domains/add-domain-button.tsx) does a full-page redirect to
// the stepper's fullscreen domain search at /setup/domain. Same target here.
//
// Deliberately WITHOUT siteSlug, even though production sends it. The domain
// flow's useAssertConditions holds the step in CHECKING until the site resolves
// in Redux (flows/domain/domain.ts), and every site in this prototype is a mock
// that the real API returns nothing for — passing our slug left the step on its
// loading bar forever. With no slug the assert short-circuits to SUCCESS and the
// real search renders. Trade-off: the flow then treats it as a domain-first
// purchase, so picking a domain leads to "Choose how to use your domain"
// instead of plans → checkout.
// The wp-admin URL currently being rendered, for round-tripping through a
// Calypso flow. Falls back to the variant's home when REQUEST_URI is unusable.
function untangling_current_admin_url() {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	if ( ! $uri || 0 !== strpos( $uri, '/wp-admin/' ) ) {
		return untangling_plan_flow_home_url();
	}
	return home_url( $uri );
}

// Plan-only marketplace steps default their exits here: the core Dashboard in
// the dashboard variant, the My Site page in the drawer variant
// (untangling-hosting is retired in both), the Hosting page everywhere else,
// where it is still the live brand anchor.
function untangling_plan_flow_home_url() {
	switch ( untangling_get_variant() ) {
		case 'dashboard':
			return admin_url( 'index.php' );
		case 'drawer':
			return admin_url( 'admin.php?page=untangling-mysite' );
		default:
			return admin_url( 'admin.php?page=untangling-hosting' );
	}
}

// MSD return URLs must survive wp_validate_redirect, which allows only the
// site's own host. Core compares hosts without ports, so bare hostnames
// cover :3333; the configured MSD host covers remote demos (calypso.live).
add_filter( 'allowed_redirect_hosts', function ( $hosts ) {
	$extra = array( 'my.localhost', 'calypso.localhost' );
	$msd_host = wp_parse_url( UNTANGLING_MSD_URL, PHP_URL_HOST );
	if ( $msd_host ) {
		$extra[] = $msd_host;
	}
	return array_merge( $hosts, $extra );
} );

function untangling_domain_search_url( $pricing_args = array( 'ctx' => 'ms' ) ) {
	return add_query_arg(
		array(
			'dashboard' => 'dotcom',
			// Where the stepper hands the chosen domain back to. The prototype
			// owns pricing and checkout, so the Calypso flow is used for the
			// domain search alone — one pricing page and one checkout across
			// the whole prototype, whichever entry point started the flow.
			// $pricing_args picks the plan namespace: the My Site drawer passes
			// ctx=ms (default), the sidebar upsell prices the shared demo plan.
			// The pricing page's ✕ needs its own return target — back_to below
			// only serves the Calypso step's Back button and never reaches the
			// prototype. Before the merge so callers can override it.
			'untangling_pricing' => rawurlencode( untangling_marketplace_url( 'themes', array_merge( array( 'ustep' => 'pricing', 'back' => rawurlencode( untangling_current_admin_url() ) ), $pricing_args ) ) ),
			// Back should return to the screen the visitor actually left, not a
			// fixed landing — the Email card sits on the Plan section, so a
			// hardcoded page= dropped them a section away on the way back.
			// add_query_arg does not encode values, and this one carries its own
			// query string — encode it or `page=` breaks out as a sibling arg.
			'back_to'   => rawurlencode( untangling_current_admin_url() ),
		),
		UNTANGLING_MSD_URL . '/setup/domain'
	);
}

function untangling_get_visibility() {
	if ( (int) get_option( 'blog_public', 1 ) < 0 ) {
		return array( 'label' => 'Private', 'tone' => 'neutral', 'tip' => 'Only you and approved members can view your site.' );
	}
	return array( 'label' => 'Public', 'tone' => 'success', 'tip' => 'Anyone can view your site.' );
}

function untangling_get_primary_domain() {
	return defined( 'UNTANGLING_PRIMARY_DOMAIN' ) ? UNTANGLING_PRIMARY_DOMAIN : 'aperture-diaries.com';
}

function untangling_get_domain_upsell() {
	return defined( 'UNTANGLING_DOMAIN_UPSELL' ) ? UNTANGLING_DOMAIN_UPSELL : 'aperture.blog';
}

// The MSD upsell diamond (client/dashboard/components/icons → `upsell`) for
// PHP-rendered upgrade CTAs; the React side renders the same path from
// OV_ICONS.upsell. The 24×24 icon has heavy built-in padding (the glyph is
// only ~14×12), so the viewBox is cropped to the glyph here — core .button
// labels sit right next to the icon box and the padding read as misalignment.
// Sized by the .untangling-upsell-diamond global rule.
function untangling_upsell_diamond() {
	return '<svg class="untangling-upsell-diamond" xmlns="http://www.w3.org/2000/svg" viewBox="4.4 5.4 15.2 13.2" aria-hidden="true"><path d="M18.9397 9.87999L15.4197 6.06999L15.3597 6.00999C15.2897 5.93999 15.1997 5.89999 15.0997 5.89999H8.87973C8.77973 5.89999 8.68973 5.93999 8.61973 6.00999L5.05973 9.87999C4.93973 10.01 4.93973 10.21 5.05973 10.34L11.5397 17.86C11.6497 17.99 11.8197 18.07 11.9997 18.07C12.1797 18.07 12.3397 17.99 12.4597 17.86L18.9397 10.34C19.0597 10.21 19.0497 10.01 18.9397 9.87999ZM15.4097 7.53999L17.3297 9.63999H15.1697L15.4097 7.53999ZM14.4297 6.83999L14.1097 9.63999H10.2897L9.64973 6.83999H14.4297ZM8.68973 7.42999L9.19973 9.63999H6.66973L8.68973 7.42999ZM6.61973 10.6H9.42973L10.8397 15.49L6.61973 10.6ZM12.0397 15.87L10.5297 10.6H13.8597L12.0397 15.87ZM14.9697 10.6H17.3797L13.3697 15.24L14.9697 10.6Z"/></svg>';
}

// What each plan includes, as {label, tip} pairs — the plan card lists them
// with the same hover tooltips as the pricing page, so a visitor can read
// what a feature actually means without leaving the card. Eight per plan
// (four rows in the card's two-column grid); copy follows
// untangling_plan_pricing() and wordpress.com/pricing.
function untangling_plan_card_features( $plan ) {
	$domain      = array( 'Free domain for one year', sprintf( 'Get a custom domain – like %s – free for the first year.', untangling_get_domain_upsell() ) );
	$storage_tip = 'Upload more images, videos, audio, and documents to your website.';
	$ad_free     = array( 'Ad-free experience', 'Your visitors browse ad-free. WordPress.com ads are removed from your site.' );
	$unlimited   = array( 'Unlimited pages, posts, and users', 'Create as much content as you want and invite as many collaborators as you need.' );
	$premium_themes  = array( 'All premium themes', 'Install any premium theme from the WordPress.com showcase.' );
	$install_plugins = array( 'Install plugins and themes', 'Install any of the thousands of WordPress plugins and themes.' );
	$sftp            = array( 'SFTP/SSH and database access', 'Developer access to your site’s files and database.' );
	$priority        = array( '24/7 priority support', 'Round-the-clock priority support from our expert team.' );
	$backups         = array( 'Real-time backups and one-click restores', 'Every change saved; restore any moment with one click.' );

	$features = array(
		'Free'     => array(
			array( 'Free .wordpress.com address', 'Get a free site address like yoursite.wordpress.com.' ),
			array( '1 GB storage', 'Room for your images and documents.' ),
			array( 'Dozens of free themes', 'Pick from dozens of free themes to style your site.' ),
			array( 'Community support', 'Get help from community forums and guides.' ),
			array( 'Unlimited pages and posts', 'Write as much as you want — there is no cap on your content.' ),
			array( 'Built-in newsletter', 'Send every new post to your subscribers by email.' ),
			array( 'Spam protection with Akismet', 'Akismet filters spam out of your comments automatically.' ),
			array( 'Managed hosting and updates', 'We run the servers and keep WordPress up to date for you.' ),
		),
		'Personal' => array(
			$domain,
			array( '6 GB storage', $storage_tip ),
			$ad_free,
			array( 'Fast email support', 'Email our Happiness Engineers and get unblocked quickly.' ),
			array( 'Personal-tier themes', 'Unlock every theme in the Personal tier of the showcase.' ),
			array( 'Dozens of premium plugins', 'Install dozens of premium plugins from the WordPress.com marketplace.' ),
			array( 'Style customization', 'Fine-tune colors, fonts, and layout in the site editor.' ),
			$unlimited,
		),
		'Premium'  => array(
			$premium_themes,
			array( '13 GB storage', $storage_tip ),
			$domain,
			array( 'Live chat support', 'Chat with our Happiness Engineers in real time.' ),
			array( 'Payments and paid subscriptions', 'Collect payments, donations, and paid subscriptions with PayPal and Stripe.' ),
			array( 'Premium stats and analytics', 'See where your visitors come from and what they read.' ),
			array( 'Upload videos', 'Ad-free, high-definition video hosting with VideoPress.' ),
			$ad_free,
		),
		// Ordered for the people this prototype is built around — bloggers and
			// creators — so the card leads with reach, design, and income rather
			// than the server-level features. SFTP, staging, and edge caching are
			// still Business; they live on the Hosting page, where a developer
			// goes looking for them.
			'Business' => array(
			$install_plugins,
			$premium_themes,
			array( 'Advanced SEO tools', 'Control how your posts appear in search results and on social.' ),
			array( 'Payments and paid subscriptions', 'Collect payments, donations, and paid subscriptions with PayPal and Stripe.' ),
			array( 'Google Analytics integration', 'See who is reading, where they came from, and what they do next.' ),
			array( '50 GB storage', $storage_tip ),
			$backups,
			$priority,
		),
		'Commerce' => array(
			array( 'Sell products and subscriptions', 'Sell physical and digital goods and recurring subscriptions.' ),
			array( 'Premium store designs', 'Professionally designed store themes built for selling.' ),
			$install_plugins,
			array( '50 GB storage', $storage_tip ),
			array( 'Store analytics', 'Track sales, revenue, and your best-selling products.' ),
			array( 'Sell in 60+ countries', 'Accept payments and ship worldwide.' ),
			$sftp,
			$priority,
		),
	);
	$list = isset( $features[ $plan ] ) ? $features[ $plan ] : $features['Free'];
	return array_map(
		function ( $feature ) {
			return array( 'label' => $feature[0], 'tip' => $feature[1] );
		},
		$list
	);
}

// Plan-dependent card data for the WordPress.com page. Pass a plan to read
// another scope's plan (the My Site drawer keeps its own override).
function untangling_get_plan_meta( $for_plan = null ) {
	$plans = array(
		'Free'     => array(
			'renew'    => 'No expiration, free forever',
			// No note: at 70% the bar already says it, and the line only
			// belongs here when storage is actually in warning territory.
			'storage'  => array( 0.7, 1, null ),
		),
		'Personal' => array(
			'renew'    => 'Renews March 14, 2027',
			'storage'  => array( 1.4, 6, null ),
		),
		'Premium'  => array(
			'renew'    => 'Renews March 14, 2027',
			'storage'  => array( 4.2, 13, null ),
		),
		'Business' => array(
			'renew'    => 'Renews March 14, 2027',
			// The 4th entry is the "needs attention" flag that drove the My Site
			// AttentionCard (storage almost full). Dropped on 2026-08-18: a
			// storage alarm is not a next step, and Atomic (Business) opened on
			// a red banner before the page had said anything else. The note on
			// the storage meter still carries the pressure where storage lives.
			'storage'  => array( 41.8, 50, 'Almost full. New uploads may fail.' ),
		),
		'Commerce' => array(
			'renew'    => 'Renews March 14, 2027',
			'storage'  => array( 22.4, 50, null ),
		),
	);
	$plan = $for_plan && isset( $plans[ $for_plan ] ) ? $for_plan : untangling_get_plan();
	$meta = $plans[ $plan ];
	$meta['features'] = untangling_plan_card_features( $plan );
	return $meta;
}

// Data for the Plan & products compare card: the plan you are on, paired
// against the one tier we would actually recommend next. Both columns carry
// five rows on the same five axes in the same order, so the upgrade column
// reads as a line-by-line answer to what you already have rather than a
// feature dump. A plan can appear in two pairs with different rows, because
// the question changes with the tier above it — Premium answers Free on reach
// and design, and answers Business on scale.
//
// The recommended target is not simply rank + 1: Free skips Personal and is
// answered by Premium (the tier the pricing page recommends), and Business is
// answered by Commerce, because a site that outgrows Business is nearly always
// growing a store. Commerce has nothing above it and gets no compare card.
function untangling_plan_compare( $plan = null ) {
	$plan = $plan ? $plan : untangling_get_plan();

	$prices = array(
		'Free'     => 'US$0/month',
		'Personal' => 'US$4/month, billed annually',
		'Premium'  => 'US$8/month, billed annually',
		'Business' => 'US$25/month, billed annually',
		'Commerce' => 'US$45/month, billed annually',
	);

	$storage_tip = __( 'Upload more images, videos, audio, and documents to your website.' );
	$support_247 = array( __( '24/7 priority support' ), __( 'Round-the-clock priority support from our expert team.' ) );

	// Keyed by the plan you are on: the tier we recommend next, then the two
	// columns of paired rows (yours first, theirs second).
	$pairs = array(
		'Free' => array(
			'Premium',
			array(
				array( __( '1 GB storage' ), __( 'Room for your images, documents, and other media.' ) ),
				array( __( 'Dozens of free themes' ), __( 'Choose from dozens of professionally designed free themes.' ) ),
				array( __( 'Free .wordpress.com address' ), sprintf( __( 'Your site address ends in .wordpress.com, like %s.' ), untangling_get_site_slug() . '.wordpress.com' ) ),
				array( __( 'WordPress.com ads displayed' ), __( 'Free sites display WordPress.com ads to visitors.' ) ),
				array( __( 'Community support' ), __( 'Get help from support guides and the community forums.' ) ),
			),
			array(
				array( __( '13 GB storage' ), $storage_tip ),
				array( __( 'All premium themes' ), __( 'Install any premium theme from the WordPress.com showcase.' ) ),
				array( __( 'Free domain for one year' ), sprintf( __( 'Get a custom domain – like %s – free for the first year.' ), untangling_get_domain_upsell() ) ),
				array( __( 'Ad-free experience' ), __( 'Your visitors browse ad-free. WordPress.com ads are removed from your site.' ) ),
				array( __( 'Fast support from our expert team' ), __( 'Fast email support from our expert team of Happiness Engineers.' ) ),
			),
		),
		'Personal' => array(
			'Premium',
			array(
				array( __( '6 GB storage' ), $storage_tip ),
				array( __( 'Personal-tier themes' ), __( 'Unlock every theme in the Personal tier of the showcase.' ) ),
				array( __( 'Dozens of premium plugins' ), __( 'Install dozens of premium plugins from the WordPress.com marketplace.' ) ),
				array( __( 'Fast email support' ), __( 'Email our Happiness Engineers and get unblocked quickly.' ) ),
				array( __( 'Ad-free experience' ), __( 'Your visitors browse ad-free. WordPress.com ads are removed from your site.' ) ),
			),
			array(
				array( __( '13 GB storage' ), $storage_tip ),
				array( __( 'All premium themes' ), __( 'Install any premium theme from the WordPress.com showcase.' ) ),
				array( __( 'All premium plugins' ), __( 'Install any premium plugin from the WordPress.com marketplace.' ) ),
				array( __( 'Live chat support' ), __( 'Chat with our Happiness Engineers in real time.' ) ),
				array( __( 'Payments and paid subscriptions' ), __( 'Collect payments, donations, and paid subscriptions with PayPal and Stripe.' ) ),
			),
		),
		'Premium' => array(
			'Business',
			array(
				array( __( '13 GB storage' ), $storage_tip ),
				array( __( 'All premium themes' ), __( 'Install any premium theme from the WordPress.com showcase.' ) ),
				array( __( 'Premium stats and analytics' ), __( 'See where your visitors come from and what they read.' ) ),
				array( __( 'Live chat support' ), __( 'Chat with our Happiness Engineers in real time.' ) ),
				array( __( 'Payments and paid subscriptions' ), __( 'Collect payments, donations, and paid subscriptions with PayPal and Stripe.' ) ),
			),
			array(
				array( __( '50 GB storage' ), $storage_tip ),
				array( __( 'Install any plugin or theme' ), __( 'Install any of the thousands of WordPress plugins and themes.' ) ),
				array( __( 'Advanced SEO and Google Analytics' ), __( 'Control how your posts appear in search, and see what readers do next.' ) ),
				$support_247,
				array( __( 'Real-time backups and one-click restores' ), __( 'Every change saved; restore any moment with one click.' ) ),
			),
		),
		// Business is answered on selling, not on servers: the site is already
		// on the tier that unlocks plugins, SFTP, and staging, so the only
		// thing left to grow into is a store.
		'Business' => array(
			'Commerce',
			array(
				array( __( 'Accept payments and donations' ), __( 'Collect payments, donations, and paid subscriptions with PayPal and Stripe.' ) ),
				array( __( 'Install any plugin or theme' ), __( 'Install any of the thousands of WordPress plugins and themes.' ) ),
				array( __( 'Advanced SEO tools' ), __( 'Control how your posts appear in search results and on social.' ) ),
				array( __( 'Google Analytics integration' ), __( 'See who is reading, where they came from, and what they do next.' ) ),
				$support_247,
			),
			array(
				array( __( 'Sell products and subscriptions' ), __( 'Sell physical and digital goods and recurring subscriptions.' ) ),
				array( __( 'Premium store designs and plugins' ), __( 'Store themes and premium store plugins built for selling.' ) ),
				array( __( 'Sell and ship in 60+ countries' ), __( 'Accept payments and ship worldwide.' ) ),
				array( __( 'Store analytics' ), __( 'Track sales, revenue, and your best-selling products.' ) ),
				$support_247,
			),
		),
	);

	// The badge on the upgrade column. Commerce is the one target where the
	// generic "Recommended" wasted the only line that could say why this plan
	// and not the next one along: it is the store plan, so the badge says so.
	$pills = array(
		'Commerce' => __( 'Made for stores' ),
	);

	$label = function ( $rows ) {
		return array_map(
			function ( $row ) {
				return array( 'label' => $row[0], 'tip' => $row[1] );
			},
			$rows
		);
	};

	if ( ! isset( $pairs[ $plan ] ) ) {
		return array( 'current' => null, 'next' => null );
	}

	list( $next, $mine, $theirs ) = $pairs[ $plan ];

	return array(
		'current' => array(
			'name'     => $plan,
			'price'    => $prices[ $plan ],
			'features' => $label( $mine ),
		),
		'next'    => array(
			'name'     => $next,
			'price'    => $prices[ $next ],
			'features' => $label( $theirs ),
			'pill'     => isset( $pills[ $next ] ) ? $pills[ $next ] : __( 'Recommended' ),
		),
	);
}

// Simple vs Atomic default follows the plan (Free/Premium sites are Simple);
// the ?untangling_site_type= override still wins once used.
function untangling_default_site_type() {
	return in_array( untangling_get_plan(), array( 'Free', 'Premium' ), true ) ? 'simple' : 'atomic';
}

// The MSD site-card previews load the front end with ?iframe=true (same
// params wpcom uses); keep the auto-logged-in admin bar out of them.
add_action( 'init', function () {
	if ( isset( $_GET['iframe'] ) || isset( $_GET['preview'] ) ) {
		add_filter( 'show_admin_bar', '__return_false' );
	}
} );

// Let the MSD (my.localhost:3333) hydrate site cards from this site's REST API.
add_filter( 'rest_pre_serve_request', function ( $served ) {
	$origin = get_http_origin();
	if ( UNTANGLING_MSD_URL === $origin ) {
		header( 'Access-Control-Allow-Origin: ' . UNTANGLING_MSD_URL );
		header( 'Access-Control-Allow-Methods: GET, OPTIONS' );
		header( 'Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages' );
		header( 'Vary: Origin', false );
	}
	return $served;
}, 20 );

// The MSD omnibar mirrors this site's admin-bar upsell pill; serve it the same
// offer the bar renders so the two surfaces never disagree. The upsell URL
// builders read REQUEST_URI for their back link — inside REST that would be
// /wp-json/…, so point it at the My Site page for the duration of the call.
add_action( 'rest_api_init', function () {
	register_rest_route( 'untangling/v1', '/upsell', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function () {
			$offer                  = untangling_upsell_offer();
			$request_uri            = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
			$_SERVER['REQUEST_URI'] = 'dashboard' === untangling_get_variant()
				? '/wp-admin/index.php'
				: '/wp-admin/admin.php?page=untangling-mysite';
			$href                   = untangling_upsell_url( 'omnibar' );
			$_SERVER['REQUEST_URI'] = $request_uri;
			return array(
				'active' => 'omnibar' === untangling_get_active_upsell(),
				'pill'   => $offer['pill'],
				'text'   => $offer['text'],
				'gem'    => $offer['gem'],
				'href'   => $href,
			);
		},
	) );
} );

// Local demo only: the MSD → WP Admin jump must not hit a login wall.
// Guarded to localhost so this never does anything on a real host.
add_action( 'init', function () {
	if ( is_user_logged_in() ) {
		return;
	}
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	if ( 'localhost' !== $host && '127.0.0.1' !== $host ) {
		return;
	}
	$user = get_user_by( 'id', 1 );
	if ( $user ) {
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID );
		// auth_redirect() validates cookies from the request, so make them
		// visible to this request too, not only the response.
		$expiration                     = time() + 2 * DAY_IN_SECONDS;
		$_COOKIE[ AUTH_COOKIE ]         = wp_generate_auth_cookie( $user->ID, $expiration, 'auth' );
		$_COOKIE[ SECURE_AUTH_COOKIE ]  = wp_generate_auth_cookie( $user->ID, $expiration, 'secure_auth' );
		$_COOKIE[ LOGGED_IN_COOKIE ]    = wp_generate_auth_cookie( $user->ID, $expiration, 'logged_in' );
	}
} );

/**
 * Menu variant. Two live designs, switchable from the Prototype controls
 * panel (?untangling_variant=dashboard|drawer, persisted):
 * - 'dashboard' (default): the all-in core Dashboard — activity log, backups,
 *   checklist etc. as index.php widgets; no My Site parent, Plan & products
 *   as its own top-level item, hosting lives on the Dashboard as MSD
 *   previews.
 * - 'drawer': the My Site drawer — a My Site item directly below Dashboard
 *   whose four children are sidebar submenu links (the Untangle Calypso IA
 *   shape).
 * The 'submenu'/'plain' Hosting-page variants were retired; their render
 * paths survive only for UNTANGLING_FORCE_VARIANT (Playground demo configs).
 */
function untangling_get_variant() {
	if ( defined( 'UNTANGLING_FORCE_VARIANT' ) ) {
		return UNTANGLING_FORCE_VARIANT;
	}
	if ( ! untangling_is_locked_demo() && isset( $_GET['untangling_variant'] ) && in_array( $_GET['untangling_variant'], array( 'dashboard', 'drawer' ), true ) ) {
		update_option( 'untangling_variant', $_GET['untangling_variant'] );
	}
	return get_option( 'untangling_variant', 'dashboard' );
}

/**
 * Site type mimic: 'atomic' or 'simple'.
 * Simple sites get the same core screens as Atomic, with install/write
 * actions replaced by upgrade CTAs.
 * Switch with ?untangling_site_type=atomic|simple (persisted).
 */
function untangling_get_site_type() {
	if ( defined( 'UNTANGLING_FORCE_SITE_TYPE' ) ) {
		return UNTANGLING_FORCE_SITE_TYPE;
	}
	if ( ! untangling_is_locked_demo() && isset( $_GET['untangling_site_type'] ) && in_array( $_GET['untangling_site_type'], array( 'atomic', 'simple' ), true ) ) {
		// Toggling resets the My Site plan to the pure mapping (Simple = Free,
		// Atomic = Business) — a checkout override and its storage add-on
		// belong to the plan the demo just left behind.
		if ( $_GET['untangling_site_type'] !== get_option( 'untangling_site_type', untangling_default_site_type() ) ) {
			delete_option( 'untangling_ms_plan_override' );
			delete_option( 'untangling_ms_storage_addon' );
		}
		update_option( 'untangling_site_type', $_GET['untangling_site_type'] );
	}
	return get_option( 'untangling_site_type', untangling_default_site_type() );
}

function untangling_is_simple() {
	return 'simple' === untangling_get_site_type();
}

/**
 * Marketplace variant. 'fullscreen' (V1): themes AND plugins live in the
 * chromeless fullscreen Marketplace, linked from the sidebar and the WP.com
 * banners. 'split' (V2): the production-Atomic experience — plugins keep the
 * core-unified Marketplace tab in Add Plugins; Appearance → Themes stays
 * core's installed-themes screen and Appearance → Theme Showcase renders the
 * catalog in the content area, with theme details in the admin chrome too
 * (section 3f). 'tabs' (V3): fully in-admin — Add Themes gets a Marketplace
 * tab like Add Plugins, the Theme Showcase sidebar entry disappears, and both
 * banners upsell plans. The fullscreen page keeps serving the
 * pricing/checkout steps (and V2's plugin details) only.
 * Switch with ?untangling_marketplace=fullscreen|split|tabs (persisted), or
 * from the Prototype controls.
 */
function untangling_get_marketplace_mode() {
	if ( defined( 'UNTANGLING_FORCE_MARKETPLACE' ) ) {
		return UNTANGLING_FORCE_MARKETPLACE;
	}
	if ( ! untangling_is_locked_demo() && isset( $_GET['untangling_marketplace'] ) && in_array( $_GET['untangling_marketplace'], array( 'fullscreen', 'split', 'tabs' ), true ) ) {
		update_option( 'untangling_marketplace', $_GET['untangling_marketplace'] );
	}
	return get_option( 'untangling_marketplace', 'tabs' );
}

function untangling_marketplace_url( $tab = 'themes', $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => 'untangling-marketplace', 'mkt' => $tab ), $args ), admin_url( 'admin.php' ) );
}

/**
 * Plan filter for the V3 Marketplace tabs — two comparable treatments,
 * switched from Prototype controls: 'included' renders an
 * All plans / Included-with-my-plan link pair under the category row;
 * 'dropdown' renders a right-aligned tier select on the category row.
 * Switch with ?untangling_plan_filter=included|dropdown (persisted).
 */
function untangling_get_plan_filter() {
	if ( defined( 'UNTANGLING_FORCE_PLAN_FILTER' ) ) {
		return UNTANGLING_FORCE_PLAN_FILTER;
	}
	if ( ! untangling_is_locked_demo() && isset( $_GET['untangling_plan_filter'] ) && in_array( $_GET['untangling_plan_filter'], array( 'included', 'dropdown' ), true ) ) {
		update_option( 'untangling_plan_filter', $_GET['untangling_plan_filter'] );
	}
	return get_option( 'untangling_plan_filter', 'included' );
}

/**
 * Upsell placement — three comparable homes for the same nudge,
 * switched from Prototype controls:
 *   'menu-top'  the classic spot, above the menu, redesigned for a 160px
 *               dark column instead of the white card that ships today;
 *   'menu-foot' pinned below the menu, out of the navigation's way;
 *   'omnibar'   no card at all — a pill in the admin bar.
 * 'none' turns it off. Switch with
 * ?untangling_upsell=none|menu-top|menu-foot|omnibar (persisted).
 */
function untangling_get_upsell_placement() {
	$allowed = array( 'none', 'menu-top', 'menu-foot', 'omnibar' );
	if ( defined( 'UNTANGLING_FORCE_UPSELL' ) ) {
		return in_array( UNTANGLING_FORCE_UPSELL, $allowed, true ) ? UNTANGLING_FORCE_UPSELL : 'none';
	}
	if ( ! untangling_is_locked_demo() && isset( $_GET['untangling_upsell'] ) && in_array( $_GET['untangling_upsell'], $allowed, true ) ) {
		update_option( 'untangling_upsell', $_GET['untangling_upsell'] );
	}
	return get_option( 'untangling_upsell', 'none' );
}

/**
 * What actually renders. Atomic sites are Business in this prototype — there
 * is no free domain left to sell them — so the nudge is Simple-only. The
 * chosen placement still persists while you are on Atomic; it just goes quiet.
 */
function untangling_get_active_upsell() {
	return untangling_get_upsell_placement();
}

// One nudge component, two offers: what it sells follows the site type.
// Simple has nothing attached yet, so it sells the annual plan (free domain);
// Atomic is already on Business, so it sells the two-year renewal instead.
// Same card, same button, same pill — only the words and the landing change.
function untangling_upsell_offer() {
	if ( untangling_is_simple() ) {
		return array(
			'text' => __( 'Free domain with an annual plan' ),
			'cta'  => __( 'Upgrade' ),
			'gem'  => true,
			'pill' => __( 'Free domain' ),
		);
	}
	// Benefit first so the number never ends the sentence (no "20%" orphan on
	// the narrow card); the NBSP keeps "two years" together on the last line.
	return array(
		'text' => __( 'Save 20% when you renew for two years' ),
		'cta'  => __( 'Renew now' ),
		'gem'  => true,
		'pill' => __( 'Save 20%' ),
	);
}

// Where every placement sends you — each offer enters at the step its promise
// starts at. Simple sells a free domain, and a domain starts with picking a
// name: enter the real Calypso domain search, which hands the chosen name to
// the shared pricing step and on into the shared checkout. Atomic sells the
// two-year renewal of the plan already owned — there is nothing to choose, so
// it goes straight to checkout with the 20% discount itemized in the cart.
function untangling_upsell_url( $placement ) {
	if ( ! untangling_is_simple() ) {
		$plan = untangling_get_plan();
		return untangling_marketplace_url( 'themes', array(
			'ustep' => 'checkout',
			'flow'  => 'renew',
			// Renewing Free is not a thing — if the demo plan was reset under
			// an Atomic site type, sell the Premium renewal instead.
			'plan'  => 'Free' === $plan ? 'Premium' : $plan,
			'from'  => $placement,
			'back'  => rawurlencode( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : admin_url() ),
		) );
	}
	return untangling_domain_search_url( array(
		'ref'  => 'domain-upsell',
		'from' => $placement,
	) );
}

/* -------------------------------------------------------------------------
 * My Site drawer state — every option the drawer variant touches is
 * namespaced `untangling_ms_*`, on purpose: the submenu/plain variants and
 * the Marketplace keep their own shared demo state (untangling_plan_override
 * and friends), and interacting with one variant must never bleed into
 * another. The drawer reads and writes only these keys.
 * ---------------------------------------------------------------------- */

// The drawer's plan follows the Site type toggle — Simple presents the real
// Free plan, Atomic the real Business plan — so both variants read as real
// sites. A checkout "purchase" (ctx=ms → untangling_ms_plan_override) wins
// until the site type is toggled or the demo is reset; it never touches
// untangling_plan_override.
function untangling_ms_get_plan() {
	$override = get_option( 'untangling_ms_plan_override' );
	if ( in_array( $override, array( 'Free', 'Personal', 'Premium', 'Business', 'Commerce' ), true ) ) {
		return $override;
	}
	return untangling_is_simple() ? 'Free' : 'Business';
}

// Just created vs Established. The override (Prototype controls) wins; with
// no override the site is Established exactly when the launchpad is complete.
function untangling_ms_get_state() {
	if ( defined( 'UNTANGLING_FORCE_MS_STATE' ) ) {
		return UNTANGLING_FORCE_MS_STATE;
	}
	$override = get_option( 'untangling_ms_state' );
	if ( in_array( $override, array( 'new', 'established' ), true ) ) {
		return $override;
	}
	return get_option( 'untangling_ms_lp_complete' ) ? 'established' : 'new';
}

/**
 * Hosting health for the Hosting page's state cards. The MSD derives this from
 * real activity (last backup activity name, scan threat count); nothing here
 * has a backup or a scanner to report on, so the scenario is a demo switch.
 * 'ok' is every card green; 'attention' is the failure branch MSD renders when
 * a backup errors or the scanner finds threats.
 */
function untangling_ms_hosting_state() {
	if ( defined( 'UNTANGLING_FORCE_MS_HOSTING' ) ) {
		return UNTANGLING_FORCE_MS_HOSTING;
	}
	$override = get_option( 'untangling_ms_hosting' );
	return in_array( $override, array( 'ok', 'attention' ), true ) ? $override : 'ok';
}

function untangling_plan_filter_styles() {
	static $printed = false;
	if ( $printed ) {
		return;
	}
	$printed = true;
	?>
	<style>
	.untangling-filter-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin: 12px 0 20px; }
	.untangling-filter-row .subsubsub { float: none; margin: 0; }
	.untangling-plan-view { display: flex; align-items: center; gap: 8px; }
	.untangling-plan-view select { min-width: 200px; }
	ul.subsubsub.untangling-plan-filters { white-space: nowrap; }
	.untangling-plan-label { color: #646970; margin-inline-end: 4px; }
	.untangling-upsell-hero, .untangling-marketplace { clear: both; }
	</style>
	<?php
}

function untangling_plan_filter_links( $total, $included_count ) {
	untangling_plan_filter_styles();
	?>
	<ul class="subsubsub untangling-plan-filters">
		<li><span class="untangling-plan-label"><?php esc_html_e( 'Plan:' ); ?></span></li>
		<li><a href="#" data-plan="all" class="current" aria-current="page"><?php esc_html_e( 'All plans' ); ?> <span class="count">(<?php echo esc_html( number_format_i18n( $total ) ); ?>)</span></a> |</li>
		<li><a href="#" data-plan="included"><?php esc_html_e( 'Included with my plan' ); ?> <span class="count">(<?php echo esc_html( number_format_i18n( $included_count ) ); ?>)</span></a></li>
	</ul>
	<?php
}

function untangling_plan_filter_dropdown( $tiers ) {
	untangling_plan_filter_styles();
	?>
	<label class="untangling-plan-view">
		<span><?php esc_html_e( 'Plan' ); ?></span>
		<select data-plan-filter>
			<option value="all"><?php esc_html_e( 'All plans' ); ?></option>
			<option value="included"><?php esc_html_e( 'Included with my plan' ); ?></option>
			<?php foreach ( $tiers as $tier ) : ?>
				<option value="<?php echo esc_attr( $tier ); ?>"><?php echo esc_html( $tier ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<?php
}

// Demo-state params: the checkout mimic persists the purchased plan, the
// Marketplace persists activate/install mimics (never the real theme — the
// six sites keep their seeded look), Prototype controls reset everything.
add_action( 'admin_init', function () {
	if ( isset( $_GET['untangling_set_plan'] ) && in_array( $_GET['untangling_set_plan'], array( 'Personal', 'Premium', 'Business', 'Commerce' ), true ) ) {
		update_option( 'untangling_plan_override', $_GET['untangling_set_plan'] );
	}
	if ( isset( $_GET['untangling_activate_theme'] ) ) {
		update_option( 'untangling_mkt_active_theme', sanitize_key( $_GET['untangling_activate_theme'] ) );
	}
	if ( isset( $_GET['untangling_install_plugin'] ) ) {
		$installed   = (array) get_option( 'untangling_mkt_installed', array() );
		$installed[] = sanitize_key( $_GET['untangling_install_plugin'] );
		update_option( 'untangling_mkt_installed', array_values( array_unique( $installed ) ) );
	}
	// Launchpad progress: the accordion persists done/skipped tasks with a
	// background GET so the sidebar submenu and page tabs can drop the
	// Launchpad entry once everything is complete.
	if ( isset( $_GET['untangling_lp_done'] ) ) {
		$done = array_values( array_filter( array_map( 'sanitize_key', explode( ',', (string) wp_unslash( $_GET['untangling_lp_done'] ) ) ) ) );
		update_option( 'untangling_lp_done', $done );
	}
	if ( isset( $_GET['untangling_lp_complete'] ) ) {
		update_option( 'untangling_lp_complete', $_GET['untangling_lp_complete'] ? 1 : 0 );
	}
	// My Site drawer state — namespaced writers; see the untangling_ms_*
	// helpers for why these never touch the shared keys above.
	if ( isset( $_GET['untangling_ms_set_plan'] ) && in_array( $_GET['untangling_ms_set_plan'], array( 'Personal', 'Premium', 'Business', 'Commerce' ), true ) ) {
		update_option( 'untangling_ms_plan_override', $_GET['untangling_ms_set_plan'] );
	}
	if ( isset( $_GET['untangling_ms_add_storage'] ) ) {
		if ( isset( untangling_storage_addon_pricing()[ (int) $_GET['untangling_ms_add_storage'] ] ) ) {
			update_option( 'untangling_ms_storage_addon', (int) $_GET['untangling_ms_add_storage'] );
		} elseif ( 0 === (int) $_GET['untangling_ms_add_storage'] ) {
			delete_option( 'untangling_ms_storage_addon' );
		}
	}
	if ( isset( $_GET['untangling_ms_lp_done'] ) ) {
		$ms_done = array_values( array_filter( array_map( 'sanitize_key', explode( ',', (string) wp_unslash( $_GET['untangling_ms_lp_done'] ) ) ) ) );
		update_option( 'untangling_ms_lp_done', $ms_done );
	}
	if ( isset( $_GET['untangling_ms_lp_complete'] ) ) {
		update_option( 'untangling_ms_lp_complete', $_GET['untangling_ms_lp_complete'] ? 1 : 0 );
	}
	if ( isset( $_GET['untangling_ms_state'] ) && in_array( $_GET['untangling_ms_state'], array( 'new', 'established' ), true ) ) {
		update_option( 'untangling_ms_state', $_GET['untangling_ms_state'] );
		// Jumping states from Prototype controls implies the launchpad moment:
		// Established means the checklist is behind you, Just created replays it.
		if ( 'established' === $_GET['untangling_ms_state'] ) {
			update_option( 'untangling_ms_lp_complete', 1 );
		} else {
			delete_option( 'untangling_ms_lp_done' );
			delete_option( 'untangling_ms_lp_complete' );
		}
	}
	if ( isset( $_GET['untangling_ms_hosting'] ) && in_array( $_GET['untangling_ms_hosting'], array( 'ok', 'attention' ), true ) ) {
		update_option( 'untangling_ms_hosting', $_GET['untangling_ms_hosting'] );
	}
	if ( isset( $_GET['untangling_ms_replay'] ) ) {
		delete_option( 'untangling_ms_lp_done' );
		delete_option( 'untangling_ms_lp_complete' );
		delete_option( 'untangling_ms_state' );
	}
	if ( isset( $_GET['untangling_reset_demo'] ) ) {
		delete_option( 'untangling_plan_override' );
		// Site type drives the My Site plan (Simple = Free, Atomic = Business),
		// so a full reset returns it to the config-derived default too.
		delete_option( 'untangling_site_type' );
		delete_option( 'untangling_mkt_active_theme' );
		delete_option( 'untangling_mkt_installed' );
		delete_option( 'untangling_lp_done' );
		delete_option( 'untangling_lp_complete' );
		delete_option( 'untangling_ms_plan_override' );
		delete_option( 'untangling_ms_lp_done' );
		delete_option( 'untangling_ms_lp_complete' );
		delete_option( 'untangling_ms_state' );
		delete_option( 'untangling_ms_storage_addon' );
		delete_option( 'untangling_ms_hosting' );
		delete_option( 'untangling_hosting_design' );
		delete_option( 'untangling_upsell' );
		delete_option( 'untangling_marketplace' );
		delete_option( 'untangling_plan_filter' );
		delete_option( 'untangling_variant' );
		// The dashboard variant's designed first look lives partly in user
		// meta (Screen Options hides, drag order, collapsed boxes) — a full
		// reset restores the curated defaults there too.
		$user_id = get_current_user_id();
		if ( $user_id ) {
			delete_user_meta( $user_id, 'metaboxhidden_dashboard' );
			delete_user_meta( $user_id, 'meta-box-order_dashboard' );
			delete_user_meta( $user_id, 'closedpostboxes_dashboard' );
			delete_user_meta( $user_id, 'screen_layout_dashboard' );
		}
	}
} );

/* -------------------------------------------------------------------------
 * 1. "Hosting" top-level menu — the merged Hosting +
 *    Upgrades brand anchor. Replaces My Home and the old Hosting/Upgrades
 *    entries; slug stays `untangling-hosting` so existing links keep working.
 * ---------------------------------------------------------------------- */

// The pre-drawer page is retired: nothing should ever render it in either
// live variant. It stays registered (below) only so old persisted links do
// not 404. Drawer: redirect it to the My Site page, keeping every other query
// arg (persisted `untangling_*` toggles) intact and mapping the old Help &
// Learn tab onto the drawer's `ms=help` section. Dashboard: the My Site page
// itself is also retired except its Plan & products (sidebar: Upgrades) and
// Help & Learn sections — everything else (next steps, hosting) lives on
// index.php as widgets, so those hits redirect there too.
add_action( 'admin_init', function () {
	$variant = untangling_get_variant();
	$page    = isset( $_GET['page'] ) ? $_GET['page'] : '';

	if ( 'drawer' === $variant && 'untangling-hosting' === $page ) {
		$args = $_GET;
		$args['page'] = 'untangling-mysite';

		if ( isset( $args['untangling_tab'] ) ) {
			if ( 'learn-more' === $args['untangling_tab'] ) {
				$args['ms'] = 'help';
			}
			unset( $args['untangling_tab'] );
		}

		wp_safe_redirect( admin_url( 'admin.php?' . http_build_query( $args ) ) );
		exit;
	}

	if ( 'dashboard' === $variant ) {
		$ms = isset( $_GET['ms'] ) ? $_GET['ms'] : '';
		$is_retired_mysite = 'untangling-mysite' === $page && ! in_array( $ms, array( 'plan', 'help' ), true );
		if ( 'untangling-hosting' === $page || $is_retired_mysite ) {
			$args = $_GET;
			unset( $args['page'], $args['ms'], $args['untangling_tab'] );
			wp_safe_redirect( admin_url( 'index.php' . ( $args ? '?' . http_build_query( $args ) : '' ) ) );
			exit;
		}
	}
} );

add_action( 'admin_menu', function () {
	$variant = untangling_get_variant();

	if ( 'dashboard' === $variant ) {
		// Both retired pages stay registered but hidden: untangling-hosting so
		// persisted links keep redirecting, untangling-mysite because its Plan
		// & products section still renders there (and its enqueue hook —
		// toplevel_page_untangling-mysite — only exists while the page is
		// registered).
		add_menu_page( __( 'Hosting' ), __( 'Hosting' ), 'manage_options', 'untangling-hosting', 'untangling_render_hosting_page', 'dashicons-cloud', 1 );
		remove_menu_page( 'untangling-hosting' );
		add_menu_page( __( 'My Site' ), __( 'My Site' ), 'manage_options', 'untangling-mysite', 'untangling_render_mysite_page', '', 2 );
		remove_menu_page( 'untangling-mysite' );

		// Upgrades keeps its own top-level anchor, directly below Dashboard —
		// a direct link into the retained Plan & products section (the page
		// keeps its name; the sidebar says what you go there to do). Next steps
		// and Hosting became index.php widgets. Help & Learn is the last item
		// of the Settings group (position 81: after Settings at 80, before
		// core's last separator at 99), a direct link into the retained help
		// section.
		add_menu_page( __( 'Upgrades' ), __( 'Upgrades' ), 'manage_options', 'admin.php?page=untangling-mysite&ms=plan', '', 'dashicons-cart', 2 );
		add_menu_page( __( 'Help & Learn' ), __( 'Help & Learn' ), 'manage_options', 'admin.php?page=untangling-mysite&ms=help', '', 'dashicons-editor-help', 81 );

		// Parity mocks keep their order: Stats, then Jetpack (both collide at
		// 3, first registered wins the earlier slot).
		add_menu_page( __( 'Stats' ), __( 'Stats' ), 'manage_options', UNTANGLING_MSD_URL . '/stats', '', 'dashicons-chart-bar', 3 );
		add_menu_page(
			__( 'Jetpack' ),
			__( 'Jetpack' ),
			'manage_options',
			'#',
			'',
			'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><path fill="#a7aaad" d="M16 0C7.2 0 0 7.2 0 16s7.2 16 16 16 16-7.2 16-16S24.8 0 16 0zm-1 19H7l8-16v16zm2 10V13h8l-8 16z"/></svg>' ),
			3
		);
		return;
	}

	if ( 'drawer' === $variant ) {
		// Drawer variant (Untangle Calypso IA): no Hosting brand anchor. The
		// old page stays registered but hidden so persisted links keep working.
		add_menu_page( __( 'Hosting' ), __( 'Hosting' ), 'manage_options', 'untangling-hosting', 'untangling_render_hosting_page', 'dashicons-cloud', 1 );
		remove_menu_page( 'untangling-hosting' );

		// My Site, directly below Dashboard (index.php holds position 2; a
		// colliding position lands just after the item it collides with). The
		// icon is the W mark — @wordpress/icons `wordpress`, the same glyph the
		// MSD uses on its WP Admin button. Sidebar SVGs inherit the menu color
		// through fill:currentColor.
		add_menu_page(
			__( 'My Site' ),
			__( 'My Site' ),
			'manage_options',
			'untangling-mysite',
			'untangling_render_mysite_page',
			'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-2 -2 24 24"><path fill="currentColor" d="M20 10c0-5.51-4.49-10-10-10C4.48 0 0 4.49 0 10c0 5.52 4.48 10 10 10 5.51 0 10-4.48 10-10zM7.78 15.37L4.37 6.22c.55-.02 1.17-.08 1.17-.08.5-.06.44-1.13-.06-1.11 0 0-1.45.11-2.37.11-.18 0-.37 0-.58-.01C4.12 2.69 6.87 1.11 10 1.11c2.33 0 4.45.87 6.05 2.34-.68-.11-1.65.39-1.65 1.58 0 .74.45 1.36.9 2.1.35.61.55 1.36.55 2.46 0 1.49-1.4 5-1.4 5l-3.03-8.37c.54-.02.82-.17.82-.17.5-.05.44-1.25-.06-1.22 0 0-1.44.12-2.38.12-.87 0-2.33-.12-2.33-.12-.5-.03-.56 1.2-.06 1.22l.92.08 1.26 3.41zM17.41 10c.24-.64.74-1.87.43-4.25.7 1.29 1.05 2.71 1.05 4.25 0 3.29-1.73 6.24-4.4 7.78.97-2.59 1.94-5.2 2.92-7.78zM6.1 18.09C3.12 16.65 1.11 13.53 1.11 10c0-1.3.23-2.48.72-3.59C3.25 10.3 4.67 14.2 6.1 18.09zm4.03-6.63l2.58 6.98c-.86.29-1.76.45-2.71.45-.79 0-1.57-.11-2.29-.33.81-2.38 1.62-4.74 2.42-7.1z"/></svg>' ),
			2
		);
		// Sidebar children, not tabs. The first replaces the auto-duplicated
		// parent entry; the rest are `admin.php?…` deep links (direct links).
		add_submenu_page( 'untangling-mysite', __( 'My Site' ), __( 'Next steps' ), 'manage_options', 'untangling-mysite', 'untangling_render_mysite_page' );
		add_submenu_page( 'untangling-mysite', __( 'Plan & products' ), __( 'Plan & products' ), 'manage_options', 'admin.php?page=untangling-mysite&ms=plan' );
		add_submenu_page( 'untangling-mysite', __( 'Hosting' ), __( 'Hosting' ), 'manage_options', 'admin.php?page=untangling-mysite&ms=hosting' );
		add_submenu_page( 'untangling-mysite', __( 'Help & Learn' ), __( 'Help & Learn' ), 'manage_options', 'admin.php?page=untangling-mysite&ms=help' );

		// Parity mocks keep their order below My Site: Stats, then Jetpack
		// (both collide at 3, first registered wins the earlier slot).
		add_menu_page( __( 'Stats' ), __( 'Stats' ), 'manage_options', UNTANGLING_MSD_URL . '/stats', '', 'dashicons-chart-bar', 3 );
		add_menu_page(
			__( 'Jetpack' ),
			__( 'Jetpack' ),
			'manage_options',
			'#',
			'',
			'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><path fill="#a7aaad" d="M16 0C7.2 0 0 7.2 0 16s7.2 16 16 16 16-7.2 16-16S24.8 0 16 0zm-1 19H7l8-16v16zm2 10V13h8l-8 16z"/></svg>' ),
			3
		);
		return;
	}

	add_menu_page(
		__( 'Hosting' ),
		__( 'Hosting' ),
		'manage_options',
		'untangling-hosting',
		'untangling_render_hosting_page',
		'dashicons-cloud',
		// First item in the sidebar, above Dashboard — the competitor-standard
		// brand-anchor position (Hostinger, GoDaddy, WP Engine, SiteGround).
		1
	);

	// Mock WP.com sidebar entries for visual parity with a real site. A URL
	// slug renders as a direct link; colliding positions land just after the
	// item they collide with (Stats after Dashboard, Jetpack after Hosting).
	add_menu_page( __( 'Stats' ), __( 'Stats' ), 'manage_options', UNTANGLING_MSD_URL . '/stats', '', 'dashicons-chart-bar', 2 );
	add_menu_page(
		__( 'Jetpack' ),
		__( 'Jetpack' ),
		'manage_options',
		'#',
		'',
		'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><path fill="#a7aaad" d="M16 0C7.2 0 0 7.2 0 16s7.2 16 16 16 16-7.2 16-16S24.8 0 16 0zm-1 19H7l8-16v16zm2 10V13h8l-8 16z"/></svg>' ),
		3
	);

	if ( 'submenu' === $variant ) {
		// The submenu mirrors the page tabs; `admin.php?…` slugs render as
		// direct links, so each item deep-links its tab.
		add_submenu_page( 'untangling-hosting', __( 'My site' ), __( 'My site' ), 'manage_options', 'untangling-hosting', 'untangling_render_hosting_page' );
		add_submenu_page( 'untangling-hosting', __( 'Help & Learn' ), __( 'Help & Learn' ), 'manage_options', 'admin.php?page=untangling-hosting&untangling_tab=learn-more' );
	}
} );

// Marketplace entry points. The page itself registers as a (hidden) top-level
// page so it lives at admin.php?page=untangling-marketplace; the sidebar items
// are plain deep links. This fullscreen Theme Showcase and Plugins →
// Marketplace are V1 only: V2 (split) has its own Theme Showcase submenu
// pointing at the in-admin showcase (section 3f), and V3 (tabs) gives themes
// an in-admin Marketplace tab instead.
add_action( 'admin_menu', function () {
	add_menu_page( __( 'Marketplace' ), __( 'Marketplace' ), 'manage_options', 'untangling-marketplace', 'untangling_render_marketplace_page' );
	remove_menu_page( 'untangling-marketplace' );

	if ( 'fullscreen' === untangling_get_marketplace_mode() ) {
		add_submenu_page( 'themes.php', __( 'Theme Showcase' ), __( 'Theme Showcase' ), 'manage_options', 'admin.php?page=untangling-marketplace&mkt=themes', '', 1 );
	}
	if ( 'fullscreen' === untangling_get_marketplace_mode() ) {
		// Second item in both dropdowns: Themes / Marketplace and
		// Installed Plugins / Marketplace.
		add_submenu_page( 'plugins.php', __( 'Marketplace' ), __( 'Marketplace' ), 'manage_options', 'admin.php?page=untangling-marketplace&mkt=plugins', '', 1 );
	}
}, 11 );

// Simple sites don't manage core/plugin updates themselves — hide the
// Updates screen in simple mode (the Omnibar's cross-site updates count
// stays, it belongs to the MSD).
add_action( 'admin_menu', function () {
	if ( untangling_is_simple() ) {
		remove_submenu_page( 'index.php', 'update-core.php' );
	}
}, 999 );

// Highlight the submenu item matching the open tab (deep-link param).
add_filter( 'submenu_file', function ( $submenu_file ) {
	if ( isset( $_GET['page'], $_GET['untangling_tab'] ) && 'untangling-hosting' === $_GET['page']
		&& in_array( $_GET['untangling_tab'], array( 'learn-more' ), true ) ) {
		return 'admin.php?page=untangling-hosting&untangling_tab=' . $_GET['untangling_tab'];
	}
	// My Site drawer sections are sidebar links, so the sidebar is the tab bar.
	if ( isset( $_GET['page'], $_GET['ms'] ) && 'untangling-mysite' === $_GET['page']
		&& in_array( $_GET['ms'], array( 'plan', 'hosting', 'help' ), true ) ) {
		return 'admin.php?page=untangling-mysite&ms=' . $_GET['ms'];
	}
	return $submenu_file;
} );

// Dashboard variant: Upgrades and Help & Learn are top-level direct links, so
// the pages they open (the retained My Site plan / help sections) must
// highlight their item — otherwise no sidebar entry lights up on them.
add_filter( 'parent_file', function ( $parent_file ) {
	if ( 'dashboard' === untangling_get_variant()
		&& isset( $_GET['page'], $_GET['ms'] ) && 'untangling-mysite' === $_GET['page']
		&& in_array( $_GET['ms'], array( 'plan', 'help' ), true ) ) {
		return 'admin.php?page=untangling-mysite&ms=' . $_GET['ms'];
	}
	return $parent_file;
} );

// Dashboard variant: a separator between Help & Learn and Collapse Menu. Core
// strips a trailing separator from $menu as the last step of
// wp-admin/includes/menu.php, after every menu hook, so Help & Learn cannot
// own one through the menu array. Put it back after menu.php has built the
// global and before menu-header.php prints it — admin_head sits between.
add_action( 'admin_head', function () {
	global $menu;
	if ( 'dashboard' !== untangling_get_variant() || ! is_array( $menu ) || empty( $menu ) ) {
		return;
	}
	$last = end( $menu );
	if ( isset( $last[4] ) && 'wp-menu-separator' === $last[4] ) {
		return;
	}
	$menu[] = array( '', 'read', 'separator-untangling-last', '', 'wp-menu-separator' );
} );

function untangling_render_hosting_page() {
	echo '<div class="untangling-app"><div id="untangling-root"></div></div>';
}

// The page is built with the WordPress Design System: @wordpress/components
// from the core bundle (Button, Card, ProgressBar, ToggleGroupControl…) plus
// the --wpds-* semantic tokens. Core doesn't ship @wordpress/theme yet, so the
// token values are declared locally under `.untangling-app` with the
// documented names, and the page CSS only consumes var(--wpds-*).
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'toplevel_page_untangling-hosting' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'wp-components' );
	wp_register_script( 'untangling-app', '', array( 'wp-element', 'wp-components', 'wp-i18n' ), '0.1.0', true );
	wp_enqueue_script( 'untangling-app' );
	wp_add_inline_script(
		'untangling-app',
		'window.untanglingData = ' . wp_json_encode(
			array(
				'msd'          => UNTANGLING_MSD_URL,
				// Fullscreen upgrade flow from the Marketplace, reused
				// item-less: pricing page + straight-to-Premium checkout.
				'plansUrl'     => untangling_marketplace_url( 'themes', array( 'ustep' => 'pricing', 'back' => rawurlencode( untangling_current_admin_url() ) ) ),
				'checkoutUrl'  => untangling_marketplace_url( 'themes', array( 'ustep' => 'checkout', 'plan' => 'Premium', 'back' => rawurlencode( untangling_current_admin_url() ) ) ),
				'variant'      => untangling_get_variant(),
				'siteType'     => untangling_get_site_type(),
				'marketplace'  => untangling_get_marketplace_mode(),
				'planFilter'   => untangling_get_plan_filter(),
				'planOverride' => (bool) get_option( 'untangling_plan_override' ),
				'locked'       => untangling_is_locked_demo(),
				'plan'         => untangling_get_plan(),
				'planMeta'     => untangling_get_plan_meta(),
				'siteSlug'     => untangling_get_site_slug(),
				'domain'       => untangling_get_primary_domain(),
				'siteUrl'      => home_url( '/' ),
				'domainUpsell' => untangling_get_domain_upsell(),
				'siteName'     => get_bloginfo( 'name' ),
				'siteIcon'     => get_site_icon_url( 64 ),
				'visibility'   => untangling_get_visibility(),
				'lpDone'       => array_values( (array) get_option( 'untangling_lp_done', array( 'theme' ) ) ),
				'lpComplete'   => (bool) get_option( 'untangling_lp_complete' ),
				'mysiteForce'  => defined( 'UNTANGLING_FORCE_MYSITE' ) ? UNTANGLING_FORCE_MYSITE : null,
			)
		) . ';',
		'before'
	);
	wp_add_inline_script( 'untangling-app', untangling_app_js() );
	wp_add_inline_style( 'wp-components', untangling_app_css() );
} );

function untangling_app_js() {
	return <<<'JS'
( function () {
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useRef = wp.element.useRef;
	var useLayoutEffect = wp.element.useLayoutEffect;
	var Fragment = wp.element.Fragment;
	var C = wp.components;
	var Button = C.Button;
	var SelectControl = C.SelectControl;
	var VStack = C.__experimentalVStack;
	var Text = C.__experimentalText;
	var Card = C.Card, CardHeader = C.CardHeader, CardBody = C.CardBody, CardFooter = C.CardFooter, CardDivider = C.CardDivider;
	var HStack = C.__experimentalHStack, FlexItem = C.FlexItem, FlexBlock = C.FlexBlock;
	var ProgressBar = C.ProgressBar;
	var TextControl = C.TextControl;
	var TabPanel = C.TabPanel;
	var ToggleGroup = C.__experimentalToggleGroupControl;
	var ToggleGroupOption = C.__experimentalToggleGroupControlOption;
	var Badge = C.Badge || function ( p ) {
		return el( 'span', { className: 'untangling-fallback-badge' }, p.children );
	};

	var data = window.untanglingData || {};
	var msd = data.msd || '#';
	var plansUrl = data.plansUrl || msd + '/plans';
	var checkoutUrl = data.checkoutUrl || plansUrl;
	var isPlain = 'plain' === data.variant;

	// The W mark, brand blue — the page-header product icon (Jetpack AI Hub pattern).
	var WPCOM_MARK = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zM3.5 12c0-1.232.264-2.402.736-3.459L8.291 19.65A8.5 8.5 0 013.5 12zm8.5 8.501c-.834 0-1.64-.122-2.401-.346l2.551-7.411 2.613 7.158a.718.718 0 00.061.117 8.497 8.497 0 01-2.824.482zm1.172-12.486c.512-.027.973-.081.973-.081.458-.054.404-.727-.054-.701 0 0-1.377.108-2.266.108-.835 0-2.239-.108-2.239-.108-.459-.026-.512.674-.054.701 0 0 .434.054.892.081l1.324 3.629-1.86 5.579-3.096-9.208c.512-.027.973-.081.973-.081.458-.054.403-.727-.055-.701 0 0-1.376.108-2.265.108-.16 0-.347-.004-.547-.01A8.491 8.491 0 0112 3.5c2.213 0 4.228.846 5.74 2.232-.037-.002-.072-.007-.11-.007-.835 0-1.427.727-1.427 1.509 0 .701.404 1.293.835 1.994.323.566.701 1.293.701 2.344 0 .727-.28 1.572-.647 2.748l-.848 2.833-3.072-9.138zm3.101 11.332l2.596-7.506c.485-1.213.646-2.182.646-3.045 0-.313-.021-.603-.057-.874A8.455 8.455 0 0120.5 12a8.493 8.493 0 01-4.227 7.347z"/></svg>';

	// DS TabPanel tab definitions. (@wordpress/ui ships a newer Tabs component,
	// but core doesn't bundle @wordpress/ui yet — TabPanel is the DS tabs
	// pattern available in wp.components.)
	var TABS = [
		{ name: 'my-site', title: 'My site' },
		{ name: 'learn-more', title: 'Help & Learn' },
	];

	function initialTab() {
		var tab = new URLSearchParams( window.location.search ).get( 'untangling_tab' );
		return TABS.some( function ( t ) { return t.name === tab; } ) ? tab : 'my-site';
	}

	function go( key, value ) {
		var url = new URL( window.location.href );
		url.searchParams.set( key, value );
		window.location.href = url.toString();
	}

	function title( text ) {
		return el( 'h2', { className: 'untangling-card-title' }, text );
	}

	// MSD overview pattern: badge sits next to the title; top-right is for actions.
	function titleWithBadge( text, badge ) {
		return el( HStack, { justify: 'flex-start', alignment: 'center', spacing: 2, expanded: false },
			title( text ),
			el( Badge, null, badge )
		);
	}

	function meta( text ) {
		return el( 'p', { className: 'untangling-meta-text' }, text );
	}

	// Jetpack AI Hub header pattern: product icon + title, subtitle underneath.
	// The tab row below is a DS TabPanel; its tablist visually completes the header.
	// Two designs, switched live from the Prototype controls:
	// v1 "Hosting" breadcrumb (the original) vs v2 site-identity (MSD site
	// header pattern: icon + name + URL + visibility, WP.com lockup right).
	var HEADER_VARIANTS = [
		{ value: 'hosting', label: 'V1 · Hosting breadcrumb' },
		{ value: 'site', label: 'V2 · Site identity' },
	];

	function initialHeader() {
		var fromUrl = new URLSearchParams( window.location.search ).get( 'untangling_header' );
		if ( HEADER_VARIANTS.some( function ( v ) { return v.value === fromUrl; } ) ) {
			try { window.localStorage.setItem( 'untangling-header', fromUrl ); } catch ( e ) {}
			return fromUrl;
		}
		try { return window.localStorage.getItem( 'untangling-header' ) || 'site'; } catch ( e ) { return 'site'; }
	}

	// Visibility badge with the CSS tooltip (data-tip + delegated positioning).
	function visibilityBadge() {
		var visibility = data.visibility || {};
		return el( 'span', {
			className: 'untangling-hubrow-badge is-' + ( visibility.tone || 'success' ) + ' untangling-feature-tip',
			tabIndex: 0,
			'data-tip': visibility.tip || 'Anyone can view your site.',
		}, visibility.label || 'Public' );
	}

	function domainLink() {
		return el( 'a', {
			className: 'untangling-header-domain',
			href: data.siteUrl || '#',
			target: '_blank',
			rel: 'noopener noreferrer',
			title: 'View site (opens in a new tab)',
		},
			data.domain || '',
			el( 'span', { className: 'untangling-header-domain-arrow', 'aria-hidden': true }, ' ↗' )
		);
	}

	function Header( props ) {
		if ( 'site' === props.variant ) {
			return el( 'div', { className: 'untangling-header' },
				el( HStack, { justify: 'space-between', alignment: 'flex-start', wrap: true, spacing: 4 },
					el( FlexItem, null,
						el( 'div', { className: 'untangling-siteid' },
							data.siteIcon
								? el( 'img', { className: 'untangling-siteid-icon', src: data.siteIcon, alt: '' } )
								: el( 'span', { className: 'untangling-siteid-icon is-fallback', 'aria-hidden': true }, ( data.siteName || 'S' ).charAt( 0 ) ),
							el( 'div', { className: 'untangling-siteid-main' },
								el( 'h1', { className: 'untangling-title' }, data.siteName || 'Your site' ),
								el( 'div', { className: 'untangling-siteid-meta' },
									domainLink(),
									visibilityBadge()
								)
							)
						)
					),
					el( FlexItem, null,
						el( Button, { variant: 'secondary', href: msd + '/sites/' + ( data.siteSlug || '' ) }, 'Go to Hosting Overview ↗' )
					)
				)
			);
		}
		return el( 'div', { className: 'untangling-header' },
			el( HStack, { justify: 'space-between', alignment: 'flex-start', wrap: true, spacing: 4 },
				el( FlexItem, null,
					el( 'div', { className: 'untangling-header-brand' },
						el( 'h1', { className: 'untangling-title' }, 'Hosting' ),
						el( 'span', { className: 'untangling-header-domain-sep', 'aria-hidden': true }, '/' ),
						domainLink(),
						visibilityBadge()
					)
				),
				el( FlexItem, null,
					el( Button, { variant: 'secondary', href: msd + '/sites/' + ( data.siteSlug || '' ) }, 'Go to Hosting Overview ↗' )
				)
			)
		);
	}

	function AudienceCard() {
		return el( Card, null,
			el( CardHeader, null, title( 'Your audience' ) ),
			el( CardBody, null,
				el( HStack, { spacing: 8, justify: 'flex-start', wrap: true, expanded: false },
					el( FlexItem, null, el( 'div', { className: 'untangling-stat-value' }, '1.2K' ), meta( 'views this week' ) ),
					el( FlexItem, null, el( 'div', { className: 'untangling-stat-value' }, '214' ), meta( 'subscribers · +12 this month' ) )
				)
			),
			el( CardFooter, null,
				el( Button, { variant: 'tertiary', href: msd + '/stats' }, 'Stats ↗' )
			)
		);
	}

	// Plan-card design variants, switched live from the prototype panel.
	// Free plan only — the upsell designs target Free; paid plans keep default.
	var PLANCARD_VARIANTS = [
		{ value: 'v1', label: 'V1 · Default' },
		{ value: 'v2', label: 'V2 · Premium upsell' },
		{ value: 'v3', label: 'V3 · Plan status' },
		{ value: 'v4', label: 'V4 · Creator offer' },
		{ value: 'v5', label: 'V5 · Compare plans' },
	];
	var PREMIUM_GB = 13;

	function initialPlancard() {
		var fromUrl = new URLSearchParams( window.location.search ).get( 'untangling_plancard' );
		if ( PLANCARD_VARIANTS.some( function ( v ) { return v.value === fromUrl; } ) ) {
			try { window.localStorage.setItem( 'untangling-plancard', fromUrl ); } catch ( e ) {}
			return fromUrl;
		}
		try { return window.localStorage.getItem( 'untangling-plancard' ) || 'v1'; } catch ( e ) { return 'v1'; }
	}

	function planCardHeader() {
		return el( CardHeader, null, titleWithBadge( 'Plan', data.plan ) );
	}

	function upsellIcon() {
		return el( 'span', { className: 'untangling-upsell-cta-icon', 'aria-hidden': true, dangerouslySetInnerHTML: { __html: OV_ICONS.upsellGlyph } } );
	}

	// Accepts plain strings or { label, tip } pairs; the pairs get the same
	// CSS hover tooltip as the upsell card and the pricing page.
	function featureList( features ) {
		return el( 'ul', { className: 'untangling-feature-list' },
			features.map( function ( feature, index ) {
				if ( 'string' === typeof feature ) {
					return el( 'li', { key: index }, feature );
				}
				return el( 'li', { key: index },
					el( 'span', { className: 'untangling-feature-tip', tabIndex: 0, 'data-tip': feature.tip }, feature.label )
				);
			} )
		);
	}

	function PlanCardDefault() {
		var isFree = 'Free' === data.plan;
		var isTop = 'Commerce' === data.plan;
		var planMeta = data.planMeta || {};
		return el( Card, { className: 'untangling-plan-card' },
			planCardHeader(),
			el( CardBody, null,
				meta( planMeta.renew || '' ),
				featureList( planMeta.features || [] )
			),
			el( CardFooter, null,
				el( HStack, { justify: 'flex-start', spacing: 2, expanded: false },
					! isTop && el( Button, { variant: 'secondary', className: 'untangling-upgrade', href: plansUrl }, isFree ? 'Upgrade to a paid plan' : 'Upgrade plan' ),
					! isFree && el( Button, { variant: 'tertiary', href: msd + '/me/billing' }, 'Manage ↗' )
				)
			)
		);
	}

	function PlanCardUpsell() {
		var planMeta = data.planMeta || {};
		// Feature copy and tooltips follow wordpress.com/pricing; the domain
		// example is personalized with this site's upsell domain.
		var features = [
			{ label: 'Free domain for one year', tip: 'Get a custom domain – like ' + data.domainUpsell + ' – free for the first year.' },
			{ label: '13 GB storage', tip: 'Upload more images, videos, audio, and documents to your website.' },
			{ label: 'All premium themes', tip: 'Install any premium theme from the WordPress.com marketplace.' },
			{ label: 'No ads for visitors', tip: 'Your visitors browse ad-free. WordPress.com ads are removed from your site.' },
			{ label: 'Payments & paid subscriptions', tip: 'Collect payments, donations, and paid subscriptions with PayPal and Stripe.' },
		];
		return el( Card, { className: 'untangling-plan-card' },
			planCardHeader(),
			el( CardBody, null,
				meta( planMeta.renew || '' ),
				el( 'div', { className: 'untangling-plan-upsell' },
					el( HStack, { justify: 'space-between', alignment: 'baseline', expanded: false },
						el( 'span', { className: 'untangling-plan-upsell-name' }, 'Premium' ),
						el( 'span', { className: 'untangling-plan-upsell-price' }, '$8/mo, billed yearly' )
					),
					el( 'ul', { className: 'untangling-feature-list' },
						features.map( function ( feature, index ) {
							// CSS tooltip (data-tip + ::after): the wp.components
							// Tooltip in this bundle silently fails with a delay
							// prop, and the demo needs instant, reliable hovers.
							return el( 'li', { key: index },
								el( 'span', { className: 'untangling-feature-tip', tabIndex: 0, 'data-tip': feature.tip }, feature.label )
							);
						} )
					),
					el( Button, { variant: 'secondary', className: 'untangling-upgrade untangling-plan-upsell-cta', href: checkoutUrl }, 'Upgrade to Premium' )
				)
			),
			el( CardFooter, null,
				el( Button, { variant: 'tertiary', href: plansUrl }, 'Compare all plans' )
			)
		);
	}

	// Storage row: the track is the plan's own 1 GB (amber = used); the chip
	// carries the Premium comparison.
	function PlanCardStatus() {
		var storage = ( data.planMeta && data.planMeta.storage ) || [ 0, 1, null ];
		var used = storage[ 0 ], total = storage[ 1 ];
		var usedPct = ( 100 * used / total ) + '%';
		function chip( text ) {
			return el( 'span', { className: 'untangling-plan-chip' }, text );
		}
		function row( extraClass, label, sub, right ) {
			return el( 'div', { className: 'untangling-plan-row' + ( extraClass ? ' ' + extraClass : '' ) },
				el( 'div', { className: 'untangling-plan-row-label' },
					el( 'span', null, label ),
					sub && el( 'small', null, sub )
				),
				right
			);
		}
		return el( Card, { className: 'untangling-plan-card' },
			planCardHeader(),
			el( CardBody, null,
				el( 'div', { className: 'untangling-plan-rows' },
					row( 'untangling-plan-row-storage', 'Storage', used + ' of ' + total + ' GB used', chip( PREMIUM_GB + ' GB on Premium' ) ),
					el( 'div', { className: 'untangling-storage-compare', role: 'img', 'aria-label': 'Storage: ' + used + ' GB used of ' + total + ' GB. Premium includes ' + PREMIUM_GB + ' GB.' },
						el( 'div', { className: 'untangling-storage-compare-track' },
							el( 'span', { className: 'untangling-storage-compare-used', style: { width: usedPct } } )
						)
					),
					row( '', 'Site address', 'Default', chip( 'Free domain for one year' ) ),
					row( '', 'Ads', 'Shown to your readers', chip( 'Removed on Premium' ) ),
					row( '', 'Earning tools', 'Payments & paid subscriptions', chip( 'Premium' ) )
				)
			),
			el( CardFooter, null,
				el( HStack, { justify: 'flex-start', spacing: 2, expanded: false },
					el( Button, { variant: 'secondary', className: 'untangling-upgrade', href: checkoutUrl }, 'Upgrade to Premium' ),
					el( Button, { variant: 'tertiary', href: plansUrl }, 'Compare plans' )
				)
			)
		);
	}

	function PlanCardOffer() {
		return el( Card, { className: 'untangling-plan-card' },
			planCardHeader(),
			el( CardBody, null,
				el( 'div', { className: 'untangling-plan-eyebrow' }, 'Limited offer · up to 55% off' ),
				el( 'h3', { className: 'untangling-plan-headline' }, 'Get a custom domain free for a year' ),
				meta( 'A custom domain makes your blog easier to share, remember, and trust. Included free for a year with any annual plan.' ),
				featureList( [ '✓ Free custom domain for a year', '✓ No WordPress.com ads', '✓ Earn from your writing' ] )
			),
			el( CardFooter, null,
				el( HStack, { justify: 'flex-start', alignment: 'center', spacing: 3, expanded: false },
					el( Button, { variant: 'secondary', className: 'untangling-upgrade', href: plansUrl }, 'Claim 55% off' ),
					el( 'span', { className: 'untangling-plan-fine' }, 'Applies to annual paid plans' )
				)
			)
		);
	}

	// V5: side-by-side compare — the Free column is what you have, the Premium
	// column upgrades it row by row (gray checks vs green; pattern from the
	// plan-upgrade card). Answers "what do I have" and "what does the upgrade
	// change" in one glance, without ambiguous chips.
	function PlanCardCompare() {
		// Same CSS tooltip as the V2 upsell card (span.untangling-feature-tip
		// + data-tip); copy follows wordpress.com/pricing.
		var freeCol = [
			{ label: '1 GB storage', tip: 'Room for your images, documents, and other media.' },
			{ label: 'Dozens of free themes', tip: 'Choose from dozens of professionally designed free themes.' },
			{ label: 'Free .wordpress.com address', tip: 'Your site address ends in .wordpress.com, like ' + ( data.siteSlug || 'yoursite' ) + '.wordpress.com.' },
			{ label: 'Community support', tip: 'Get help from support guides and the community forums.' },
		];
		var premiumCol = [
			{ label: '13 GB storage', tip: 'Upload more images, videos, audio, and documents to your website.' },
			{ label: 'All premium themes', tip: 'Install any premium theme from the WordPress.com marketplace.' },
			{ label: 'Free domain for one year', tip: 'Get a custom domain – like ' + data.domainUpsell + ' – free for the first year.' },
			{ label: 'Fast support from our expert team', tip: 'Fast email support from our expert team of Happiness Engineers.' },
		];
		function compareCol( name, chipText, chipClass, price, desc, features, listClass, cta, recommended ) {
			return el( 'div', { className: 'untangling-plan-compare-col' + ( recommended ? ' is-recommended' : '' ) },
				el( 'div', { className: 'untangling-plan-compare-name' },
					el( 'span', null, name ),
					el( 'span', { className: 'untangling-plan-chip ' + chipClass }, chipText )
				),
				el( 'div', { className: 'untangling-plan-compare-price' }, price ),
				desc && el( 'p', { className: 'untangling-plan-compare-desc' }, desc ),
				el( 'ul', { className: 'untangling-plan-compare-list' + ( listClass ? ' ' + listClass : '' ) },
					features.map( function ( feature, index ) {
						return el( 'li', { key: index },
							el( 'span', { className: 'untangling-feature-tip', tabIndex: 0, 'data-tip': feature.tip }, feature.label )
						);
					} )
				),
				cta && el( 'div', { className: 'untangling-plan-compare-cta' }, cta )
			);
		}
		return el( Card, { className: 'untangling-plan-card' },
			el( CardHeader, null, title( 'Plan upgrade' ) ),
			el( CardBody, null,
				el( 'div', { className: 'untangling-plan-compare' },
					compareCol( 'Free', 'Current plan', 'is-neutral', 'US$0/month', null, freeCol, 'is-muted',
						el( Button, { variant: 'secondary', size: 'compact', href: plansUrl }, 'Manage plan' ), false ),
					compareCol( 'Premium', 'Recommended', 'is-dark', 'US$8/month, billed annually', null, premiumCol, '',
						el( Button, { variant: 'primary', size: 'compact', icon: upsellIcon(), className: 'untangling-upgrade', href: checkoutUrl }, 'Upgrade to Premium' ), true )
				)
			),
			el( CardFooter, null,
				el( 'a', { className: 'untangling-linkfooter', href: plansUrl },
					el( 'span', null, 'See all plans' ),
					el( 'span', { className: 'untangling-ovcard-chevron', 'aria-hidden': true } )
				)
			)
		);
	}

	function PlanCard( props ) {
		if ( 'Free' === data.plan ) {
			if ( 'v2' === props.variant ) { return el( PlanCardUpsell ); }
			if ( 'v3' === props.variant ) { return el( PlanCardStatus ); }
			if ( 'v4' === props.variant ) { return el( PlanCardOffer ); }
			if ( 'v5' === props.variant ) { return el( PlanCardCompare ); }
		}
		return el( PlanCardDefault );
	}

	// Free sites keep the product rail short: Plan upgrade + Domains only.
	// Storage and Email surface once the site is on a paid plan.
	function StorageCard() {
		if ( 'Free' === data.plan ) { return null; }
		var storage = ( data.planMeta && data.planMeta.storage ) || [ 0, 1, null ];
		var used = storage[ 0 ], total = storage[ 1 ], caution = storage[ 2 ];
		var percent = Math.round( 100 * used / total );
		var trackStyle = caution ? { '--wp-components-color-foreground': 'var(--wpds-color-foreground-content-caution-weak)' } : {};
		var bar = ProgressBar
			? el( 'div', { className: 'untangling-storage-track', style: trackStyle },
				el( ProgressBar, { value: percent, className: 'untangling-progress' } ) )
			: el( 'div', { className: 'untangling-progress-fallback' }, el( 'span', { style: { width: percent + '%' } } ) );
		return el( Card, null,
			el( CardHeader, null, title( 'Storage' ) ),
			el( CardBody, null,
				bar,
				el( 'div', { className: 'untangling-stat-line' }, used + ' GB of ' + total + ' GB used' ),
				caution && el( 'p', { className: 'untangling-caution' }, caution )
			),
			el( CardFooter, null,
				el( Button, { variant: 'secondary', className: 'untangling-upgrade', href: msd + '/plans' }, 'Free' === data.plan ? 'Upgrade for more space' : 'Add storage' )
			)
		);
	}

	// Domain upsell, mirroring MSD's overview-domain-upsell-card: the Callout
	// layout (copy + CTA left, full-bleed image right) and the exact
	// DomainUpsellIllustraction SVG (dot pattern, gradient browser frame,
	// lock + live domain in the address bar). Copy is plan-aware like MSD:
	// Free upsells the plan, paid plans surface their domain credit.
	function domainArt( domain ) {
		return '<svg width="318" height="192" viewBox="0 0 318 192" fill="none" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" preserveAspectRatio="xMinYMin slice" aria-hidden="true">'
			+ '<g clip-path="url(#udomclip)">'
			+ '<rect width="318" height="192" fill="white"/>'
			+ '<rect width="318" height="192" fill="url(#udompattern)" fill-opacity="0.12"/>'
			+ '<path d="M37 49C37 42.3726 42.3726 37 49 37H325V196H37V49Z" fill="white"/>'
			+ '<rect x="51" y="89" width="162" height="20" rx="4" fill="#F7F7F7"/>'
			+ '<rect x="51" y="119" width="288" height="90" rx="4" fill="#F7F7F7"/>'
			+ '<path d="M37 49C37 42.3726 42.3726 37 49 37H325V75H37V49Z" fill="#F7F7F7"/>'
			+ '<circle cx="55" cy="56" r="3.25" fill="#F7F8FE" stroke="#C3C4C7" stroke-width="1.5"/>'
			+ '<circle cx="67" cy="56" r="3.25" fill="#F7F8FE" stroke="#C3C4C7" stroke-width="1.5"/>'
			+ '<circle cx="79" cy="56" r="3.25" fill="#F7F8FE" stroke="#C3C4C7" stroke-width="1.5"/>'
			+ '<rect x="95" y="45" width="240" height="22" rx="4" fill="white"/>'
			+ '<text x="119" y="60" text-anchor="start" direction="ltr" fill="#1E1E1E" font-size="12px">' + domain + '</text>'
			+ '<path fill-rule="evenodd" clip-rule="evenodd" d="M109.25 55.25H109.1V53.75C109.1 52.625 108.2 51.65 107 51.65C105.8 51.65 104.9 52.625 104.9 53.75V55.25H104.75C104.3 55.25 104 55.55 104 56V59C104 59.45 104.3 59.75 104.75 59.75H109.25C109.7 59.75 110 59.45 110 59V56C110 55.55 109.7 55.25 109.25 55.25ZM107.9 55.25H106.025V53.75C106.025 53.225 106.475 52.85 106.925 52.85C107.375 52.85 107.825 53.3 107.825 53.75V55.25H107.9Z" fill="#3858E9"/>'
			+ '<path d="M325 196H37V49C37 42.3726 42.3726 37 49 37H325V196ZM38 75V195H324V75H38ZM49 38C42.9249 38 38 42.9249 38 49V74H324V38H49Z" fill="url(#udomgradient)"/>'
			+ '</g>'
			+ '<defs>'
			+ '<pattern id="udompattern" patternContentUnits="objectBoundingBox" width="0.0188679" height="0.03125"><use xlink:href="#udomdots" transform="scale(0.00157233 0.00260417)"/></pattern>'
			+ '<linearGradient id="udomgradient" x1="302.32" y1="59.8261" x2="76.018" y2="144.39" gradientUnits="userSpaceOnUse"><stop stop-color="#069E08"/><stop offset="1" stop-color="#3858E9"/></linearGradient>'
			+ '<clipPath id="udomclip"><rect width="318" height="192" fill="white"/></clipPath>'
			+ '<image id="udomdots" width="12" height="12" preserveAspectRatio="none" xlink:href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAAGklEQVR4nGNgGAUo4D82QSYCirFqIsmG4QAAKKwD//0jFGoAAAAASUVORK5CYII="/>'
			+ '</defs>'
			+ '</svg>';
	}

	function DomainCard() {
		var upsell = data.domainUpsell;
		var isFree = 'Free' === data.plan;
		var copy = isFree
			? {
				title: 'The perfect domain awaits',
				before: 'Upgrade to an annual paid plan to get ' + upsell + ' free for one year. You can also ',
				link: 'choose your own domain name',
				cta: 'Choose a plan',
				href: plansUrl,
			}
			: {
				title: 'Claim your free domain',
				before: upsell + ' is included free for one year with your paid plan. Claim this domain or ',
				link: 'choose your own',
				cta: 'Claim this domain',
				href: msd + '/domains',
			};
		return el( Card, { className: 'untangling-domain-card' },
			el( CardBody, { className: 'untangling-domain-body' },
				el( 'div', { className: 'untangling-domain-copy' },
					title( copy.title ),
					el( 'p', { className: 'untangling-domain-desc' },
						copy.before,
						el( 'a', { href: msd + '/domains' }, copy.link ),
						'.'
					),
					el( 'div', null,
						el( Button, {
							variant: 'primary',
							size: 'compact',
							icon: upsellIcon(),
							className: 'untangling-upgrade untangling-domain-cta',
							href: copy.href,
						}, copy.cta )
					)
				),
				el( 'div', { className: 'untangling-domain-art', 'aria-hidden': true, dangerouslySetInnerHTML: { __html: domainArt( upsell ) } } )
			)
		);
	}

	// No mailbox yet — MSD Emails-style empty state doubling as the upsell.
	function EmailCard() {
		if ( 'Free' === data.plan ) { return null; }
		return el( Card, null,
			el( CardHeader, null, title( 'Email' ) ),
			el( CardBody, null,
				el( 'div', { className: 'untangling-email-upsell' },
					el( 'div', { className: 'untangling-stat-line' }, 'Get your custom email' ),
					meta( 'Build trust with your readers using an email that matches your domain.' ),
					el( Button, { variant: 'secondary', href: msd + '/emails' }, 'Add email' )
				)
			)
		);
	}

	// Plain variant only: lifecycle module for the blogger persona.
	function GrowCard() {
		var rows = [
			[ 'Claim ' + data.domainUpsell, 'A custom domain makes your site easier to find and share.', 'Get the domain ↗', msd + '/domains', false ],
			[ 'Start a paid newsletter', 'Turn your 214 subscribers into supporters with monthly contributions.', 'Set up payments ↗', msd, false ],
			[ 'Sell your prints', 'Open a store for prints and presets with the Commerce plan.', 'Upgrade', msd + '/plans', true ],
		];
		return el( Card, null,
			el( CardHeader, null, title( 'Grow your blog' ) ),
			el( CardBody, null,
				el( 'div', { className: 'untangling-grow-list' },
					rows.map( function ( row, index ) {
						return el( HStack, { key: index, justify: 'space-between', alignment: 'center', wrap: true, className: 'untangling-grow-row' },
							el( FlexBlock, null,
								el( 'div', { className: 'untangling-stat-line' }, row[ 0 ] ),
								meta( row[ 1 ] )
							),
							el( FlexItem, null,
								el( Button, { variant: 'tertiary', className: row[ 4 ] ? 'untangling-upgrade' : undefined, href: row[ 3 ] }, row[ 2 ] )
							)
						);
					} )
				)
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * "My site" overview (left column): site health, glance tiles (views,
	 * performance, visibility, storage), and the AI assistant banner.
	 * Free-plan framing: useful signal first, honest upsell where a limit
	 * is actually near (storage) or a capability is gated (AI requests).
	 * ------------------------------------------------------------------- */

	function dot( tone ) {
		return el( 'span', { className: 'untangling-dot is-' + tone, 'aria-hidden': true } );
	}

	// Section head: title + optional status badge, sitting above a tile grid.
	function sectionHead( text, badge ) {
		return el( 'div', { className: 'untangling-section-head' },
			el( 'h2', { className: 'untangling-section-title' }, text ),
			badge && el( Badge, { className: 'untangling-badge-success' }, badge )
		);
	}

	// Health and glance cards follow the MSD OverviewCard pattern
	// (client/dashboard/components/overview-card): uppercase muted title,
	// large heading, small muted description, whole card is the link.
	// Intents mirror MSD — upsell paints the title brand, warning paints
	// the description.
	function GlanceCard( props ) {
		return el( 'a', {
			className: 'untangling-ovcard untangling-glance' + ( props.intent ? ' is-' + props.intent : '' ),
			href: props.href || '#',
		},
			el( 'span', { className: 'untangling-ovcard-top' },
				el( 'span', { className: 'untangling-ovcard-title' },
					props.dot && dot( props.dot ),
					props.title
				),
				el( 'span', { className: 'untangling-ovcard-chevron', 'aria-hidden': true } )
			),
			el( 'span', { className: 'untangling-ovcard-heading' }, props.heading ),
			props.desc && el( 'span', { className: 'untangling-ovcard-desc' }, props.desc ),
			props.children
		);
	}

	var HEALTH_CHECKS = [
		{ dot: 'success', title: 'WordPress', heading: 'Up to date', desc: 'We handle core updates for you.', href: 'site-health.php' },
		{ dot: 'caution', title: 'Plugins', heading: '2 updates ready', desc: 'A quick install keeps things secure.', href: 'plugins.php' },
		{ dot: 'success', title: 'SSL', heading: 'Certificate active', desc: 'Visitors connect securely.', href: 'site-health.php' },
		{ dot: 'success', title: 'Security', heading: 'No threats found', desc: 'Last scan: today.', href: 'site-health.php' },
	];

	function HealthCheckCard( props ) {
		return el( GlanceCard, props.check );
	}

	// Site views: last 7 days, single series — brand fill (validated against
	// the light surface), day + count on hover, no legend needed.
	var WEEK_VIEWS = [
		[ 'Friday', 150 ], [ 'Saturday', 132 ], [ 'Sunday', 96 ], [ 'Monday', 187 ],
		[ 'Tuesday', 163 ], [ 'Wednesday', 204 ], [ 'Thursday', 226 ],
	];

	function ViewsTile() {
		var max = Math.max.apply( null, WEEK_VIEWS.map( function ( day ) { return day[ 1 ]; } ) );
		return el( GlanceCard, { title: 'Views', heading: '1.2K', desc: 'this week', href: msd + '/stats' },
			el( 'span', { className: 'untangling-spark', role: 'img', 'aria-label': 'Views per day over the last seven days' },
				WEEK_VIEWS.map( function ( day, index ) {
					return el( 'span', {
						key: index,
						className: 'untangling-spark-bar',
						style: { height: Math.round( 100 * day[ 1 ] / max ) + '%' },
						title: day[ 0 ] + ' · ' + day[ 1 ] + ' views',
					} );
				} )
			)
		);
	}

	// Performance testing is a paid feature — MSD upsell-intent card.
	function PerformanceTile() {
		return el( GlanceCard, {
			title: 'Performance',
			intent: 'upsell',
			heading: 'See your speed scores',
			desc: 'Find out how fast your site loads for readers, and what to improve first. Included with Premium.',
			href: plansUrl,
		} );
	}

	function VisibilityTile() {
		return el( GlanceCard, {
			dot: 'success',
			title: 'Visibility',
			heading: 'Public',
			desc: 'Anyone can visit your site, and search engines can find it.',
			href: 'options-reading.php',
		} );
	}

	function StorageTile() {
		return el( GlanceCard, {
			dot: 'warning',
			title: 'Storage',
			intent: 'warning',
			heading: '0.9 GB of 1 GB',
			desc: 'Almost full — Premium adds 13 GB.',
			href: plansUrl,
		},
			el( 'span', { className: 'untangling-meter is-warning', role: 'img', 'aria-label': 'Storage 90 percent full' },
				el( 'span', { style: { width: '90%' } } )
			)
		);
	}

	// "My site" main-column layout variants, switched from the prototype panel.
	// Three directions explore what a Blogger & Creator persona should see
	// first (see competitor audit); Grow · journey is the default.
	var MYSITE_VARIANTS = [
		{ value: 'growth', label: 'Grow · journey' },
		{ value: 'momentum', label: 'Momentum · daily home' },
		{ value: 'earn', label: 'Earn & Reach · business' },
		{ value: 'onecol', label: 'One column · stacked' },
		{ value: 'cards', label: 'Overview cards · clickable rail' },
		{ value: 'hub', label: 'Hub · sectioned' },
	];

	function initialMysite() {
		// A locked demo can pin the layout (UNTANGLING_FORCE_MYSITE) so
		// visitors land in one experience with no way to wander off it.
		if ( data.mysiteForce && MYSITE_VARIANTS.some( function ( v ) { return v.value === data.mysiteForce; } ) ) {
			return data.mysiteForce;
		}
		var fromUrl = new URLSearchParams( window.location.search ).get( 'untangling_mysite' );
		if ( MYSITE_VARIANTS.some( function ( v ) { return v.value === fromUrl; } ) ) {
			try { window.localStorage.setItem( 'untangling-mysite', fromUrl ); } catch ( e ) {}
			return fromUrl;
		}
		// Validate the stored value too — a stale 'default' (view removed)
		// must fall back to growth instead of rendering nothing.
		try {
			var saved = window.localStorage.getItem( 'untangling-mysite' );
			return MYSITE_VARIANTS.some( function ( v ) { return v.value === saved; } ) ? saved : 'growth';
		} catch ( e ) { return 'growth'; }
	}

	function actionRows( rows ) {
		return el( 'div', { className: 'untangling-grow-list' },
			rows.map( function ( row, index ) {
				return el( HStack, { key: index, justify: 'space-between', alignment: 'center', wrap: true },
					el( FlexBlock, null,
						el( 'div', { className: 'untangling-stat-line' }, row[ 0 ] ),
						meta( row[ 1 ] )
					),
					el( FlexItem, null,
						el( Button, { variant: 'tertiary', className: row[ 4 ] ? 'untangling-upgrade' : undefined, href: row[ 3 ] }, row[ 2 ] )
					)
				);
			} )
		);
	}

	// Momentum: the daily working home — write, respond, watch the pulse.
	function WriteCard() {
		var ideas = [ 'Golden hour, part two', 'Reader Q&A: gear', 'One street, four seasons' ];
		return el( Card, null,
			el( CardHeader, null, title( 'Write' ) ),
			el( CardBody, null,
				el( HStack, { justify: 'space-between', alignment: 'center', wrap: true },
					el( FlexBlock, null,
						el( 'div', { className: 'untangling-stat-line' }, 'Fog at Miner’s Point' ),
						meta( 'Draft · edited yesterday' )
					),
					el( FlexItem, null,
						el( Button, { variant: 'tertiary', href: 'edit.php?post_status=draft' }, 'Continue draft ↗' )
					)
				),
				el( 'p', { className: 'untangling-meta-text untangling-idea-lede' }, 'Ideas from what your readers loved:' ),
				el( 'div', { className: 'untangling-chip-row' },
					ideas.map( function ( idea, index ) {
						return el( Button, { key: index, variant: 'secondary', size: 'small', href: 'post-new.php' }, idea );
					} )
				)
			),
			el( CardFooter, null,
				el( Button, { variant: 'primary', href: 'post-new.php' }, 'New post' )
			)
		);
	}

	function EngagementCard() {
		var comments = [
			[ 'Marta R.', '“The fog shot is stunning. Which lens was this?”', 'Chasing the Golden Hour' ],
			[ 'Deniz K.', '“This post convinced me to try the 5 AM walk.”', 'Chasing the Golden Hour' ],
			[ 'Priya S.', '“Subscribed after this one. More like it, please!”', 'Slow mornings, fast light' ],
		];
		return el( Card, null,
			el( CardHeader, null,
				title( 'Engagement' ),
				meta( '3 comments await your reply' )
			),
			el( CardBody, null,
				el( 'div', { className: 'untangling-grow-list' },
					comments.map( function ( comment, index ) {
						return el( HStack, { key: index, justify: 'space-between', alignment: 'center', wrap: true },
							el( FlexBlock, null,
								el( 'div', { className: 'untangling-stat-line' }, comment[ 0 ] ),
								meta( comment[ 1 ] + ' · on ' + comment[ 2 ] )
							),
							el( FlexItem, null,
								el( Button, { variant: 'tertiary', href: 'edit-comments.php' }, 'Reply ↗' )
							)
						);
					} )
				)
			),
			el( CardFooter, null,
				el( Button, { variant: 'tertiary', href: 'edit-comments.php' }, 'All comments ↗' )
			)
		);
	}

	function PulseCard() {
		return el( Card, null,
			el( CardHeader, null, title( 'Audience pulse' ) ),
			el( CardBody, null,
				el( 'svg', { className: 'untangling-spark', viewBox: '0 0 140 48', preserveAspectRatio: 'none', 'aria-hidden': true },
					el( 'polyline', { points: '4,34 26,26 48,31 70,22 92,12 114,17 136,6', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round' } )
				),
				el( HStack, { spacing: 8, justify: 'flex-start', wrap: true, expanded: false },
					el( FlexItem, null, el( 'div', { className: 'untangling-stat-value' }, '1.2K' ), meta( 'views this week' ) ),
					el( FlexItem, null, el( 'div', { className: 'untangling-stat-value' }, '214' ), meta( 'subscribers · +12 this month' ) ),
					el( FlexItem, null, el( 'div', { className: 'untangling-stat-value' }, '312' ), meta( 'views · best post this week' ) )
				),
				meta( 'Best this week: “Chasing the Golden Hour”' )
			),
			el( CardFooter, null,
				el( Button, { variant: 'tertiary', href: msd + '/stats' }, 'Stats ↗' )
			)
		);
	}

	function MomentumView() {
		return el( Fragment, null,
			el( WriteCard ),
			el( EngagementCard ),
			el( PulseCard )
		);
	}

	// Grow: the setup journey in the AI Launchpad accordion style — the real
	// wp-admin Site Setup page (jetpack-mu-wpcom ai-launchpad, task registry
	// mirrored here with blogger copy). One task open at a time; Skip marks
	// done and advances. Progress persists server-side (background GET, same
	// pattern as the other untangling_* toggles) so the Launchpad tab and
	// sidebar submenu can retire once everything is done or skipped; hosts
	// pass onComplete to run the celebration moment at that point.
	var LP_TASKS = [
		{ id: 'theme', label: 'Choose a theme' },
		{ id: 'post', label: 'Write your first post', desc: 'Share your inaugural creative piece with readers.', cta: 'Write post', href: 'post-new.php' },
		{ id: 'about', label: 'Add your About page', desc: 'Introduce yourself and your writing journey.', cta: 'Add page', href: 'post-new.php?post_type=page' },
		{ id: 'social', label: 'Connect your social media accounts', desc: 'Share new posts to your social profiles automatically.', cta: 'Connect accounts', href: msd },
		{ id: 'welcome', label: 'Write a welcome message', desc: 'Greet new readers when they land on your blog.', cta: 'Write message', href: msd },
		{ id: 'launch', label: 'Launch your site', desc: 'Make your blog public when you’re ready.', cta: 'Launch site', href: msd },
	];

	function lpInitialDone() {
		var map = {};
		( data.lpDone && data.lpDone.length ? data.lpDone : [ 'theme' ] ).forEach( function ( id ) {
			map[ id ] = true;
		} );
		return map;
	}

	function GrowthView( props ) {
		var tasks = LP_TASKS;
		var doneState = useState( lpInitialDone );
		var done = doneState[ 0 ], setDone = doneState[ 1 ];
		var openState = useState( function () {
			var initial = lpInitialDone();
			var first = tasks.find( function ( t ) { return ! initial[ t.id ]; } );
			return first ? first.id : null;
		} );
		var open = openState[ 0 ], setOpen = openState[ 1 ];
		function persist( next ) {
			var ids = tasks.filter( function ( t ) { return next[ t.id ]; } ).map( function ( t ) { return t.id; } );
			var complete = ids.length === tasks.length;
			try {
				window.fetch(
					'admin.php?page=untangling-hosting&untangling_lp_done=' + ids.join( ',' ) + ( complete ? '&untangling_lp_complete=1' : '' ),
					{ credentials: 'same-origin' }
				);
			} catch ( e ) {}
			return complete;
		}
		function skip( id ) {
			var next = Object.assign( {}, done );
			next[ id ] = true;
			setDone( next );
			if ( persist( next ) ) {
				setOpen( null );
				if ( props && props.onComplete ) {
					props.onComplete();
				}
				return;
			}
			var idx = tasks.findIndex( function ( t ) { return t.id === id; } );
			var following = tasks.slice( idx + 1 ).concat( tasks.slice( 0, idx ) )
				.find( function ( t ) { return ! next[ t.id ]; } );
			setOpen( following ? following.id : null );
		}
		var doneCount = tasks.filter( function ( t ) { return !! done[ t.id ]; } ).length;
		return el( Card, null,
			el( CardHeader, null,
				title( 'Launchpad' ),
				meta( doneCount + ' of ' + tasks.length + ' complete' )
			),
			el( CardBody, null,
				el( 'div', { className: 'untangling-lp' },
					tasks.map( function ( task ) {
						var isDone = !! done[ task.id ];
						var isOpen = open === task.id && ! isDone;
						return el( 'div', { key: task.id, className: 'untangling-lp-task' + ( isDone ? ' is-done' : '' ) + ( isOpen ? ' is-open' : '' ) },
							el( 'button', {
								className: 'untangling-lp-head',
								'aria-expanded': isOpen,
								disabled: isDone,
								onClick: function () { setOpen( isOpen ? null : task.id ); },
							},
								el( 'span', { className: 'untangling-lp-circle', 'aria-hidden': true }, isDone ? '✓' : '' ),
								el( 'span', { className: 'untangling-lp-label' }, task.label ),
								! isDone && el( 'span', { className: 'untangling-lp-chevron', 'aria-hidden': true } )
							),
							isOpen && el( 'div', { className: 'untangling-lp-body' },
								meta( task.desc ),
								el( HStack, { justify: 'flex-start', spacing: 2, expanded: false },
									el( Button, { variant: 'primary', href: task.href }, task.cta ),
									el( Button, { variant: 'tertiary', onClick: function () { skip( task.id ); } }, 'Skip' )
								)
							)
						);
					} )
				)
			)
		);
	}

	// The completion moment: check pops in, a one-shot confetti burst fans
	// out, then the host swaps in the Grow section. Shown only at the moment
	// the last task is done or skipped — a completed site never sees it again.
	function LpCelebration() {
		var pieces = [];
		for ( var i = 0; i < 12; i++ ) {
			pieces.push( el( 'span', { key: i, className: 'untangling-confetti', 'aria-hidden': true } ) );
		}
		return el( Card, null,
			el( CardBody, null,
				el( 'div', { className: 'untangling-celebrate', role: 'status' },
					el( 'div', { className: 'untangling-celebrate-stage' },
						el( 'span', { className: 'untangling-celebrate-check', 'aria-hidden': true }, '✓' ),
						pieces
					),
					el( 'h2', { className: 'untangling-card-title' }, 'Your site is ready!' ),
					meta( 'Quick start complete — nice work. Time to grow your audience.' )
				)
			)
		);
	}

	// Grow your site: takes over the Quick start slot once setup is done.
	// A 2×2 grid: Blaze (insight-driven), domain, newsletter (plan-aware),
	// and a free audience action — growth, honestly labeled, never a wall of
	// upsells.
	function GrowSection() {
		var isFree = 'Free' === data.plan;
		return el( 'div', { className: 'untangling-grow-enter' },
			el( 'div', { className: 'untangling-quick-grid' },
				el( HubRow, { icon: 'globe', title: 'Claim your domain', desc: 'Get ' + data.domainUpsell + ' and make your site easier to find.', href: msd + '/domains' } ),
				el( HubRow, { icon: 'email', title: 'Start a newsletter', badge: isFree ? 'Premium' : 'Included', badgeTone: isFree ? undefined : 'success', upsell: isFree, desc: 'Email every new post straight to your readers’ inboxes.', href: isFree ? plansUrl : msd } ),
				el( HubRow, { icon: 'seen', title: 'Reach your first 100 subscribers', desc: 'You have 12 so far. Add a subscribe block to grow faster.', href: msd + '/stats' } ),
				el( HubRow, { icon: 'performance', title: 'Promote with Blaze', desc: 'Your top post got 214 views. Blaze can bring it new readers.', href: msd + '/advertising' } )
			)
		);
	}

	// Launchpad → Grow: the launchpad accordion owns the top of My site
	// until every task is done or skipped; the celebration hands the slot to
	// the Grow section.
	function QuickStartOrGrow() {
		var phaseState = useState( data.lpComplete ? 'grow' : 'launchpad' );
		var phase = phaseState[ 0 ], setPhase = phaseState[ 1 ];
		if ( 'launchpad' === phase ) {
			return el( Fragment, null,
				el( GrowthView, { onComplete: function () {
					setPhase( 'celebrate' );
					window.setTimeout( function () {
						setPhase( 'grow' );
					}, 2600 );
				} } )
			);
		}
		if ( 'celebrate' === phase ) {
			return el( LpCelebration );
		}
		return el( Fragment, null,
			sectionTitle( 'Grow your site' ),
			el( GrowSection )
		);
	}

	// Earn & Reach: the creator-business hub — newsletter, revenue, discovery.
	function EarnView() {
		var isFree = 'Free' === data.plan;
		return el( Fragment, null,
			el( Card, null,
				el( CardHeader, null, title( 'Newsletter' ) ),
				el( CardBody, null,
					el( HStack, { spacing: 8, justify: 'flex-start', wrap: true, expanded: false },
						el( FlexItem, null, el( 'div', { className: 'untangling-stat-value' }, '214' ), meta( 'subscribers · +12 this month' ) ),
						el( FlexItem, null, el( 'div', { className: 'untangling-stat-value' }, '62%' ), meta( 'opens · last send' ) ),
						el( FlexItem, null, el( 'div', { className: 'untangling-stat-value' }, '18%' ), meta( 'clicks · last send' ) )
					),
					meta( 'Last sent: “Golden hour field notes” · 3 days ago' )
				),
				el( CardFooter, null,
					el( HStack, { justify: 'flex-start', spacing: 2, expanded: false },
						el( Button, { variant: 'primary', href: 'post-new.php' }, 'Write newsletter' ),
						el( Button, { variant: 'tertiary', href: msd }, 'Subscribers ↗' )
					)
				)
			),
			el( Card, null,
				el( CardHeader, null,
					title( 'Earnings' ),
					isFree ? meta( 'Not earning yet' ) : meta( 'Stripe connected' )
				),
				el( CardBody, null,
					isFree
						? el( Fragment, null,
							el( 'div', { className: 'untangling-stat-value' }, '€0' ),
							meta( 'Your 214 subscribers are ready to support you. Keep all your revenue. WordPress.com takes no cut.' )
						)
						: el( Fragment, null,
							el( 'div', { className: 'untangling-stat-value' }, '€42' ),
							meta( 'this month · 6 paid subscribers' )
						),
					actionRows( [
						[ 'Paid subscriptions', 'Monthly or yearly support from readers, on every plan including Free.', isFree ? 'Set up ↗' : 'Manage ↗', msd, false ],
						[ 'Ad revenue', 'Earn from ads on your posts with WordAds.', isFree ? 'Upgrade' : 'Manage ↗', msd + '/plans', isFree ],
						[ 'Tips & donations', 'Let readers say thanks with one-time contributions.', 'Set up ↗', msd, false ],
					] )
				)
			),
			el( Card, null,
				el( CardHeader, null, title( 'Reach' ) ),
				el( CardBody, null,
					actionRows( [
						[ 'WordPress.com Reader', '89 people follow you in the Reader.', 'Open Reader ↗', msd + '/reader', false ],
						[ 'Fediverse', 'Share posts to Mastodon and the open social web.', 'Enable ↗', msd, false ],
						[ 'Social sharing', 'Auto-share new posts to your social profiles.', 'Connect ↗', msd, false ],
					] )
				)
			)
		);
	}

	// One column: every card stacked in a single centered column (Jetpack AI
	// Hub pattern) — quick-start link grid up top, section titles between.
	function sectionTitle( text ) {
		return el( 'h3', { className: 'untangling-section-title' }, text );
	}

	function QuickStartGrid() {
		var items = [
			{ icon: 'seen', title: 'Site visibility', badge: ( data.visibility && data.visibility.label ) || 'Public', badgeTone: ( data.visibility && data.visibility.tone ) || 'success', desc: 'Control who can view your site.', href: msd + '/sites/' + ( data.siteSlug || '' ) + '/settings/site-visibility' },
			{ icon: 'globe', title: 'Claim your domain', desc: 'Get ' + data.domainUpsell + ' for your site.', href: msd + '/domains' },
			{ icon: 'email', title: 'Start a newsletter', badge: 'Premium', upsell: 'Free' === data.plan, desc: 'Email new posts to your readers.', href: plansUrl },
			{ icon: 'stats', title: 'View your stats', desc: 'See which posts readers love most.', href: msd + '/stats' },
		];
		return el( 'div', { className: 'untangling-quick-grid' },
			items.map( function ( item, index ) {
				return el( HubRow, Object.assign( { key: index }, item ) );
			} )
		);
	}

	function OneColView( props ) {
		return el( Fragment, null,
			el( QuickStartOrGrow ),
			sectionTitle( 'Plan & products' ),
			el( PlanCard, { variant: props.plancard } ),
			el( StorageCard ),
			el( DomainCard ),
			el( EmailCard )
		);
	}

	// MSD overview-card pattern (dashboard-overview-card): whole card is a
	// link — icon + uppercase label + chevron up top, big heading, muted
	// description. Hover tints the card and flips all text to the accent.
	// Icon markup is copied verbatim from @wordpress/icons (the Gutenberg
	// icon library) — core doesn't expose wp.icons, so the paths are inlined.
	var OV_ICONS = {
		plan: WPCOM_MARK,
		stats: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path fill-rule="evenodd" clip-rule="evenodd" d="M11.25 5h1.5v15h-1.5V5zM6 10h1.5v10H6V10zm12 4h-1.5v6H18v-6z"/></svg>',
		globe: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M12 4c-4.4 0-8 3.6-8 8s3.6 8 8 8 8-3.6 8-8-3.6-8-8-8Zm6.5 8c0 .6 0 1.2-.2 1.8h-2.7c0-.6.2-1.1.2-1.8s0-1.2-.2-1.8h2.7c.2.6.2 1.1.2 1.8Zm-.9-3.2h-2.4c-.3-.9-.7-1.8-1.1-2.4-.1-.2-.2-.4-.3-.5 1.6.5 3 1.6 3.8 3ZM12.8 17c-.3.5-.6 1-.8 1.3-.2-.3-.5-.8-.8-1.3-.3-.5-.6-1.1-.8-1.7h3.3c-.2.6-.5 1.2-.8 1.7Zm-2.9-3.2c-.1-.6-.2-1.1-.2-1.8s0-1.2.2-1.8H14c.1.6.2 1.1.2 1.8s0 1.2-.2 1.8H9.9ZM11.2 7c.3-.5.6-1 .8-1.3.2.3.5.8.8 1.3.3.5.6 1.1.8 1.7h-3.3c.2-.6.5-1.2.8-1.7Zm-1-1.2c-.1.2-.2.3-.3.5-.4.7-.8 1.5-1.1 2.4H6.4c.8-1.4 2.2-2.5 3.8-3Zm-1.8 8H5.7c-.2-.6-.2-1.1-.2-1.8s0-1.2.2-1.8h2.7c0 .6-.2 1.1-.2 1.8s0 1.2.2 1.8Zm-2 1.4h2.4c.3.9.7 1.8 1.1 2.4.1.2.2.4.3.5-1.6-.5-3-1.6-3.8-3Zm7.4 3c.1-.2.2-.3.3-.5.4-.7.8-1.5 1.1-2.4h2.4c-.8 1.4-2.2 2.5-3.8 3Z"/></svg>',
		pencil: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="m19 7-3-3-8.5 8.5-1 4 4-1L19 7Zm-7 11.5H5V20h7v-1.5Z"/></svg>',
		// The MSD upsell diamond (client/dashboard/components/icons → `upsell`).
		upsell: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" aria-hidden="true"><path fill="currentColor" d="M18.9397 9.87999L15.4197 6.06999L15.3597 6.00999C15.2897 5.93999 15.1997 5.89999 15.0997 5.89999H8.87973C8.77973 5.89999 8.68973 5.93999 8.61973 6.00999L5.05973 9.87999C4.93973 10.01 4.93973 10.21 5.05973 10.34L11.5397 17.86C11.6497 17.99 11.8197 18.07 11.9997 18.07C12.1797 18.07 12.3397 17.99 12.4597 17.86L18.9397 10.34C19.0597 10.21 19.0497 10.01 18.9397 9.87999ZM15.4097 7.53999L17.3297 9.63999H15.1697L15.4097 7.53999ZM14.4297 6.83999L14.1097 9.63999H10.2897L9.64973 6.83999H14.4297ZM8.68973 7.42999L9.19973 9.63999H6.66973L8.68973 7.42999ZM6.61973 10.6H9.42973L10.8397 15.49L6.61973 10.6ZM12.0397 15.87L10.5297 10.6H13.8597L12.0397 15.87ZM14.9697 10.6H17.3797L13.3697 15.24L14.9697 10.6Z"/></svg>',
		storage: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M19 6.5H5c-.8 0-1.5.7-1.5 1.5v8c0 .8.7 1.5 1.5 1.5h14c.8 0 1.5-.7 1.5-1.5V8c0-.8-.7-1.5-1.5-1.5zM19 16H5V8h14v8zM7 13h10v1.5H7V13z"/></svg>',
		email: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path fill-rule="evenodd" clip-rule="evenodd" d="M3 7c0-1.1.9-2 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Zm2-.5h14c.3 0 .5.2.5.5v1L12 13.5 4.5 7.9V7c0-.3.2-.5.5-.5Zm-.5 3.3V17c0 .3.2.5.5.5h14c.3 0 .5-.2.5-.5V9.8L12 15.4 4.5 9.8Z"/></svg>',
	};
	// The upsell diamond cropped to its glyph (the 24×24 box is ~40% padding)
	// for tiny inline uses like badges, where the padded box reads as a
	// too-small icon with lopsided spacing.
	OV_ICONS.upsellGlyph = OV_ICONS.upsell.replace( 'viewBox="0 0 24 24" width="24" height="24"', 'viewBox="4.4 5.4 15.2 13.2"' );
	OV_ICONS.seen = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M3.99961 13C4.67043 13.3354 4.6703 13.3357 4.67017 13.3359L4.67298 13.3305C4.67621 13.3242 4.68184 13.3135 4.68988 13.2985C4.70595 13.2686 4.7316 13.2218 4.76695 13.1608C4.8377 13.0385 4.94692 12.8592 5.09541 12.6419C5.39312 12.2062 5.84436 11.624 6.45435 11.0431C7.67308 9.88241 9.49719 8.75 11.9996 8.75C14.502 8.75 16.3261 9.88241 17.5449 11.0431C18.1549 11.624 18.6061 12.2062 18.9038 12.6419C19.0523 12.8592 19.1615 13.0385 19.2323 13.1608C19.2676 13.2218 19.2933 13.2686 19.3093 13.2985C19.3174 13.3135 19.323 13.3242 19.3262 13.3305L19.3291 13.3359C19.3289 13.3357 19.3288 13.3354 19.9996 13C20.6704 12.6646 20.6703 12.6643 20.6701 12.664L20.6697 12.6632L20.6688 12.6614L20.6662 12.6563L20.6583 12.6408C20.6517 12.6282 20.6427 12.6108 20.631 12.5892C20.6078 12.5459 20.5744 12.4852 20.5306 12.4096C20.4432 12.2584 20.3141 12.0471 20.1423 11.7956C19.7994 11.2938 19.2819 10.626 18.5794 9.9569C17.1731 8.61759 14.9972 7.25 11.9996 7.25C9.00203 7.25 6.82614 8.61759 5.41987 9.9569C4.71736 10.626 4.19984 11.2938 3.85694 11.7956C3.68511 12.0471 3.55605 12.2584 3.4686 12.4096C3.42484 12.4852 3.39142 12.5459 3.36818 12.5892C3.35656 12.6108 3.34748 12.6282 3.34092 12.6408L3.33297 12.6563L3.33041 12.6614L3.32948 12.6632L3.32911 12.664C3.32894 12.6643 3.32879 12.6646 3.99961 13ZM11.9996 16C13.9326 16 15.4996 14.433 15.4996 12.5C15.4996 10.567 13.9326 9 11.9996 9C10.0666 9 8.49961 10.567 8.49961 12.5C8.49961 14.433 10.0666 16 11.9996 16Z"/></svg>';
	OV_ICONS.plugins = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M10.5 4v4h3V4H15v4h1.5a1 1 0 011 1v4l-3 4v2a1 1 0 01-1 1h-3a1 1 0 01-1-1v-2l-3-4V9a1 1 0 011-1H9V4h1.5zm.5 12.5v2h2v-2l3-4v-3H8v3l3 4z"/></svg>';
	OV_ICONS.performance = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M3.445 16.505a.75.75 0 001.06.05l5.005-4.55 4.024 3.521 4.716-4.715V14h1.5V8.25H14v1.5h3.19l-3.724 3.723L9.49 9.995l-5.995 5.45a.75.75 0 00-.05 1.06z"/></svg>';

	/* Hub variant: AI-Hub-style overview — an untitled stat strip on top,
	   then titled sections of icon row cards. */

	function HubRow( props ) {
		return el( 'a', { className: 'untangling-hubrow', href: props.href || '#' },
			el( 'span', { className: 'untangling-hubrow-icon', 'aria-hidden': true, dangerouslySetInnerHTML: { __html: OV_ICONS[ props.icon ] } } ),
			el( 'span', { className: 'untangling-hubrow-main' },
				el( 'span', { className: 'untangling-hubrow-title' },
					props.title,
					props.badge && el( 'span', { className: 'untangling-hubrow-badge' + ( props.badgeTone ? ' is-' + props.badgeTone : '' ) },
					props.upsell && el( 'span', { className: 'untangling-hubrow-badge-icon', 'aria-hidden': true, dangerouslySetInnerHTML: { __html: OV_ICONS.upsellGlyph } } ),
					props.badge
				)
				),
				el( 'span', { className: 'untangling-hubrow-desc' }, props.desc )
			),
			el( 'span', { className: 'untangling-ovcard-chevron', 'aria-hidden': true } )
		);
	}

	function HubStrip() {
		var storage = ( data.planMeta && data.planMeta.storage ) || [ 0.9, 1 ];
		var used = storage[ 0 ], total = storage[ 1 ];
		var percent = Math.round( 100 * used / total );
		var max = Math.max.apply( null, WEEK_VIEWS.map( function ( day ) { return day[ 1 ]; } ) );
		return el( 'div', { className: 'untangling-hubstrip' },
			el( 'div', { className: 'untangling-hubcell' },
				el( 'span', { className: 'untangling-hubcell-label' }, 'Plan' ),
				el( HStack, { justify: 'space-between', alignment: 'center' },
					el( 'span', { className: 'untangling-hubcell-value' }, data.plan || 'Free' ),
					el( Button, { variant: 'primary', className: 'untangling-upgrade', href: plansUrl }, 'Upgrade' )
				)
			),
			el( 'div', { className: 'untangling-hubcell' },
				el( 'span', { className: 'untangling-hubcell-label' }, 'Storage' ),
				el( HStack, { justify: 'space-between', alignment: 'baseline' },
					el( 'span', { className: 'untangling-hubcell-value' }, used + ' GB' ),
					el( 'span', { className: 'untangling-hubcell-max' }, total + ' GB' )
				),
				el( 'span', { className: 'untangling-meter is-warning', role: 'img', 'aria-label': 'Storage ' + percent + ' percent full' },
					el( 'span', { style: { width: percent + '%' } } )
				)
			),
			el( 'div', { className: 'untangling-hubcell' },
				el( 'span', { className: 'untangling-hubcell-label' }, 'Views this week' ),
				el( HStack, { justify: 'space-between', alignment: 'flex-end' },
					el( 'span', { className: 'untangling-hubcell-value' }, '1.2K' ),
					el( 'span', { className: 'untangling-spark is-compact', 'aria-hidden': true },
						WEEK_VIEWS.map( function ( day, index ) {
							return el( 'span', {
								key: index,
								className: 'untangling-spark-bar',
								style: { height: Math.round( 100 * day[ 1 ] / max ) + '%' },
								title: day[ 0 ] + ' · ' + day[ 1 ] + ' views',
							} );
						} )
					)
				)
			)
		);
	}

	function HubView() {
		return el( Fragment, null,
			el( HubStrip ),
			el( 'section', { className: 'untangling-section' },
				sectionHead( 'Keep it running' ),
				el( 'div', { className: 'untangling-tiles' },
					el( HubRow, { icon: 'plugins', title: '2 plugin updates ready', desc: 'A quick install keeps things secure.', href: 'plugins.php' } ),
					el( HubRow, { icon: 'performance', title: 'Test your performance', badge: 'Premium', desc: 'See how fast your site loads for readers.', href: plansUrl } )
				)
			),
			el( 'section', { className: 'untangling-section' },
				sectionHead( 'Grow your site' ),
				el( 'div', { className: 'untangling-tiles' },
					el( HubRow, { icon: 'globe', title: 'Get ' + data.domainUpsell, desc: 'Put your site on its own address.', href: msd + '/domains' } ),
					el( HubRow, { icon: 'stats', title: 'See your stats', desc: 'Views, visitors, and where readers come from.', href: msd + '/stats' } )
				)
			)
		);
	}

	function MsdCard( props ) {
		return el( 'a', { className: 'untangling-ovcard', href: props.href },
			el( 'span', { className: 'untangling-ovcard-top' },
				el( 'span', { className: 'untangling-ovcard-title' },
					props.icon && el( 'span', { className: 'untangling-ovcard-icon', 'aria-hidden': true, dangerouslySetInnerHTML: { __html: OV_ICONS[ props.icon ] } } ),
					props.label
				),
				el( 'span', { className: 'untangling-ovcard-chevron', 'aria-hidden': true } )
			),
			el( 'span', { className: 'untangling-ovcard-heading' }, props.heading ),
			props.desc && el( 'span', { className: 'untangling-ovcard-desc' }, props.desc )
		);
	}

	function OverviewRail() {
		var planMeta = data.planMeta || {};
		return el( Fragment, null,
			el( MsdCard, {
				icon: 'plan',
				label: 'Plan',
				heading: data.plan,
				desc: planMeta.renew || '',
				href: msd + '/plans',
			} ),
			el( MsdCard, {
				icon: 'stats',
				label: 'Audience',
				heading: '1.2K views',
				desc: '214 subscribers · +12 this month.',
				href: msd + '/stats',
			} ),
			'Free' !== data.plan && el( MsdCard, {
				icon: 'storage',
				label: 'Storage',
				heading: '0.7 of 1 GB',
				desc: 'Filling up — photos eat space fast.',
				href: msd + '/plans',
			} ),
			el( MsdCard, {
				icon: 'globe',
				label: 'Site address',
				heading: data.domain,
				desc: data.domainUpsell + ' is available.',
				href: msd + '/domains',
			} ),
			'Free' !== data.plan && el( MsdCard, {
				icon: 'email',
				label: 'Email',
				heading: 'No custom email',
				desc: 'Get hello@' + data.domainUpsell + ' for your blog.',
				href: msd + '/emails',
			} )
		);
	}

	// Help & Learn hero: a real prompt box instead of a button that only opens
	// an empty panel. Enter or "Ask" hands the question to the Support
	// Assistant panel in the admin footer, which posts it, thinks, and
	// answers — so the demo shows the whole path without leaving wp-admin.
	// Copy comes from untangling_help_panel_data() so both Help & Learn
	// surfaces and the panel stay in sync.
	function HelpCard() {
		var help = window.untanglingHelpData || {};
		var draftState = useState( '' );
		var draft = draftState[ 0 ], setDraft = draftState[ 1 ];

		function ask( question ) {
			var text = ( question || '' ).trim();
			if ( ! text || ! window.untanglingHelp ) {
				return;
			}
			setDraft( '' );
			window.untanglingHelp.open( text );
		}

		return el( Card, { className: 'untangling-help-card' },
			el( CardBody, null,
				el( 'div', { className: 'untangling-ask-head' },
					el( 'span', { className: 'untangling-ask-mark', 'aria-hidden': true }, sparkMark() ),
					el( 'div', null,
						title( help.heading || 'Ask about your site' ),
						meta( help.lede || '' )
					)
				),
				el( 'form', {
					className: 'untangling-ask-form',
					onSubmit: function ( event ) {
						event.preventDefault();
						ask( draft );
					},
				},
					el( 'input', {
						type: 'text',
						className: 'untangling-ask-input',
						value: draft,
						placeholder: help.placeholder || '',
						'aria-label': help.heading || 'Ask about your site',
						autoComplete: 'off',
						onChange: function ( event ) {
							setDraft( event.target.value );
						},
					} ),
					el( Button, { variant: 'primary', type: 'submit', disabled: ! draft.trim() }, help.cta || 'Ask' )
				)
			)
		);
	}

	// Launchpad tab: blogger onboarding checklist (prototype placeholder).
	function LaunchpadView() {
		var tasks = [
			[ 'Name your site', true ],
			[ 'Choose a design', true ],
			[ 'Write your first post', true ],
			[ 'Claim a custom domain', false, 'Get ' + data.domainUpsell + ' ↗', msd + '/domains' ],
			[ 'Send your first newsletter', false, 'Write newsletter ↗', msd ],
			[ 'Earn your first payment', false, 'Set up payments ↗', msd ],
		];
		var doneCount = tasks.filter( function ( t ) { return t[ 1 ]; } ).length;
		var bar = ProgressBar
			? el( ProgressBar, { value: Math.round( 100 * doneCount / tasks.length ), className: 'untangling-progress' } )
			: null;
		return el( 'div', { className: 'untangling-narrow' },
			el( Card, null,
				el( CardHeader, null,
					title( 'Launchpad' ),
					meta( doneCount + ' of ' + tasks.length + ' complete' )
				),
				el( CardBody, null,
					bar,
					el( 'ul', { className: 'untangling-launchpad-list' },
						tasks.map( function ( task, index ) {
							return el( 'li', { key: index, className: task[ 1 ] ? 'is-done' : '' },
								el( HStack, { justify: 'space-between', alignment: 'center', wrap: true },
									el( FlexItem, null,
										el( 'span', { className: 'untangling-launchpad-mark' }, task[ 1 ] ? '✓' : '' ),
										el( 'span', { className: 'untangling-launchpad-label' }, task[ 0 ] )
									),
									! task[ 1 ] && el( FlexItem, null,
										el( Button, { variant: 'tertiary', href: task[ 3 ] }, task[ 2 ] )
									)
								)
							);
						} )
					)
				)
			)
		);
	}

	// Help tab: the assistant card plus a short guides list.
	// Learn tab: a visual learning hub at the My Site content width.
	// Real content: videos from youtube.com/@wordpressdotcom, courses and
	// guides from wordpress.com/support; thumbnails hotlinked from both.
	var LEARN_VIDEOS = [
		[ 'wm0jPV234zc', 'The New WordPress Editor Built for Writing', '3:56' ],
		[ '9rCal5dxMiM', 'Turn a rough draft into a finished post with AI', '3:31' ],
		[ 'KDLqEd_QAD8', 'Connect Claude to your WordPress.com site', '3:30' ],
		[ 'OFRAPMPQMeA', 'Create and edit navigation menus', '6:12' ],
		[ 'wHz4uiaBbOE', 'Connect an existing domain to WordPress.com', '10:12' ],
		[ 'i0zK9qNIj_g', 'WordPress Forms Made Simple', '8:14' ],
	];

	var LEARN_COURSES = [
		[ 'https://wordpress.com/support/courses/create-your-blog/', 'https://en.support.wordpress.com/wp-content/uploads/2025/06/blogging-course.jpg', 'Create your blog', '1 hour', 'Set up your blog, publish posts, and grow your audience.' ],
		[ 'https://wordpress.com/support/courses/grow-your-audience/', 'https://en.support.wordpress.com/wp-content/uploads/2025/11/final.png', 'Grow your audience', '1 hour', 'Attract visitors, build subscribers, and engage your community.' ],
		[ 'https://wordpress.com/support/courses/monetize-your-website/', 'https://en.support.wordpress.com/wp-content/uploads/2026/02/thumbnail-monetize-your-site.png', 'Monetize your site', '1 hour', 'Charge for content, sell products, and grow recurring revenue.' ],
	];

	// Icon paths copied from @wordpress/icons (Gutenberg icon library) —
	// core does not register the package as a script, so they are inlined.
	// Entries: [ path d, uses evenodd fill rule ].
	var DS_ICONS = {
		'tip': [ 'M12 15.8c-3.7 0-6.8-3-6.8-6.8s3-6.8 6.8-6.8c3.7 0 6.8 3 6.8 6.8s-3.1 6.8-6.8 6.8zm0-12C9.1 3.8 6.8 6.1 6.8 9s2.4 5.2 5.2 5.2c2.9 0 5.2-2.4 5.2-5.2S14.9 3.8 12 3.8zM8 17.5h8V19H8zM10 20.5h4V22h-4z', false ],
		'globe': [ 'M12 4c-4.4 0-8 3.6-8 8s3.6 8 8 8 8-3.6 8-8-3.6-8-8-8Zm6.5 8c0 .6 0 1.2-.2 1.8h-2.7c0-.6.2-1.1.2-1.8s0-1.2-.2-1.8h2.7c.2.6.2 1.1.2 1.8Zm-.9-3.2h-2.4c-.3-.9-.7-1.8-1.1-2.4-.1-.2-.2-.4-.3-.5 1.6.5 3 1.6 3.8 3ZM12.8 17c-.3.5-.6 1-.8 1.3-.2-.3-.5-.8-.8-1.3-.3-.5-.6-1.1-.8-1.7h3.3c-.2.6-.5 1.2-.8 1.7Zm-2.9-3.2c-.1-.6-.2-1.1-.2-1.8s0-1.2.2-1.8H14c.1.6.2 1.1.2 1.8s0 1.2-.2 1.8H9.9ZM11.2 7c.3-.5.6-1 .8-1.3.2.3.5.8.8 1.3.3.5.6 1.1.8 1.7h-3.3c.2-.6.5-1.2.8-1.7Zm-1-1.2c-.1.2-.2.3-.3.5-.4.7-.8 1.5-1.1 2.4H6.4c.8-1.4 2.2-2.5 3.8-3Zm-1.8 8H5.7c-.2-.6-.2-1.1-.2-1.8s0-1.2.2-1.8h2.7c0 .6-.2 1.1-.2 1.8s0 1.2.2 1.8Zm-2 1.4h2.4c.3.9.7 1.8 1.1 2.4.1.2.2.4.3.5-1.6-.5-3-1.6-3.8-3Zm7.4 3c.1-.2.2-.3.3-.5.4-.7.8-1.5 1.1-2.4h2.4c-.8 1.4-2.2 2.5-3.8 3Z', false ],
		'pencil': [ 'm19 7-3-3-8.5 8.5-1 4 4-1L19 7Zm-7 11.5H5V20h7v-1.5Z', false ],
		'chart-bar': [ 'M11.25 5h1.5v15h-1.5V5zM6 10h1.5v10H6V10zm12 4h-1.5v6H18v-6z', true ],
		'currency-dollar': [ 'M10.7 9.6c.3-.2.8-.4 1.3-.4s1 .2 1.3.4c.3.2.4.5.4.6 0 .4.3.8.8.8s.8-.3.8-.8c0-.8-.5-1.4-1.1-1.9-.4-.3-.9-.5-1.4-.6v-.3c0-.4-.3-.8-.8-.8s-.8.3-.8.8v.3c-.5 0-1 .3-1.4.6-.6.4-1.1 1.1-1.1 1.9s.5 1.4 1.1 1.9c.6.4 1.4.6 2.2.6h.2c.5 0 .9.2 1.1.4.3.2.4.5.4.6s0 .4-.4.6c-.3.2-.8.4-1.3.4s-1-.2-1.3-.4c-.3-.2-.4-.5-.4-.6 0-.4-.3-.8-.8-.8s-.8.3-.8.8c0 .8.5 1.4 1.1 1.9.4.3.9.5 1.4.6v.3c0 .4.3.8.8.8s.8-.3.8-.8v-.3c.5 0 1-.3 1.4-.6.6-.4 1.1-1.1 1.1-1.9s-.5-1.4-1.1-1.9c-.5-.4-1.2-.6-1.9-.6H12c-.6 0-1-.2-1.3-.4-.3-.2-.4-.5-.4-.6s0-.4.4-.6ZM12 4c-4.4 0-8 3.6-8 8s3.6 8 8 8 8-3.6 8-8-3.6-8-8-8Zm0 14.5c-3.6 0-6.5-2.9-6.5-6.5S8.4 5.5 12 5.5s6.5 2.9 6.5 6.5-2.9 6.5-6.5 6.5Z', false ],
		'login': [ 'M11 14.5l1.1 1.1 3-3 .5-.5-.6-.6-3-3-1 1 1.7 1.7H5v1.5h7.7L11 14.5zM16.8 5h-7c-1.1 0-2 .9-2 2v1.5h1.5V7c0-.3.2-.5.5-.5h7c.3 0 .5.2.5.5v10c0 .3-.2.5-.5.5h-7c-.3 0-.5-.2-.5-.5v-1.5H7.8V17c0 1.1.9 2 2 2h7c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2z', false ],
		'comment': [ 'M18 4H6c-1.1 0-2 .9-2 2v12.9c0 .6.5 1.1 1.1 1.1.3 0 .5-.1.8-.3L8.5 17H18c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm.5 11c0 .3-.2.5-.5.5H7.9l-2.4 2.4V6c0-.3.2-.5.5-.5h12c.3 0 .5.2.5.5v9z', false ],
		'people': [ 'M15.5 9.5a1 1 0 100-2 1 1 0 000 2zm0 1.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5zm-2.25 6v-2a2.75 2.75 0 00-2.75-2.75h-4A2.75 2.75 0 003.75 15v2h1.5v-2c0-.69.56-1.25 1.25-1.25h4c.69 0 1.25.56 1.25 1.25v2h1.5zm7-2v2h-1.5v-2c0-.69-.56-1.25-1.25-1.25H15v-1.5h2.5A2.75 2.75 0 0120.25 15zM9.5 8.5a1 1 0 11-2 0 1 1 0 012 0zm1.5 0a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z', true ],
	};

	function dsIcon( name ) {
		var icon = DS_ICONS[ name ];
		return el( 'svg', { className: 'untangling-ds-icon', viewBox: '0 0 24 24', fill: 'currentColor', 'aria-hidden': true },
			el( 'path', icon[ 1 ] ? { d: icon[ 0 ], fillRule: 'evenodd', clipRule: 'evenodd' } : { d: icon[ 0 ] } )
		);
	}

	// The four-point star the Support Assistant panel uses for its own avatar,
	// so the CTA and the answers it produces read as the same assistant.
	var SPARK_PATH = 'M12 2l2.2 7.8L22 12l-7.8 2.2L12 22l-2.2-7.8L2 12l7.8-2.2z';

	function sparkMark() {
		return el( 'svg', { className: 'untangling-ds-icon', viewBox: '0 0 24 24', fill: 'currentColor', 'aria-hidden': true },
			el( 'path', { d: SPARK_PATH } )
		);
	}

	var LEARN_GUIDES = [
		[ 'tip', 'Get started', [
			[ 'Build a website with AI', 'https://wordpress.com/support/ai-website-builder/' ],
			[ 'Build your website in five steps', 'https://wordpress.com/support/five-step-website-setup/' ],
			[ 'Set up your blog in five steps', 'https://wordpress.com/support/five-step-blog-setup/' ],
		] ],
		[ 'globe', 'Domains', [
			[ 'Register a new domain name', 'https://wordpress.com/support/domains/register-domain/' ],
			[ 'Connecting vs transferring a domain', 'https://wordpress.com/support/domain-connection-vs-domain-transfer/' ],
			[ 'Set up your custom domain', 'https://wordpress.com/support/domains/' ],
		] ],
		[ 'pencil', 'Create content', [
			[ 'About the WordPress editors', 'https://wordpress.com/support/editors/' ],
			[ 'Start from pre-built content', 'https://wordpress.com/support/starter-content/' ],
			[ 'Improve a post with Jetpack AI', 'https://wordpress.com/support/wordpress-editor/jetpack-ai/' ],
		] ],
		[ 'chart-bar', 'Grow your audience', [
			[ 'Increase your site’s traffic', 'https://wordpress.com/support/getting-more-views-and-traffic/' ],
			[ 'Optimize for search engines (SEO)', 'https://wordpress.com/support/seo/' ],
			[ 'Advertise your content with Blaze', 'https://wordpress.com/support/promote-a-post/' ],
		] ],
		[ 'currency-dollar', 'Monetize', [
			[ 'Earn money from ads', 'https://wordpress.com/support/wordads-and-earn/' ],
			[ 'Accept payments', 'https://wordpress.com/support/wordpress-editor/blocks/payments/accept-payments/' ],
			[ 'Sell digital products', 'https://wordpress.com/support/sell-digital-products/' ],
		] ],
		[ 'login', 'Move your site', [
			[ 'Migrate a site to WordPress.com', 'https://wordpress.com/support/import/import-an-entire-wordpress-site/' ],
			[ 'Import a website', 'https://wordpress.com/support/import/' ],
			[ 'Request a free migration', 'https://wordpress.com/support/request-a-free-migration/' ],
		] ],
	];

	var LEARN_SUPPORT = [
		[ 'comment', 'Contact us', 'Get answers from our AI assistant, with access to 24/7 expert human support on paid plans.', 'https://wordpress.com/help/contact/' ],
		[ 'people', 'Ask a question in our forum', 'Browse questions and get answers from other experienced users.', 'https://wordpress.com/forums/' ],
	];

	function learnHead( heading, linkLabel, href ) {
		return el( 'div', { className: 'untangling-learn-head' },
			sectionTitle( heading ),
			linkLabel && el( Button, { variant: 'tertiary', href: href, target: '_blank' }, linkLabel + ' ↗' )
		);
	}

	function VideoCard( props ) {
		var video = props.video;
		return el( 'a', { className: 'untangling-media-card', href: 'https://www.youtube.com/watch?v=' + video[ 0 ], target: '_blank', rel: 'noreferrer' },
			el( 'span', { className: 'untangling-media-thumb' },
				el( 'img', { src: 'https://i.ytimg.com/vi/' + video[ 0 ] + '/hq720.jpg', alt: '', loading: 'lazy' } ),
				el( 'span', { className: 'untangling-media-play', 'aria-hidden': true } )
			),
			el( 'span', { className: 'untangling-media-body' },
				el( 'span', { className: 'untangling-media-row' },
					el( 'span', { className: 'untangling-media-title' }, video[ 1 ] ),
					el( 'span', { className: 'untangling-media-duration' }, video[ 2 ] )
				)
			)
		);
	}

	function CourseCard( props ) {
		var course = props.course;
		return el( 'a', { className: 'untangling-media-card', href: course[ 0 ], target: '_blank', rel: 'noreferrer' },
			el( 'span', { className: 'untangling-media-thumb' },
				el( 'img', { src: course[ 1 ], alt: '', loading: 'lazy' } )
			),
			el( 'span', { className: 'untangling-media-body' },
				el( 'span', { className: 'untangling-media-row' },
					el( 'span', { className: 'untangling-media-title' }, course[ 2 ] ),
					el( 'span', { className: 'untangling-media-duration' }, course[ 3 ] )
				),
				el( 'span', { className: 'untangling-media-desc' }, course[ 4 ] )
			)
		);
	}

	function GuideTopicCard( props ) {
		var topic = props.topic;
		return el( 'div', { className: 'untangling-topic-card' },
			el( 'span', { className: 'untangling-topic-icon', 'aria-hidden': true },
				dsIcon( topic[ 0 ] )
			),
			el( 'span', { className: 'untangling-topic-title' }, topic[ 1 ] ),
			el( 'span', { className: 'untangling-topic-links' },
				topic[ 2 ].map( function ( link, index ) {
					return el( 'a', { key: index, href: link[ 1 ], target: '_blank', rel: 'noreferrer' }, link[ 0 ] );
				} )
			)
		);
	}

	function SupportCard( props ) {
		var item = props.item;
		return el( 'a', { className: 'untangling-support-card', href: item[ 3 ], target: '_blank', rel: 'noreferrer' },
			el( 'span', { className: 'untangling-support-icon', 'aria-hidden': true },
				dsIcon( item[ 0 ] )
			),
			el( 'span', { className: 'untangling-media-title' }, item[ 1 ] ),
			el( 'span', { className: 'untangling-media-desc' }, item[ 2 ] )
		);
	}

	function HelpView() {
		return el( 'div', { className: 'untangling-learn' },
			el( HelpCard ),
			el( 'section', { className: 'untangling-learn-section' },
				learnHead( 'Video tutorials', 'Visit our YouTube channel', 'https://www.youtube.com/@wordpressdotcom' ),
				el( 'div', { className: 'untangling-media-grid' },
					LEARN_VIDEOS.map( function ( video ) { return el( VideoCard, { key: video[ 0 ], video: video } ); } )
				)
			),
			el( 'section', { className: 'untangling-learn-section' },
				learnHead( 'Courses', 'Browse all courses', 'https://wordpress.com/support/courses/' ),
				el( 'div', { className: 'untangling-media-grid' },
					LEARN_COURSES.map( function ( course, index ) { return el( CourseCard, { key: index, course: course } ); } )
				)
			),
			el( 'section', { className: 'untangling-learn-section' },
				learnHead( 'Guides', 'View all guides', 'https://wordpress.com/support/guides/' ),
				el( 'div', { className: 'untangling-media-grid' },
					LEARN_GUIDES.map( function ( topic ) { return el( GuideTopicCard, { key: topic[ 1 ], topic: topic } ); } )
				)
			),
			el( 'section', { className: 'untangling-learn-section' },
				learnHead( 'Couldn’t find what you needed?' ),
				el( 'div', { className: 'untangling-support-grid' },
					LEARN_SUPPORT.map( function ( item ) { return el( SupportCard, { key: item[ 1 ], item: item } ); } )
				)
			)
		);
	}

	// Prototype chrome: a quiet W button that opens the controls panel — a DS
	// Card built from wp.components (Card, SelectControl, ToggleGroupControl,
	// Button). Drag the fab or the panel header to reposition it anywhere on
	// the page; the position lives only for the current page view, so every
	// load starts back at the default bottom-right corner. The panel's IA is
	// two groups: "This page" (client-side switches — localStorage +
	// ?untangling_plancard= for shareable links, instant) and "Site-wide"
	// (persisted server-side, reload through go()). Long option lists render
	// as compact selects, 2–3-way switches as toggle groups (the DS guidance
	// for each), so the panel fits a laptop viewport without scrolling.

	// One line about the selected value only — the full three-way comparison
	// used to live in a five-line help paragraph.
	var MARKETPLACE_HELP = {
		fullscreen: 'Themes + plugins in the chromeless Marketplace.',
		split: 'Production Atomic: core Themes screen + Appearance → Theme Showcase; plugins keep the Add Plugins tab.',
		tabs: 'Marketplace tabs in Add Plugins and Add Themes, plus plans-upsell banners.',
	};
	var PLAN_FILTER_HELP = {
		included: '“Included with my plan” links on both Marketplace tabs.',
		dropdown: 'A tier dropdown on both Marketplace tabs.',
	};

	function ProtoPanel( props ) {
		var openState = useState( false );
		var open = openState[ 0 ], setOpen = openState[ 1 ];
		var copiedState = useState( false );
		var copied = copiedState[ 0 ], setCopied = copiedState[ 1 ];
		var posState = useState( null ); // null = the default bottom-right corner, from CSS.
		var pos = posState[ 0 ], setPos = posState[ 1 ];
		var wrapRef = useRef( null );
		var dragRef = useRef( null );

		function clamp( left, top ) {
			var node = wrapRef.current;
			var margin = 8;
			var maxLeft = window.innerWidth - ( node ? node.offsetWidth : 44 ) - margin;
			var maxTop = window.innerHeight - ( node ? node.offsetHeight : 44 ) - margin;
			return {
				left: Math.max( margin, Math.min( left, maxLeft ) ),
				top: Math.max( margin, Math.min( top, maxTop ) ),
			};
		}

		// Re-clamp when the fab grows into the panel or the window shrinks, so
		// a saved position never leaves the panel half off-screen.
		useLayoutEffect( function () {
			function reclamp() {
				setPos( function ( current ) {
					return current ? clamp( current.left, current.top ) : current;
				} );
			}
			reclamp();
			window.addEventListener( 'resize', reclamp );
			return function () { window.removeEventListener( 'resize', reclamp ); };
		}, [ open ] );

		function startDrag( event ) {
			if ( 0 !== event.button ) {
				return;
			}
			var rect = wrapRef.current.getBoundingClientRect();
			var drag = { dx: event.clientX - rect.left, dy: event.clientY - rect.top, sx: event.clientX, sy: event.clientY, moved: false };
			dragRef.current = drag;
			function onMove( ev ) {
				// 5px threshold keeps plain clicks (open, close) from turning into drags.
				if ( ! drag.moved && Math.hypot( ev.clientX - drag.sx, ev.clientY - drag.sy ) < 5 ) {
					return;
				}
				drag.moved = true;
				setPos( clamp( ev.clientX - drag.dx, ev.clientY - drag.dy ) );
			}
			function onUp() {
				window.removeEventListener( 'pointermove', onMove );
				window.removeEventListener( 'pointerup', onUp );
				// The click suppression only needs to survive until the click
				// event that follows this pointerup; a header drag produces no
				// such click, so clear the flag before it can swallow the next
				// real button press.
				window.setTimeout( function () {
					if ( dragRef.current === drag ) {
						dragRef.current = null;
					}
				}, 0 );
			}
			window.addEventListener( 'pointermove', onMove );
			window.addEventListener( 'pointerup', onUp );
		}

		function headerDrag( event ) {
			if ( event.target.closest( 'button, a, input, label' ) ) {
				return;
			}
			startDrag( event );
		}

		// The click that lands after a drag's pointerup must not toggle the panel.
		function afterDragClick( action ) {
			return function () {
				var drag = dragRef.current;
				dragRef.current = null;
				if ( drag && drag.moved ) {
					return;
				}
				action();
			};
		}

		function copyLink() {
			var url = new URL( window.location.href );
			url.searchParams.set( 'untangling_plancard', props.plancard );
			url.searchParams.set( 'untangling_header', props.header );
			navigator.clipboard.writeText( url.toString() ).then( function () {
				setCopied( true );
				window.setTimeout( function () { setCopied( false ); }, 2000 );
			} );
		}

		var mark = el( 'span', {
			className: 'untangling-proto-mark',
			'aria-hidden': true,
			dangerouslySetInnerHTML: { __html: WPCOM_MARK },
		} );

		return el( 'div', {
			className: 'untangling-proto-wrap',
			ref: wrapRef,
			style: pos ? { left: pos.left + 'px', top: pos.top + 'px', right: 'auto', bottom: 'auto' } : null,
		},
			! open && el( Button, {
				className: 'untangling-proto-fab',
				label: 'Prototype controls',
				icon: mark,
				onPointerDown: startDrag,
				onClick: afterDragClick( function () { setOpen( true ); } ),
			} ),
			open && el( Card, { className: 'untangling-proto-panel', size: 'small', elevation: 3 },
				el( CardHeader, { className: 'untangling-proto-head', onPointerDown: headerDrag },
					el( Text, { upperCase: true, size: 11, weight: 500, variant: 'muted' }, 'Prototype controls' ),
					el( Button, {
						size: 'small',
						variant: 'tertiary',
						className: 'untangling-proto-min',
						label: 'Minimize',
						// The panel collapses into the fab rather than closing, so
						// the control is a minimize dash (@wordpress/icons lineSolid),
						// not an ✕.
						icon: el( 'svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24, 'aria-hidden': true },
							el( 'path', { d: 'M5 11.25h14v1.5H5z' } )
						),
						onClick: afterDragClick( function () {
							setOpen( false );
							setPos( null ); // minimizing snaps the fab back to its home corner
						} ),
					} )
				),
				el( CardBody, null,
					el( VStack, { spacing: 2 },
						el( Text, { upperCase: true, size: 11, weight: 500, variant: 'muted' }, 'This page' ),
						'Free' === data.plan && el( SelectControl, {
							label: 'Plan card',
							value: props.plancard,
							options: PLANCARD_VARIANTS,
							size: 'compact',
							__nextHasNoMarginBottom: true,
							onChange: props.onPlancard,
						} ),
						el( SelectControl, {
							label: 'My site layout',
							value: props.mysite,
							options: MYSITE_VARIANTS,
							size: 'compact',
							__nextHasNoMarginBottom: true,
							onChange: props.onMysite,
						} ),
						ToggleGroup && el( ToggleGroup, {
							label: 'Header',
							value: props.header,
							isBlock: true,
							__nextHasNoMarginBottom: true,
							onChange: props.onHeader,
						},
							el( ToggleGroupOption, { value: 'hosting', label: 'Breadcrumb' } ),
							el( ToggleGroupOption, { value: 'site', label: 'Site identity' } )
						)
					)
				),
				el( CardDivider ),
				el( CardBody, null,
					el( VStack, { spacing: 2 },
						el( Text, { upperCase: true, size: 11, weight: 500, variant: 'muted' }, 'Site-wide · reloads' ),
						ToggleGroup && el( ToggleGroup, {
							label: 'Site type',
							value: data.siteType,
							isBlock: true,
							__nextHasNoMarginBottom: true,
							help: 'Simple = Free plan · Atomic = Business plan',
							onChange: function ( value ) { go( 'untangling_site_type', value ); },
						},
							el( ToggleGroupOption, { value: 'atomic', label: 'Atomic' } ),
							el( ToggleGroupOption, { value: 'simple', label: 'Simple' } )
						),
						ToggleGroup && el( ToggleGroup, {
							label: 'Marketplace',
							value: data.marketplace,
							isBlock: true,
							__nextHasNoMarginBottom: true,
							help: MARKETPLACE_HELP[ data.marketplace ],
							onChange: function ( value ) { go( 'untangling_marketplace', value ); },
						},
							el( ToggleGroupOption, { value: 'fullscreen', label: 'Fullscreen' } ),
							el( ToggleGroupOption, { value: 'split', label: 'Split' } ),
							el( ToggleGroupOption, { value: 'tabs', label: 'Tabs' } )
						),
						'tabs' === data.marketplace && ToggleGroup && el( ToggleGroup, {
							label: 'Plan filter',
							value: data.planFilter,
							isBlock: true,
							__nextHasNoMarginBottom: true,
							help: PLAN_FILTER_HELP[ data.planFilter ],
							onChange: function ( value ) { go( 'untangling_plan_filter', value ); },
						},
							el( ToggleGroupOption, { value: 'included', label: 'Included' } ),
							el( ToggleGroupOption, { value: 'dropdown', label: 'Dropdown' } )
						)
					)
				),
				el( CardFooter, { className: 'untangling-proto-foot' },
					el( Button, { variant: 'link', onClick: copyLink }, copied ? 'Copied ✓' : 'Copy link' ),
					data.planOverride && el( Button, {
						variant: 'link',
						isDestructive: true,
						onClick: function () { go( 'untangling_reset_demo', '1' ); },
					}, 'Reset demo (' + data.plan + ')' )
				)
			)
		);
	}

	function App() {
		// Keep the tab deep-linkable (?untangling_tab=) without a reload.
		function onSelect( tabName ) {
			var url = new URL( window.location.href );
			url.searchParams.set( 'untangling_tab', tabName );
			window.history.replaceState( null, '', url.toString() );
		}

		var plancardState = useState( initialPlancard() );
		var plancard = plancardState[ 0 ], setPlancard = plancardState[ 1 ];
		function choosePlancard( value ) {
			setPlancard( value );
			try { window.localStorage.setItem( 'untangling-plancard', value ); } catch ( e ) {}
			var url = new URL( window.location.href );
			url.searchParams.set( 'untangling_plancard', value );
			window.history.replaceState( null, '', url.toString() );
		}

		var mysiteState = useState( initialMysite() );
		var mysite = mysiteState[ 0 ], setMysite = mysiteState[ 1 ];
		function chooseMysite( value ) {
			setMysite( value );
			try { window.localStorage.setItem( 'untangling-mysite', value ); } catch ( e ) {}
			var url = new URL( window.location.href );
			url.searchParams.set( 'untangling_mysite', value );
			window.history.replaceState( null, '', url.toString() );
		}

		var headerState = useState( initialHeader() );
		var header = headerState[ 0 ], setHeader = headerState[ 1 ];
		function chooseHeader( value ) {
			setHeader( value );
			try { window.localStorage.setItem( 'untangling-header', value ); } catch ( e ) {}
			var url = new URL( window.location.href );
			url.searchParams.set( 'untangling_header', value );
			window.history.replaceState( null, '', url.toString() );
		}

		return el( 'div', null,
			el( Header, { variant: header } ),
			el( TabPanel, {
				className: 'untangling-tabpanel',
				tabs: TABS,
				initialTabName: initialTab(),
				onSelect: onSelect,
			}, function ( tab ) {
				return el( 'div', { className: 'untangling-content' },
					'my-site' === tab.name && 'onecol' === mysite && el( 'div', { className: 'untangling-narrow' },
						el( OneColView, { plancard: plancard } )
					),
					'my-site' === tab.name && 'hub' === mysite && el( 'div', { className: 'untangling-narrow' },
						el( HubView )
					),
					'my-site' === tab.name && 'onecol' !== mysite && 'hub' !== mysite && el( 'div', { className: 'untangling-grid' },
						el( 'div', { className: 'untangling-col' },
							'momentum' === mysite && el( MomentumView ),
							'earn' === mysite && el( EarnView ),
							el( 'section', { className: 'untangling-section' },
								sectionHead( 'Site health', 'Good' ),
								el( 'div', { className: 'untangling-tiles' },
									HEALTH_CHECKS.map( function ( check, index ) {
										return el( HealthCheckCard, { key: index, check: check } );
									} )
								)
							),
							el( 'section', { className: 'untangling-section' },
								sectionHead( 'At a glance' ),
								el( 'div', { className: 'untangling-tiles' },
									el( ViewsTile ),
									el( PerformanceTile ),
									el( VisibilityTile ),
									el( StorageTile )
								)
							)
						),
						'cards' === mysite
							? el( 'div', { className: 'untangling-col' }, el( OverviewRail ) )
							: el( 'div', { className: 'untangling-col' },
								el( PlanCard, { variant: plancard } ),
								el( DomainCard ),
								el( StorageCard ),
								el( EmailCard )
							)
					),
					'learn-more' === tab.name && el( HelpView )
				);
			} ),
			! window.untanglingData.locked && el( ProtoPanel, { plancard: plancard, onPlancard: choosePlancard, mysite: mysite, onMysite: chooseMysite, header: header, onHeader: chooseHeader } )
		);
	}

	var root = document.getElementById( 'untangling-root' );
	if ( root && wp.element.createRoot ) {
		wp.element.createRoot( root ).render( el( App ) );
	}

	// Feature tooltips open above where the cursor entered, then stay put —
	// mouseover fires once per entry, so the bubble doesn't chase the cursor.
	// (Delegated: the spans re-render when the plan-card variant switches.)
	document.addEventListener( 'mouseover', function ( event ) {
		var tip = event.target && event.target.closest && event.target.closest( '.untangling-feature-tip' );
		if ( tip ) {
			tip.style.setProperty( '--untangling-tip-x', ( event.clientX - tip.getBoundingClientRect().left ) + 'px' );
		}
	} );
} )();
JS;
}

function untangling_app_css() {
	return <<<'CSS'
/* Official @wordpress/theme design tokens (prebuilt, vendored verbatim —
   core does not ship @wordpress/theme yet). Scoped to the page. */
body.toplevel_page_untangling-hosting,
.untangling-app {
	--wpds-border-radius-xs: 1px;
	--wpds-border-radius-sm: 2px;
	--wpds-border-radius-md: 4px;
	--wpds-border-radius-lg: 8px;
	--wpds-border-radius-xl: 12px;
	--wpds-border-width-xs: 1px;
	--wpds-border-width-sm: 2px;
	--wpds-border-width-md: 4px;
	--wpds-border-width-lg: 8px;
	--wpds-border-width-focus: 2px;
	--wpds-color-background-surface-neutral: #fcfcfc;
	--wpds-color-background-surface-neutral-strong: #fff;
	--wpds-color-background-surface-neutral-weak: #f4f4f4;
	--wpds-color-background-surface-brand: #ecf0fa;
	--wpds-color-background-surface-success: #c6f7cd;
	--wpds-color-background-surface-success-weak: #ebffed;
	--wpds-color-background-surface-info: #deebfa;
	--wpds-color-background-surface-info-weak: #f3f9ff;
	--wpds-color-background-surface-warning: #fde6be;
	--wpds-color-background-surface-warning-weak: #fff7e1;
	--wpds-color-background-surface-caution: #fee995;
	--wpds-color-background-surface-caution-weak: #fff9ca;
	--wpds-color-background-surface-error: #f6e6e3;
	--wpds-color-background-surface-error-weak: #fff6f5;
	--wpds-color-background-interactive-neutral-strong: #2d2d2d;
	--wpds-color-background-interactive-neutral-strong-active: #1e1e1e;
	--wpds-color-background-interactive-neutral-strong-disabled: #e6e6e6;
	--wpds-color-background-interactive-neutral-weak: #0000;
	--wpds-color-background-interactive-neutral-weak-active: #ededed;
	--wpds-color-background-interactive-neutral-weak-disabled: #0000;
	--wpds-color-background-interactive-brand-strong: #3858e9;
	--wpds-color-background-interactive-brand-strong-active: #2e49d9;
	--wpds-color-background-interactive-brand-strong-disabled: #e6e6e6;
	--wpds-color-background-interactive-brand-weak: #0000;
	--wpds-color-background-interactive-brand-weak-active: #e6eaf4;
	--wpds-color-background-interactive-brand-weak-disabled: #0000;
	--wpds-color-background-interactive-error: #0000;
	--wpds-color-background-interactive-error-active: #fff6f5;
	--wpds-color-background-interactive-error-disabled: #0000;
	--wpds-color-background-interactive-error-strong: #cc1818;
	--wpds-color-background-interactive-error-strong-active: #b90000;
	--wpds-color-background-interactive-error-strong-disabled: #e6e6e6;
	--wpds-color-background-interactive-error-weak: #0000;
	--wpds-color-background-interactive-error-weak-active: #f6e6e3;
	--wpds-color-background-interactive-error-weak-disabled: #0000;
	--wpds-color-background-track-neutral-weak: #f0f0f0;
	--wpds-color-background-track-neutral: #dbdbdb;
	--wpds-color-background-thumb-neutral-weak: #8d8d8d;
	--wpds-color-background-thumb-neutral-weak-active: #6e6e6e;
	--wpds-color-background-thumb-brand: #3858e9;
	--wpds-color-background-thumb-brand-active: #3858e9;
	--wpds-color-background-thumb-brand-disabled: #dbdbdb;
	--wpds-color-background-thumb-neutral-weak-disabled: #dbdbdb;
	--wpds-color-foreground-content-neutral: #1e1e1e;
	--wpds-color-foreground-content-neutral-weak: #707070;
	--wpds-color-foreground-content-success: #002900;
	--wpds-color-foreground-content-success-weak: #008030;
	--wpds-color-foreground-content-info: #001b4f;
	--wpds-color-foreground-content-info-weak: #006bd7;
	--wpds-color-foreground-content-warning: #2e1900;
	--wpds-color-foreground-content-warning-weak: #926300;
	--wpds-color-foreground-content-caution: #281d00;
	--wpds-color-foreground-content-caution-weak: #826a00;
	--wpds-color-foreground-content-error: #470000;
	--wpds-color-foreground-content-error-weak: #cc1818;
	--wpds-color-foreground-interactive-neutral: #1e1e1e;
	--wpds-color-foreground-interactive-neutral-active: #1e1e1e;
	--wpds-color-foreground-interactive-neutral-disabled: #8d8d8d;
	--wpds-color-foreground-interactive-neutral-strong: #f0f0f0;
	--wpds-color-foreground-interactive-neutral-strong-active: #f0f0f0;
	--wpds-color-foreground-interactive-neutral-strong-disabled: #8d8d8d;
	--wpds-color-foreground-interactive-neutral-weak: #707070;
	--wpds-color-foreground-interactive-neutral-weak-active: #1e1e1e;
	--wpds-color-foreground-interactive-neutral-weak-disabled: #8d8d8d;
	--wpds-color-foreground-interactive-brand: #3858e9;
	--wpds-color-foreground-interactive-brand-active: #0b0070;
	--wpds-color-foreground-interactive-brand-disabled: #8d8d8d;
	--wpds-color-foreground-interactive-brand-strong: #eff0f2;
	--wpds-color-foreground-interactive-brand-strong-active: #eff0f2;
	--wpds-color-foreground-interactive-brand-strong-disabled: #8d8d8d;
	--wpds-color-foreground-interactive-error: #cc1818;
	--wpds-color-foreground-interactive-error-active: #470000;
	--wpds-color-foreground-interactive-error-disabled: #8d8d8d;
	--wpds-color-foreground-interactive-error-strong: #f2efef;
	--wpds-color-foreground-interactive-error-strong-active: #f2efef;
	--wpds-color-foreground-interactive-error-strong-disabled: #8d8d8d;
	--wpds-color-stroke-surface-neutral: #dbdbdb;
	--wpds-color-stroke-surface-neutral-weak: #f0f0f0;
	--wpds-color-stroke-surface-neutral-strong: #8d8d8d;
	--wpds-color-stroke-surface-brand: #b0bbd6;
	--wpds-color-stroke-surface-brand-strong: #3858e9;
	--wpds-color-stroke-surface-success: #94d29e;
	--wpds-color-stroke-surface-success-strong: #008030;
	--wpds-color-stroke-surface-info: #a9c6e7;
	--wpds-color-stroke-surface-info-strong: #006bd7;
	--wpds-color-stroke-surface-warning: #e1bc7c;
	--wpds-color-stroke-surface-warning-strong: #926300;
	--wpds-color-stroke-surface-caution: #cfc28d;
	--wpds-color-stroke-surface-caution-strong: #826a00;
	--wpds-color-stroke-surface-error: #dab1aa;
	--wpds-color-stroke-surface-error-strong: #cc1818;
	--wpds-color-stroke-interactive-neutral: #8d8d8d;
	--wpds-color-stroke-interactive-neutral-active: #6e6e6e;
	--wpds-color-stroke-interactive-neutral-disabled: #dbdbdb;
	--wpds-color-stroke-interactive-neutral-strong: #6e6e6e;
	--wpds-color-stroke-interactive-brand: #3858e9;
	--wpds-color-stroke-interactive-brand-active: #2337c8;
	--wpds-color-stroke-interactive-brand-disabled: #dbdbdb;
	--wpds-color-stroke-interactive-error: #cc1818;
	--wpds-color-stroke-interactive-error-active: #9d0000;
	--wpds-color-stroke-interactive-error-disabled: #dbdbdb;
	--wpds-color-stroke-interactive-error-strong: #cc1818;
	--wpds-color-stroke-focus: #3858e9;
	--wpds-cursor-control: pointer;
	--wpds-dimension-padding-xs: 4px;
	--wpds-dimension-padding-sm: 8px;
	--wpds-dimension-padding-md: 12px;
	--wpds-dimension-padding-lg: 16px;
	--wpds-dimension-padding-xl: 20px;
	--wpds-dimension-padding-2xl: 24px;
	--wpds-dimension-padding-3xl: 32px;
	--wpds-dimension-gap-xs: 4px;
	--wpds-dimension-gap-sm: 8px;
	--wpds-dimension-gap-md: 12px;
	--wpds-dimension-gap-lg: 16px;
	--wpds-dimension-gap-xl: 24px;
	--wpds-dimension-gap-2xl: 32px;
	--wpds-dimension-gap-3xl: 40px;
	--wpds-dimension-size-5xs: 4px;
	--wpds-dimension-size-4xs: 8px;
	--wpds-dimension-size-3xs: 12px;
	--wpds-dimension-size-2xs: 16px;
	--wpds-dimension-size-xs: 20px;
	--wpds-dimension-size-sm: 24px;
	--wpds-dimension-size-md: 32px;
	--wpds-dimension-size-lg: 40px;
	--wpds-dimension-surface-width-xs: 240px;
	--wpds-dimension-surface-width-sm: 320px;
	--wpds-dimension-surface-width-md: 400px;
	--wpds-dimension-surface-width-lg: 560px;
	--wpds-dimension-surface-width-xl: 720px;
	--wpds-dimension-surface-width-2xl: 960px;
	--wpds-motion-duration-xs: 50ms;
	--wpds-motion-duration-sm: 100ms;
	--wpds-motion-duration-md: 200ms;
	--wpds-motion-duration-lg: 300ms;
	--wpds-motion-duration-xl: 400ms;
	--wpds-motion-easing-subtle: cubic-bezier( 0.15, 0, 0.15, 1 );
	--wpds-motion-easing-balanced: cubic-bezier( 0.4, 0, 0.2, 1 );
	--wpds-motion-easing-expressive: cubic-bezier( 0.25, 0, 0, 1 );
	--wpds-typography-font-family-heading: -apple-system, system-ui, 'Segoe UI',
	--wpds-typography-font-family-body: -apple-system, system-ui, 'Segoe UI',
	--wpds-typography-font-family-mono: 'Menlo', 'Consolas', monaco, monospace;
	--wpds-typography-font-size-xs: 11px;
	--wpds-typography-font-size-sm: 12px;
	--wpds-typography-font-size-md: 13px;
	--wpds-typography-font-size-lg: 15px;
	--wpds-typography-font-size-xl: 20px;
	--wpds-typography-font-size-2xl: 32px;
	--wpds-typography-line-height-xs: 16px;
	--wpds-typography-line-height-sm: 20px;
	--wpds-typography-line-height-md: 24px;
	--wpds-typography-line-height-lg: 28px;
	--wpds-typography-line-height-xl: 32px;
	--wpds-typography-line-height-2xl: 40px;
	--wpds-typography-font-weight-default: 400;
	--wpds-typography-font-weight-emphasis: 600;
	--wpds-border-width-focus: 1.5px;
}
.untangling-app {
	color: var(--wpds-color-foreground-content-neutral);
}
/* Text never renders pure black: pin headings (which otherwise inherit
   wp-admin's own heading colors) to the gray-900 content token. */
.untangling-app h1,
.untangling-app h2,
.untangling-app h3 {
	color: var(--wpds-color-foreground-content-neutral);
}
/* Canvas below the header rule matches the MSD (#fcfcfc, surface-neutral) */
body.toplevel_page_untangling-hosting,
body.toplevel_page_untangling-hosting #wpcontent,
body.toplevel_page_untangling-hosting #wpbody-content {
	background: var(--wpds-color-background-surface-neutral, #fcfcfc);
}
/* Full-bleed header: core's #wpcontent padding would inset it on the left */
body.toplevel_page_untangling-hosting #wpcontent {
	padding-left: 0;
}
/* Full-width header, host-dashboard pattern: "Hosting / domain" breadcrumb-style
   title, subtitle. The DS TabPanel tablist below shares the white band and
   carries the bottom rule — tab typography/hover/focus stay stock DS. */
.untangling-app .untangling-header { background: var(--wpds-color-background-surface-neutral-strong, #fff); padding: var(--wpds-dimension-gap-2xl) var(--wpds-dimension-gap-2xl) 0; }
.untangling-app .untangling-header-brand { display: flex; align-items: baseline; gap: var(--wpds-dimension-gap-sm); }
/* Site-identity header (V2): icon + name + URL + visibility; lockup right. */
.untangling-app .untangling-siteid { display: flex; align-items: center; gap: var(--wpds-dimension-gap-lg); }
.untangling-app .untangling-siteid-icon { flex: none; width: 44px; height: 44px; border-radius: var(--wpds-border-radius-md); object-fit: cover; }
.untangling-app .untangling-siteid-icon.is-fallback { display: inline-flex; align-items: center; justify-content: center; background: var(--wpds-color-background-surface-neutral-weak); color: var(--wpds-color-foreground-content-neutral); font-size: var(--wpds-typography-font-size-xl); font-weight: 500; }
.untangling-app .untangling-siteid-main { display: grid; gap: 2px; }
.untangling-app .untangling-siteid-meta { display: flex; align-items: center; gap: var(--wpds-dimension-gap-sm); }
.untangling-app .untangling-header-domain-sep { color: var(--wpds-color-foreground-content-neutral-weak); font-size: var(--wpds-typography-font-size-md); line-height: var(--wpds-typography-line-height-md); }
/* The domain links to the live site. The ↗ is always visible (hiding it left
   a hover-reserved gap before the visibility badge that read as a bug); rest
   state is neutral, hover/focus takes the admin accent color. */
.untangling-app .untangling-header-domain { color: var(--wpds-color-foreground-content-neutral-weak); font-size: var(--wpds-typography-font-size-md); line-height: var(--wpds-typography-line-height-md); text-decoration: none; transition: color 0.1s linear; }
.untangling-app .untangling-header-domain:hover,
.untangling-app .untangling-header-domain:focus-visible { color: var(--wpds-color-stroke-surface-brand-strong, var(--wp-admin-theme-color, #3858e9)); }
/* Page h1 sits one type step above the card titles (lg) so the hierarchy reads. */
.untangling-app .untangling-title { font-size: var(--wpds-typography-font-size-xl); line-height: var(--wpds-typography-line-height-xl); font-weight: var(--wpds-typography-font-weight-emphasis); margin: 0; padding: 0; }
/* The rule is an inset shadow (not border-bottom) so the DS active-tab
   indicator, drawn at bottom: 0 inside the item, sits on top of it — one
   line, never two. */
.untangling-app .untangling-tabpanel .components-tab-panel__tabs { background: var(--wpds-color-background-surface-neutral-strong, #fff); padding: var(--wpds-dimension-gap-sm) var(--wpds-dimension-gap-2xl) 0; box-shadow: inset 0 calc(-1 * var(--wpds-border-width-xs)) 0 0 var(--wpds-color-stroke-surface-neutral); }
/* The TabPanel panel div spans the whole page canvas here, so its stock
   :focus-visible ring renders as a stray full-width line under the tabs.
   Focus stays visible on the tabs themselves. */
.untangling-app .untangling-tabpanel .components-tab-panel__tab-content:focus-visible { box-shadow: none; }
/* The DS puts a transparent outline on the active-tab indicator (::after) for
   Windows High Contrast. Chrome with a11y contrast/focus-highlight settings
   paints its native two-tone focus ring there instead — a stray line hanging
   below the tablist that flickers in when the content underneath repaints.
   The indicator is decorative (the tab button carries focus), so drop it. */
.untangling-app .untangling-tabpanel .components-tab-panel__tabs-item.is-active::after { outline: none; }
/* Minimal Tabs variant (@wordpress/ui tabs--minimal): no horizontal tab
   padding + 1rem gap, so the first label left-aligns with the header/content
   edge; indicator and labels use the neutral interactive colors. The stock
   focus ring (::before) is inset 12px for padded tabs — outset it instead. */
.untangling-app .untangling-tabpanel .components-tab-panel__tabs { gap: var(--wpds-dimension-gap-md, 1rem); }
.untangling-app .untangling-tabpanel .components-tab-panel__tabs-item { padding-left: 0; padding-right: 0; color: var(--wpds-color-foreground-interactive-neutral); }
.untangling-app .untangling-tabpanel .components-tab-panel__tabs-item:hover,
.untangling-app .untangling-tabpanel .components-tab-panel__tabs-item:focus-visible,
.untangling-app .untangling-tabpanel .components-tab-panel__tabs-item.is-active { color: var(--wpds-color-foreground-interactive-neutral-active, #1e1e1e); }
.untangling-app .untangling-tabpanel .components-tab-panel__tabs-item::before { left: -2px; right: -2px; }
/* Hover preview: the stock ::after underline at 0 height, raised in gray on
   hover; the active tab keeps the stock accent-blue indicator. */
.untangling-app .untangling-tabpanel .components-tab-panel__tabs-item:hover:not(.is-active)::after { height: calc(1 * var(--wp-admin-border-width-focus, 2px)); background: var(--wpds-color-stroke-surface-neutral-strong, #949494); }
.untangling-app .untangling-content { max-width: 1080px; margin-inline: auto; padding: 0 var(--wpds-dimension-gap-2xl); }
.untangling-app .untangling-grid { display: grid; grid-template-columns: minmax(0, 1.8fr) minmax(280px, 1fr); gap: var(--wpds-dimension-gap-lg); margin-top: var(--wpds-dimension-gap-2xl); align-items: start; }
@media ( max-width: 1100px ) { .untangling-app .untangling-grid { grid-template-columns: 1fr; } }
.untangling-app .untangling-col { display: grid; gap: var(--wpds-dimension-gap-lg); }
/* Type hierarchy inside cards: title 15px semibold > content 13px > meta 12px.
   Stat values (20px) are the only thing allowed to outsize the title — data,
   not structure. Card headers stay slim: less vertical padding, bigger title. */
.untangling-app .untangling-card-title { font-size: var(--wpds-typography-font-size-lg); line-height: var(--wpds-typography-line-height-md); font-weight: var(--wpds-typography-font-weight-emphasis); margin: 0; }
.untangling-app .components-card__header { padding-top: var(--wpds-dimension-padding-lg); padding-bottom: var(--wpds-dimension-padding-lg); }
/* Footers: 16px above/below the 36px action mirrors the header padding and
   reads balanced against the 24px sides. Every footer button keeps its box on
   the 24px content edge — no per-variant offsets, one alignment rule. */
.untangling-app .components-card__footer { padding-top: var(--wpds-dimension-padding-lg); padding-bottom: var(--wpds-dimension-padding-lg); }
/* Text-style actions inside card bodies: pull the label flush with the card
   content edge — the tertiary Button's 12px inner padding otherwise reads as
   misalignment. Direct children only, so buttons paired inside HStacks keep
   their spacing. */
.untangling-app .components-card__body > .components-button.is-tertiary { margin-left: -12px; }
.untangling-app .untangling-meta-text { display: block; color: var(--wpds-color-foreground-content-neutral-weak); font-size: var(--wpds-typography-font-size-sm); margin: 0 0 var(--wpds-dimension-gap-xs); }
.untangling-app .untangling-stat-value { font-size: var(--wpds-typography-font-size-xl); font-weight: 500; }
.untangling-app .untangling-stat-line { font-size: var(--wpds-typography-font-size-md); font-weight: var(--wpds-typography-font-weight-emphasis); margin-bottom: 2px; }
.untangling-app .untangling-email-upsell { display: grid; justify-items: start; text-align: start; gap: var(--wpds-dimension-gap-xs); }
.untangling-app .untangling-email-upsell .untangling-meta-text { max-width: 420px; margin-bottom: var(--wpds-dimension-gap-sm); }
/* Domain upsell, MSD Callout geometry (is-image-full-bleed): container flush,
   padded copy column + full-bleed illustration column, 50/50. */
.untangling-app .untangling-domain-card { overflow: hidden; }
.untangling-app .components-card__body.untangling-domain-body { padding: 0; }
.untangling-app .untangling-domain-body { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; }
.untangling-app .untangling-domain-copy { display: flex; flex-direction: column; align-items: flex-start; justify-content: flex-start; gap: 16px; padding: 24px; }
.untangling-app .untangling-domain-desc { margin: 0; color: var(--wpds-color-foreground-content-neutral-weak); font-size: var(--wpds-typography-font-size-md); }
.untangling-app .untangling-domain-desc a { color: inherit; text-decoration: underline; text-underline-offset: 3px; }
.untangling-app .untangling-upsell-cta-icon { display: inline-flex; }
/* Cropped glyph sized to the 14×12 diamond recipe; the raw 24-box reads
   oversized and lopsided next to the 13px button label. */
.untangling-app .untangling-upsell-cta-icon svg { width: 14px; height: 12px; fill: currentColor; }
/* Button's has-icon recipe trims left padding for a 24px icon; with the 14px
   diamond that reads lopsided — restore symmetric padding. */
.untangling-app .components-button.has-icon.has-text { padding-left: 12px; padding-right: 12px; gap: 6px; }
.untangling-app .untangling-domain-cta-icon svg { display: block; width: 20px; height: 20px; }
.untangling-app .untangling-domain-cta-icon svg path { fill: currentColor; }
.untangling-app .untangling-domain-art { position: relative; }
.untangling-app .untangling-domain-art svg { position: absolute; inset: 0; width: 100%; height: 100%; }
@media ( max-width: 782px ) { .untangling-app .untangling-domain-body { grid-template-columns: 1fr; } .untangling-app .untangling-domain-art { display: none; } }
.untangling-app .untangling-caution { color: var(--wpds-color-foreground-content-caution-weak); font-size: var(--wpds-typography-font-size-sm); margin: var(--wpds-dimension-gap-xs) 0 0; }
/* Local stand-in for the @wordpress/ui Badge (core doesn't bundle
   @wordpress/ui yet) — styled to the DS Badge 'none' intent: sentence case,
   default weight, sm type on the neutral-weak surface. */
.untangling-app .untangling-fallback-badge { display: inline-block; background: var(--wpds-color-background-surface-neutral-weak); border-radius: var(--wpds-border-radius-sm); padding: 0 var(--wpds-dimension-padding-sm); font-size: var(--wpds-typography-font-size-sm); line-height: var(--wpds-typography-line-height-sm); font-weight: var(--wpds-typography-font-weight-default); color: var(--wpds-color-foreground-content-neutral); }
.untangling-app .untangling-feature-list { margin: var(--wpds-dimension-gap-sm) 0 0; padding: 0; list-style: none; display: grid; gap: var(--wpds-dimension-gap-xs); font-size: var(--wpds-typography-font-size-md); }
.untangling-app .untangling-storage-track { margin-bottom: var(--wpds-dimension-gap-sm); }
/* --- My site overview: sections of MSD-style glance cards ---------------- */
.untangling-app .untangling-section { display: grid; gap: var(--wpds-dimension-gap-lg); }
.untangling-app .untangling-section ~ .untangling-section { margin-top: var(--wpds-dimension-gap-2xl); }
/* One-column flow: extra air above each section title (the grid gap alone
   reads cramped); the first title stays flush. */
.untangling-app .untangling-narrow .untangling-section-title { margin-top: var(--wpds-dimension-gap-3xl); }
.untangling-app .untangling-narrow .untangling-section-title:first-child { margin-top: 0; }
.untangling-app .untangling-section-head { display: flex; align-items: center; gap: var(--wpds-dimension-gap-md); }
.untangling-app .untangling-section-title { font-size: var(--wpds-typography-font-size-lg); line-height: var(--wpds-typography-line-height-md); font-weight: 400; margin: 0; }
.untangling-app .untangling-section-head .untangling-fallback-badge,
.untangling-app .untangling-section-head .components-badge { align-self: center; line-height: 1.4; }
.untangling-app .untangling-dot { width: 8px; height: 8px; border-radius: 50%; flex: 0 0 auto; }
.untangling-app .untangling-dot.is-success { background: var(--wpds-color-stroke-surface-success-strong); }
.untangling-app .untangling-dot.is-caution { background: var(--wpds-color-stroke-surface-caution-strong); }
.untangling-app .untangling-dot.is-warning { background: var(--wpds-color-stroke-surface-warning-strong); }
.untangling-app .untangling-badge-success { background: var(--wpds-color-background-surface-success); color: var(--wpds-color-foreground-content-success); }
.untangling-app .untangling-tiles { display: grid; grid-template-columns: 1fr 1fr; gap: var(--wpds-dimension-gap-lg); align-items: stretch; }
@media ( max-width: 782px ) { .untangling-app .untangling-tiles { grid-template-columns: 1fr; } }
/* Glance card = ovcard plus room for one inline visual (sparkline / meter),
   pinned to the bottom so rows stay aligned. Upsell intent paints the title
   brand; warning intent paints the description (MSD OverviewCard behavior). */
.untangling-app .untangling-glance { display: flex; flex-direction: column; align-items: stretch; }
.untangling-app .untangling-glance .untangling-ovcard-title { display: inline-flex; align-items: center; gap: var(--wpds-dimension-gap-sm); }
.untangling-app .untangling-glance.is-upsell .untangling-ovcard-title { color: var(--ov-accent); }
.untangling-app .untangling-glance.is-warning .untangling-ovcard-desc { color: var(--wpds-color-foreground-content-warning-weak); }
.untangling-app .untangling-glance.is-warning:hover .untangling-ovcard-desc { color: var(--ov-accent); }
/* Sparkline: 2px gaps between bars, rounded data-ends anchored to the baseline. */
.untangling-app .untangling-spark { display: flex; align-items: flex-end; gap: 2px; width: 100%; height: 40px; margin-top: var(--wpds-dimension-gap-lg); }
.untangling-app .untangling-spark-bar { flex: 1; background: var(--wpds-color-background-thumb-brand); border-radius: 4px 4px 0 0; min-height: 4px; }
.untangling-app .untangling-meter { display: block; width: 100%; height: 8px; background: var(--wpds-color-background-track-neutral-weak); border-radius: var(--wpds-border-radius-sm); overflow: hidden; margin-top: var(--wpds-dimension-gap-lg); }
.untangling-app .untangling-meter span { display: block; height: 100%; border-radius: inherit; }
.untangling-app .untangling-meter.is-warning span { background: var(--wpds-color-stroke-surface-warning-strong); }
.untangling-app .untangling-glance .untangling-spark,
.untangling-app .untangling-glance .untangling-meter { margin-top: auto; }
.untangling-app .untangling-glance .untangling-ovcard-desc { margin-bottom: var(--wpds-dimension-gap-lg); }
/* --- Hub variant: stat strip + titled sections of icon rows -------------- */
.untangling-app .untangling-hubstrip { display: grid; grid-template-columns: repeat(3, 1fr); background: var(--wpds-color-background-surface-neutral-strong, #fff); border: 1px solid var(--wpds-color-stroke-surface-neutral); border-radius: var(--wpds-border-radius-lg); overflow: hidden; margin-bottom: var(--wpds-dimension-gap-lg); }
.untangling-app .untangling-hubcell { display: flex; flex-direction: column; gap: var(--wpds-dimension-gap-md); padding: var(--wpds-dimension-padding-2xl); min-width: 0; }
.untangling-app .untangling-hubcell + .untangling-hubcell { border-inline-start: 1px solid var(--wpds-color-stroke-surface-neutral-weak); }
@media ( max-width: 782px ) {
	.untangling-app .untangling-hubstrip { grid-template-columns: 1fr; }
	.untangling-app .untangling-hubcell + .untangling-hubcell { border-inline-start: 0; border-top: 1px solid var(--wpds-color-stroke-surface-neutral-weak); }
}
.untangling-app .untangling-hubcell-label { text-transform: uppercase; letter-spacing: 0.02em; font-size: var(--wpds-typography-font-size-sm); font-weight: 500; color: var(--wpds-color-foreground-content-neutral-weak); }
.untangling-app .untangling-hubcell-value { font-size: var(--wpds-typography-font-size-xl); line-height: var(--wpds-typography-line-height-xl); font-weight: 500; }
.untangling-app .untangling-hubcell-max { color: var(--wpds-color-foreground-content-neutral-weak); font-size: var(--wpds-typography-font-size-sm); }
.untangling-app .untangling-hubcell .untangling-meter { margin-top: auto; }
.untangling-app .untangling-spark.is-compact { height: 32px; width: 96px; flex: 0 0 auto; margin-top: 0; }
/* Icon rows, AI Hub quick-start style: icon | title + description | chevron,
   whole row is the link; hover matches the ovcard accent treatment. */
.untangling-app .untangling-hubrow { --ov-accent: var(--wpds-color-stroke-surface-brand-strong, var(--wp-admin-theme-color, #3858e9)); display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: var(--wpds-dimension-gap-md); align-items: start; background: var(--wpds-color-background-surface-neutral-strong, #fff); border: 1px solid var(--wpds-color-stroke-surface-neutral); border-radius: var(--wpds-border-radius-lg); padding: var(--wpds-dimension-padding-2xl); text-decoration: none; color: inherit; transition: background 0.1s linear, box-shadow 0.1s linear; }
.untangling-app .untangling-hubrow:hover { background: color-mix( in srgb, var(--ov-accent) 2%, var(--wpds-color-background-surface-neutral-strong, #fff) ); box-shadow: 0 0 0 1px color-mix( in srgb, var(--ov-accent) 12%, transparent ); }
.untangling-app .untangling-hubrow:hover .untangling-hubrow-title,
.untangling-app .untangling-hubrow:hover .untangling-hubrow-desc { color: var(--ov-accent); }
.untangling-app .untangling-hubrow:hover .untangling-hubrow-icon svg { fill: var(--ov-accent); }
.untangling-app .untangling-hubrow:hover .untangling-ovcard-chevron { border-color: var(--ov-accent); }
.untangling-app .untangling-hubrow-icon svg { display: block; fill: var(--wpds-color-foreground-content-neutral); }
.untangling-app .untangling-hubrow-main { display: flex; flex-direction: column; gap: var(--wpds-dimension-gap-xs); }
.untangling-app .untangling-hubrow-title { display: inline-flex; align-items: center; gap: var(--wpds-dimension-gap-sm); font-size: var(--wpds-typography-font-size-md); font-weight: var(--wpds-typography-font-weight-emphasis); }
.untangling-app .untangling-hubrow-desc { color: var(--wpds-color-foreground-content-neutral-weak); font-size: var(--wpds-typography-font-size-md); }
.untangling-app .untangling-hubrow-badge { background: var(--wpds-color-background-surface-brand); color: var(--wpds-color-foreground-interactive-brand); }
/* Beats the shared badge recipe's inline-block (higher specificity) so the
   upsell diamond centers against the label instead of riding the baseline. */
.untangling-app .untangling-hubrow .untangling-hubrow-badge { display: inline-flex; align-items: center; gap: 4px; }
.untangling-app .untangling-hubrow-badge-icon { display: inline-flex; }
.untangling-app .untangling-hubrow-badge-icon svg { width: 13px; height: 11px; fill: currentColor; }
/* Status tones mirror MSD Badge intents (site-visibility summary). */
.untangling-app .untangling-hubrow-badge.is-success { background: var(--wpds-color-background-surface-success-weak); color: var(--wpds-color-foreground-content-success-weak); }
.untangling-app .untangling-hubrow-badge.is-warning { background: var(--wpds-color-background-surface-warning-weak); color: var(--wpds-color-foreground-content-warning-weak); }
.untangling-app .untangling-hubrow-badge.is-neutral { background: var(--wpds-color-background-surface-neutral-weak); color: var(--wpds-color-foreground-content-neutral); }
.untangling-app .untangling-hubrow .untangling-ovcard-chevron { align-self: center; }
.untangling-app .untangling-progress { max-width: none; width: 100%; }
.untangling-app .untangling-progress-fallback { background: var(--wpds-color-background-surface-neutral-weak); border-radius: var(--wpds-border-radius-sm); height: 8px; overflow: hidden; margin-bottom: var(--wpds-dimension-gap-sm); }
.untangling-app .untangling-progress-fallback span { display: block; height: 100%; background: var(--wpds-color-foreground-content-caution-weak); }
.untangling-app .untangling-grow-list { display: grid; gap: var(--wpds-dimension-gap-md); }
.untangling-app .untangling-spark { display: block; width: 100%; height: 48px; margin-bottom: var(--wpds-dimension-gap-md); color: var(--wpds-color-stroke-surface-brand-strong, #3858e9); }
.untangling-app .untangling-idea-lede { margin-top: var(--wpds-dimension-gap-md); }
.untangling-app .untangling-chip-row { display: flex; flex-wrap: wrap; gap: var(--wpds-dimension-gap-xs); margin-top: var(--wpds-dimension-gap-xs); }
/* One-column variant: AI Hub-style section titles + quick-start link cards */
.untangling-app .untangling-section-title { font-size: var(--wpds-typography-font-size-lg); font-weight: 400; margin: var(--wpds-dimension-gap-md) 0 0; }
.untangling-app .untangling-quick-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); grid-auto-rows: 1fr; gap: var(--wpds-dimension-gap-lg); }
@media ( max-width: 600px ) { .untangling-app .untangling-quick-grid { grid-template-columns: 1fr; } }
.untangling-app .untangling-narrow { max-width: var(--wpds-dimension-surface-width-xl); margin-inline: auto; margin-top: var(--wpds-dimension-gap-2xl); display: grid; gap: var(--wpds-dimension-gap-lg); }
/* Learn tab: full-width learning hub. Media cards share one shell for
   videos, courses, and guide topics; radius hardcoded like the accordion
   (the vendored token cascade leaves radius-* at pill values). */
/* Help & Learn hero — a prompt box, shared by the hosting tab and the My Site
   page. The input is hand-drawn rather than an InputControl: the DS input
   mounts without its emotion styles in this environment (same reason the
   segmented control is hand-drawn), so it is matched to the DS geometry
   instead — 40px tall, 4px radius, brand focus ring. */
.untangling-app .untangling-ask-head { display: flex; align-items: flex-start; gap: var(--wpds-dimension-gap-md); }
.untangling-app .untangling-ask-mark { flex: none; width: 32px; height: 32px; border-radius: 999px; background: var(--wpds-color-background-surface-brand); color: var(--wpds-color-foreground-interactive-brand); display: grid; place-items: center; }
.untangling-app .untangling-ask-mark svg { width: 20px; height: 20px; display: block; fill: currentColor; }
.untangling-app .untangling-ask-head .untangling-meta-text { margin: 4px 0 0; }
.untangling-app .untangling-ask-form { display: flex; align-items: center; gap: var(--wpds-dimension-gap-sm); margin-top: var(--wpds-dimension-gap-lg); }
.untangling-app .untangling-ask-input { flex: 1; min-width: 0; height: 40px; box-sizing: border-box; padding: 0 var(--wpds-dimension-padding-md); border: 1px solid var(--wpds-color-stroke-interactive-neutral); border-radius: 4px; background: var(--wpds-color-background-surface-neutral-strong, #fff); color: var(--wpds-color-foreground-content-neutral); font-size: var(--wpds-typography-font-size-md); line-height: 1.5; }
.untangling-app .untangling-ask-input::placeholder { color: var(--wpds-color-foreground-content-neutral-weak); }
.untangling-app .untangling-ask-input:focus { border-color: var(--wpds-color-stroke-interactive-brand); box-shadow: 0 0 0 1px var(--wpds-color-stroke-interactive-brand); outline: none; }
.untangling-app .untangling-ask-form .components-button { flex: none; height: 40px; }
.untangling-app .untangling-learn { margin-top: var(--wpds-dimension-gap-2xl); display: grid; gap: var(--wpds-dimension-gap-2xl); }
.untangling-app .untangling-learn-section { display: grid; gap: var(--wpds-dimension-gap-md); }
.untangling-app .untangling-learn-head { display: flex; align-items: center; justify-content: space-between; gap: var(--wpds-dimension-gap-md); }
.untangling-app .untangling-learn-head .untangling-section-title { margin: 0; }
.untangling-app .untangling-media-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--wpds-dimension-gap-lg); }
@media ( max-width: 960px ) { .untangling-app .untangling-media-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media ( max-width: 600px ) { .untangling-app .untangling-media-grid { grid-template-columns: 1fr; } }
.untangling-app .untangling-media-card { display: flex; flex-direction: column; background: var(--wpds-color-background-surface-neutral-strong, #fff); border: 1px solid var(--wpds-color-stroke-surface-neutral); border-radius: 8px; overflow: hidden; text-decoration: none; color: inherit; }
.untangling-app .untangling-media-card:hover { border-color: var(--wpds-color-stroke-surface-brand-strong, #3858e9); }
.untangling-app .untangling-media-thumb { position: relative; display: block; aspect-ratio: 16 / 9; background: var(--wpds-color-background-surface-neutral-weak); }
/* Absolutely positioned so a non-16/9 image cannot stretch the thumb box
   past its aspect-ratio and unbalance the card bodies in the row. */
.untangling-app .untangling-media-thumb img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: block; }
.untangling-app .untangling-media-play { position: absolute; inset: 0; display: grid; place-items: center; opacity: 0; background: rgba(0, 0, 0, 0.25); transition: opacity var(--wpds-motion-duration-sm) var(--wpds-motion-easing-subtle); }
.untangling-app .untangling-media-play::after { content: ''; width: 0; height: 0; border-style: solid; border-width: 10px 0 10px 16px; border-color: transparent transparent transparent #fff; margin-left: 4px; }
.untangling-app .untangling-media-card:hover .untangling-media-play,
.untangling-app .untangling-media-card:focus-visible .untangling-media-play { opacity: 1; }
@media ( prefers-reduced-motion: reduce ) { .untangling-app .untangling-media-play { transition: none; } }
.untangling-app .untangling-media-body { display: grid; gap: var(--wpds-dimension-gap-xs); padding: var(--wpds-dimension-padding-lg); }
.untangling-app .untangling-media-row { display: flex; justify-content: space-between; align-items: baseline; gap: var(--wpds-dimension-gap-md); }
.untangling-app .untangling-media-title { font-size: var(--wpds-typography-font-size-md); font-weight: var(--wpds-typography-font-weight-emphasis); }
.untangling-app .untangling-media-duration { flex: none; color: var(--wpds-color-foreground-content-neutral-weak); font-size: var(--wpds-typography-font-size-sm); font-variant-numeric: tabular-nums; }
.untangling-app .untangling-media-desc { color: var(--wpds-color-foreground-content-neutral-weak); font-size: var(--wpds-typography-font-size-sm); text-wrap: pretty; }
.untangling-app .untangling-topic-card { display: grid; gap: var(--wpds-dimension-gap-sm); align-content: start; justify-items: start; background: var(--wpds-color-background-surface-neutral-strong, #fff); border: 1px solid var(--wpds-color-stroke-surface-neutral); border-radius: 8px; padding: var(--wpds-dimension-padding-xl); }
.untangling-app .untangling-topic-icon { width: 32px; height: 32px; border-radius: 999px; background: var(--wpds-color-background-surface-brand); color: var(--wpds-color-foreground-interactive-brand); display: grid; place-items: center; }
.untangling-app .untangling-topic-icon .untangling-ds-icon { width: 20px; height: 20px; display: block; }
.untangling-app .untangling-topic-title { font-size: var(--wpds-typography-font-size-md); font-weight: var(--wpds-typography-font-weight-emphasis); }
.untangling-app .untangling-topic-links { display: grid; gap: var(--wpds-dimension-gap-xs); justify-items: start; }
.untangling-app .untangling-topic-links a { color: var(--wpds-color-foreground-interactive-brand); font-size: var(--wpds-typography-font-size-md); text-decoration: none; }
.untangling-app .untangling-topic-links a:hover { text-decoration: underline; }
.untangling-app .untangling-support-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--wpds-dimension-gap-lg); }
@media ( max-width: 600px ) { .untangling-app .untangling-support-grid { grid-template-columns: 1fr; } }
.untangling-app .untangling-support-card { display: grid; gap: var(--wpds-dimension-gap-xs); justify-items: start; background: var(--wpds-color-background-surface-neutral-weak); border: 1px solid transparent; border-radius: 8px; padding: var(--wpds-dimension-padding-2xl); text-decoration: none; color: inherit; }
.untangling-app .untangling-support-card:hover { border-color: var(--wpds-color-stroke-surface-brand-strong, #3858e9); }
.untangling-app .untangling-support-icon { width: 40px; height: 40px; border-radius: 999px; background: var(--wpds-color-background-surface-neutral-strong, #fff); color: var(--wpds-color-foreground-interactive-brand); display: grid; place-items: center; margin-bottom: var(--wpds-dimension-gap-xs); }
.untangling-app .untangling-support-icon .untangling-ds-icon { width: 24px; height: 24px; display: block; }
.untangling-app .untangling-launchpad-list { list-style: none; margin: var(--wpds-dimension-gap-md) 0 0; padding: 0; display: grid; }
.untangling-app .untangling-launchpad-list li { padding: var(--wpds-dimension-gap-sm) 0; border-bottom: 1px solid var(--wpds-color-stroke-surface-neutral-weak); }
.untangling-app .untangling-launchpad-list li:last-child { border-bottom: 0; }
/* Checklist row CTAs stay hidden until the row is hovered (or reached by
   keyboard) — a full column of buttons reads as noise. */
.untangling-app .untangling-launchpad-list li .components-button { opacity: 0; transition: opacity var(--wpds-motion-duration-sm) var(--wpds-motion-easing-subtle); }
.untangling-app .untangling-launchpad-list li:hover .components-button,
.untangling-app .untangling-launchpad-list li:focus-within .components-button { opacity: 1; }
@media ( prefers-reduced-motion: reduce ) { .untangling-app .untangling-launchpad-list li .components-button { transition: none; } }
/* AI Launchpad accordion (wp-admin Site Setup pattern): one bordered card per
   task. Radii hardcoded — the vendored token cascade leaves radius-* at pill
   values. */
/* MSD overview-card mimic (dashboard-overview-card): whole card is a link;
   hover = 2% accent wash + 12% accent ring + all text/icons flip to accent. */
.untangling-app .untangling-ovcard { --ov-accent: var(--wpds-color-stroke-surface-brand-strong, var(--wp-admin-theme-color, #3858e9)); display: block; background: var(--wpds-color-background-surface-neutral-strong, #fff); border: 1px solid var(--wpds-color-stroke-surface-neutral); border-radius: 8px; padding: var(--wpds-dimension-padding-2xl); text-decoration: none; color: inherit; transition: background 0.1s linear, box-shadow 0.1s linear; }
.untangling-app .untangling-ovcard:hover { background: color-mix( in srgb, var(--ov-accent) 2%, var(--wpds-color-background-surface-neutral-strong, #fff) ); box-shadow: 0 0 0 1px color-mix( in srgb, var(--ov-accent) 12%, transparent ); }
.untangling-app .untangling-ovcard:hover span { color: var(--ov-accent); }
.untangling-app .untangling-ovcard:hover svg { fill: var(--ov-accent); }
.untangling-app .untangling-ovcard:hover .untangling-ovcard-chevron { border-color: var(--ov-accent); }
.untangling-app .untangling-ovcard-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--wpds-dimension-gap-md); }
.untangling-app .untangling-ovcard-title { display: inline-flex; align-items: center; gap: var(--wpds-dimension-gap-sm); text-transform: uppercase; letter-spacing: 0.02em; font-size: var(--wpds-typography-font-size-sm); font-weight: 500; color: var(--wpds-color-foreground-content-neutral); }
.untangling-app .untangling-ovcard-icon svg { display: block; fill: var(--ov-accent); }
.untangling-app .untangling-ovcard-chevron { flex: none; width: 8px; height: 8px; border-right: 1.5px solid var(--wpds-color-foreground-content-neutral-weak); border-top: 1.5px solid var(--wpds-color-foreground-content-neutral-weak); transform: rotate( 45deg ); }
.untangling-app .untangling-ovcard-heading { display: block; font-size: var(--wpds-typography-font-size-xl); font-weight: 500; margin-bottom: var(--wpds-dimension-gap-xs); }
.untangling-app .untangling-ovcard-desc { display: block; color: var(--wpds-color-foreground-content-neutral-weak); font-size: var(--wpds-typography-font-size-md); }
.untangling-app .untangling-lp { display: grid; gap: var(--wpds-dimension-gap-lg); }
.untangling-app .untangling-lp-task { background: var(--wpds-color-background-surface-neutral-strong, #fff); border: 1px solid var(--wpds-color-stroke-surface-neutral); border-radius: 8px; }
.untangling-app .untangling-lp-head { display: flex; align-items: center; gap: var(--wpds-dimension-gap-md); width: 100%; background: transparent; border: 0; padding: var(--wpds-dimension-padding-xl) var(--wpds-dimension-padding-2xl); font-family: inherit; font-size: var(--wpds-typography-font-size-lg); font-weight: 500; color: var(--wpds-color-foreground-content-neutral); text-align: left; cursor: pointer; }
.untangling-app .untangling-lp-task.is-done .untangling-lp-head { cursor: default; }
.untangling-app .untangling-lp-head:focus-visible { outline: var(--wpds-border-width-focus) solid var(--wpds-color-stroke-focus); outline-offset: 2px; border-radius: 8px; }
.untangling-app .untangling-lp-circle { flex: none; width: 22px; height: 22px; border-radius: 50%; border: 1.5px dashed var(--wpds-color-stroke-surface-neutral-strong); display: inline-flex; align-items: center; justify-content: center; font-size: var(--wpds-typography-font-size-sm); color: var(--wpds-color-foreground-content-neutral-weak); }
.untangling-app .untangling-lp-task.is-done .untangling-lp-circle { border-style: solid; }
.untangling-app .untangling-lp-task.is-done .untangling-lp-label { color: var(--wpds-color-foreground-content-neutral-weak); text-decoration: line-through; }
.untangling-app .untangling-lp-chevron { margin-left: auto; flex: none; width: 9px; height: 9px; border-right: 1.5px solid var(--wpds-color-foreground-content-neutral); border-bottom: 1.5px solid var(--wpds-color-foreground-content-neutral); transform: rotate( 45deg ) translateY( -2px ); }
.untangling-app .untangling-lp-task.is-open .untangling-lp-chevron { transform: rotate( -135deg ) translateY( -2px ); }
.untangling-app .untangling-lp-body { padding: 0 var(--wpds-dimension-padding-2xl) var(--wpds-dimension-padding-xl); }
.untangling-app .untangling-lp-body .untangling-meta-text { font-size: var(--wpds-typography-font-size-md); margin-bottom: var(--wpds-dimension-gap-md); }
/* Quick start completion moment: check pops in, a one-shot confetti burst
   fans out from behind it, copy fades up. Pieces get their trajectory from
   per-child --tx/--ty; colors cycle brand blue / green / amber / pink. */
.untangling-app .untangling-celebrate { display: grid; justify-items: center; text-align: center; gap: var(--wpds-dimension-gap-xs); padding: var(--wpds-dimension-padding-2xl) 0; }
.untangling-app .untangling-celebrate-stage { position: relative; width: 64px; height: 64px; margin-bottom: var(--wpds-dimension-gap-md); }
.untangling-app .untangling-celebrate-check { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: var(--wpds-color-background-interactive-brand-strong, #3858e9); color: #fff; border-radius: 50%; font-size: 28px; animation: untangling-check-pop 0.5s cubic-bezier(0.18, 0.89, 0.32, 1.28) both; }
.untangling-app .untangling-celebrate .untangling-card-title,
.untangling-app .untangling-celebrate .untangling-meta-text { animation: untangling-fade-up 0.35s ease-out 0.2s both; }
.untangling-app .untangling-confetti { position: absolute; top: 50%; left: 50%; width: 7px; height: 11px; border-radius: 2px; opacity: 0; animation: untangling-confetti 1.1s ease-out 0.25s both; }
.untangling-app .untangling-confetti:nth-child(4n+2) { background: var(--wpds-color-background-interactive-brand-strong, #3858e9); }
.untangling-app .untangling-confetti:nth-child(4n+3) { background: #00a32a; }
.untangling-app .untangling-confetti:nth-child(4n+4) { background: #f0b849; }
.untangling-app .untangling-confetti:nth-child(4n+5) { background: #e26f9c; }
.untangling-app .untangling-confetti:nth-child(2)  { --tx: -74px;  --ty: -58px; }
.untangling-app .untangling-confetti:nth-child(3)  { --tx: -30px;  --ty: -86px; animation-delay: 0.31s; }
.untangling-app .untangling-confetti:nth-child(4)  { --tx: 26px;   --ty: -90px; }
.untangling-app .untangling-confetti:nth-child(5)  { --tx: 72px;   --ty: -62px; animation-delay: 0.29s; }
.untangling-app .untangling-confetti:nth-child(6)  { --tx: 96px;   --ty: -18px; }
.untangling-app .untangling-confetti:nth-child(7)  { --tx: 88px;   --ty: 34px;  animation-delay: 0.33s; }
.untangling-app .untangling-confetti:nth-child(8)  { --tx: 44px;   --ty: 72px; }
.untangling-app .untangling-confetti:nth-child(9)  { --tx: -8px;   --ty: 88px;  animation-delay: 0.3s; }
.untangling-app .untangling-confetti:nth-child(10) { --tx: -56px;  --ty: 66px; }
.untangling-app .untangling-confetti:nth-child(11) { --tx: -92px;  --ty: 22px;  animation-delay: 0.34s; }
.untangling-app .untangling-confetti:nth-child(12) { --tx: -98px;  --ty: -20px; }
.untangling-app .untangling-confetti:nth-child(13) { --tx: 58px;   --ty: -84px; animation-delay: 0.36s; }
@keyframes untangling-check-pop {
	0% { transform: scale(0.3); opacity: 0; }
	100% { transform: scale(1); opacity: 1; }
}
@keyframes untangling-confetti {
	0% { opacity: 0; transform: translate(-50%, -50%) rotate(0deg); }
	12% { opacity: 1; }
	100% { opacity: 0; transform: translate(calc(-50% + var(--tx, 0px)), calc(-50% + var(--ty, 0px))) rotate(320deg); }
}
@keyframes untangling-fade-up {
	from { opacity: 0; transform: translateY(8px); }
	to { opacity: 1; transform: none; }
}
/* Grow your site: the section fades up as it takes the Quick start slot. */
.untangling-app .untangling-grow-enter { display: grid; gap: var(--wpds-dimension-gap-lg); animation: untangling-fade-up 0.35s ease-out both; }
@media ( prefers-reduced-motion: reduce ) {
	.untangling-app .untangling-celebrate-check,
	.untangling-app .untangling-celebrate .untangling-card-title,
	.untangling-app .untangling-celebrate .untangling-meta-text,
	.untangling-app .untangling-grow-enter { animation: none; }
	.untangling-app .untangling-confetti { display: none; }
}
.untangling-app .untangling-launchpad-mark { display: inline-flex; align-items: center; justify-content: center; width: var(--wpds-dimension-size-xs); height: var(--wpds-dimension-size-xs); margin-right: var(--wpds-dimension-gap-sm); border: 1px solid var(--wpds-color-stroke-surface-neutral-strong); border-radius: 50%; font-size: var(--wpds-typography-font-size-sm); vertical-align: middle; }
.untangling-app li.is-done .untangling-launchpad-mark { background: var(--wpds-color-background-interactive-brand-strong); border-color: var(--wpds-color-background-interactive-brand-strong); color: var(--wpds-color-foreground-interactive-brand-strong); }
.untangling-app li.is-done .untangling-launchpad-label { color: var(--wpds-color-foreground-content-neutral-weak); }
/* Prototype chrome: quiet floating entry (W mark) + controls panel, fixed to
   the viewport's bottom right. z-index stays under the admin bar (99999). */
/* Prototype controls: DS components handle their own styling; the CSS left
   here is only the floating-layer chrome (fixed wrapper, round fab, drag
   affordance). */
.untangling-app .untangling-proto-wrap { position: fixed; right: 24px; bottom: 24px; z-index: 9991; }
.untangling-app .untangling-proto-fab.components-button { width: 44px; height: 44px; padding: 0; border: 0; border-radius: 50%; justify-content: center; background: var(--wpds-color-background-surface-neutral-strong, #fff); box-shadow: 0 2px 12px rgba( 0, 0, 0, 0.14 ); touch-action: none; }
.untangling-app .untangling-proto-fab.components-button:hover { box-shadow: 0 4px 16px rgba( 0, 0, 0, 0.2 ); }
.untangling-app .untangling-proto-fab svg { display: block; fill: var(--wpds-color-foreground-interactive-brand); }
.untangling-app .untangling-proto-mark { display: flex; }
.untangling-app .untangling-proto-panel { width: 280px; max-height: calc( 100vh - 48px ); overflow-y: auto; border-radius: var(--wpds-border-radius-md); }
.untangling-app .untangling-proto-head { cursor: grab; user-select: none; -webkit-user-select: none; touch-action: none; letter-spacing: 0.06em; }
.untangling-app .untangling-proto-head:active { cursor: grabbing; }
/* Minimize is chrome, not an action: gray at rest, brand blue on hover. */
.untangling-app .untangling-proto-min.components-button { color: var(--wpds-color-foreground-content-neutral-weak); }
.untangling-app .untangling-proto-min.components-button:hover:not(:disabled) { color: var(--wpds-color-foreground-interactive-brand); }
/* Compact density: the panel is a dev tool that must fit a laptop viewport
   without scrolling, so it runs tighter than the DS card defaults. */
.untangling-app .untangling-proto-panel .components-card__body { padding: 12px 16px; }
.untangling-app .untangling-proto-head.components-card__header { padding: 6px 16px; }
.untangling-app .untangling-proto-panel .components-base-control__help { margin-top: 4px; font-size: 11px; line-height: 1.4; }
.untangling-app .untangling-proto-foot.components-card__footer { padding: 10px 16px; }
/* The bundled ToggleGroupControl mounts with none of its emotion styles in
   this environment (no container chrome, no animated active backdrop), so
   paint the whole DS segmented-control recipe here: bordered container,
   quiet text segments, dark active fill from the data-active-item attribute.
   App-wide: every toggle group (proto panel, Performance, Logs…) needs it.
   Labels never wrap — DS guidance for ToggleGroupControl.
   Radii are hardcoded (4px shell / 2px segment) — the vendored token cascade
   leaves --wpds-border-radius-* at pill values. */
.untangling-app .components-toggle-group-control { display: inline-flex; align-items: stretch; gap: 2px; padding: 2px; background: var(--wpds-color-background-surface-neutral, #fff); border: 1px solid var(--wpds-color-stroke-interactive-neutral, #949494); border-radius: 4px; }
.untangling-app .components-toggle-group-control-option-base { appearance: none; margin: 0; border: 0; background: transparent; font: inherit; font-size: 12px; font-weight: var(--wpds-typography-font-weight-emphasis, 500); line-height: 1; min-height: 24px; padding: 0 8px; display: inline-flex; align-items: center; justify-content: center; color: var(--wpds-color-foreground-interactive-neutral-weak, #757575); border-radius: 2px; white-space: nowrap; cursor: var(--wpds-cursor-control, pointer); transition: background var(--wpds-motion-duration-sm) var(--wpds-motion-easing-subtle), color var(--wpds-motion-duration-sm) var(--wpds-motion-easing-subtle); }
.untangling-app .components-toggle-group-control-option-base:hover:not([data-active-item]) { color: var(--wpds-color-foreground-interactive-brand, #3858e9); }
.untangling-app .components-toggle-group-control-option-base:focus-visible { outline: var(--wpds-border-width-focus, 1.5px) solid var(--wpds-color-stroke-focus, #3858e9); outline-offset: -1px; }
.untangling-app .components-toggle-group-control-option-base[data-active-item] { background: var(--wpds-color-background-interactive-neutral-strong, #2d2d2d); color: var(--wpds-color-foreground-interactive-neutral-strong, #f0f0f0); }
@media ( prefers-reduced-motion: reduce ) { .untangling-app .components-toggle-group-control-option-base { transition: none; } }
/* Plan-card variants (switched from the prototype panel) */
/* Hardcoded surface: the vendored token cascade leaves --wpds-border-radius-*
   at pill values (md = 22px), which balloons this block — 6px is the design. */
.untangling-app .untangling-plan-upsell { margin-top: var(--wpds-dimension-gap-md); background: #f6f7ff; border: 1px solid #dfe5fc; border-radius: 6px; padding: var(--wpds-dimension-padding-lg); }
.untangling-app .untangling-plan-upsell-name { font-size: var(--wpds-typography-font-size-md); font-weight: var(--wpds-typography-font-weight-emphasis); }
.untangling-app .untangling-plan-upsell-price { font-size: var(--wpds-typography-font-size-sm); color: var(--wpds-color-foreground-content-neutral-weak); font-variant-numeric: tabular-nums; }
.untangling-app .untangling-plan-upsell-cta { margin-top: var(--wpds-dimension-gap-md); }
.untangling-app .untangling-feature-tip { position: relative; cursor: default; }
/* The badge recipe clips overflow (ellipsis); a badge hosting the tooltip must not. */
.untangling-app .untangling-hubrow-badge.untangling-feature-tip { overflow: visible; }
/* Bubble matches wordpress.com/pricing: dark, wrapped at ~240px, 8px radius.
   It centers above the cursor — a mousemove listener feeds --untangling-tip-x. */
/* Metrics mirror the wp.components Tooltip (12px text, 4×8 padding, 2px
   radius, content-sized up to 300px) — the component itself silently fails
   with a delay prop in this bundle, so the bubble stays hand-rolled CSS. */
.untangling-app .untangling-feature-tip::after { content: attr(data-tip); position: absolute; bottom: calc( 100% + 8px ); left: var(--untangling-tip-x, 50%); transform: translateX( -50% ); width: max-content; max-width: 300px; background: #1e1e1e; color: #f0f0f0; font-size: 12px; font-weight: 400; line-height: 1.4; padding: 4px 8px; border-radius: 2px; opacity: 0; pointer-events: none; transition: opacity var(--wpds-motion-duration-sm) var(--wpds-motion-easing-subtle); z-index: 10; }
.untangling-app .untangling-feature-tip:hover::after,
.untangling-app .untangling-feature-tip:focus-visible::after { opacity: 1; }
@media ( prefers-reduced-motion: reduce ) { .untangling-app .untangling-feature-tip::after { transition: none; } }
.untangling-app .untangling-plan-rows { display: grid; }
.untangling-app .untangling-plan-row { display: flex; align-items: center; justify-content: space-between; gap: var(--wpds-dimension-gap-md); padding: var(--wpds-dimension-gap-sm) 0; border-bottom: 1px solid var(--wpds-color-stroke-surface-neutral-weak); }
.untangling-app .untangling-plan-row:last-child { border-bottom: 0; }
.untangling-app .untangling-plan-row-label span { font-size: var(--wpds-typography-font-size-md); }
.untangling-app .untangling-plan-row-label small { display: block; font-size: var(--wpds-typography-font-size-sm); color: var(--wpds-color-foreground-content-neutral-weak); }
/* One badge recipe everywhere (DS Badge geometry); only the tone differs. */
.untangling-app .untangling-plan-chip,
.untangling-app .untangling-hubrow-badge,
.untangling-app .untangling-fallback-badge { display: inline-block; flex: none; font-size: var(--wpds-typography-font-size-xs); line-height: var(--wpds-typography-line-height-xs); font-weight: 500; border-radius: var(--wpds-border-radius-sm); padding: 2px var(--wpds-dimension-padding-sm); white-space: nowrap; max-width: 180px; overflow: hidden; text-overflow: ellipsis; }
.untangling-app .untangling-plan-chip { color: var(--wpds-color-foreground-interactive-brand); background: var(--wpds-color-background-surface-brand); }
/* Storage row: the track is the plan's own allowance; amber = used. */
.untangling-app .untangling-plan-row-storage { border-bottom: 0; padding-bottom: 0; }
.untangling-app .untangling-storage-compare { padding: var(--wpds-dimension-gap-xs) 0 var(--wpds-dimension-gap-sm); border-bottom: 1px solid var(--wpds-color-stroke-surface-neutral-weak); }
.untangling-app .untangling-storage-compare-track { position: relative; height: 8px; border-radius: var(--wpds-border-radius-sm); background: var(--wpds-color-background-surface-neutral-weak); overflow: hidden; }
.untangling-app .untangling-storage-compare-used { position: absolute; inset: 0 auto 0 0; background: var(--wpds-color-foreground-content-caution-weak); }
/* Compare variant: Free vs Premium. Subgrid keeps name/price/desc/features on
   shared rows across both columns; each column is its own bordered inner card
   (Launchpad-task style), the recommended one brand-tinted. */
.untangling-app .untangling-plan-compare { display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: auto auto auto 1fr auto; gap: 0 var(--wpds-dimension-gap-lg); }
.untangling-app .untangling-plan-compare-col { min-width: 0; display: grid; grid-template-rows: subgrid; grid-row: 1 / -1; padding: var(--wpds-dimension-padding-xl); background: var(--wpds-color-background-surface-neutral-strong, #fff); border: 1px solid var(--wpds-color-stroke-surface-neutral); border-radius: 8px; }
.untangling-app .untangling-plan-compare-col.is-recommended { background: color-mix( in srgb, var(--wpds-color-stroke-surface-brand-strong, #3858e9) 4%, #fff ); border-color: color-mix( in srgb, var(--wpds-color-stroke-surface-brand-strong, #3858e9) 16%, transparent ); }
.untangling-app .untangling-plan-compare-name { display: flex; align-items: center; gap: var(--wpds-dimension-gap-sm); font-size: var(--wpds-typography-font-size-xl); line-height: var(--wpds-typography-line-height-xl); font-weight: 500; }
.untangling-app .untangling-plan-compare-price { margin-top: var(--wpds-dimension-gap-xs); margin-bottom: var(--wpds-dimension-gap-md); font-size: var(--wpds-typography-font-size-sm); font-weight: 500; }
.untangling-app .untangling-plan-compare-desc { color: var(--wpds-color-foreground-content-neutral-weak); font-size: var(--wpds-typography-font-size-md); margin: var(--wpds-dimension-gap-xs) 0 var(--wpds-dimension-gap-lg); }
.untangling-app .untangling-plan-compare-list { list-style: none; margin: 0; padding: 0; display: grid; align-content: start; gap: var(--wpds-dimension-gap-md); font-size: var(--wpds-typography-font-size-md); }
.untangling-app .untangling-plan-compare-list.is-muted { color: var(--wpds-color-foreground-content-neutral-weak); }
.untangling-app .untangling-plan-compare-cta { margin-top: var(--wpds-dimension-gap-lg); display: flex; align-items: center; gap: var(--wpds-dimension-gap-sm); flex-wrap: wrap; }
/* Card footer link row, MSD "See all activity" style: label left, chevron right. */
.untangling-app .untangling-linkfooter { display: flex; align-items: center; justify-content: space-between; width: 100%; text-decoration: none; color: var(--wpds-color-foreground-content-neutral); font-size: var(--wpds-typography-font-size-md); }
.untangling-app .untangling-linkfooter:hover { color: var(--wpds-color-stroke-surface-brand-strong, var(--wp-admin-theme-color, #3858e9)); }
.untangling-app .untangling-linkfooter:hover .untangling-ovcard-chevron { border-color: currentColor; }
.untangling-app .untangling-linkfooter:focus-visible { outline: var(--wpds-border-width-focus) solid var(--wpds-color-stroke-focus); outline-offset: 2px; border-radius: var(--wpds-border-radius-sm); }
.untangling-app .untangling-plan-chip.is-neutral { color: var(--wpds-color-foreground-content-neutral); background: var(--wpds-color-background-surface-neutral-weak); }
.untangling-app .untangling-plan-chip.is-success { color: var(--wpds-color-foreground-content-success-weak); background: var(--wpds-color-background-surface-success-weak); }
.untangling-app .untangling-plan-chip.is-dark { color: #fff; background: #2c3338; }
/* Creator-offer variant */
.untangling-app .untangling-plan-eyebrow { font-size: var(--wpds-typography-font-size-xs); font-weight: var(--wpds-typography-font-weight-emphasis); letter-spacing: 0.06em; text-transform: uppercase; color: var(--wpds-color-foreground-interactive-brand); margin-bottom: var(--wpds-dimension-gap-xs); }
.untangling-app .untangling-plan-headline { font-size: var(--wpds-typography-font-size-xl); line-height: var(--wpds-typography-line-height-lg); font-weight: var(--wpds-typography-font-weight-default); margin: 0 0 var(--wpds-dimension-gap-xs); text-wrap: balance; }
.untangling-app .untangling-plan-fine { font-size: var(--wpds-typography-font-size-xs); color: var(--wpds-color-foreground-content-neutral-weak); }
CSS;
}

/* -------------------------------------------------------------------------
 * 2. Plugins screen: Marketplace tab (install_plugins_tabs, like Woo)
 * ---------------------------------------------------------------------- */

// Marketplace sits before Favorites in the tab order. V2 (split) and V3
// (tabs) — in V1 the plugins marketplace lives in the fullscreen page.
add_filter( 'install_plugins_tabs', function ( $tabs ) {
	if ( ! in_array( untangling_get_marketplace_mode(), array( 'split', 'tabs' ), true ) ) {
		return $tabs;
	}
	$reordered = array();
	foreach ( $tabs as $key => $label ) {
		if ( 'favorites' === $key ) {
			$reordered['wpcom_marketplace'] = __( 'Marketplace' );
		}
		$reordered[ $key ] = $label;
	}
	if ( ! isset( $reordered['wpcom_marketplace'] ) ) {
		$reordered['wpcom_marketplace'] = __( 'Marketplace' );
	}
	return $reordered;
} );

// WP renders tab content via install_plugins_{$tab}. Core plugin-card markup
// so the Marketplace tab matches the other Add Plugins tabs exactly; the
// bottom-right compatibility cell becomes the plan signal (included or the
// required tier), and the CTA follows it. Real marketplace plugins from
// wordpress.com/plugins with hotlinked icons.
// Shared curated catalog — the Add Plugins tab (V2) and the fullscreen
// Marketplace (V1) render the same real marketplace plugins.
// slug, name, description, author, icon, tier, rating, ratings count, installs, updated, category, monthly price (null = free plugin).
function untangling_marketplace_plugins() {
	return array(
		array( 'wordpress-seo-premium', 'Yoast SEO Premium', 'Advanced SEO tools, redirects, and internal linking suggestions.', 'Team Yoast', 'https://ps.w.org/wordpress-seo/assets/icon-256x256.gif', 'Business', 4.5, 1204, '300,000+', '3 days ago', 'seo', '10.00' ),
		array( 'google-site-kit', 'Site Kit by Google', 'Search Console, Analytics, and AdSense in one dashboard.', 'Google', 'https://ps.w.org/google-site-kit/assets/icon-256x256.png', 'Personal', 4, 642, '4+ Million', '2 days ago', 'seo', null ),
		array( 'optinmonster', 'OptinMonster', 'Popups, subscriber growth, and lead generation for your site.', 'OptinMonster', 'https://ps.w.org/optinmonster/assets/icon-256x256.png', 'Premium', 4.5, 7563, '800,000+', '5 days ago', 'marketing', '9.90' ),
		array( 'leadin', 'HubSpot', 'CRM, email marketing, live chat, and forms.', 'HubSpot', 'https://ps.w.org/leadin/assets/icon-256x256.png', 'Personal', 4.5, 245, '200,000+', '4 days ago', 'marketing', null ),
		array( 'zero-bs-crm', 'Jetpack CRM', 'A simple CRM for entrepreneurs and small businesses.', 'Automattic', 'https://ps.w.org/zero-bs-crm/assets/icon-256x256.png', 'Personal', 4.5, 81, '10,000+', '1 week ago', 'marketing', null ),
		array( 'mailpoet', 'MailPoet', 'Send newsletters and post notifications from WordPress.', 'MailPoet', 'https://ps.w.org/mailpoet/assets/icon-256x256.png', 'Personal', 4.5, 1327, '700,000+', '3 days ago', 'email', null ),
		array( 'fluent-crm', 'FluentCRM', 'Email marketing, automation, and CRM inside WordPress.', 'WPManageNinja', 'https://ps.w.org/fluent-crm/assets/icon-256x256.png', 'Premium', 5, 412, '40,000+', '6 days ago', 'email', '12.00' ),
		array( 'give', 'GiveWP', 'Create donation pages and collect more for your cause.', 'GiveWP', 'https://ps.w.org/give/assets/icon-256x256.jpg', 'Personal', 4.5, 1946, '100,000+', '1 week ago', 'payments', null ),
		array( 'easy-digital-downloads', 'Easy Digital Downloads', 'Create and sell digital products from your site.', 'Easy Digital Downloads', 'https://ps.w.org/easy-digital-downloads/assets/icon.svg', 'Premium', 4.5, 836, '50,000+', '5 days ago', 'payments', '8.90' ),
		array( 'gravityforms', 'Gravity Forms', 'The most advanced form builder for WordPress.', 'Gravity Forms', 'https://woocommerce.com/wp-content/uploads/2022/08/gravity-forms-160x160-1.png', 'Premium', 5, 128, '1+ Million', '2 weeks ago', 'forms', '12.00' ),
		array( 'sensei-pro', 'Sensei Pro', 'Create and sell courses, quizzes, and interactive lessons.', 'Automattic', 'https://wordpress.com/wp-content/lib/marketplace-images/sensei-pro.svg', 'Business', 4, 359, '10,000+', '6 days ago', 'education', '9.00' ),
		array( 'automatewoo', 'AutomateWoo', 'Powerful marketing automation for your WooCommerce store.', 'WooCommerce', 'https://woocommerce.com/wp-content/uploads/2019/10/woo-AutomateWoo.png', 'Commerce', 4, 74, '30,000+', '1 week ago', 'store', '11.00' ),
		array( 'woocommerce-subscriptions', 'WooCommerce Subscriptions', 'Let customers subscribe to your products or services.', 'WooCommerce', 'https://woocommerce.com/wp-content/uploads/2012/09/Woo_Subscriptions_icon-marketplace-160x160-2.png', 'Commerce', 4, 57, '60,000+', '4 days ago', 'store', '24.00' ),
		array( 'woocommerce-bookings', 'WooCommerce Bookings', 'Let customers book appointments and reservations.', 'WooCommerce', 'https://woocommerce.com/wp-content/uploads/2014/05/Bookings_icon-marketplace-160x160-2.png', 'Commerce', 4, 42, '20,000+', '1 week ago', 'store', '20.00' ),
	);
}

function untangling_marketplace_plugin_categories() {
	return array(
		'all'       => __( 'All' ),
		'seo'       => __( 'Search optimization' ),
		'marketing' => __( 'Marketing' ),
		'email'     => __( 'Email' ),
		'payments'  => __( 'Payments' ),
		'forms'     => __( 'Forms' ),
		'education' => __( 'Education' ),
		'store'     => __( 'Store tools' ),
	);
}

add_action( 'install_plugins_wpcom_marketplace', function () {
	$plan       = untangling_get_plan();
	$plugins    = untangling_marketplace_plugins();
	$categories = untangling_marketplace_plugin_categories();
	$current    = ( isset( $_GET['category'] ) && isset( $categories[ $_GET['category'] ] ) ) ? sanitize_key( $_GET['category'] ) : 'all';

	$is_tabs        = 'tabs' === untangling_get_marketplace_mode();
	$plan_filter    = untangling_get_plan_filter();
	$included_count = 0;
	foreach ( $plugins as $p ) {
		if ( untangling_plan_rank( $plan ) >= untangling_plan_rank( $p[5] ) ) {
			$included_count++;
		}
	}
	// Category row: core subsubsub list with counts, like the Posts screen.
	$counts = array_count_values( array_column( $plugins, 10 ) );
	$items  = array();
	foreach ( $categories as $key => $label ) {
		$count   = 'all' === $key ? count( $plugins ) : ( isset( $counts[ $key ] ) ? $counts[ $key ] : 0 );
		$url     = admin_url( 'plugin-install.php?tab=wpcom_marketplace&category=' . $key );
		$items[] = '<li><a href="' . esc_url( $url ) . '" data-category="' . esc_attr( $key ) . '"' . ( $key === $current ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $label ) . ' <span class="count">(' . esc_html( number_format_i18n( $count ) ) . ')</span></a>';
	}
	untangling_plugins_upsell_banner();

	echo '<div class="untangling-filter-row">';
	echo '<ul class="subsubsub untangling-cat-filters">' . implode( " |</li>\n", $items ) . '</li></ul>';
	if ( $is_tabs && 'included' === $plan_filter ) {
		untangling_plan_filter_links( count( $plugins ), $included_count );
	} elseif ( $is_tabs && 'dropdown' === $plan_filter ) {
		untangling_plan_filter_dropdown( array( 'Personal', 'Premium', 'Business', 'Commerce' ) );
	}
	echo '</div>';

	echo '<div class="wp-list-table widefat plugin-install untangling-marketplace"><div id="the-list">';
	foreach ( $plugins as $p ) {
		list( $slug, $name, $desc, $author, $icon, $tier, $rating, $num, $installs, $updated, $cat, $price ) = $p;
		$included = untangling_plan_rank( $plan ) >= untangling_plan_rank( $tier );
		$details  = 'https://wordpress.com/plugins/' . $slug;
		$hidden   = ( 'all' !== $current && $cat !== $current ) ? ' style="display:none"' : '';
		echo '<div class="plugin-card plugin-card-' . esc_attr( $slug ) . '" data-category="' . esc_attr( $cat ) . '" data-tier="' . esc_attr( $tier ) . '" data-included="' . ( $included ? '1' : '' ) . '"' . $hidden . '>';
		echo '<div class="plugin-card-top">';
		echo '<div class="name column-name"><h3><a href="' . esc_url( $details ) . '" target="_blank" rel="noreferrer">' . esc_html( $name ) . '<img class="plugin-icon" src="' . esc_url( $icon ) . '" alt=""></a></h3></div>';
		echo '<div class="action-links"><ul class="plugin-action-buttons">';
		if ( $included ) {
			echo '<li><a class="install-now button" href="#">' . esc_html__( 'Install Now' ) . '</a></li>';
		} else {
			echo '<li><a class="button button-primary untangling-upgrade" href="' . esc_url( untangling_marketplace_url( 'plugins', array( 'ustep' => 'pricing', 'type' => 'plugin', 'slug' => $slug ) ) ) . '">' . untangling_upsell_diamond() . esc_html__( 'Upgrade and Activate' ) . '</a></li>';
		}
		echo '<li><a href="' . esc_url( $details ) . '" target="_blank" rel="noreferrer">' . esc_html__( 'More Details' ) . '</a></li>';
		echo '</ul></div>';
		echo '<div class="desc column-description"><p>' . esc_html( $desc ) . '</p><p class="authors"><cite>' . esc_html( 'By ' . $author ) . '</cite></p></div>';
		echo '</div>';
		echo '<div class="plugin-card-bottom">';
		echo '<div class="vers column-rating">';
		wp_star_rating( array( 'rating' => $rating, 'type' => 'rating', 'number' => $num, 'echo' => true ) );
		echo '<span class="num-ratings" aria-hidden="true">(' . esc_html( number_format_i18n( $num ) ) . ')</span></div>';
		echo '<div class="column-updated"><strong>' . esc_html__( 'Last Updated:' ) . '</strong> ' . esc_html( $updated ) . '</div>';
		echo '<div class="column-downloaded">' . esc_html( sprintf( __( '%s Active Installations' ), $installs ) ) . '</div>';
		echo '<div class="column-compatibility">';
		echo '<span class="compatibility-compatible"><strong>' . esc_html__( 'Compatible' ) . '</strong> ' . esc_html__( 'with your version of WordPress' ) . '</span>';
		if ( $included ) {
			echo '<span class="compatibility-compatible"><strong>' . esc_html__( 'Included' ) . '</strong> ' . esc_html( sprintf( __( 'in your %s plan' ), $plan ) ) . '</span>';
		} elseif ( $price ) {
			echo '<span class="untangling-tier-required">' . esc_html( 'US$' . $price . '/month' ) . ' + <strong>' . esc_html( $tier ) . '</strong> ' . esc_html__( 'plan' ) . '</span>';
		} else {
			echo '<span class="untangling-tier-required">' . esc_html__( 'Requires the' ) . ' <strong>' . esc_html( $tier ) . '</strong> ' . esc_html__( 'plan' ) . '</span>';
		}
		echo '</div></div></div>';
	}
	echo '</div></div><div class="clear"></div>';
	?>
	<script>
	( function () {
		var links = document.querySelectorAll( '.untangling-cat-filters a' );
		var planLinks = document.querySelectorAll( '.untangling-plan-filters a' );
		var planSelect = document.querySelector( '[data-plan-filter]' );
		var cards = document.querySelectorAll( '.untangling-marketplace .plugin-card' );
		var category = <?php echo wp_json_encode( $current ); ?>;
		var planChoice = 'all';
		function catOk( card, key ) {
			return 'all' === key || card.dataset.category === key;
		}
		function planOk( card, choice ) {
			return 'all' === choice || ( 'included' === choice ? !! card.dataset.included : card.dataset.tier === choice );
		}
		function refreshCounts() {
			links.forEach( function ( link ) {
				var n = 0;
				cards.forEach( function ( card ) { if ( catOk( card, link.dataset.category ) && planOk( card, planChoice ) ) { n++; } } );
				var count = link.querySelector( '.count' );
				if ( count ) { count.textContent = '(' + n + ')'; }
			} );
			planLinks.forEach( function ( link ) {
				var n = 0;
				cards.forEach( function ( card ) { if ( catOk( card, category ) && planOk( card, link.dataset.plan ) ) { n++; } } );
				var count = link.querySelector( '.count' );
				if ( count ) { count.textContent = '(' + n + ')'; }
			} );
		}
		function applyFilters() {
			cards.forEach( function ( card ) {
				card.style.display = ( catOk( card, category ) && planOk( card, planChoice ) ) ? '' : 'none';
			} );
			refreshCounts();
		}
		links.forEach( function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				category = link.dataset.category;
				links.forEach( function ( other ) {
					other.classList.toggle( 'current', other === link );
					if ( other === link ) { other.setAttribute( 'aria-current', 'page' ); } else { other.removeAttribute( 'aria-current' ); }
				} );
				applyFilters();
				window.history.replaceState( null, '', link.href );
			} );
		} );
		planLinks.forEach( function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				planChoice = link.dataset.plan;
				planLinks.forEach( function ( other ) {
					other.classList.toggle( 'current', other === link );
					if ( other === link ) { other.setAttribute( 'aria-current', 'page' ); } else { other.removeAttribute( 'aria-current' ); }
				} );
				applyFilters();
			} );
		} );
		if ( planSelect ) {
			planSelect.addEventListener( 'change', function () {
				planChoice = planSelect.value;
				applyFilters();
			} );
		}
	} )();
	</script>
	<?php
} );

// Simple mode: core plugin search results keep the exact core UI, but the
// install action becomes an upgrade CTA (open question #1, now decided).
// The CTA opens the fullscreen pricing step tailored to the clicked plugin
// (flow=install carries the wp.org name; the required tier matches the
// cards' "Requires the X plan" signal). `back` is a fixed admin URL, not
// REQUEST_URI: search results re-render through admin-ajax.php.
add_filter( 'plugin_install_action_links', function ( $links, $plugin ) {
	if ( ! untangling_is_simple() ) {
		return $links;
	}
	// Keep core's More Details link so the card matches the stock layout.
	$details = array_values( array_filter( $links, function ( $link ) {
		return false !== strpos( $link, 'open-plugin-details-modal' );
	} ) );
	$pricing = untangling_marketplace_url( 'plugins', array(
		'ustep' => 'pricing',
		'type'  => 'plugin',
		'flow'  => 'install',
		'pname' => isset( $plugin['name'] ) ? wp_strip_all_tags( $plugin['name'] ) : '',
		'back'  => rawurlencode( admin_url( 'plugin-install.php' ) ),
	) );
	return array_merge(
		array( '<a class="button button-primary untangling-upgrade" href="' . esc_url( $pricing ) . '">' . untangling_upsell_diamond() . esc_html__( 'Upgrade to install' ) . '</a>' ),
		$details
	);
}, 20, 2 );

// Simple mode: the More Details modal (core's plugin-information iframe)
// follows the cards — Install Now becomes Upgrade to install and opens the
// same fullscreen pricing → checkout flow in the parent window. The iframe
// document doesn't get the admin inline styles or the card filter, so both
// the diamond rule and the button swap are injected here.
add_action( 'admin_print_footer_scripts', function () {
	global $pagenow;
	if ( 'plugin-install.php' !== $pagenow || ! isset( $_GET['tab'] ) || 'plugin-information' !== $_GET['tab'] || ! untangling_is_simple() ) {
		return;
	}
	$pricing = untangling_marketplace_url( 'plugins', array(
		'ustep' => 'pricing',
		'type'  => 'plugin',
		'flow'  => 'install',
		'back'  => rawurlencode( admin_url( 'plugin-install.php' ) ),
	) );
	?>
	<style>.untangling-upsell-diamond { width: 14px; height: 12px; fill: currentColor; vertical-align: -1px; margin-inline-end: 6px; }</style>
	<script>
	( function () {
		var footer = document.getElementById( 'plugin-information-footer' );
		var button = footer && footer.querySelector( '.button' );
		if ( ! button ) {
			return;
		}
		var title = document.querySelector( '#plugin-information-title h2' );
		var url = new URL( <?php echo wp_json_encode( $pricing ); ?> );
		if ( title ) {
			url.searchParams.set( 'pname', title.textContent.trim() );
		}
		var link = document.createElement( 'a' );
		// Primary like every upgrade CTA; `right` keeps core's bottom-right
		// placement for the stock Install Now.
		link.className = 'button button-primary right untangling-upgrade';
		link.href = url.toString();
		link.target = '_parent';
		link.innerHTML = <?php echo wp_json_encode( untangling_upsell_diamond() . esc_html__( 'Upgrade to install' ) ); ?>;
		button.replaceWith( link );
	} )();
	</script>
	<?php
} );

// Simple mode: the Upload Plugin form is removed (no upgrade prompt shown).
add_action( 'admin_init', function () {
	if ( untangling_is_simple() ) {
		remove_action( 'install_plugins_upload', 'install_plugins_upload' );
	}
} );

// Partner CTA in regular search results (Mike's DOTPAR-24 exploration, shown as an option).
add_filter( 'plugin_install_action_links', function ( $links, $plugin ) {
	$partner_slugs = array( 'wordpress-seo' );
	if ( isset( $plugin['slug'] ) && in_array( $plugin['slug'], $partner_slugs, true ) ) {
		array_unshift( $links, '<a class="button button-primary" href="' . esc_url( UNTANGLING_MSD_URL . '/plans' ) . '">' . esc_html__( 'Premium version in Marketplace ↗' ) . '</a>' );
	}
	return $links;
}, 10, 2 );

// Core-tab cards (Featured/Popular/Recommended/search) get the same
// bottom-right plan signal as the Marketplace cards, appended under core's
// "Compatible with your version of WordPress". Core re-renders search
// results via AJAX, so the append re-applies through a MutationObserver.
// Simple sites name the plan that unlocks installs (Free → Personal, to
// match the upsell banner; Premium → Business).
add_action( 'admin_footer-plugin-install.php', function () {
	$plan = untangling_get_plan();
	if ( untangling_is_simple() ) {
		$tier   = 'Premium' === $plan ? __( 'Business' ) : __( 'Personal' );
		$signal = '<span class="untangling-tier-required">' . esc_html__( 'Requires the' ) . ' <strong>' . esc_html( $tier ) . '</strong> ' . esc_html__( 'plan' ) . '</span>';
	} else {
		$signal = '<span class="compatibility-compatible"><strong>' . esc_html__( 'Included' ) . '</strong> ' . esc_html( sprintf( __( 'in your %s plan' ), $plan ) ) . '</span>';
	}
	?>
	<script>
	( function () {
		var signal = <?php echo wp_json_encode( $signal ); ?>;
		function swap() {
			document.querySelectorAll( '.plugin-card .column-compatibility' ).forEach( function ( cell ) {
				if ( cell.closest( '.untangling-marketplace' ) || cell.dataset.untanglingSwapped ) {
					return;
				}
				cell.dataset.untanglingSwapped = '1';
				cell.insertAdjacentHTML( 'beforeend', signal );
			} );
		}
		swap();
		var list = document.getElementById( 'the-list' );
		if ( list ) {
			new MutationObserver( swap ).observe( list, { childList: true, subtree: true } );
		}
	} )();
	</script>
	<?php
} );

// The Recommended tab's intro line attributes suggestions to install
// telemetry the prototype does not have; blank it (CSS collapses the
// empty <p> core still prints).
add_filter( 'gettext', function ( $translation, $text ) {
	if ( 'These suggestions are based on the plugins you and other users have installed.' === $text ) {
		return '';
	}
	return $translation;
}, 10, 2 );

// Free-plan upsell banner on every Add Plugins tab — above the category
// filters on Marketplace, at the top of the tab content elsewhere (echoed
// before core's display_plugins_table at priority 10). Personal is the
// first paid step up from Free, so only Free sites see it. `inline` keeps
// it where it is echoed — common.js moves non-inline notices to the
// header.
foreach ( array( 'featured', 'popular', 'recommended', 'favorites', 'search' ) as $untangling_tab ) {
	add_action( "install_plugins_{$untangling_tab}", 'untangling_plugins_upsell_banner', 5 );
}
unset( $untangling_tab );

function untangling_plugins_upsell_banner() {
	if ( 'Free' !== untangling_get_plan() ) {
		return;
	}
	if ( in_array( untangling_get_marketplace_mode(), array( 'split', 'tabs' ), true ) ) {
		untangling_plugins_upsell_hero();
		return;
	}
	?>
	<div class="notice inline untangling-plugins-upsell">
		<span class="untangling-plugins-upsell-icon dashicons dashicons-info-outline"></span>
		<div class="untangling-plugins-upsell-copy">
			<h2><?php esc_html_e( 'Access thousands of plugins with the Personal Plan' ); ?></h2>
			<p><?php esc_html_e( 'Free domain included.' ); ?></p>
		</div>
		<a class="button button-primary untangling-upgrade" href="<?php echo esc_url( untangling_marketplace_url( 'plugins', array( 'ustep' => 'pricing', 'ref' => 'plugins-upsell-hero', 'back' => rawurlencode( $_SERVER['REQUEST_URI'] ) ) ) ); ?>"><?php esc_html_e( 'Upgrade' ); ?></a>
	</div>
	<?php
}

// Split (V2) and Tabs (V3): the Free-plan upsell renders as a WP.com showcase hero —
// same visual language as the wpcom themes/plugins banners (Recoleta
// heading, logo lockup, rounded dark panel, plugin-tile artwork) instead
// of an admin notice. Both CTAs open the fullscreen pricing step.
function untangling_plugins_upsell_hero() {
	static $printed = false;
	$assets = 'https://cdn.jsdelivr.net/gh/Automattic/jetpack@f319779638296460446adc163ff042ef57789b15/projects/packages/jetpack-mu-wpcom/src/features/wpcom-plugins/images';
	if ( ! $printed ) {
		$printed = true;
		?>
		<style>
		@font-face {
			font-display: swap;
			font-family: Recoleta;
			font-weight: 400;
			src: url(https://s1.wp.com/i/fonts/recoleta/400.woff2) format("woff2"), url(https://s1.wp.com/i/fonts/recoleta/400.woff) format("woff");
		}
		/* Compact banner: metrics mirror .wpcom-themes-banner.is-upsell exactly
		   so the plugins and themes upsells come out the same height. */
		.untangling-upsell-hero {
			/* White Recoleta on dark renders heavier than the Themes banner's
			   dark-on-light without antialiasing. */
			-webkit-font-smoothing: antialiased;
			-moz-osx-font-smoothing: grayscale;
			background-color: #242424;
			padding: 24px 32px;
			border-radius: 10px;
			margin: 20px 0 24px;
			background-image: url(<?php echo esc_url( $assets . '/banner-background.webp' ); ?>);
			background-repeat: no-repeat;
			background-position: bottom right 48px;
			background-size: 340px;
		}
		.untangling-upsell-hero__content { width: 540px; }
		.untangling-upsell-hero__content img { height: 16px; width: auto; display: block; }
		#wpcontent .untangling-upsell-hero h3,
		#wpcontent .untangling-upsell-hero p { font-weight: 400; letter-spacing: -0.32px; margin: 8px 0; text-wrap: pretty; }
		.untangling-upsell-hero h3 { font-family: Recoleta, serif; font-size: 24px; line-height: 32px; color: #fff; }
		.untangling-upsell-hero p { font-size: 14px; line-height: 20px; color: #a7aaad; }
		/* WPDS default Button metrics (40px, emphasis weight, radius-sm);
		   token fallbacks because wp-admin screens don't load the cascade. */
		.untangling-upsell-hero a,
		.untangling-upsell-hero a:visited { display: inline-flex; align-items: center; box-sizing: border-box; height: var(--wpds-dimension-size-lg, 40px); padding: 0 var(--wpds-dimension-padding-lg, 16px); background-color: var(--wpds-color-background-interactive-brand-strong, #3858e9); color: #fff; border-radius: var(--wpds-border-radius-sm, 4px); font-size: var(--wpds-typography-font-size-md, 13px); font-weight: var(--wpds-typography-font-weight-emphasis, 500); line-height: 20px; letter-spacing: normal; text-decoration: none; margin-top: 12px; }
		.untangling-upsell-hero a:hover,
		.untangling-upsell-hero a:focus { background-color: #fff; color: #1d2327; }
		@media ( max-width: 1120px ) {
			.untangling-upsell-hero { background-position: bottom right 5px; background-size: 280px; }
		}
		@media ( max-width: 850px ) {
			.untangling-upsell-hero { background-image: none; }
			.untangling-upsell-hero__content { width: auto; }
		}
		</style>
		<?php
	}
	?>
	<div class="untangling-upsell-hero">
		<div class="untangling-upsell-hero__content">
			<img src="<?php echo esc_url( $assets . '/wpcom-logo.svg' ); ?>" alt="WordPress.com">
			<h3><?php esc_html_e( 'Unlock thousands of plugins' ); ?></h3>
			<p><?php esc_html_e( 'Upgrade to any annual plan and get a free domain for the first year.' ); ?></p>
			<a class="untangling-upsell-cta" href="<?php echo esc_url( untangling_marketplace_url( 'plugins', array( 'ustep' => 'pricing', 'ref' => 'plugins-upsell-hero', 'back' => rawurlencode( $_SERVER['REQUEST_URI'] ) ) ) ); ?>"><?php esc_html_e( 'See all plans' ); ?></a>
		</div>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * 3. Themes screens: the production WP.com themes banner. Markup, styles
 *    and hide-on-search behavior copied from jetpack-mu-wpcom
 *    (features/wpcom-themes); images hotlinked from the Jetpack repo via
 *    jsDelivr, CTA pointed at the MSD theme showcase.
 * ---------------------------------------------------------------------- */

function untangling_themes_banner() {
	$assets  = 'https://cdn.jsdelivr.net/gh/Automattic/jetpack@f319779638296460446adc163ff042ef57789b15/projects/packages/jetpack-mu-wpcom/src/features/wpcom-themes/images';
	$is_tabs = 'tabs' === untangling_get_marketplace_mode();
	// V3: the discovery banner becomes the plans upsell (same lavender panel,
	// mirroring the dark plugins hero). Business/Commerce already include
	// every theme tier, so they skip it.
	if ( $is_tabs && untangling_plan_rank( untangling_get_plan() ) >= untangling_plan_rank( 'Business' ) ) {
		return;
	}
	// V3 shows the upsell only on Add Themes; the installed-themes page stays clean.
	if ( $is_tabs && 'themes.php' === $GLOBALS['pagenow'] ) {
		return;
	}
	// V2: production Atomic keeps the installed-themes screen banner-free — the
	// Theme Showcase submenu is the discovery entry. Add Themes keeps the
	// banner, whose CTA already points back at the in-admin showcase.
	if ( 'split' === untangling_get_marketplace_mode() && 'themes.php' === $GLOBALS['pagenow'] ) {
		return;
	}
	$heading   = $is_tabs ? __( 'Unlock thousands of themes' ) : __( 'Beautiful themes for every idea' );
	$blurb     = $is_tabs ? __( 'Upgrade to any annual plan and get a free domain for the first year.' ) : __( 'Dive deep into the world of WordPress.com themes. Discover the responsive and stunning designs waiting to bring your site to life.' );
	$cta_label = $is_tabs ? __( 'See all plans' ) : __( 'Explore themes' );
	if ( $is_tabs ) {
		$cta_url = untangling_marketplace_url( 'themes', array( 'ustep' => 'pricing', 'ref' => 'themes-upsell-banner', 'back' => rawurlencode( $_SERVER['REQUEST_URI'] ) ) );
	} elseif ( 'split' === untangling_get_marketplace_mode() ) {
		// V2's showcase is the Themes screen, not the fullscreen page.
		$cta_url = untangling_themes_screen_url( array( 'ref' => 'wpcom-themes-banner' ) );
	} else {
		$cta_url = untangling_marketplace_url( 'themes', array( 'ref' => 'wpcom-themes-banner' ) );
	}
	?>
	<style>
	@font-face {
		font-display: swap;
		font-family: Recoleta;
		font-weight: 400;
		src: url(https://s1.wp.com/i/fonts/recoleta/400.woff2) format("woff2"), url(https://s1.wp.com/i/fonts/recoleta/400.woff) format("woff");
	}
	/* Compact banner: vertical metrics mirror .untangling-upsell-hero (the
	   dark plugins hero) exactly so the two banners come out the same height.
	   Applies to both the tabs-mode upsell and the discovery variant. */
	.wpcom-themes-banner {
		background-color: #dbe0f9;
		padding: 24px 32px;
		border-radius: 10px;
		margin-bottom: 25px;
		background-image: url(<?php echo esc_url( $assets . '/banner-background.webp' ); ?>);
		background-repeat: no-repeat;
		background-position: center right 10px;
		background-size: 300px;
	}
	.wpcom-themes-banner.hidden { display: none; }
	.wpcom-themes-banner__content { width: 540px; }
	.wpcom-themes-banner__content img { height: 16px; width: auto; display: block; }
	/* themes.php: the installed-themes search moves below the banner (see
	   the script), so the banner tops both this page and the Plugins page
	   at the same spot under the page title. */
	.themes-php .wpcom-themes-banner { margin-top: 25px; }
	.themes-php .search-form.search-themes { float: right; margin: 0 0 16px; }
	.themes-php .theme-browser { clear: both; }
	.wpcom-themes-banner h3,
	.wpcom-themes-banner p { font-weight: 400; letter-spacing: -0.32px; margin: 8px 0; text-wrap: pretty; }
	.wpcom-themes-banner h3 { font-family: Recoleta, serif; font-size: 24px; line-height: 32px; color: #101517; }
	.wpcom-themes-banner p { font-size: 14px; line-height: 20px; color: #2c3338; }
	/* WPDS default Button metrics, mirroring the plugins hero CTA exactly
	   (including antialiasing — its banner smooths all text, this one
	   doesn't, which otherwise renders this label visibly heavier). */
	.wpcom-themes-banner a,
	.wpcom-themes-banner a:visited { display: inline-flex; align-items: center; box-sizing: border-box; height: var(--wpds-dimension-size-lg, 40px); padding: 0 var(--wpds-dimension-padding-lg, 16px); background-color: var(--wpds-color-background-interactive-neutral-strong, #101517); color: #fff; border-radius: var(--wpds-border-radius-sm, 4px); font-size: var(--wpds-typography-font-size-md, 13px); font-weight: var(--wpds-typography-font-weight-emphasis, 500); line-height: 20px; letter-spacing: normal; text-decoration: none; margin-top: 12px; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
	.wpcom-themes-banner a:hover,
	.wpcom-themes-banner a:focus { background-color: #1d2327; color: #fff; }
	@media ( max-width: 1120px ) {
		.wpcom-themes-banner { background-position: center right -60px; background-size: 260px; }
	}
	@media ( max-width: 850px ) {
		.wpcom-themes-banner { background-image: none; }
		.wpcom-themes-banner__content { width: auto; }
	}
	</style>
	<script>
	( function () {
		var themeBrowser = document.querySelector( '.theme-browser' );
		if ( ! themeBrowser ) {
			return;
		}

		themeBrowser.insertAdjacentHTML(
			'beforebegin',
			'<div class="wpcom-themes-banner<?php echo $is_tabs ? ' is-upsell' : ''; ?>">' +
				'<div class="wpcom-themes-banner__content">' +
					'<img src="<?php echo esc_url( $assets . '/wpcom-logo.svg' ); ?>" alt="WordPress.com">' +
					'<h3><?php echo esc_js( $heading ); ?></h3>' +
					'<p><?php echo esc_js( $blurb ); ?></p>' +
					'<a href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_js( $cta_label ); ?></a>' +
				'</div>' +
			'</div>'
		);

		var themesBanner = document.querySelector( '.wpcom-themes-banner' );

		var searchForm = document.querySelector( '.search-form.search-themes' );
		if ( searchForm ) {
			themesBanner.insertAdjacentElement( 'afterend', searchForm );
		}

		var wpcomThemesObserver = new MutationObserver( function () {
			var searchInput = document.querySelector( '#wp-filter-search-input' );
			if (
				document.querySelector( '.loading-content .spinner' ) ||
				document.querySelector( '[data-sort="favorites"].current' ) ||
				document.querySelector( '.show-filters .filter-drawer' ) ||
				( searchInput && searchInput.value && ! document.querySelector( '.no-results p.no-themes' ) )
			) {
				themesBanner.classList.add( 'hidden' );
			} else {
				themesBanner.classList.remove( 'hidden' );
			}
		} );
		wpcomThemesObserver.observe( themeBrowser, { childList: true } );
		wpcomThemesObserver.observe( document.body, { attributes: true } );
	} )();
	</script>
	<?php
}
add_action( 'admin_footer-themes.php', 'untangling_themes_banner' );
add_action( 'admin_footer-theme-install.php', 'untangling_themes_banner' );

/* -------------------------------------------------------------------------
 * 3e. Add Themes screen: Marketplace tab (V3 tabs only) — mirrors the Add
 * Plugins Marketplace tab so both experiences look the same. A tab injected
 * into core's Popular/Latest/Block Themes filter bar renders the shared
 * theme catalog in core theme-card markup, with the same subsubsub category
 * filters as the plugins tab (theme categories from the dotcom showcase)
 * and a plan-tier signal on every card. Core's Backbone browser never sees
 * the tab (no data-sort); the panel swaps in client-side and deep-links via
 * ?untangling_browse=marketplace.
 * ---------------------------------------------------------------------- */

add_action( 'admin_footer-theme-install.php', function () {
	if ( 'tabs' !== untangling_get_marketplace_mode() ) {
		return;
	}
	$plan        = untangling_get_plan();
	$rank        = untangling_plan_rank( $plan );
	$active_slug = get_option( 'untangling_mkt_active_theme', '' );
	$details     = untangling_marketplace_theme_details();
	$themes      = untangling_marketplace_themes();
	$tab_url     = admin_url( 'theme-install.php?untangling_browse=marketplace' );

	$plan_filter    = untangling_get_plan_filter();
	$included_count = 0;
	foreach ( $themes as $t ) {
		if ( $rank >= untangling_plan_rank( untangling_theme_tier_plan( $t[2] ) ) ) {
			$included_count++;
		}
	}

	// Theme categories from the dotcom showcase (the subjects the shared
	// catalog already carries), rendered like the plugins tab's subsubsub.
	$categories = array(
		'all'         => __( 'All' ),
		'blog'        => __( 'Blog' ),
		'portfolio'   => __( 'Portfolio' ),
		'business'    => __( 'Business' ),
		'store'       => __( 'Store' ),
		'photography' => __( 'Photography' ),
	);
	$counts = array_count_values( array_column( $themes, 4 ) );
	$items  = array();
	foreach ( $categories as $key => $label ) {
		$count   = 'all' === $key ? count( $themes ) : ( isset( $counts[ $key ] ) ? $counts[ $key ] : 0 );
		$items[] = '<li><a href="#" data-category="' . esc_attr( $key ) . '"' . ( 'all' === $key ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $label ) . ' <span class="count">(' . esc_html( number_format_i18n( $count ) ) . ')</span></a>';
	}
	?>
	<style>
	body.untangling-mkt-tab-open .theme-browser.content-filterable,
	body.untangling-mkt-tab-open .no-themes { display: none !important; }
	/* Grid, not core's float layout: the float widths hang off :nth-child(3n)
	   margins, which still count display:none cards, so client-side filtering
	   leaves holes mid-row. Grid reflows the survivors cleanly. */
	#untangling-theme-marketplace .untangling-tab-themes { clear: both; display: grid; grid-template-columns: repeat( 3, minmax( 0, 1fr ) ); column-gap: 4%; }
	/* Core's float grid puts 4%-of-container gutters on both axes. A grid
	   item's percentage margin resolves against its own grid area (~30.67%
	   of the container here), so the bottom margin is scaled per column
	   count to land back at 4% of the container: 4 / 30.67 ≈ 13%. */
	#untangling-theme-marketplace .theme-browser .theme { float: none; width: auto; margin: 0 0 13%; }
	@media ( max-width: 1120px ) {
		#untangling-theme-marketplace .untangling-tab-themes { grid-template-columns: repeat( 2, minmax( 0, 1fr ) ); }
		/* 2 columns: item ≈ 48% of container → 4 / 48 ≈ 8.33%. */
		#untangling-theme-marketplace .theme-browser .theme { margin-bottom: 8.33%; }
	}
	@media ( max-width: 480px ) {
		#untangling-theme-marketplace .untangling-tab-themes { grid-template-columns: 1fr; }
		#untangling-theme-marketplace .theme-browser .theme { margin-bottom: 4%; }
	}
	#untangling-theme-marketplace .untangling-filter-row { margin: 8px 0 24px; }
	#untangling-theme-marketplace .theme { cursor: pointer; }
	/* Plan-tier signal, like the tier pills on wordpress.com/themes. Always
	   visible (not hover-revealed) so the required plan reads upfront. */
	#untangling-theme-marketplace .untangling-tab-badge { position: absolute; top: 8px; inset-inline-end: 8px; z-index: 2; background: #fff; color: #1d2327; border-radius: 3px; padding: 4px 8px; font-size: 12px; line-height: 1.2; box-shadow: 0 1px 3px rgba(0,0,0,0.25); }
	#untangling-theme-marketplace .untangling-tab-badge.is-active-badge { background: #00a32a; color: #fff; }
	#untangling-theme-marketplace .untangling-tab-badge.is-tier-badge { background: #101517; color: #fff; }
	/* The Details & Preview pill mirrors core's .more-details under our own
	   class (core JS owns that name); the action buttons use core's real
	   .theme-id-container/.theme-actions structure and styling. */
	#untangling-theme-marketplace .untangling-tab-details { opacity: 0; position: absolute; top: 35%; inset-inline: 20%; width: 60%; box-sizing: border-box; z-index: 2; background: rgba(0,0,0,0.7); color: #fff; font-size: 15px; font-weight: 600; text-shadow: 0 1px 0 rgba(0,0,0,0.6); -webkit-font-smoothing: antialiased; text-align: center; text-decoration: none; border-radius: 3px; padding: 15px 12px; transition: opacity 0.1s ease-in-out; }
	/* Core-style theme preview overlay. The chrome rules are copied from
	   core's .theme-install-overlay styles, re-scoped to our ID so core's
	   Backbone preview and this overlay never touch each other. */
	body.untangling-overlay-open { overflow: hidden; }
	#untangling-theme-overlay { display: none; visibility: visible; }
	#untangling-theme-overlay.single-theme { display: block; }
	#untangling-theme-overlay iframe { height: 100%; width: 100%; z-index: 20; transition: opacity 0.3s; }
	#untangling-theme-overlay .wp-full-overlay-sidebar .wp-full-overlay-header { padding: 0; }
	#untangling-theme-overlay .close-full-overlay,
	#untangling-theme-overlay .previous-theme,
	#untangling-theme-overlay .next-theme { display: block; position: relative; float: left; width: 45px; height: 45px; background: #f0f0f1; border: 0; border-right: 1px solid #dcdcde; color: #3c434a; cursor: pointer; text-decoration: none; transition: color 0.1s ease-in-out, background 0.1s ease-in-out; }
	#untangling-theme-overlay .close-full-overlay:hover,
	#untangling-theme-overlay .close-full-overlay:focus,
	#untangling-theme-overlay .previous-theme:hover,
	#untangling-theme-overlay .previous-theme:focus,
	#untangling-theme-overlay .next-theme:hover,
	#untangling-theme-overlay .next-theme:focus { background: #dcdcde; border-color: #c3c4c7; color: #1e1e1e; outline: none; box-shadow: none; }
	#untangling-theme-overlay .close-full-overlay:before { font: normal 22px/1 dashicons; content: "\f335"; position: relative; top: 2px; }
	#untangling-theme-overlay .previous-theme:before { font: normal 20px/1 dashicons; content: "\f341"; position: relative; top: 2px; }
	#untangling-theme-overlay .next-theme:before { font: normal 20px/1 dashicons; content: "\f345"; position: relative; top: 2px; }
	#untangling-theme-overlay .previous-theme.disabled,
	#untangling-theme-overlay .next-theme.disabled { color: #c3c4c7; background: #f0f0f1; cursor: default; pointer-events: none; }
	#untangling-theme-overlay .install-theme-info { display: block; padding: 10px 20px 60px; }
	/* Core's .theme-install-overlay rules our container can't carry (the
	   class is dropped so Backbone leaves us alone): white content area, and
	   the 32px header button — pinned explicitly because the local admin
	   refresh inflates .wp-core-ui .button to a 40px min-height. */
	#untangling-theme-overlay .wp-full-overlay-sidebar { background: #f0f0f1; border-right: 1px solid #dcdcde; }
	#untangling-theme-overlay .wp-full-overlay-sidebar-content { background: #fff; border-top: 1px solid #dcdcde; border-bottom: 1px solid #dcdcde; }
	#untangling-theme-overlay .wp-full-overlay-header .theme-install { float: right; margin: 7px 10px 0 0; min-height: 32px; line-height: 2.30769231; font-size: 13px; padding: 0 10px; }
	#untangling-theme-overlay .theme-by { display: block; color: #646970; margin-top: 2px; }
	#untangling-theme-overlay .theme-screenshot:after { content: ""; display: block; padding-top: 66.66%; }
	#untangling-theme-overlay .theme-description { margin-top: 12px; line-height: 1.6; color: #50575e; }
	#untangling-theme-overlay .wp-full-overlay-main { background: #fff; }
	#untangling-theme-overlay .untangling-overlay-badge { display: inline-block; margin: 10px 0 0; background: #fff; color: #1d2327; border: 1px solid #dcdcde; border-radius: 3px; padding: 4px 8px; font-size: 12px; line-height: 1.2; }
	#untangling-theme-overlay .untangling-overlay-badge.is-active-badge { background: #00a32a; border-color: #00a32a; color: #fff; }
	#untangling-theme-overlay .untangling-overlay-badge.is-tier-badge { background: #101517; border-color: #101517; color: #fff; }
	#untangling-theme-marketplace .theme:hover .untangling-tab-details,
	#untangling-theme-marketplace .theme:focus-within .untangling-tab-details { opacity: 1; }
	#untangling-theme-marketplace .theme:focus-within .theme-actions { opacity: 1; }
	</style>
	<div id="untangling-theme-marketplace" hidden>
		<div class="untangling-filter-row">
			<ul class="subsubsub untangling-cat-filters"><?php echo implode( " |</li>\n", $items ) . '</li>'; ?></ul>
			<?php
			if ( 'included' === $plan_filter ) {
				untangling_plan_filter_links( count( $themes ), $included_count );
			} else {
				untangling_plan_filter_dropdown( array( 'Free', 'Personal', 'Premium', 'Business' ) );
			}
			?>
		</div>
		<div class="theme-browser rendered">
			<?php // Core's Installer view removes every `.themes` under .wrap at render — a custom grid class keeps the cards out of its reach (card CSS only needs .theme-browser). ?>
			<div class="untangling-tab-themes wp-clearfix">
				<?php
				$overlay_data = array();
				$card_idx     = 0;
				foreach ( $themes as $t ) {
					list( $slug, $name, $tier, $shot, $subject, $recommended, $price ) = $t;
					$tier_plan  = untangling_theme_tier_plan( $tier );
					$included   = $rank >= untangling_plan_rank( $tier_plan );
					$is_active  = $slug === $active_slug;
					$author     = isset( $details[ $slug ] ) ? $details[ $slug ][0] : 'Automattic';
					$demo       = isset( $details[ $slug ] ) ? $details[ $slug ][1] : '';
					$desc       = isset( $details[ $slug ] ) ? $details[ $slug ][2] : '';
					$detail_url = untangling_marketplace_url( 'themes', array( 'ustep' => 'details', 'slug' => $slug ) );
					$cta_url    = $included
						? add_query_arg( 'untangling_activate_theme', $slug, $tab_url )
						: untangling_marketplace_url( 'themes', array( 'ustep' => 'pricing', 'type' => 'theme', 'slug' => $slug ) );
					if ( $is_active ) {
						$tier_label  = '✓ ' . __( 'Active' );
						$badge_class = 'is-active-badge';
					} elseif ( $included ) {
						$tier_label  = __( 'Included with plan' );
						$badge_class = '';
					} elseif ( $price ) {
						$tier_label  = sprintf( __( 'US$%s/mo + %s plan' ), $price, $tier_plan );
						$badge_class = 'is-tier-badge';
					} else {
						$tier_label  = sprintf( __( '%s plan' ), $tier_plan );
						$badge_class = 'is-tier-badge';
					}
					$overlay_data[] = array(
						'name'   => $name,
						'author' => $author,
						'shot'   => $shot,
						'desc'   => $desc,
						// Showcase preview flags: WP.com demo sites hide the
						// masterbar and the "Get this theme" banner with these.
						'demo'   => $demo ? add_query_arg( array( 'demo' => 'true', 'iframe' => 'true', 'theme_preview' => 'true' ), $demo ) : '',
						'tier'   => $tier_label,
						'badge'  => $badge_class,
						'cta'    => $is_active ? '' : ( $included ? __( 'Install' ) : __( 'Upgrade' ) ),
						'ctaUrl' => $is_active ? '' : $cta_url,
						'gated'  => ! $is_active && ! $included,
					);
					?>
					<div class="theme" data-idx="<?php echo (int) $card_idx++; ?>" data-category="<?php echo esc_attr( $subject ); ?>" data-tier="<?php echo esc_attr( $tier_plan ); ?>" data-included="<?php echo $included ? '1' : ''; ?>" data-details="<?php echo esc_url( $detail_url ); ?>" tabindex="0">
						<span class="untangling-tab-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $tier_label ); ?></span>
						<div class="theme-screenshot"><img src="<?php echo esc_url( $shot ); ?>" alt="" decoding="async"></div>
						<a class="untangling-tab-details" href="<?php echo esc_url( $detail_url ); ?>"><?php esc_html_e( 'Details & Preview' ); ?></a>
						<div class="theme-author"><?php echo esc_html( sprintf( __( 'By %s' ), $author ) ); ?></div>
						<div class="theme-id-container">
							<h3 class="theme-name"><?php echo esc_html( $name ); ?></h3>
							<div class="theme-actions">
								<?php // WP 7.0 cards use the 32px compact size; default buttons are 40px and overflow the name plate. ?>
								<?php if ( ! $is_active ) : ?>
									<a class="button button-primary button-compact" href="<?php echo esc_url( $cta_url ); ?>"><?php echo ( $included ? '' : untangling_upsell_diamond() ) . esc_html( $included ? __( 'Install' ) : __( 'Upgrade' ) ); ?></a>
								<?php endif; ?>
								<?php if ( $demo ) : ?>
									<a class="button button-compact" href="<?php echo esc_url( $demo ); ?>" target="_blank" rel="noreferrer"><?php esc_html_e( 'Preview' ); ?></a>
								<?php else : ?>
									<a class="button button-compact" href="<?php echo esc_url( $detail_url ); ?>"><?php esc_html_e( 'Preview' ); ?></a>
								<?php endif; ?>
							</div>
						</div>
					</div>
					<?php
				}
				?>
			</div>
		</div>
	</div>
	<?php // No `theme-install-overlay` class here: core's Backbone preview renders into $('.theme-install-overlay') and would wipe this container's DOM. Its CSS is copied under the ID instead. ?>
	<div id="untangling-theme-overlay" class="wp-full-overlay expanded">
		<div class="wp-full-overlay-sidebar">
			<div class="wp-full-overlay-header">
				<button type="button" class="close-full-overlay"><span class="screen-reader-text"><?php esc_html_e( 'Close' ); ?></span></button>
				<button type="button" class="previous-theme"><span class="screen-reader-text"><?php esc_html_e( 'Previous theme' ); ?></span></button>
				<button type="button" class="next-theme"><span class="screen-reader-text"><?php esc_html_e( 'Next theme' ); ?></span></button>
				<a class="button button-primary theme-install" href="#"><?php esc_html_e( 'Install' ); ?></a>
			</div>
			<div class="wp-full-overlay-sidebar-content">
				<div class="install-theme-info">
					<h3 class="theme-name"></h3>
					<span class="theme-by"></span>
					<span class="untangling-overlay-badge"></span>
					<div class="theme-screenshot"><img src="" alt="" /></div>
					<div class="theme-details">
						<div class="theme-description"></div>
					</div>
				</div>
			</div>
			<div class="wp-full-overlay-footer">
				<button type="button" class="collapse-sidebar button" aria-expanded="true" aria-label="<?php esc_attr_e( 'Collapse Sidebar' ); ?>">
					<span class="collapse-sidebar-arrow"></span>
					<span class="collapse-sidebar-label"><?php esc_html_e( 'Collapse' ); ?></span>
				</button>
			</div>
		</div>
		<div class="wp-full-overlay-main"><iframe title="<?php esc_attr_e( 'Theme preview' ); ?>" src="about:blank"></iframe></div>
	</div>
	<script>
	( function () {
		var filterLinks = document.querySelector( '.wp-filter .filter-links' );
		var coreBrowser = document.querySelector( '.theme-browser.content-filterable' );
		var panel = document.getElementById( 'untangling-theme-marketplace' );
		if ( ! filterLinks || ! coreBrowser || ! panel ) {
			return;
		}
		// The footer-printed panel moves up next to core's browser (right
		// after the wpcom banner, which section 3 injected before it).
		coreBrowser.parentNode.insertBefore( panel, coreBrowser );

		var li = document.createElement( 'li' );
		var tab = document.createElement( 'a' );
		tab.href = <?php echo wp_json_encode( $tab_url ); ?>;
		tab.textContent = <?php echo wp_json_encode( __( 'Marketplace' ) ); ?>;
		li.appendChild( tab );
		var favorites = filterLinks.querySelector( '[data-sort="favorites"]' );
		filterLinks.insertBefore( li, favorites ? favorites.parentNode : null );

		var countEl = document.querySelector( '.wp-filter .theme-count' );
		var visibleCount = <?php echo (int) count( $themes ); ?>;
		function setCount() {
			if ( countEl && ! panel.hidden && countEl.textContent !== String( visibleCount ) ) {
				countEl.textContent = visibleCount;
			}
		}

		function setOpen( open ) {
			panel.hidden = ! open;
			document.body.classList.toggle( 'untangling-mkt-tab-open', open );
			tab.classList.toggle( 'current', open );
			if ( open ) {
				filterLinks.querySelectorAll( 'a' ).forEach( function ( link ) {
					if ( link !== tab ) {
						link.classList.remove( 'current' );
					}
				} );
				setCount();
			}
		}

		// Core rewrites the count after each async fetch; keep ours on top
		// while the tab is open (setCount only writes on a real difference,
		// so the observer cannot loop).
		if ( countEl ) {
			new MutationObserver( setCount ).observe( countEl, { childList: true, characterData: true, subtree: true } );
		}

		// Applying a Feature Filter is a wp.org search — hand back to core.
		document.addEventListener( 'click', function ( event ) {
			if ( ! panel.hidden && event.target.closest && event.target.closest( '.filter-drawer .apply-filters' ) ) {
				setOpen( false );
			}
		} );

		// Capture phase so core's Backbone sort handler never sees the click.
		tab.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			event.stopPropagation();
			setOpen( true );
			window.history.replaceState( null, '', tab.href );
		}, true );

		// Leaving the tab: any core sort link or a search restores the browser.
		filterLinks.addEventListener( 'click', function ( event ) {
			var link = event.target.closest ? event.target.closest( 'a' ) : null;
			if ( link && link !== tab && ! panel.hidden ) {
				setOpen( false );
			}
		} );
		var search = document.getElementById( 'wp-filter-search-input' );
		if ( search ) {
			search.addEventListener( 'input', function () {
				if ( ! panel.hidden ) {
					setOpen( false );
				}
			} );
		}

		if ( -1 !== window.location.search.indexOf( 'untangling_browse=marketplace' ) ) {
			setOpen( true );
			// Core's router runs at DOM-ready (after this inline script) and
			// re-marks Featured as current; re-assert once everything settled.
			window.addEventListener( 'load', function () {
				if ( ! panel.hidden ) {
					setOpen( true );
				}
			} );
		}

		// Core-style preview overlay: the card body, the Details & Preview
		// pill and the Preview button all open it; Install/Upgrade (the only
		// primary button) keeps navigating to its flow.
		var overlayData = <?php echo wp_json_encode( $overlay_data ); ?>;
		var overlay = document.getElementById( 'untangling-theme-overlay' );
		var overlayIdx = 0;
		var ov = {
			name: overlay.querySelector( '.theme-name' ),
			by: overlay.querySelector( '.theme-by' ),
			badge: overlay.querySelector( '.untangling-overlay-badge' ),
			shot: overlay.querySelector( '.theme-screenshot img' ),
			desc: overlay.querySelector( '.theme-description' ),
			cta: overlay.querySelector( '.theme-install' ),
			frame: overlay.querySelector( 'iframe' ),
			prev: overlay.querySelector( '.previous-theme' ),
			next: overlay.querySelector( '.next-theme' )
		};
		function visibleIndexes() {
			var list = [];
			cards.forEach( function ( card ) {
				if ( 'none' !== card.style.display ) {
					list.push( parseInt( card.dataset.idx, 10 ) );
				}
			} );
			return list;
		}
		function overlayRender() {
			var d = overlayData[ overlayIdx ];
			ov.name.textContent = d.name;
			ov.by.textContent = 'By ' + d.author;
			ov.badge.textContent = d.tier;
			ov.badge.className = 'untangling-overlay-badge' + ( d.badge ? ' ' + d.badge : '' );
			ov.shot.src = d.shot;
			ov.desc.textContent = d.desc;
			if ( d.cta ) {
				// d.cta is a fixed i18n label; the diamond marks gated tiers,
				// matching the card CTAs.
				ov.cta.innerHTML = ( d.gated ? <?php echo wp_json_encode( untangling_upsell_diamond() ); ?> : '' ) + d.cta;
				ov.cta.href = d.ctaUrl;
				ov.cta.style.display = '';
			} else {
				ov.cta.style.display = 'none';
			}
			ov.frame.src = d.demo || 'about:blank';
			var list = visibleIndexes();
			var pos = list.indexOf( overlayIdx );
			ov.prev.classList.toggle( 'disabled', pos <= 0 );
			ov.next.classList.toggle( 'disabled', -1 === pos || pos >= list.length - 1 );
		}
		function openOverlay( idx ) {
			overlayIdx = idx;
			overlayRender();
			overlay.classList.add( 'single-theme' );
			document.body.classList.add( 'untangling-overlay-open' );
		}
		function closeOverlay() {
			overlay.classList.remove( 'single-theme' );
			ov.frame.src = 'about:blank';
			document.body.classList.remove( 'untangling-overlay-open' );
		}
		function stepOverlay( dir ) {
			var list = visibleIndexes();
			var target = list[ list.indexOf( overlayIdx ) + dir ];
			if ( 'undefined' !== typeof target ) {
				overlayIdx = target;
				overlayRender();
			}
		}
		overlay.querySelector( '.close-full-overlay' ).addEventListener( 'click', closeOverlay );
		ov.prev.addEventListener( 'click', function () { stepOverlay( -1 ); } );
		ov.next.addEventListener( 'click', function () { stepOverlay( 1 ); } );
		overlay.querySelector( '.collapse-sidebar' ).addEventListener( 'click', function () {
			var collapsed = overlay.classList.toggle( 'collapsed' );
			overlay.classList.toggle( 'expanded', ! collapsed );
			this.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
		} );
		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && overlay.classList.contains( 'single-theme' ) ) {
				closeOverlay();
			}
		} );
		panel.addEventListener( 'click', function ( event ) {
			var card = event.target.closest ? event.target.closest( '.theme' ) : null;
			if ( ! card || ! card.dataset.idx ) {
				return;
			}
			var link = event.target.closest( 'a' );
			if ( link && link.classList.contains( 'button-primary' ) ) {
				return;
			}
			if ( link || ! event.target.closest( '.untangling-cat-filters, .untangling-plan-filters' ) ) {
				event.preventDefault();
				openOverlay( parseInt( card.dataset.idx, 10 ) );
			}
		} );

		// Category + plan filters — same client-side swap as the plugins tab;
		// the filter-bar counter tracks the combined result.
		var catLinks = panel.querySelectorAll( '.untangling-cat-filters a' );
		var planLinks = panel.querySelectorAll( '.untangling-plan-filters a' );
		var planSelect = panel.querySelector( '[data-plan-filter]' );
		var cards = panel.querySelectorAll( '.theme' );
		var category = 'all';
		var planChoice = 'all';
		function catOk( card, key ) {
			return 'all' === key || card.dataset.category === key;
		}
		function planOk( card, choice ) {
			return 'all' === choice || ( 'included' === choice ? !! card.dataset.included : card.dataset.tier === choice );
		}
		// Counts follow the other filter, so every click visibly changes the
		// row even when the surviving cards happen to lead the grid.
		function refreshCounts() {
			catLinks.forEach( function ( link ) {
				var n = 0;
				cards.forEach( function ( card ) { if ( catOk( card, link.dataset.category ) && planOk( card, planChoice ) ) { n++; } } );
				var count = link.querySelector( '.count' );
				if ( count ) { count.textContent = '(' + n + ')'; }
			} );
			planLinks.forEach( function ( link ) {
				var n = 0;
				cards.forEach( function ( card ) { if ( catOk( card, category ) && planOk( card, link.dataset.plan ) ) { n++; } } );
				var count = link.querySelector( '.count' );
				if ( count ) { count.textContent = '(' + n + ')'; }
			} );
		}
		function applyFilters() {
			var shown = 0;
			cards.forEach( function ( card ) {
				var match = catOk( card, category ) && planOk( card, planChoice );
				card.style.display = match ? '' : 'none';
				if ( match ) {
					shown++;
				}
			} );
			visibleCount = shown;
			setCount();
			refreshCounts();
		}
		catLinks.forEach( function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				category = link.dataset.category;
				catLinks.forEach( function ( other ) {
					other.classList.toggle( 'current', other === link );
					if ( other === link ) { other.setAttribute( 'aria-current', 'page' ); } else { other.removeAttribute( 'aria-current' ); }
				} );
				applyFilters();
			} );
		} );
		planLinks.forEach( function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				planChoice = link.dataset.plan;
				planLinks.forEach( function ( other ) {
					other.classList.toggle( 'current', other === link );
					if ( other === link ) { other.setAttribute( 'aria-current', 'page' ); } else { other.removeAttribute( 'aria-current' ); }
				} );
				applyFilters();
			} );
		} );
		if ( planSelect ) {
			planSelect.addEventListener( 'change', function () {
				planChoice = planSelect.value;
				applyFilters();
			} );
		}
	} )();
	</script>
	<?php
}, 11 );

/* -------------------------------------------------------------------------
 * 3b. Unified upgrade overlay (placeholder for the opinionated-checkout project).
 * Every upgrade CTA (class `untangling-upgrade`) opens this same overlay
 * instead of dumping the user somewhere else — the opinionated-flow idea.
 * ---------------------------------------------------------------------- */

add_action( 'admin_footer', function () {
	$msd     = UNTANGLING_MSD_URL;
	$current = untangling_get_plan();
	$plans   = array(
		array( 'Premium', '€8', __( 'Premium themes, monetization' ) ),
		array( 'Business', '€25', __( 'Plugins, SFTP/SSH, database' ) ),
		array( 'Commerce', '€45', __( 'Sell products, store extensions' ) ),
	);
	?>
	<div id="untangling-overlay" hidden>
		<div class="untangling-overlay-backdrop"></div>
		<div class="untangling-overlay-modal" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Choose a plan' ); ?>">
			<button type="button" class="untangling-overlay-close" aria-label="<?php esc_attr_e( 'Close' ); ?>">&times;</button>
			<h1><?php esc_html_e( 'Choose a plan' ); ?></h1>
			<p class="untangling-overlay-note"><?php esc_html_e( 'Prototype placeholder: the unified plans → domains → checkout overlay. Every upgrade CTA in WP Admin and the Hosting Dashboard opens this same flow.' ); ?></p>
			<div class="untangling-overlay-grid">
				<?php foreach ( $plans as $plan ) : list( $name, $price, $blurb ) = $plan; ?>
					<div class="untangling-overlay-plan<?php echo $name === $current ? ' is-current' : ''; ?>">
						<h2><?php echo esc_html( $name ); ?></h2>
						<p class="untangling-overlay-price"><?php echo esc_html( $price ); ?><span>/<?php esc_html_e( 'month' ); ?></span></p>
						<p class="untangling-meta"><?php echo esc_html( $blurb ); ?></p>
						<?php if ( $name === $current ) : ?>
							<span class="button" disabled><?php esc_html_e( 'Your plan' ); ?></span>
						<?php else : ?>
							<?php // /plans redirects logged-out to wordpress.com/pricing on the dev server; billing renders. ?>
							<a class="button button-primary" href="<?php echo esc_url( $msd . '/me/billing' ); ?>"><?php esc_html_e( 'Select' ); ?></a>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	<script>
	// Every MSD-bound link carries the wp-admin page it was clicked on
	// (stamped at click time), so the MSD "Back to site" card returns here.
	( function () {
		var msd = <?php echo wp_json_encode( UNTANGLING_MSD_URL ); ?>;
		document.addEventListener( 'click', function ( event ) {
			var link = event.target.closest ? event.target.closest( 'a[href]' ) : null;
			if ( ! link || 0 !== link.href.indexOf( msd ) ) {
				return;
			}
			var url = new URL( link.href );
			url.searchParams.set( 'ref', 'wp-admin' );
			url.searchParams.set( 'back', window.location.href );
			link.href = url.toString();
		}, true );
	} )();
	( function () {
		var overlay = document.getElementById( 'untangling-overlay' );
		if ( ! overlay ) {
			return;
		}
		document.addEventListener( 'click', function ( event ) {
			// Sidebar "Plans" opens the same overlay: /plans has no logged-in
			// landing on the local dev server (it 302s to wordpress.com/pricing).
			// CTAs that point at the fullscreen upgrade flow (Marketplace
			// pricing/checkout) navigate there instead of opening the overlay.
			var trigger = event.target.closest( '.untangling-upgrade, #adminmenu a[href$="/plans"], #wpadminbar a[href$="/plans"]' );
			if ( trigger && trigger.href && -1 !== trigger.href.indexOf( 'page=untangling-marketplace' ) ) {
				return;
			}
			if ( trigger ) {
				event.preventDefault();
				overlay.hidden = false;
				return;
			}
			if ( event.target.closest( '.untangling-overlay-close' ) || event.target.classList.contains( 'untangling-overlay-backdrop' ) ) {
				overlay.hidden = true;
			}
		} );
		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				overlay.hidden = true;
			}
		} );
	} )();
	</script>
	<?php
} );

/* -------------------------------------------------------------------------
 * 3c. Marketplace — three switchable versions (Prototype controls):
 *   fullscreen (V1): chromeless Marketplace page for themes + plugins,
 *     entered from Appearance/Plugins → Marketplace and the WP.com banners.
 *   split (V2): plugins keep the core-unified Marketplace tab (section 2);
 *     themes render in the wp-admin content area (section 3f).
 *   tabs (V3): fully in-admin — plugins keep the tab (section 2), themes get
 *     their own Marketplace tab on Add Themes (section 3e), both banners
 *     upsell plans; this page only serves details/pricing/checkout steps.
 * Shell metrics/typography lifted from the production onboarding stepper
 * (step-container-v2: 56px top bar, Recoleta 32→44px headings, 1344px grid,
 * checkout 8+4 columns); catalog design from wordpress.com/themes and
 * wordpress.com/plugins (shared 1220px content width).
 * ---------------------------------------------------------------------- */

// The production WP.com plugins banner (jetpack-mu-wpcom wpcom-plugins) —
// markup, copy and styles copied like the themes banner in section 3, CTA
// pointed at the fullscreen Marketplace. V1 only; V2 keeps the tab instead.
function untangling_plugins_banner() {
	if ( 'fullscreen' !== untangling_get_marketplace_mode() ) {
		return;
	}
	$assets = 'https://cdn.jsdelivr.net/gh/Automattic/jetpack@f319779638296460446adc163ff042ef57789b15/projects/packages/jetpack-mu-wpcom/src/features/wpcom-plugins/images';
	?>
	<style>
	@font-face {
		font-display: swap;
		font-family: Recoleta;
		font-weight: 400;
		src: url(https://s1.wp.com/i/fonts/recoleta/400.woff2) format("woff2"), url(https://s1.wp.com/i/fonts/recoleta/400.woff) format("woff");
	}
	.wpcom-plugins-banner {
		background-color: #242424;
		padding: 64px 32px;
		border-radius: 10px;
		margin: 25px 0;
		background-image: url(<?php echo esc_url( $assets . '/banner-background.webp' ); ?>);
		background-repeat: no-repeat;
		background-position: bottom 12px right 64px;
		background-size: 530px;
	}
	.wpcom-plugins-banner.hidden { display: none; }
	.wpcom-plugins-banner__content { width: 540px; }
	/* The wpcom-plugins logo SVG ships with no width/height attributes (only
	   a 139.9x21 viewBox), so an unstyled <img> inflates to the content
	   width; pin it to the same 21px lockup height as the themes banner. */
	.wpcom-plugins-banner__content img { height: 21px; width: auto; display: block; }
	#wpcontent .wpcom-plugins-banner h3,
	#wpcontent .wpcom-plugins-banner p { font-weight: 400; letter-spacing: -0.32px; margin: 10px 0; text-wrap: pretty; }
	.wpcom-plugins-banner h3 { font-family: Recoleta, serif; font-size: 32px; line-height: 40px; color: #fff; }
	.wpcom-plugins-banner p { font-size: 16px; line-height: 24px; color: #a7aaad; }
	.wpcom-plugins-banner a,
	.wpcom-plugins-banner a:visited { background-color: #3858e9; color: #fff; border-radius: 4px; padding: 10px 24px; font-size: 14px; line-height: 20px; letter-spacing: 0.32px; text-decoration: none; display: inline-block; margin-top: 32px; }
	.wpcom-plugins-banner a:hover,
	.wpcom-plugins-banner a:focus { background-color: #fff; color: #1d2327; }
	@media ( max-width: 1260px ) {
		.wpcom-plugins-banner { padding: 32px; background-size: 400px; }
		.wpcom-plugins-banner a { padding: 10px 20px; margin-top: 12px; }
	}
	@media ( max-width: 1120px ) {
		.wpcom-plugins-banner { background-position: bottom right 5px; background-size: 300px; }
	}
	@media ( max-width: 850px ) {
		.wpcom-plugins-banner { background-image: none; }
		.wpcom-plugins-banner__content { width: auto; }
	}
	@media ( max-width: 782px ) {
		.wpcom-plugins-banner { padding: 24px; }
		.wpcom-plugins-banner h3,
		.wpcom-plugins-banner p { margin: 8px 0; }
		.wpcom-plugins-banner h3 { font-size: 24px; line-height: 32px; }
		.wpcom-plugins-banner p { font-size: 14px; line-height: 20px; }
	}
	</style>
	<script>
	( function () {
		// plugin-install.php has #plugin-filter (banner goes before it, like
		// production); plugins.php gets it after the page-title rule instead.
		var filter = document.querySelector( '#plugin-filter' );
		var anchor = filter || document.querySelector( '.wp-header-end' );
		if ( ! anchor ) {
			return;
		}
		anchor.insertAdjacentHTML(
			filter ? 'beforebegin' : 'afterend',
			'<div class="wpcom-plugins-banner">' +
				'<div class="wpcom-plugins-banner__content">' +
					'<img src="<?php echo esc_url( $assets . '/wpcom-logo.svg' ); ?>" alt="WordPress.com">' +
					'<h3><?php echo esc_js( __( 'Plug into possibilities' ) ); ?></h3>' +
					'<p><?php echo esc_js( __( 'Discover a curated selection of plugins that add new features to your site, like SEO tools, newsletters, and payments.' ) ); ?></p>' +
					'<a href="<?php echo esc_url( untangling_marketplace_url( 'plugins', array( 'ref' => 'wpcom-plugins-banner' ) ) ); ?>"><?php echo esc_js( __( 'Explore plugins' ) ); ?></a>' +
				'</div>' +
			'</div>'
		);
		if ( ! filter ) {
			return;
		}
		// Production behavior: hide the banner while a search is active.
		var banner = document.querySelector( '.wpcom-plugins-banner' );
		var observer = new MutationObserver( function () {
			if ( ! document.querySelector( '.plugin-install-search .current' ) || document.querySelector( '.no-plugin-results' ) ) {
				banner.classList.remove( 'hidden' );
			} else {
				banner.classList.add( 'hidden' );
			}
		} );
		observer.observe( filter, { childList: true } );
	} )();
	</script>
	<?php
}
add_action( 'admin_footer-plugins.php', 'untangling_plugins_banner' );
add_action( 'admin_footer-plugin-install.php', 'untangling_plugins_banner' );

// Curated theme catalog — real WP.com themes (names, tiers and screenshots
// from public-api.wordpress.com/wpcom/v2/themes; partner prices mocked).
// slug, name, tier (free|personal|premium|partner), screenshot, subject,
// recommended flag, monthly price (partner themes only).
function untangling_marketplace_themes() {
	$s = 'https://i0.wp.com/s2.wp.com/wp-content/themes/pub/';
	return array(
		array( 'archivist', 'The Archivist', 'free', $s . 'archivist/screenshot.png?ssl=1&w=640', 'blog', true, null ),
		array( 'retrospect', 'Retrospect', 'free', $s . 'retrospect/screenshot.png?ssl=1&w=640', 'photography', true, null ),
		array( 'primarium', 'Primarium', 'free', $s . 'primarium/screenshot.png?ssl=1&w=640', 'blog', false, null ),
		array( 'golazo', 'Golazo', 'personal', $s . 'golazo/screenshot.jpg?ssl=1&w=640', 'blog', true, null ),
		array( 'noteslab', 'NotesLab', 'personal', $s . 'noteslab/screenshot.jpg?ssl=1&w=640', 'blog', true, null ),
		array( 'parr', 'Parr', 'personal', $s . 'parr/screenshot.png?ssl=1&w=640', 'photography', true, null ),
		array( 'stijl', 'Stijl', 'personal', $s . 'stijl/screenshot.png?ssl=1&w=640', 'portfolio', true, null ),
		array( 'sankofa', 'Sankofa', 'personal', $s . 'sankofa/screenshot.jpg?ssl=1&w=640', 'blog', false, null ),
		array( 'moire', 'Moire', 'personal', $s . 'moire/screenshot.png?ssl=1&w=640', 'blog', false, null ),
		array( 'crafted', 'Crafted', 'personal', $s . 'crafted/screenshot.png?ssl=1&w=640', 'portfolio', true, null ),
		array( 'eventure', 'Eventure', 'personal', $s . 'eventure/screenshot.png?ssl=1&w=640', 'business', false, null ),
		array( 'punk', 'Punk', 'personal', $s . 'punk/screenshot.png?ssl=1&w=640', 'blog', false, null ),
		array( 'clairevoyant', 'Clairevoyant', 'personal', $s . 'clairevoyant/screenshot.png?ssl=1&w=640', 'blog', false, null ),
		array( 'substrata', 'Substrata', 'personal', $s . 'substrata/screenshot.png?ssl=1&w=640', 'portfolio', false, null ),
		array( 'nouvelle', 'Nouvelle', 'personal', $s . 'nouvelle/screenshot.png?ssl=1&w=640', 'blog', false, null ),
		array( 'auriel', 'Auriel', 'personal', $s . 'auriel/screenshot.png?ssl=1&w=640', 'business', false, null ),
		array( 'lente', 'Lente', 'premium', $s . 'lente/screenshot.png?ssl=1&w=640', 'blog', true, null ),
		array( 'hvacool', 'HVACool', 'premium', $s . 'hvacool/screenshot.png?ssl=1&w=640', 'business', false, null ),
		array( 'launchit', 'Launchit', 'premium', $s . 'launchit/screenshot.png?ssl=1&w=640', 'business', true, null ),
		array( 'patisserie', 'Patisserie', 'premium', $s . 'patisserie/screenshot.png?ssl=1&w=640', 'business', false, null ),
		array( 'organic-stax', 'STAX', 'partner', 'https://theme.files.wordpress.com/2023/02/stax-featured.jpg?w=640', 'business', true, '9.00' ),
		array( 'solarone', 'SolarOne', 'partner', 'https://theme.files.wordpress.com/2023/01/solarone-feature.png?w=640', 'business', false, '8.00' ),
		array( 'natural-block', 'Natural Block', 'partner', 'https://theme.files.wordpress.com/2023/02/natural-featured.jpeg?w=640', 'store', false, '7.00' ),
		array( 'macchiato', 'Macchiato', 'partner', 'https://theme.files.wordpress.com/2023/03/macchiato-thumb.jpeg?w=640', 'store', false, '8.00' ),
		array( 'bagberry', 'BagBerry', 'partner', 'https://theme.files.wordpress.com/2023/03/bagberry-thumb.jpeg?w=640', 'store', true, '9.00' ),
		array( 'portfolio-wp-pro', 'Portfolio WP Pro', 'partner', 'https://i0.wp.com/theme.wordpress.com/wp-content/uploads/2024/10/screenshot-1.png?ssl=1&w=640', 'portfolio', false, '6.00' ),
	);
}

// Theme tier → plan that includes it (partner themes also carry a price).
function untangling_theme_tier_plan( $tier ) {
	$map = array( 'free' => 'Free', 'personal' => 'Personal', 'premium' => 'Premium', 'partner' => 'Business' );
	return isset( $map[ $tier ] ) ? $map[ $tier ] : 'Business';
}

// Real per-theme details from the public API (rest/v1.2/themes/{slug}):
// author, demo URL, full description, top feature tags. Used by the theme
// details step; harvested 2026-07-31.
function untangling_marketplace_theme_details() {
	return array(
		'archivist' => array( 'Automattic', 'https://archivistthemedemo.wordpress.com/', 'The Archivist is a typewriter-inspired theme designed for meticulous storytellers and memory-keepers of all kinds. Its subtle design emphasizes structure and clarity, making it ideal for collections of letters, field notes, or essays.', array( 'Full Site Editing', 'RTL Language Support', 'Translation ready', 'Threaded Comments', 'Style Variations' ) ),
		'retrospect' => array( 'Automattic', 'https://retrospectdemo.wordpress.com/', 'A theme for casual photographers capturing the beauty of everyday life.', array( 'Full Site Editing', 'Block Themes', 'RTL Language Support', 'Featured Images', 'Block Editor Styles' ) ),
		'primarium' => array( 'Automattic', 'https://primariumdemo.wordpress.com/', 'This theme is a text-first notebook that showcases handwriting-inspired typography through poetic, personal writing with a clean reading flow. Inspired by the Primarium project—an open-source handwriting system by TypeTogether on Google Fonts that models primary education handwriting—it links design with pedagogical research. It suits writers who favor reflective, poetic, and personal content over traditional blog posts.', array( 'Full Site Editing', 'Block Themes', 'RTL Language Support', 'Threaded Comments', 'Style Variations' ) ),
		'golazo' => array( 'Automattic', 'https://golazodemo.wordpress.com/', 'Golazo is a blog theme with the energy of the beautiful game. Its unique 50:50 split layout pairs a floodlit pitch with your stories—one half stadium, one half page.', array( 'Full Site Editing', 'Block Themes', 'RTL Language Support', 'Threaded Comments', 'Style Variations' ) ),
		'noteslab' => array( 'Automattic', 'https://noteslabdemo.wordpress.com/', 'A theme designed for academics, researchers, and students that facilitates posts, long-form publications, and structured project pages, with minimal dependence on feature images.', array( 'Full Site Editing', 'Block Themes', 'RTL Language Support', 'Threaded Comments', 'Style Variations' ) ),
		'parr' => array( 'Automattic', 'https://parrdemo.wordpress.com/', 'Parr is a portfolio theme tailored for photographers. Inspired by the bold, documentary energy of Martin Parr, it puts imagery front and centre with clean layouts, space for captions, and visual storytelling. Designed to feel editorial and minimal, Parr balances simplicity with character — allowing saturated photographs and ironic details to stand out without distraction. Whether showcasing long-form projects, individual images, or curated series, the theme offers a structured yet flexible canvas for contemporary documentary and street photographers who want their work to feel honest, vibrant, and engaging.', array( 'Full Site Editing', 'Block Themes', 'RTL Language Support', 'Translation ready', 'Threaded Comments' ) ),
		'stijl' => array( 'Automattic', 'https://stijldemo.wordpress.com/', 'Stijl is a theme built on combining a strict modular grid with bold typography, strong rule lines, and primary-color accents to make structure visible on each page. Use it to publish essays, interviews, and case studies with clear hierarchy, curated series, and archive-first navigation—clean, fast, and unapologetically graphic.', array( 'Full Site Editing', 'Block Themes', 'RTL Language Support', 'Threaded Comments', 'Style Variations' ) ),
		'sankofa' => array( 'Automattic', 'https://sankofademo.wordpress.com/', 'Sankofa is an Afrofuturist-inspired theme that links heritage and horizons. It serves as a frame for storytellers and cultural thinkers exploring the connections between ancestry and the future.', array( 'Full Site Editing', 'Block Themes', 'RTL Language Support', 'Translation ready', 'Threaded Comments' ) ),
		'moire' => array( 'Automattic', 'https://moiredemo.wordpress.com/', 'Moire is a theme for sartorial storytellers — writers, editors, and thinkers who want their words to stand out with clarity and conviction. Its design is bold yet minimal, contemporary yet timeless: thick, confident sans-serif headings paired with an elegant serif body font create a striking visual rhythm that feels unmistakably editorial. Built on a clean white canvas, Moire removes noise and lets your content take center stage — whether you’re dissecting runway silhouettes, analyzing tailoring, or sharing personal style essays. Every layout is intentional, refined, and opinionated, giving your writing the same presence as a magazine spread. Moire also includes dedicated “link in bio” patterns, perfect for highlighting your latest stories, collaborations, or social channels in a streamlined, visual format. Designed for writers who value structure, tone, and visual impact, Moire turns your blog into a modern editorial destination.', array( 'Full Site Editing', 'Block Themes', 'RTL Language Support', 'Translation ready', 'Threaded Comments' ) ),
		'crafted' => array( 'Automattic', 'https://crafteddemo.wordpress.com/', 'Crafted is a theme designed for creators who want to share more than just their work. Whether you’re an author, coach, or creative guide, it helps you publish stories, promote your publications, and offer tools or services that support your audience.', array( 'Full Site Editing', 'RTL Language Support', 'Translation ready', 'Threaded Comments', 'Style Variations' ) ),
		'eventure' => array( 'Automattic', 'https://eventuredemo.wordpress.com/', 'Eventure is a sleek, modern theme designed for events. It emphasizes participants, speakers, and stories, helping you create an engaging event hub where people and the environment take center stage.', array( 'Full Site Editing', 'Block Themes', 'RTL Language Support', 'Translation ready', 'Threaded Comments' ) ),
		'punk' => array( 'Automattic', 'https://punkdemo.wordpress.com/', 'Punk is a theme for the ones who won’t behave. Made for bands, musicians, and bloggers who’d rather break the rules than follow them. The built-in halftone imagery pushes a scruffy, unapologetic aesthetic inspired by photocopied flyers and torn-up zines.', array( 'Full Site Editing', 'Block Themes', 'RTL Language Support', 'Translation ready', 'Threaded Comments' ) ),
		'clairevoyant' => array( 'Automattic', 'https://clairevoyantdemo.wordpress.com/', 'Clairevoyant is an opinionated WordPress block theme designed for mystics, intuitives, and those who write at the edge of the seen and unseen. Clairevoyant balances modern clarity with esoteric softness. Minimal but evocative, the layout uses grids and typographic contrast. Post titles are front and center — bold yet lyrical — with plenty of breathing room for writing to unfold. Ideal for essays, reflections, and healing offerings, this theme invites a slower scroll and a deeper read. It’s fully block-based and responsive by design.', array( 'Full Site Editing', 'Block Themes', 'Featured Images' ) ),
		'substrata' => array( 'Automattic', 'https://substratademo.wordpress.com/', 'Substrata is a minimalist blog or portfolio designed for researchers, writers, and storytellers working with field notes and reflective essays.', array( 'Full Site Editing', 'RTL Language Support', 'Translation ready', 'Threaded Comments', 'Style Variations' ) ),
		'nouvelle' => array( 'Automattic', 'https://nouvelledemo.wordpress.com/', 'Nouvelle is a blog theme inspired by the flowing lines and floral elegance of Art Nouveau. It combines ornamental detail with modern readability, making it ideal for cultural enthusiasts: poetic, decorative, and timeless.', array( 'Full Site Editing', 'Threaded Comments', 'Style Variations' ) ),
		'auriel' => array( 'Automattic', 'https://aurieldemo.wordpress.com/', 'Auriel is a refined and versatile block theme for weddings. It includes a curated collection of patterns that make it effortless for couples to create their own wedding website — from ceremony details to travel information and frequently asked questions.', array( 'Full Site Editing', 'Block Themes', 'RTL Language Support', 'Translation ready', 'Threaded Comments' ) ),
		'lente' => array( 'Automattic', 'https://lentedemo.wordpress.com/', 'Lente is a magazine theme designed with a clean, flexible layout that adapts effortlessly to a wide range of topics, with a strong focus on imagery. It offers thoughtfully designed single post templates, available both with and without a sidebar, alongside an expressive header section that gives each article a strong introduction. Modern and vibrant in character, Lente pairs a monospaced font with a refined sans-serif typeface to ensure excellent readability while adding subtle personality to the design. A set of carefully curated color variations allows you to adjust the tone of your publication, making it easy to shape a look that feels distinctly your own.', array( 'Full Site Editing', 'Block Themes', 'RTL Language Support', 'Translation ready', 'Threaded Comments' ) ),
		'hvacool' => array( 'Automattic', 'https://hvacooldemo.wordpress.com/', 'HVACool is a bold theme for heating and cooling businesses. Built for fast service requests, it highlights emergency assistance, core services, maintenance plans, and service areas, using clear typography and reusable patterns. It is perfect for contractors who need a credible site that works day and night.', array( 'Full Site Editing', 'Block Themes', 'RTL Language Support', 'Threaded Comments', 'Style Variations' ) ),
		'launchit' => array( 'Automattic', 'https://launchitdemo.wordpress.com/', 'This theme is designed to showcase a single product, plugin, or digital service. Featuring large typography, plenty of spacing, and an elegant visual rhythm, it’s perfect for landing pages that aim to build confidence and guide visitors toward action. Whether you’re launching a plugin, highlighting a development tool, or simply introducing your latest creation to the world, this theme helps you present it beautifully.', array( 'Full Site Editing', 'Threaded Comments', 'Style Variations' ) ),
		'patisserie' => array( 'Automattic', 'https://patisseriedemo.wordpress.com/', 'A bold and charming WordPress theme crafted for local businesses with a strong visual identity — perfect for bakeries, cafés, florists, and neighborhood shops.', array( 'Full Site Editing', 'Style Variations' ) ),
		'organic-stax' => array( 'Organic Themes', 'https://stax.organicthemes.com/', 'STAX is a premium block theme for the WordPress full-site editor. The design is clean, versatile, and totally customizable. Additionally, the setup wizard provides a super simple installation process — so your site will appear exactly as the demo within moments of activation. ', array( 'Full Site Editing', 'Block Themes', 'RTL Language Support', 'Full Width Template', 'Featured Images' ) ),
		'solarone' => array( 'ElmaStudio', 'https://themes.ainoblocks.io/solarone/', 'SolarOne is a fresh, minimal, and professional WordPress block theme. This theme is suitable for corporate business websites or agencies, freelancers and small startups.', array( 'Full Width Template', 'Featured Images', 'Block Editor Styles' ) ),
		'natural-block' => array( 'Organic Themes', 'https://organicthemes.com/demo/natural-block/', 'Whether you’re providing fishing charters or surf adventures, promoting local farmers markets or saving the whales, offering vegan cooking tips or selling organic lip balm — the Natural theme is a natural choice for your WordPress website.', array( 'Full Site Editing', 'Block Themes', 'RTL Language Support', 'Full Width Template', 'Featured Images' ) ),
		'macchiato' => array( 'AgniHD', 'https://demo.agnidesigns.com/macchiato/', 'The Macchiato theme is ideal for artisan storefronts. You offer exceptional products you make with care. Showcase your products in a theme designed for stores with a smaller inventory that changes regularly.', array( 'Full Site Editing', 'Block Themes', 'RTL Language Support', 'Featured Images', 'Block Editor Styles' ) ),
		'bagberry' => array( 'AgniHD', 'https://demo.agnidesigns.com/bagberry/', 'Attract customers to your store with the chic Bagberry theme. If you sell purses, handbags, clothing, jewelry, or accessories, Bagberry is the perfect theme to show them off.', array( 'Full Site Editing', 'Block Themes', 'RTL Language Support', 'Featured Images', 'Block Editor Styles' ) ),
		'portfolio-wp-pro' => array( 'Press75', 'https://portfoliowp.com/demo/', 'PortfolioWP is a powerful and fully customizable WordPress block theme designed for artists, designers, photographers, and creatives to professionally showcase their work. With 60 pre-designed patterns and 13 full-page templates, you can quickly build a stunning portfolio site with just a few clicks. The theme\'s minimal and ultra-clean design adapts seamlessly to mobile devices, ensuring a delightful user experience on any screen. Plus, PortfolioWP is fully compatible with WooCommerce, allowing you to easily sell your art, prints, and merchandise online. Whether you\'re building a personal portfolio or a full-fledged online store, PortfolioWP provides the tools you need to bring your vision to life.', array( 'Block Themes', 'RTL Language Support', 'Translation ready', 'Style Variations', 'Full Width Template' ) ),
	);
}

function untangling_marketplace_find_item( $type, $slug ) {
	if ( 'plugin' === $type ) {
		foreach ( untangling_marketplace_plugins() as $p ) {
			if ( $p[0] === $slug ) {
				return array( 'name' => $p[1], 'image' => $p[4], 'tier' => $p[5], 'price' => $p[11] );
			}
		}
	} else {
		foreach ( untangling_marketplace_themes() as $t ) {
			if ( $t[0] === $slug ) {
				return array( 'name' => $t[1], 'image' => $t[3], 'tier' => untangling_theme_tier_plan( $t[2] ), 'price' => $t[6] );
			}
		}
	}
	return null;
}

// Monthly US$ prices + short feature lists for the pricing step (current
// plan and higher tiers only, per the opinionated-flows brief).
// Cumulative production-style lists (wordpress.com/pricing): each tier
// repeats what the tier below offers and adds its own, so higher tiers
// visibly list more. Entries are [ label, tooltip ].
// Storage add-on tiers — GB => US$/month billed yearly, mirroring the
// production Add-ons page dropdown.
function untangling_storage_addon_pricing() {
	return array( 50 => 50.00, 100 => 83.33, 150 => 125.00, 200 => 166.67, 250 => 208.33, 300 => 250.00, 350 => 291.67 );
}

function untangling_plan_pricing() {
	$domain_tip  = sprintf( __( 'Get a custom domain – like %s – free for the first year.' ), untangling_get_domain_upsell() );
	$storage_tip = __( 'Upload more images, videos, audio, and documents to your website.' );

	// Every plan leads with the same aligned rows — domain, storage,
	// support, then the tier-defining themes and plugins rows (bold via the
	// third entry) — so the columns line up on the pricing grid. Tier
	// extras stack below in a stable cumulative order.
	// Four variations: entering from the plugins upsell hero bolds the
	// plugins rows, from the themes upsell banner bolds the theme rows, from
	// the free-domain nudge bolds the domain row; every other entry (the
	// WordPress.com page) shows all features in regular weight.
	$from_plugins     = ( isset( $_GET['ref'] ) && 'plugins-upsell-hero' === $_GET['ref'] ) || ( isset( $_GET['flow'] ) && 'install' === $_GET['flow'] );
	$from_themes      = isset( $_GET['ref'] ) && 'themes-upsell-banner' === $_GET['ref'];
	// A hosting entry (need=) bolds the rows that answer the promise it came
	// from. Keyed, not matched on the label — the labels are translated.
	$need_cfg         = untangling_get_need();
	$all_needs        = untangling_hosting_needs();
	$need_rows        = $need_cfg ? $all_needs[ $need_cfg ]['rows'] : array();
	$bold             = function ( $key ) use ( $need_rows ) {
		return in_array( $key, $need_rows, true );
	};
	// Claiming a specific name (domain=) is itself a domain-led entry, so the
	// row bolds like ref=domain-upsell — and says the actual name instead of
	// the generic promise, since that name is the whole reason for the visit.
	$claimed          = untangling_claim_domain();
	$from_domain      = $claimed || ( isset( $_GET['ref'] ) && 'domain-upsell' === $_GET['ref'] );
	$domain           = $claimed
		? array( sprintf( __( '%s free for one year' ), $claimed ), sprintf( __( 'Register %s free for the first year. It renews with your plan after that.' ), $claimed ), true )
		: array( __( 'Free domain for one year' ), $domain_tip, $from_domain );
	$premium_themes   = array( __( 'All premium themes' ), __( 'Install any premium theme from the WordPress.com showcase.' ), $from_themes );
	$premium_plugins  = array( __( 'All premium plugins' ), __( 'Install any premium plugin from the WordPress.com marketplace.' ), $from_plugins );
	$priority_support = array( __( '24/7 priority support' ), __( 'Round-the-clock priority support from our expert team.' ) );

	// Shared by every paid tier, below the aligned rows.
	$paid_tail = array(
		array( __( 'Ad-free experience' ), __( 'Your visitors browse ad-free. WordPress.com ads are removed from your site.' ) ),
		array( __( 'Unlimited pages, posts, and users' ), __( 'Create as much content as you want and invite as many collaborators as you need.' ) ),
	);

	// Premium and up.
	$premium_tail = array_merge( $paid_tail, array(
		array( __( 'Premium stats and analytics' ), __( 'See where your visitors come from and what they read.' ) ),
		array( __( 'Payments and paid subscriptions' ), __( 'Collect payments, donations, and paid subscriptions with PayPal and Stripe.' ) ),
		array( __( 'Upload videos' ), __( 'Ad-free, high-definition video hosting with VideoPress.' ) ),
	) );

	// Business and up.
	// Scans, staging, and logs were missing here while the Hosting page sold all
	// three — a visitor who followed that pitch found no mention of it on the
	// plan they were asked to buy.
	$business_tail = array_merge( $premium_tail, array(
		array( __( 'Install plugins and themes' ), __( 'Install any of the thousands of WordPress plugins and themes.' ), $bold( 'plugins' ) ),
		array( __( 'SFTP/SSH and database access' ), __( 'Developer access to your site’s files and database.' ), $bold( 'access' ) ),
		array( __( 'Real-time backups and one-click restores' ), __( 'Every change saved; restore any moment with one click.' ), $bold( 'backups' ) ),
		array( __( 'Daily malware scans' ), __( 'Threats found daily, with most fixes applied for you.' ), $bold( 'scans' ) ),
		array( __( 'A staging site' ), __( 'A private copy of your site to try changes on before they go live.' ), $bold( 'staging' ) ),
		array( __( 'PHP, server, and performance logs' ), __( 'Errors, every request, and how fast your pages answer.' ), $bold( 'logs' ) ),
	) );

	return array(
		'Free'     => array( 0, array(
			array( __( 'Free .wordpress.com address' ), __( 'Get a free site address like yoursite.wordpress.com.' ) ),
			array( __( '1 GB storage' ), __( 'Room for your images and documents.' ) ),
			array( __( 'Community support' ), __( 'Get help from community forums and guides.' ) ),
			array( __( 'Dozens of free themes' ), __( 'Pick from dozens of free themes to style your site.' ), $from_themes ),
		) ),
		'Personal' => array( 4, array_merge( array(
			$domain,
			array( __( '6 GB storage' ), $storage_tip ),
			array( __( 'Fast email support' ), __( 'Email our Happiness Engineers and get unblocked quickly.' ) ),
			array( __( 'Personal-tier themes' ), __( 'Unlock every theme in the Personal tier of the showcase.' ), $from_themes ),
			array( __( 'Dozens of premium plugins' ), __( 'Install dozens of premium plugins from the WordPress.com marketplace.' ), $from_plugins ),
		), $paid_tail ) ),
		'Premium'  => array( 8, array_merge( array(
			$domain,
			array( __( '13 GB storage' ), $storage_tip ),
			array( __( 'Live chat support' ), __( 'Chat with our Happiness Engineers in real time.' ) ),
			$premium_themes,
			$premium_plugins,
		), $premium_tail ) ),
		'Business' => array( 25, array_merge( array(
			$domain,
			array( __( '50 GB storage' ), $storage_tip ),
			$priority_support,
			$premium_themes,
			$premium_plugins,
		), $business_tail ) ),
		'Commerce' => array( 45, array_merge( array(
			$domain,
			array( __( '50 GB storage' ), $storage_tip ),
			$priority_support,
			$premium_themes,
			array( __( 'Premium store plugins' ), __( 'Install premium store plugins from the WordPress.com marketplace.' ), $from_plugins ),
		), $business_tail, array(
			array( __( 'Sell products and subscriptions' ), __( 'Sell physical and digital goods and recurring subscriptions.' ) ),
			array( __( 'Premium store designs' ), __( 'Professionally designed store themes built for selling.' ), $from_themes ),
			array( __( 'Store analytics' ), __( 'Track sales, revenue, and your best-selling products.' ) ),
			array( __( 'Sell in 60+ countries' ), __( 'Accept payments and ship worldwide.' ) ),
		) ) ),
	);
}

function untangling_render_marketplace_page() {
	$mode = untangling_get_marketplace_mode();
	// The My Site drawer enters with ctx=ms and keeps its own plan
	// (untangling_ms_*), so the pricing and checkout steps have to price
	// against that plan — otherwise a Business drawer site is offered the
	// whole Free→Commerce ladder from the shared demo plan.
	$plan = ( isset( $_GET['ctx'] ) && 'ms' === $_GET['ctx'] ) ? untangling_ms_get_plan() : untangling_get_plan();
	$step = isset( $_GET['ustep'] ) && in_array( $_GET['ustep'], array( 'details', 'pricing', 'checkout', 'done' ), true ) ? $_GET['ustep'] : 'browse';
	$type = ( isset( $_GET['type'] ) && 'plugin' === $_GET['type'] ) ? 'plugin' : 'theme';
	// V2 (split) restricts the fullscreen page to themes; plugin steps that
	// started from V1 still resolve so a mid-flow switch doesn't dead-end.
	$mkt = 'browse' === $step
		? ( ( isset( $_GET['mkt'] ) && 'plugins' === $_GET['mkt'] && 'fullscreen' === $mode ) ? 'plugins' : 'themes' )
		: ( 'plugin' === $type ? 'plugins' : 'themes' );

	untangling_marketplace_styles();
	echo '<div class="untangling-mkt" data-mkt="' . esc_attr( $mkt ) . '">';
	untangling_marketplace_topbar( $mkt, $step, $mode );
	echo '<main class="untangling-mkt-main">';
	if ( 'browse' === $step ) {
		untangling_marketplace_browse( $mkt, $mode, $plan );
	} elseif ( 'details' === $step ) {
		if ( 'plugin' === $type ) {
			untangling_marketplace_plugin_details_step( $plan );
		} else {
			untangling_marketplace_details_step( $plan );
		}
	} elseif ( 'pricing' === $step ) {
		untangling_marketplace_pricing_step( $plan, $type );
	} elseif ( 'checkout' === $step ) {
		untangling_marketplace_checkout_step( $plan, $type );
	} else {
		untangling_marketplace_done_step( $type );
	}
	echo '</main>';
	untangling_marketplace_help_panel();
	echo '</div>';
	untangling_marketplace_js();
}

function untangling_marketplace_topbar( $mkt, $step, $mode ) {
	$exit = admin_url( 'plugins' === $mkt ? 'plugins.php' : 'themes.php' );
	// Plan-only upgrade flow (no item slug) enters from the WordPress.com
	// page, not the Marketplace — the brand drops the Marketplace label and
	// ✕/logo return there instead of the themes/plugins screen. Entry points
	// elsewhere (the plugins upsell hero) pass `back` so ✕ returns to the
	// screen the visitor actually came from.
	$plan_only         = in_array( $step, array( 'pricing', 'checkout', 'done' ), true ) && empty( $_GET['slug'] );
	$has_explicit_back = false;
	if ( $plan_only ) {
		$exit = untangling_plan_flow_home_url();
		if ( ! empty( $_GET['back'] ) ) {
			// No rawurldecode: PHP already decoded $_GET once and every caller
			// single-encodes; a second decode corrupts MSD URLs carrying
			// percent-escapes of their own.
			$back = wp_validate_redirect( wp_unslash( $_GET['back'] ), '' );
			if ( $back ) {
				$exit              = $back;
				$has_explicit_back = true;
			}
		}
	}
	$mark = '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM3.5 12c0-1.23.26-2.4.73-3.46L8.25 19.6C5.44 18.23 3.5 15.34 3.5 12zm8.5 8.5c-.83 0-1.64-.12-2.4-.35l2.55-7.4 2.61 7.15c.02.04.04.08.06.12-.9.31-1.85.48-2.82.48zm1.17-12.49c.51-.03.97-.08.97-.08.46-.05.4-.73-.05-.7 0 0-1.38.11-2.27.11-.84 0-2.24-.11-2.24-.11-.46-.03-.51.68-.06.7 0 0 .43.05.89.08l1.32 3.62-1.86 5.57-3.09-9.19c.51-.03.97-.08.97-.08.46-.05.4-.73-.05-.7 0 0-1.38.11-2.27.11-.16 0-.35 0-.55-.01C6.42 5.09 9.04 3.5 12 3.5c2.21 0 4.22.84 5.73 2.23-.04 0-.07-.01-.11-.01-.84 0-1.43.73-1.43 1.51 0 .7.4 1.29.84 1.99.33.57.71 1.3.71 2.35 0 .73-.28 1.58-.65 2.76l-.85 2.84-3.07-9.16zm3.1 11.36l2.6-7.51c.49-1.21.65-2.19.65-3.05 0-.31-.02-.6-.06-.87.66 1.21 1.04 2.6 1.04 4.06 0 3.13-1.7 5.86-4.23 7.37z"/></svg>';
	// V2 (split) and V3 (tabs) have no fullscreen Marketplace destination —
	// this shell only serves the details/pricing/checkout steps, so the brand
	// drops the label and the logo returns to the in-admin surface the
	// visitor was browsing.
	$tabs_mode = in_array( $mode, array( 'split', 'tabs' ), true );
	if ( 'plugins' === $mkt ) {
		$tab_home = admin_url( 'plugin-install.php?tab=wpcom_marketplace' );
	} else {
		$tab_home = 'split' === $mode
			? untangling_themes_screen_url()
			: admin_url( 'theme-install.php?untangling_browse=marketplace' );
	}
	?>
	<header class="untangling-mkt-topbar">
		<a class="untangling-mkt-brand" href="<?php echo esc_url( $plan_only ? $exit : ( $tabs_mode ? $tab_home : untangling_marketplace_url( $mkt ) ) ); ?>">
			<?php echo $mark; // phpcs:ignore ?>
			<?php if ( ! $plan_only && ! $tabs_mode ) : ?>
				<span><?php esc_html_e( 'Marketplace' ); ?></span>
			<?php endif; ?>
		</a>
		<?php if ( 'browse' === $step && 'fullscreen' === $mode ) : ?>
			<nav class="untangling-mkt-switch" aria-label="<?php esc_attr_e( 'Marketplace sections' ); ?>">
				<a href="<?php echo esc_url( untangling_marketplace_url( 'themes' ) ); ?>" data-mkt="themes" <?php echo 'themes' === $mkt ? 'class="is-active" aria-current="page"' : ''; ?>><?php esc_html_e( 'Themes' ); ?></a>
				<a href="<?php echo esc_url( untangling_marketplace_url( 'plugins' ) ); ?>" data-mkt="plugins" <?php echo 'plugins' === $mkt ? 'class="is-active" aria-current="page"' : ''; ?>><?php esc_html_e( 'Plugins' ); ?></a>
			</nav>
		<?php elseif ( 'browse' !== $step && 'done' !== $step ) : ?>
			<button type="button" class="untangling-mkt-back" onclick="history.back()">
				<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M14.6 7l-1.2-1L8 12l5.4 6 1.2-1-4.6-5z"/></svg>
				<?php esc_html_e( 'Back' ); ?>
			</button>
		<?php endif; ?>
		<div class="untangling-mkt-topbar-right">
			<button type="button" class="untangling-mkt-help-toggle"><?php esc_html_e( 'Need help?' ); ?></button>
			<span class="untangling-mkt-topbar-divider" aria-hidden="true"></span>
			<a class="untangling-mkt-exit" href="<?php echo esc_url( $exit ); ?>" aria-label="<?php esc_attr_e( 'Exit the Marketplace' ); ?>">
				<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path fill="currentColor" d="M13 11.8l6.1-6.3-1-1-6.1 6.2-6.1-6.2-1 1 6.1 6.3-6.5 6.7 1 1 6.5-6.6 6.5 6.6 1-1z"/></svg>
			</a>
		</div>
	</header>
	<script>
	// ✕ exits to the page the visitor entered the fullscreen flow from.
	// The entry referrer is remembered per tab (in-flow navigations all
	// carry the marketplace page slug, so they never overwrite it); the
	// link's href stays as a fallback for direct/bookmarked visits.
	// An explicit back param is the freshest signal — when one won above,
	// the referrer override is skipped and the stored value cleared so a
	// stale referrer from an earlier visit cannot hijack the ✕.
	( function () {
		var KEY = 'untanglingMktReturn';
		var explicitBack = <?php echo $has_explicit_back ? 'true' : 'false'; ?>;
		var ref = document.referrer;
		if ( ref && ref.indexOf( 'untangling-marketplace' ) === -1 ) {
			try {
				if ( new URL( ref ).host === window.location.host ) {
					window.sessionStorage.setItem( KEY, ref );
				}
			} catch ( e ) {}
		}
		if ( explicitBack ) {
			try {
				window.sessionStorage.removeItem( KEY );
			} catch ( e ) {}
			return;
		}
		var exit = document.querySelector( '.untangling-mkt-exit' );
		if ( exit ) {
			exit.addEventListener( 'click', function ( event ) {
				var back = window.sessionStorage.getItem( KEY );
				if ( back ) {
					event.preventDefault();
					window.location.href = back;
				}
			} );
		}
	} )();
	</script>
	<?php
}

/**
 * The theme cards shared by every Marketplace surface: the fullscreen
 * showcase (V1/V2 pricing entry) and the in-admin Themes screen (V2 split).
 * Same catalog, same plan gating, same badges — only the chrome around the
 * grid differs.
 */
function untangling_theme_grid_cards( $plan, $return_url = '' ) {
	$rank        = untangling_plan_rank( $plan );
	// In split the fullscreen browse never renders (admin_init redirect), so
	// these cards only ever serve the in-admin screen there: details stay in
	// the admin chrome and gated CTAs carry the upsell diamond, as on Atomic.
	$is_split    = 'split' === untangling_get_marketplace_mode();
	// Activating is a mimic handled on admin_init, so it can return to
	// whichever surface rendered these cards.
	$return_url  = $return_url ? $return_url : untangling_marketplace_url( 'themes' );
	$active_slug = get_option( 'untangling_mkt_active_theme', '' );
	$in_catalog  = (bool) untangling_marketplace_find_item( 'theme', $active_slug );
	if ( ! $in_catalog ) {
		// No mimic-activated theme yet: "My Themes" shows the site's
		// real theme (real name + its bundled screenshot).
		$theme      = wp_get_theme();
		$screenshot = $theme->get_screenshot();
		?>
		<article class="untangling-mkt-theme-card is-current" data-name="<?php echo esc_attr( strtolower( $theme->get( 'Name' ) ) ); ?>" data-subject="" data-recommended="" data-mine="1" data-tier="free">
			<div class="untangling-mkt-shot">
				<?php if ( $screenshot ) : ?><img src="<?php echo esc_url( $screenshot ); ?>" alt="" decoding="async"><?php endif; ?>
			</div>
			<div class="untangling-mkt-theme-info">
				<h3><?php echo esc_html( $theme->get( 'Name' ) ); ?></h3>
				<span class="untangling-mkt-badge is-activebadge">✓ <?php esc_html_e( 'Active' ); ?></span>
			</div>
		</article>
		<?php
	}
	foreach ( untangling_marketplace_themes() as $t ) {
		list( $slug, $name, $tier, $shot, $subject, $recommended, $price ) = $t;
		$tier_plan = untangling_theme_tier_plan( $tier );
		$included  = $rank >= untangling_plan_rank( $tier_plan );
		$is_active = $slug === $active_slug;
		$cta_url   = $included
			? add_query_arg( 'untangling_activate_theme', $slug, $return_url )
			: untangling_marketplace_url( 'themes', array( 'ustep' => 'pricing', 'type' => 'theme', 'slug' => $slug ) );
		?>
		<article class="untangling-mkt-theme-card<?php echo $is_active ? ' is-current' : ''; ?>" data-name="<?php echo esc_attr( strtolower( $name . ' ' . $slug ) ); ?>" data-subject="<?php echo esc_attr( $subject ); ?>" data-recommended="<?php echo $recommended ? '1' : ''; ?>" data-mine="<?php echo $is_active ? '1' : ''; ?>" data-tier="<?php echo esc_attr( $tier ); ?>">
			<?php
			$details_url = $is_split
				? untangling_themes_screen_url( array( 'ustep' => 'details', 'slug' => $slug ) )
				: untangling_marketplace_url( 'themes', array( 'ustep' => 'details', 'slug' => $slug ) );
			?>
			<div class="untangling-mkt-shot">
				<img src="<?php echo esc_url( $shot ); ?>" alt="" decoding="async">
				<div class="untangling-mkt-shot-overlay">
					<?php if ( ! $is_active ) : ?>
						<?php
						// "Unlock theme", not "Unlock this theme" — with the
						// diamond, the longer label overruns the 180px button
						// and wraps to two lines.
						?>
						<a class="untangling-mkt-shot-cta" href="<?php echo esc_url( $cta_url ); ?>"><?php echo ( $included ? '' : untangling_upsell_diamond() ) . esc_html( $included ? __( 'Activate' ) : __( 'Unlock theme' ) ); ?></a>
					<?php endif; ?>
					<a class="untangling-mkt-shot-cta is-ghost" href="<?php echo esc_url( $details_url ); ?>"><?php esc_html_e( 'Theme details' ); ?></a>
				</div>
			</div>
			<div class="untangling-mkt-theme-info">
				<h3><a href="<?php echo esc_url( $details_url ); ?>"><?php echo esc_html( $name ); ?></a></h3>
				<span class="untangling-mkt-theme-meta">
				<?php if ( $is_active ) : ?>
					<span class="untangling-mkt-badge is-activebadge">✓ <?php esc_html_e( 'Active' ); ?></span>
				<?php elseif ( $included ) : ?>
					<span class="untangling-mkt-badge is-included"><?php esc_html_e( 'Included with plan' ); ?></span>
				<?php elseif ( $price ) : ?>
					<span class="untangling-mkt-pricenote"><?php echo esc_html( 'US$' . $price . '/month' ); ?></span>
					<span class="untangling-mkt-badge is-tier"><?php echo esc_html( sprintf( __( '%s plan' ), $tier_plan ) ); ?></span>
				<?php else : ?>
					<span class="untangling-mkt-badge is-tier"><?php echo esc_html( sprintf( __( '%s plan' ), $tier_plan ) ); ?></span>
				<?php endif; ?>
				</span>
			</div>
		</article>
		<?php
	}
}


function untangling_marketplace_browse( $mkt, $mode, $plan ) {
	$rank = untangling_plan_rank( $plan );

	/* ---- Themes catalog ---- */
	?>
	<section class="untangling-mkt-catalog is-themes<?php echo 'themes' === $mkt ? ' is-active' : ''; ?>" data-catalog="themes">
		<div class="untangling-mkt-hero">
			<h1 class="untangling-mkt-brandfont"><?php esc_html_e( 'Beautiful themes for every idea' ); ?></h1>
			<p><?php esc_html_e( 'Stunning, responsive designs ready to bring your site to life.' ); ?></p>
			<div class="untangling-mkt-search">
				<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.5" d="M13 5a6 6 0 1 1-6 6 6 6 0 0 1 6-6zm-4.5 10.5L4 20"/></svg>
				<input type="search" placeholder="<?php esc_attr_e( 'Search themes…' ); ?>" aria-label="<?php esc_attr_e( 'Search themes' ); ?>">
			</div>
		</div>
		<div class="untangling-mkt-filterbar">
			<div class="untangling-mkt-pillscroll">
				<button type="button" class="untangling-mkt-pillnav is-prev" aria-label="<?php esc_attr_e( 'Scroll categories back' ); ?>" hidden><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M14.6 7l-1.2-1L8 12l5.4 6 1.2-1-4.6-5z"/></svg></button>
				<div class="untangling-mkt-pills" role="tablist">
					<button type="button" data-filter="mine"><?php esc_html_e( 'My Themes' ); ?></button>
					<button type="button" data-filter="recommended" class="is-active"><?php esc_html_e( 'Recommended' ); ?></button>
					<button type="button" data-filter="all"><?php esc_html_e( 'All' ); ?></button>
					<button type="button" data-filter="blog"><?php esc_html_e( 'Blog' ); ?></button>
					<button type="button" data-filter="portfolio"><?php esc_html_e( 'Portfolio' ); ?></button>
					<button type="button" data-filter="business"><?php esc_html_e( 'Business' ); ?></button>
					<button type="button" data-filter="store"><?php esc_html_e( 'Store' ); ?></button>
					<button type="button" data-filter="photography"><?php esc_html_e( 'Photography' ); ?></button>
				</div>
				<button type="button" class="untangling-mkt-pillnav is-next" aria-label="<?php esc_attr_e( 'Scroll categories forward' ); ?>"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M9.4 7l1.2-1 5.4 6-5.4 6-1.2-1 4.6-5z"/></svg></button>
			</div>
			<label class="untangling-mkt-view">
				<span><?php esc_html_e( 'View' ); ?></span>
				<select data-tier-filter>
					<option value="all"><?php esc_html_e( 'All' ); ?></option>
					<option value="free"><?php esc_html_e( 'Free' ); ?></option>
					<option value="partner"><?php esc_html_e( 'Partner' ); ?></option>
					<option value="personal"><?php esc_html_e( 'Personal' ); ?></option>
					<option value="premium"><?php esc_html_e( 'Premium' ); ?></option>
				</select>
				<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M17.1 9.5 12 14.6 6.9 9.5l1.1-1.1 4 4.1 4-4.1z"/></svg>
			</label>
		</div>
		<div class="untangling-mkt-theme-grid">
			<?php untangling_theme_grid_cards( $plan ); ?>
		</div>
		<p class="untangling-mkt-empty" hidden><?php esc_html_e( 'No themes match your search.' ); ?></p>
	</section>

	<?php
	/* ---- Plugins catalog (V1 only) ---- */
	if ( 'fullscreen' !== $mode ) {
		return;
	}
	$installed  = (array) get_option( 'untangling_mkt_installed', array() );
	$categories = untangling_marketplace_plugin_categories();
	$categories['all']   = __( 'Discover' );
	$categories['seo']   = __( 'Search engine optimization' );
	$categories['store'] = __( 'Ecommerce & business' );
	$yoast_url           = untangling_marketplace_url( 'plugins', array( 'ustep' => 'details', 'type' => 'plugin', 'slug' => 'wordpress-seo-premium' ) );
	?>
	<section class="untangling-mkt-catalog is-plugins<?php echo 'plugins' === $mkt ? ' is-active' : ''; ?>" data-catalog="plugins">
		<div class="untangling-mkt-hero">
			<h1 class="untangling-mkt-brandfont"><?php esc_html_e( 'Plug into possibilities' ); ?></h1>
			<p><?php esc_html_e( 'Curated plugins that add new features to your site.' ); ?></p>
			<div class="untangling-mkt-search">
				<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.5" d="M13 5a6 6 0 1 1-6 6 6 6 0 0 1 6-6zm-4.5 10.5L4 20"/></svg>
				<input type="search" placeholder="<?php esc_attr_e( 'Try searching “woocommerce”' ); ?>" aria-label="<?php esc_attr_e( 'Search plugins' ); ?>">
			</div>
		</div>
		<div class="untangling-mkt-filterbar">
			<div class="untangling-mkt-pillscroll">
				<button type="button" class="untangling-mkt-pillnav is-prev" aria-label="<?php esc_attr_e( 'Scroll categories back' ); ?>" hidden><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M14.6 7l-1.2-1L8 12l5.4 6 1.2-1-4.6-5z"/></svg></button>
				<div class="untangling-mkt-pills" role="tablist">
					<?php foreach ( $categories as $key => $label ) : ?>
						<button type="button" data-filter="<?php echo esc_attr( $key ); ?>"<?php echo 'all' === $key ? ' class="is-active"' : ''; ?>><?php echo esc_html( $label ); ?></button>
					<?php endforeach; ?>
				</div>
				<button type="button" class="untangling-mkt-pillnav is-next" aria-label="<?php esc_attr_e( 'Scroll categories forward' ); ?>"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M9.4 7l1.2-1 5.4 6-5.4 6-1.2-1 4.6-5z"/></svg></button>
			</div>
			<label class="untangling-mkt-view">
				<span><?php esc_html_e( 'View' ); ?></span>
				<select data-tier-filter>
					<option value="all"><?php esc_html_e( 'All' ); ?></option>
					<option value="personal"><?php esc_html_e( 'Personal' ); ?></option>
					<option value="premium"><?php esc_html_e( 'Premium' ); ?></option>
					<option value="business"><?php esc_html_e( 'Business' ); ?></option>
					<option value="commerce"><?php esc_html_e( 'Commerce' ); ?></option>
				</select>
				<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M17.1 9.5 12 14.6 6.9 9.5l1.1-1.1 4 4.1 4-4.1z"/></svg>
			</label>
		</div>
		<div class="untangling-mkt-spotlight">
			<img src="https://ps.w.org/wordpress-seo/assets/icon-256x256.gif" alt="">
			<div class="untangling-mkt-spotlight-text">
				<span><?php esc_html_e( 'Drive more traffic with Yoast SEO Premium' ); ?></span>
				<strong><?php esc_html_e( 'Under the spotlight' ); ?></strong>
			</div>
			<a class="untangling-mkt-button is-secondary" href="<?php echo esc_url( $yoast_url ); ?>"><?php esc_html_e( 'View details' ); ?></a>
		</div>
		<div class="untangling-mkt-section-head">
			<div>
				<h2><?php esc_html_e( 'Must-have premium plugins' ); ?></h2>
				<p><?php esc_html_e( 'Take your site further with these premium plugins.' ); ?></p>
			</div>
			<button type="button" class="untangling-mkt-browse-all" data-filter-jump="all"><?php esc_html_e( 'Browse all' ); ?></button>
		</div>
		<div class="untangling-mkt-plugin-grid">
			<?php
			foreach ( untangling_marketplace_plugins() as $p ) {
				list( $slug, $name, $desc, $author, $icon, $tier, $rating, $num, $installs, $updated, $cat, $price ) = $p;
				$included     = $rank >= untangling_plan_rank( $tier );
				$is_installed = in_array( $slug, $installed, true );
				// Cards open the details page; install/purchase happens there.
				$href         = untangling_marketplace_url( 'plugins', array( 'ustep' => 'details', 'type' => 'plugin', 'slug' => $slug ) );
				?>
				<a class="untangling-mkt-plugin-card<?php echo $is_installed ? ' is-installed' : ''; ?>" href="<?php echo esc_url( $href ); ?>" data-name="<?php echo esc_attr( strtolower( $name . ' ' . $author . ' ' . $desc ) ); ?>" data-category="<?php echo esc_attr( $cat ); ?>" data-tier="<?php echo esc_attr( strtolower( $tier ) ); ?>">
					<?php if ( $is_installed ) : ?>
						<span class="untangling-mkt-chip is-installedchip">✓ <?php esc_html_e( 'Installed' ); ?></span>
					<?php elseif ( $included ) : ?>
						<span class="untangling-mkt-chip is-included"><?php esc_html_e( 'Included with plan' ); ?></span>
					<?php else : ?>
						<span class="untangling-mkt-chip is-tier"><?php echo esc_html( sprintf( __( 'Requires %s plan' ), $tier ) ); ?></span>
					<?php endif; ?>
					<div class="untangling-mkt-plugin-head">
						<img src="<?php echo esc_url( $icon ); ?>" alt="" decoding="async">
						<div>
							<h3><?php echo esc_html( $name ); ?></h3>
							<span class="untangling-mkt-plugin-by"><?php esc_html_e( 'by' ); ?> <em><?php echo esc_html( $author ); ?></em></span>
						</div>
					</div>
					<p class="untangling-mkt-plugin-desc"><?php echo esc_html( $desc ); ?></p>
					<div class="untangling-mkt-plugin-foot">
						<span class="untangling-mkt-plugin-price">
							<?php if ( $price ) : ?>
								<?php echo esc_html( 'US$' . $price ); ?> <span><?php esc_html_e( 'monthly' ); ?></span>
							<?php else : ?>
								<?php esc_html_e( 'Free' ); ?>
							<?php endif; ?>
						</span>
						<span class="untangling-mkt-plugin-rating">★ <?php echo esc_html( number_format_i18n( $rating, 1 ) ); ?> <span>(<?php echo esc_html( number_format_i18n( $num ) ); ?>)</span></span>
					</div>
				</a>
				<?php
			}
			?>
		</div>
		<p class="untangling-mkt-empty" hidden><?php esc_html_e( 'No plugins match your search.' ); ?></p>
	</section>
	<?php
}

// Theme details — mimics production wordpress.com/theme/{slug}: breadcrumb,
// tier pill, Recoleta title + author, Preview/Activate actions, description,
// feature tags, support card, large screenshot on the right.
function untangling_marketplace_details_step( $plan, $in_admin = false ) {
	$slug = isset( $_GET['slug'] ) ? sanitize_key( $_GET['slug'] ) : '';
	$row  = null;
	foreach ( untangling_marketplace_themes() as $t ) {
		if ( $t[0] === $slug ) {
			$row = $t;
		}
	}
	$details = untangling_marketplace_theme_details();
	if ( ! $row || ! isset( $details[ $slug ] ) ) {
		echo '<div class="untangling-mkt-hero"><h1 class="untangling-mkt-brandfont">' . esc_html__( 'Theme not found' ) . '</h1></div>';
		return;
	}
	list( , $name, $tier, $shot, , , $price ) = $row;
	list( $author, $demo, $desc, $features )  = $details[ $slug ];
	$tier_plan = untangling_theme_tier_plan( $tier );
	$included  = untangling_plan_rank( $plan ) >= untangling_plan_rank( $tier_plan );
	$is_active = $slug === get_option( 'untangling_mkt_active_theme', '' );

	if ( $in_admin ) {
		// Production Atomic details: short pill, no ★.
		if ( 'free' === $tier ) {
			$pill = __( 'Free' );
		} elseif ( $included ) {
			$pill = __( 'Included with your plan' );
		} elseif ( $price ) {
			$pill = sprintf( __( 'US$%1$s/month, or included in %2$s' ), $price, $tier_plan );
		} else {
			$pill = sprintf( __( 'Available on %s' ), $tier_plan );
		}
	} elseif ( 'free' === $tier ) {
		$pill = __( 'Free theme' );
	} elseif ( $price ) {
		$pill = sprintf( __( 'US$%1$s/month, or included in %2$s' ), $price, $tier_plan );
	} else {
		$pill = sprintf( __( 'Available on %s' ), $tier_plan );
	}
	$browse_url = $in_admin ? untangling_themes_screen_url() : untangling_marketplace_url( 'themes' );
	$cta_url    = $included
		? add_query_arg( 'untangling_activate_theme', $slug, $browse_url )
		: untangling_marketplace_url( 'themes', array( 'ustep' => 'pricing', 'type' => 'theme', 'slug' => $slug ) );
	?>
	<nav class="untangling-mkt-crumbs">
		<a href="<?php echo esc_url( $browse_url ); ?>"><?php esc_html_e( 'Themes' ); ?></a>
		<span aria-hidden="true">›</span>
		<span class="is-current"><?php echo esc_html( sprintf( __( '%s Theme' ), $name ) ); ?></span>
	</nav>
	<div class="untangling-mkt-detail">
		<div class="untangling-mkt-detail-info">
			<span class="untangling-mkt-detail-tierpill"><?php echo ( $in_admin ? '' : '★ ' ) . esc_html( $pill ); ?></span>
			<div class="untangling-mkt-detail-head">
				<div>
					<h1 class="untangling-mkt-brandfont"><?php echo esc_html( $name ); ?></h1>
					<p class="untangling-mkt-detail-by"><?php echo esc_html( sprintf( __( 'by %s' ), $author ) ); ?></p>
				</div>
				<div class="untangling-mkt-detail-actions">
					<a class="untangling-mkt-button is-secondary" href="<?php echo esc_url( $demo ); ?>" target="_blank" rel="noreferrer"><?php esc_html_e( 'Preview' ); ?><?php if ( $in_admin ) : ?> <svg class="untangling-mkt-caret" width="10" height="6" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.5"/></svg><?php endif; ?></a>
					<?php if ( $is_active ) : ?>
						<span class="untangling-mkt-button is-disabled">✓ <?php esc_html_e( 'Active' ); ?></span>
					<?php else : ?>
						<a class="untangling-mkt-button is-primary" href="<?php echo esc_url( $cta_url ); ?>"><?php echo ( $included ? '' : untangling_upsell_diamond() ) . esc_html( $included ? __( 'Activate' ) : __( 'Unlock theme' ) ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( $in_admin ) : ?>
			<div class="untangling-mkt-detail-styles">
				<h2><?php esc_html_e( 'Styles' ); ?></h2>
				<p><?php esc_html_e( 'You can change your style at any time.' ); ?></p>
				<div class="untangling-mkt-style-chips">
					<button type="button" class="untangling-mkt-style-chip is-selected" style="background: #fff; color: #1e1e1e;" aria-label="<?php esc_attr_e( 'Default style' ); ?>"><span class="untangling-mkt-style-aa">Aa</span><span class="untangling-mkt-style-dots"><i style="background: #757575;"></i><i style="background: #1e1e1e;"></i></span></button>
					<button type="button" class="untangling-mkt-style-chip" style="background: #2f2f2f; color: #fff;" aria-label="<?php esc_attr_e( 'Dark style' ); ?>"><span class="untangling-mkt-style-aa">Aa</span><span class="untangling-mkt-style-dots"><i style="background: #a7a7a7;"></i><i style="background: #fff;"></i></span></button>
					<button type="button" class="untangling-mkt-style-chip" style="background: #efe8dc; color: #1e1e1e;" aria-label="<?php esc_attr_e( 'Muted style' ); ?>"><span class="untangling-mkt-style-aa">Aa</span><span class="untangling-mkt-style-dots"><i style="background: #8a8378;"></i><i style="background: #1e1e1e;"></i></span></button>
				</div>
			</div>
			<?php endif; ?>
			<div class="untangling-mkt-detail-desc"><p><?php echo esc_html( $desc ); ?></p></div>
			<h2><?php esc_html_e( 'Features' ); ?></h2>
			<div class="untangling-mkt-detail-feats">
				<?php foreach ( $features as $feature ) : ?>
					<span><?php echo esc_html( $feature ); ?></span>
				<?php endforeach; ?>
			</div>
			<div class="untangling-mkt-detail-support">
				<div class="untangling-mkt-detail-support-row">
					<div>
						<h3><?php esc_html_e( 'Learn WordPress' ); ?></h3>
						<p><?php esc_html_e( 'Follow along with beginner-friendly courses and build your first website or blog.' ); ?></p>
					</div>
					<a class="untangling-mkt-button is-secondary" href="#"><?php esc_html_e( 'Watch a course' ); ?></a>
				</div>
				<div class="untangling-mkt-detail-support-row">
					<div>
						<h3><?php esc_html_e( 'Discover comprehensive guides' ); ?></h3>
						<p><?php esc_html_e( 'Explore deep-dive tutorials for every WordPress.com feature.' ); ?></p>
					</div>
					<a class="untangling-mkt-button is-secondary" href="#"><?php esc_html_e( 'Visit guides' ); ?></a>
				</div>
				<div class="untangling-mkt-detail-support-row">
					<div>
						<h3><?php esc_html_e( 'Contact support' ); ?></h3>
						<p><?php esc_html_e( 'Get answers from our AI assistant, with access to 24/7 expert human support on paid plans.' ); ?></p>
					</div>
					<a class="untangling-mkt-button is-secondary" href="#" data-open-help><?php esc_html_e( 'Get in touch' ); ?></a>
				</div>
			</div>
		</div>
		<aside class="untangling-mkt-detail-shot">
			<img src="<?php echo esc_url( str_replace( 'w=640', 'w=1100', $shot ) ); ?>" alt="<?php echo esc_attr( $name ); ?>">
		</aside>
	</div>
	<?php
}

// Per-plugin detail copy for the details page: version, wp.org banner (null
// where the plugin has none), long-description paragraphs, highlight list.
function untangling_marketplace_plugin_details() {
	return array(
		'wordpress-seo-premium'     => array( '28.0', 'https://ps.w.org/wordpress-seo/assets/banner-1544x500.png', array(
			__( 'Real-time SEO guidance and built-in AI tools for teams that want to improve visibility without needing deep SEO expertise. Yoast SEO Premium is built for in-house marketing teams, entrepreneurs, and content creators who rely on content-driven channels to grow.' ),
			__( 'Optimize content for up to five keyphrases per page, generate SEO titles and meta descriptions with AI, and let automatic redirects keep your site free of dead links as it evolves.' ),
		), array( __( 'AI-generated titles and descriptions' ), __( 'Automatic redirect manager' ), __( 'Internal linking suggestions' ), __( 'Up to 5 keyphrases per page' ), __( '24/7 premium support' ) ) ),
		'google-site-kit'           => array( '1.160.0', 'https://ps.w.org/google-site-kit/assets/banner-1544x500.png', array(
			__( 'Site Kit is the official WordPress plugin from Google. It gives you authoritative, up-to-date insights from multiple Google products directly on your dashboard, with no code edits required.' ),
			__( 'Connect Search Console, Analytics, AdSense, and PageSpeed Insights in a few clicks and see how people find and use your site.' ),
		), array( __( 'Search Console metrics' ), __( 'Google Analytics dashboards' ), __( 'AdSense earnings overview' ), __( 'PageSpeed Insights reports' ) ) ),
		'optinmonster'              => array( '2.16.19', 'https://ps.w.org/optinmonster/assets/banner-1544x500.png', array(
			__( 'OptinMonster helps you grow your email list, get more leads, and increase sales with beautiful popups, floating bars, and gamified spin-a-wheel campaigns.' ),
			__( 'Target campaigns by page, scroll depth, or exit intent, and connect them to your favorite email marketing service.' ),
		), array( __( 'Exit-intent popups' ), __( 'Drag-and-drop campaign builder' ), __( 'A/B testing' ), __( 'Page-level targeting' ) ) ),
		'leadin'                    => array( '11.3.7', 'https://ps.w.org/leadin/assets/banner-1544x500.png', array(
			__( 'HubSpot’s WordPress plugin brings your CRM, live chat, email marketing, and forms together, so you can grow and track your audience from one place.' ),
			__( 'Every form submission and chat conversation is stored on a free CRM timeline, giving you a complete picture of each contact.' ),
		), array( __( 'Free built-in CRM' ), __( 'Live chat and chatbots' ), __( 'Email marketing' ), __( 'Analytics dashboard' ) ) ),
		'zero-bs-crm'               => array( '6.4.4', 'https://ps.w.org/zero-bs-crm/assets/banner-1544x500.png', array(
			__( 'Jetpack CRM is a simple, practical CRM for entrepreneurs and small teams. Manage contacts, quotes, invoices, and transactions without leaving WordPress.' ),
			__( 'No bloat and no steep learning curve — just the tools you need to keep on top of your customers.' ),
		), array( __( 'Contact management' ), __( 'Quotes and invoices' ), __( 'Client portal' ), __( 'Sales dashboard' ) ) ),
		'mailpoet'                  => array( '5.12.0', 'https://ps.w.org/mailpoet/assets/banner-1544x500.png', array(
			__( 'MailPoet lets you create, send, and track newsletters and automatic new-post notifications right from your WordPress dashboard.' ),
			__( 'Build emails with a drag-and-drop editor, welcome new subscribers automatically, and watch open and click stats roll in.' ),
		), array( __( 'Drag-and-drop email editor' ), __( 'New-post notifications' ), __( 'Welcome automations' ), __( 'Subscriber segmentation' ) ) ),
		'fluent-crm'                => array( '2.9.60', 'https://ps.w.org/fluent-crm/assets/banner-1544x500.png', array(
			__( 'FluentCRM is a self-hosted email marketing automation plugin. Run campaigns, funnels, and contact segmentation from inside WordPress — your data stays on your site.' ),
			__( 'Visualize each customer journey with a 360° contact view and automate follow-ups based on behavior.' ),
		), array( __( 'Email campaigns and sequences' ), __( 'Marketing automation funnels' ), __( '360° contact overview' ), __( 'Granular segmentation' ) ) ),
		'give'                      => array( '4.4.0', 'https://ps.w.org/give/assets/banner-1544x500.jpg', array(
			__( 'GiveWP is the highest-rated donation plugin for WordPress. Create beautiful donation pages and grow fundraising for your cause.' ),
			__( 'Accept one-time or recurring donations, manage donors, and report on your fundraising — all from your site.' ),
		), array( __( 'Custom donation forms' ), __( 'Donor management' ), __( 'Fundraising reports' ), __( 'Fee recovery' ) ) ),
		'easy-digital-downloads'    => array( '3.3.9', 'https://ps.w.org/easy-digital-downloads/assets/banner-1544x500.png', array(
			__( 'Easy Digital Downloads is a complete ecommerce solution for selling digital products: ebooks, software, music, or any file.' ),
			__( 'A full shopping cart, flexible payment options, and detailed earnings reports — purpose-built for digital goods.' ),
		), array( __( 'Complete shopping cart' ), __( 'Software licensing' ), __( 'Discount codes' ), __( 'Earnings reports' ) ) ),
		'gravityforms'              => array( '2.9.9', null, array(
			__( 'Gravity Forms is the most advanced form builder for WordPress. Build complex forms with conditional logic, multi-page flows, and file uploads in minutes.' ),
			__( 'Connect submissions to hundreds of services with official add-ons, from payment gateways to email marketing tools.' ),
		), array( __( 'Drag-and-drop form builder' ), __( 'Conditional logic' ), __( 'Multi-page forms' ), __( 'Payment add-ons' ) ) ),
		'sensei-pro'                => array( '4.25.1', 'https://ps.w.org/sensei-lms/assets/banner-1544x500.png', array(
			__( 'Sensei Pro, by Automattic, lets you create and sell online courses, quizzes, and interactive lessons on your own site.' ),
			__( 'Sell with WooCommerce, drip content on a schedule, and keep learners engaged with interactive videos and flashcards.' ),
		), array( __( 'Course and quiz builder' ), __( 'Sell courses with WooCommerce' ), __( 'Content drip' ), __( 'Interactive videos' ) ) ),
		'automatewoo'               => array( '6.1.11', null, array(
			__( 'AutomateWoo is powerful marketing automation for your WooCommerce store: follow-up emails, abandoned cart recovery, win-back campaigns, and more.' ),
			__( 'Create workflows triggered by store events and measure the revenue each one brings back.' ),
		), array( __( 'Abandoned cart emails' ), __( 'Follow-up workflows' ), __( 'Win-back campaigns' ), __( 'SMS notifications' ) ) ),
		'woocommerce-subscriptions' => array( '7.6.0', null, array(
			__( 'WooCommerce Subscriptions lets customers subscribe to your products or services and pay on a schedule you set — weekly, monthly, or annually.' ),
			__( 'Handle recurring billing, automatic renewals, flexible trials, and subscriber management out of the box.' ),
		), array( __( 'Recurring payments' ), __( 'Free trials and sign-up fees' ), __( 'Automatic renewals' ), __( 'Subscriber switching' ) ) ),
		'woocommerce-bookings'      => array( '2.1.14', null, array(
			__( 'WooCommerce Bookings lets customers book appointments, rentals, and reservations directly from your store — no phone calls needed.' ),
			__( 'Define time slots, capacity, and pricing rules; confirmations and reminders are handled for you.' ),
		), array( __( 'Bookable time slots' ), __( 'Capacity management' ), __( 'Person-type pricing' ), __( 'Reminder emails' ) ) ),
	);
}

// Plugin details — mimics production wordpress.com/plugins/{slug}: breadcrumb,
// icon + Recoleta title, tags, rating/version/updated meta, banner, long
// description, and the purchase box on the right.
function untangling_marketplace_plugin_details_step( $plan ) {
	$slug = isset( $_GET['slug'] ) ? sanitize_key( $_GET['slug'] ) : '';
	$row  = null;
	foreach ( untangling_marketplace_plugins() as $p ) {
		if ( $p[0] === $slug ) {
			$row = $p;
		}
	}
	$details = untangling_marketplace_plugin_details();
	if ( ! $row || ! isset( $details[ $slug ] ) ) {
		echo '<div class="untangling-mkt-hero"><h1 class="untangling-mkt-brandfont">' . esc_html__( 'Plugin not found' ) . '</h1></div>';
		return;
	}
	list( , $name, $desc, $author, $icon, $tier, $rating, $num, , $updated, $cat, $price ) = $row;
	list( $version, $banner, $paragraphs, $highlights )                                   = $details[ $slug ];
	$categories   = untangling_marketplace_plugin_categories();
	$included     = untangling_plan_rank( $plan ) >= untangling_plan_rank( $tier );
	$is_installed = in_array( $slug, (array) get_option( 'untangling_mkt_installed', array() ), true );
	$cta_url      = $included
		? add_query_arg( 'untangling_install_plugin', $slug, untangling_marketplace_url( 'plugins' ) )
		: untangling_marketplace_url( 'plugins', array( 'ustep' => 'pricing', 'type' => 'plugin', 'slug' => $slug ) );
	?>
	<nav class="untangling-mkt-crumbs">
		<a href="<?php echo esc_url( untangling_marketplace_url( 'plugins' ) ); ?>"><?php esc_html_e( 'Plugins' ); ?></a>
		<span aria-hidden="true">›</span>
		<span class="is-current"><?php echo esc_html( $name ); ?></span>
	</nav>
	<div class="untangling-mkt-plugdetail">
		<div class="untangling-mkt-plugdetail-info">
			<div class="untangling-mkt-plugdetail-head">
				<img src="<?php echo esc_url( $icon ); ?>" alt="" decoding="async">
				<div>
					<h1 class="untangling-mkt-brandfont"><?php echo esc_html( $name ); ?></h1>
					<p class="untangling-mkt-detail-by"><?php esc_html_e( 'By' ); ?> <a href="#"><?php echo esc_html( $author ); ?></a></p>
				</div>
			</div>
			<p class="untangling-mkt-plugdetail-tagline"><?php echo esc_html( $desc ); ?></p>
			<div class="untangling-mkt-plugdetail-tags">
				<span><?php esc_html_e( 'plugins' ); ?></span>
				<span><?php echo esc_html( strtolower( isset( $categories[ $cat ] ) ? $categories[ $cat ] : $cat ) ); ?></span>
			</div>
			<div class="untangling-mkt-plugdetail-meta">
				<div>
					<span><?php esc_html_e( 'Rating' ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( $rating, 1 ) ); ?>/5</strong>
					<a href="#"><?php echo esc_html( sprintf( __( '%s reviews' ), number_format_i18n( $num ) ) ); ?></a>
				</div>
				<div>
					<span><?php esc_html_e( 'Version' ); ?></span>
					<strong><?php echo esc_html( $version ); ?></strong>
				</div>
				<div>
					<span><?php esc_html_e( 'Last updated' ); ?></span>
					<strong><?php echo esc_html( $updated ); ?></strong>
				</div>
			</div>
			<?php if ( $banner ) : ?>
				<div class="untangling-mkt-plugdetail-banner">
					<img src="<?php echo esc_url( $banner ); ?>" alt="" decoding="async" onerror="this.parentNode.hidden = true;">
				</div>
			<?php endif; ?>
			<h2><?php echo esc_html( $name ); ?></h2>
			<?php foreach ( $paragraphs as $paragraph ) : ?>
				<p class="untangling-mkt-plugdetail-para"><?php echo esc_html( $paragraph ); ?></p>
			<?php endforeach; ?>
			<h2><?php esc_html_e( 'Highlights' ); ?></h2>
			<div class="untangling-mkt-detail-feats">
				<?php foreach ( $highlights as $highlight ) : ?>
					<span><?php echo esc_html( $highlight ); ?></span>
				<?php endforeach; ?>
			</div>
		</div>
		<aside class="untangling-mkt-plugdetail-buy">
			<p class="untangling-mkt-plugdetail-price">
				<?php if ( $price ) : ?>
					<strong class="untangling-mkt-brandfont"><?php echo esc_html( 'US$' . number_format_i18n( (float) $price, 2 ) ); ?></strong> <span><?php esc_html_e( 'monthly' ); ?></span>
				<?php else : ?>
					<strong class="untangling-mkt-brandfont"><?php esc_html_e( 'Free' ); ?></strong>
				<?php endif; ?>
			</p>
			<?php if ( $price ) : ?>
				<div class="untangling-mkt-plugdetail-billing">
					<label><input type="radio" name="untangling-billing" checked> <?php esc_html_e( 'Monthly' ); ?></label>
					<label><input type="radio" name="untangling-billing"> <?php esc_html_e( 'Annually' ); ?> <em><?php echo esc_html( sprintf( __( '(Save US$%s)' ), number_format_i18n( (float) $price * 0.1, 2 ) ) ); ?></em></label>
				</div>
			<?php endif; ?>
			<?php if ( $is_installed ) : ?>
				<span class="untangling-mkt-button is-disabled is-block">✓ <?php esc_html_e( 'Installed' ); ?></span>
			<?php elseif ( $included ) : ?>
				<a class="untangling-mkt-button is-primary is-block" href="<?php echo esc_url( $cta_url ); ?>"><?php esc_html_e( 'Install and activate' ); ?></a>
			<?php else : ?>
				<a class="untangling-mkt-button is-primary is-block" href="<?php echo esc_url( $cta_url ); ?>"><?php echo untangling_upsell_diamond() . ( $price ? esc_html__( 'Purchase and activate' ) : esc_html__( 'Upgrade and activate' ) ); ?></a>
			<?php endif; ?>
			<hr>
			<h3><?php echo $price ? esc_html__( 'Included with your purchase' ) : esc_html__( 'Included with this plugin' ); ?></h3>
			<ul>
				<li><?php esc_html_e( 'Plugin updates' ); ?></li>
				<li><?php echo $price ? esc_html__( '7-day money-back guarantee' ) : esc_html__( 'Community support' ); ?></li>
			</ul>
			<h3><?php esc_html_e( 'Included on all paid plans (starting at US$4/month)' ); ?></h3>
			<ul>
				<li><?php esc_html_e( 'Best-in-class hosting' ); ?></li>
				<li><?php esc_html_e( '24/7 expert support' ); ?></li>
			</ul>
			<h3><?php esc_html_e( 'Support' ); ?></h3>
			<p class="untangling-mkt-plugdetail-supportline"><?php esc_html_e( '24/7 expert support' ); ?></p>
			<p><a href="#" data-open-help><?php esc_html_e( 'How to get help!' ); ?> ⓘ</a></p>
			<p><a href="#"><?php esc_html_e( 'See privacy policy' ); ?> ↗</a></p>
		</aside>
	</div>
	<?php
}

// The domain-claim variant of the pricing + checkout steps. Entering with
// `domain=` means the visitor came from the Domains card to claim a specific
// name, so both steps carry that name and price it as free for the first year
// — the offer is the reason they're looking at plans at all.
function untangling_claim_domain() {
	if ( empty( $_GET['domain'] ) ) {
		return '';
	}
	$domain = strtolower( sanitize_text_field( wp_unslash( $_GET['domain'] ) ) );
	// Registrable name only: no paths, ports, or schemes smuggled in.
	return preg_match( '/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $domain ) ? $domain : '';
}

// Mocked list price for the claimed domain, matching the .com/.blog spread the
// real domain search quotes. Yearly; the first year is the thing being given away.
function untangling_claim_domain_price( $domain ) {
	$prices = array( 'com' => 13, 'blog' => 22, 'net' => 12, 'org' => 12, 'store' => 30 );
	$parts  = explode( '.', $domain );
	$tld    = end( $parts );
	return isset( $prices[ $tld ] ) ? $prices[ $tld ] : 13;
}

function untangling_marketplace_pricing_step( $plan, $type ) {
	$slug = isset( $_GET['slug'] ) ? sanitize_key( $_GET['slug'] ) : '';
	$item = untangling_marketplace_find_item( $type, $slug );
	if ( ! $item && $slug ) {
		echo '<div class="untangling-mkt-hero"><h1 class="untangling-mkt-brandfont">' . esc_html__( 'Item not found' ) . '</h1></div>';
		return;
	}
	// Core Add Plugins cards (wp.org catalog, Simple sites) enter with
	// flow=install: not a marketplace item, but the hero still names the
	// clicked plugin and the required tier matches the cards'
	// "Requires the X plan" signal (Free → Personal, Premium → Business).
	if ( ! $item && isset( $_GET['flow'] ) && 'install' === $_GET['flow'] ) {
		$pname = isset( $_GET['pname'] ) ? sanitize_text_field( wp_unslash( $_GET['pname'] ) ) : '';
		$item  = array(
			'name' => $pname ? $pname : __( 'any plugin' ),
			'tier' => 'Premium' === $plan ? 'Business' : 'Personal',
		);
	}
	// A Hosting-page entry carries `need=` instead of an item: the promise that
	// was clicked, which names the tier that delivers it and the rows that
	// answer it. It behaves like an item from here on — same floor, same
	// highlighted column — so the ladder shown is the current plan plus the
	// plans that can actually help, and nothing in between.
	$need     = untangling_get_need();
	$needs    = untangling_hosting_needs();
	$ctx      = ( ! $item && $need ) ? $needs[ $need ] : null;
	// No slug and no need = generic plan-upgrade entry (from the WordPress.com
	// page plan card): same pricing page, Premium highlighted as Recommended.
	$tier_rank = untangling_plan_rank( $item ? $item['tier'] : ( $ctx ? $ctx['tier'] : 'Premium' ) );
	$rank      = untangling_plan_rank( $plan );
	// A top-tier site with nothing to unlock would get a single disabled
	// column — show the tier below so "Compare plans" still compares.
	$floor_rank = ( ! $item && ! $ctx && $rank >= untangling_plan_rank( 'Commerce' ) ) ? $rank - 1 : $rank;
	$mkt        = 'plugin' === $type ? 'plugins' : 'themes';
	$claim      = untangling_claim_domain();
	// The domain is the offer, and it needs a paid plan — so the Free column
	// would be a dead end here. Lift the floor past it and let the cards show
	// what the domain actually comes with.
	if ( $claim && $floor_rank < untangling_plan_rank( 'Personal' ) ) {
		$floor_rank = untangling_plan_rank( 'Personal' );
	}
	?>
	<div class="untangling-mkt-hero">
		<h1 class="untangling-mkt-brandfont"><?php echo esc_html( $ctx ? $ctx['title'] : __( 'There’s a plan for you' ) ); ?></h1>
		<p><?php
		if ( $ctx ) {
			// The need already named the plan in its own sentence, so this half
			// only has to say where the visitor is standing today.
			echo esc_html( $ctx['lede'] . ' ' . sprintf( __( 'You’re currently on the %s plan.' ), $plan ) );
		} else {
			echo esc_html( $item
				? sprintf( __( 'Unlock %1$s with the %2$s plan or higher. You’re currently on the %3$s plan.' ), $item['name'], $item['tier'], $plan )
				: sprintf( __( 'More storage, premium themes, and expert support as you grow. You’re currently on the %s plan.' ), $plan )
			);
		}
		?></p>
	</div>
	<div class="untangling-mkt-plans">
		<?php foreach ( untangling_plan_pricing() as $name => $info ) : ?>
			<?php
			$prank      = untangling_plan_rank( $name );
			$is_current = $name === $plan;
			// Show only the current plan and the plans that unlock the item —
			// tiers in between (e.g. Personal for a Premium theme) are noise.
			if ( $prank < $floor_rank || ( ( $item || $ctx ) && ! $is_current && $prank < $tier_rank ) ) {
				continue;
			}
			list( $price, $features ) = $info;
			$is_required = $prank === $tier_rank;
			$checkout_args = $item && $slug
				? array( 'ustep' => 'checkout', 'type' => $type, 'slug' => $slug, 'plan' => $name )
				: array( 'ustep' => 'checkout', 'plan' => $name );
			if ( isset( $_GET['flow'] ) && 'install' === $_GET['flow'] ) {
				$checkout_args['type'] = 'plugin';
				$checkout_args['flow'] = 'install';
				if ( ! empty( $_GET['pname'] ) ) {
					$checkout_args['pname'] = sanitize_text_field( wp_unslash( $_GET['pname'] ) );
				}
			}
			if ( $claim ) {
				$checkout_args['domain'] = $claim;
			}
			if ( ! empty( $_GET['back'] ) ) {
				$checkout_args['back'] = rawurlencode( wp_unslash( $_GET['back'] ) );
			}
			if ( $need ) {
				$checkout_args['need'] = $need;
			}
			// The My Site drawer enters with ctx=ms so its purchase lands in the
			// drawer's own namespaced plan, not the shared demo state.
			if ( isset( $_GET['ctx'] ) && 'ms' === $_GET['ctx'] ) {
				$checkout_args['ctx'] = 'ms';
			}
			$checkout = untangling_marketplace_url( $mkt, $checkout_args );
			?>
			<div class="untangling-mkt-plan<?php echo $is_current ? ' is-current' : ''; ?><?php echo $is_required && ! $is_current ? ' is-required' : ''; ?>">
				<?php // The current plan carries no pill: its CTA already reads "Current plan".
					// The wrapper still renders (fixed 24px) so every card's title stays aligned. ?>
				<div class="untangling-mkt-plan-badges">
					<?php if ( $is_required && ! $is_current ) : ?>
						<span class="untangling-mkt-plan-pill"><?php
						if ( $item ) {
							echo esc_html( sprintf( __( 'Unlocks %s' ), $item['name'] ) );
						} else {
							echo esc_html( $ctx && $ctx['pill'] ? $ctx['pill'] : __( 'Recommended' ) );
						}
						?></span>
					<?php endif; ?>
				</div>
				<h2 class="untangling-mkt-brandfont"><?php echo esc_html( $name ); ?></h2>
				<p class="untangling-mkt-plan-price"><sup>US$</sup><span><?php echo esc_html( $price ); ?></span><em>/<?php esc_html_e( 'month' ); ?></em></p>
				<?php if ( $is_current ) : ?>
					<span class="untangling-mkt-button is-disabled"><?php esc_html_e( 'Current plan' ); ?></span>
				<?php else : ?>
					<a class="untangling-mkt-button is-primary untangling-mkt-plan-cta is-plan-<?php echo esc_attr( strtolower( $name ) ); ?>" href="<?php echo esc_url( $checkout ); ?>"><?php echo esc_html( sprintf( __( 'Get %s' ), $name ) ); ?></a>
				<?php endif; ?>
				<ul>
					<?php foreach ( $features as $feature ) : ?>
						<li<?php echo ! empty( $feature[2] ) ? ' class="is-highlight"' : ''; ?>><span class="untangling-feature-tip" tabindex="0" data-tip="<?php echo esc_attr( $feature[1] ); ?>"><?php echo esc_html( $feature[0] ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endforeach; ?>
	</div>
	<script>
	// Same tooltip behavior as the WordPress.com page: the bubble opens
	// above where the cursor entered, then stays put.
	document.addEventListener( 'mouseover', function ( event ) {
		var tip = event.target && event.target.closest && event.target.closest( '.untangling-feature-tip' );
		if ( tip ) {
			tip.style.setProperty( '--untangling-tip-x', ( event.clientX - tip.getBoundingClientRect().left ) + 'px' );
		}
	} );
	</script>
	<?php
}

function untangling_marketplace_checkout_step( $plan, $type ) {
	$slug = isset( $_GET['slug'] ) ? sanitize_key( $_GET['slug'] ) : '';
	$item = untangling_marketplace_find_item( $type, $slug );
	if ( ! $item && $slug ) {
		echo '<div class="untangling-mkt-hero"><h1 class="untangling-mkt-brandfont">' . esc_html__( 'Item not found' ) . '</h1></div>';
		return;
	}
	// No slug = plan-only checkout (from the WordPress.com page plan card).
	// Core Add Plugins entries (flow=install) carry the wp.org plugin name so
	// the order summary lists it as included with the new plan.
	$install_name = '';
	if ( ! $item && isset( $_GET['flow'] ) && 'install' === $_GET['flow'] ) {
		$install_name = isset( $_GET['pname'] ) ? sanitize_text_field( wp_unslash( $_GET['pname'] ) ) : '';
	}
	$pricing  = untangling_plan_pricing();
	$new_plan = ( isset( $_GET['plan'] ) && isset( $pricing[ $_GET['plan'] ] ) ) ? $_GET['plan'] : ( $item ? $item['tier'] : 'Premium' );
	$mkt      = 'plugin' === $type ? 'plugins' : 'themes';

	// Storage add-on checkout (addon=storage&gb=N): the cart carries the
	// add-on instead of a plan change.
	$storage_pricing = untangling_storage_addon_pricing();
	$addon_gb        = ( isset( $_GET['addon'], $_GET['gb'] ) && 'storage' === $_GET['addon'] && isset( $storage_pricing[ (int) $_GET['gb'] ] ) ) ? (int) $_GET['gb'] : 0;

	// Two-year renewal checkout (flow=renew, the Atomic sidebar nudge): the
	// plan already owned, 24 months up front, 20% off — itemized in the cart.
	$is_renew       = ! $item && ! $addon_gb && isset( $_GET['flow'] ) && 'renew' === $_GET['flow'];
	$renew_discount = 0;

	$plan_price = $addon_gb ? $storage_pricing[ $addon_gb ] : $pricing[ $new_plan ][0];
	if ( $is_renew ) {
		$plan_price     = $pricing[ $new_plan ][0] * 24;
		$renew_discount = round( $plan_price * 0.2, 2 );
	}
	$item_price = $item && $item['price'] ? (float) $item['price'] : 0;
	$total      = $plan_price + $item_price - $renew_discount;
	$user       = wp_get_current_user();
	$is_ms      = isset( $_GET['ctx'] ) && 'ms' === $_GET['ctx'];
	// ctx=ms = the My Site drawer's flow: persist into its namespaced plan key.
	if ( $addon_gb ) {
		$done_args = array( 'ustep' => 'done', 'addon' => 'storage', 'gb' => $addon_gb );
		if ( $is_ms ) {
			$done_args['untangling_ms_add_storage'] = $addon_gb;
			$done_args['ctx']                       = 'ms';
		}
	} elseif ( $is_renew ) {
		// A renewal changes nothing about the plan — no override to persist.
		$done_args = array( 'ustep' => 'done', 'flow' => 'renew', 'plan' => $new_plan );
	} else {
		$done_args = $is_ms
			? array( 'ustep' => 'done', 'untangling_ms_set_plan' => $new_plan, 'ctx' => 'ms' )
			: array( 'ustep' => 'done', 'untangling_set_plan' => $new_plan );
	}
	if ( $item ) {
		$done_args['type'] = $type;
		$done_args['slug'] = $slug;
	} elseif ( $install_name ) {
		$done_args['type']  = 'plugin';
		$done_args['flow']  = 'install';
		$done_args['pname'] = $install_name;
	}
	$claim = untangling_claim_domain();
	if ( $claim ) {
		$done_args['domain'] = $claim;
	}
	if ( ! empty( $_GET['back'] ) ) {
		$done_args['back'] = rawurlencode( wp_unslash( $_GET['back'] ) );
	}
	$done = untangling_marketplace_url( $mkt, $done_args );
	?>
	<div class="untangling-mkt-checkout">
		<div>
			<h1 class="untangling-mkt-brandfont"><?php esc_html_e( 'Checkout' ); ?></h1>
			<div class="untangling-mkt-paywith">
				<span class="is-selected"><?php esc_html_e( 'Credit or debit card' ); ?></span>
				<span><?php esc_html_e( 'PayPal' ); ?></span>
			</div>
			<div class="untangling-mkt-field">
				<label><?php esc_html_e( 'Email' ); ?></label>
				<input type="email" value="<?php echo esc_attr( $user->user_email ); ?>" readonly>
			</div>
			<div class="untangling-mkt-field">
				<label><?php esc_html_e( 'Card number' ); ?></label>
				<input type="text" value="4242 4242 4242 4242" readonly>
			</div>
			<div class="untangling-mkt-field-row">
				<div class="untangling-mkt-field">
					<label><?php esc_html_e( 'Expiry date' ); ?></label>
					<input type="text" value="12 / 28" readonly>
				</div>
				<div class="untangling-mkt-field">
					<label><?php esc_html_e( 'Security code' ); ?></label>
					<input type="text" value="•••" readonly>
				</div>
			</div>
			<div class="untangling-mkt-field">
				<label><?php esc_html_e( 'Name on card' ); ?></label>
				<input type="text" value="<?php echo esc_attr( $user->display_name ); ?>" readonly>
			</div>
			<p class="untangling-mkt-paynote">
				<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M17 10h-1V7a4 4 0 0 0-8 0v3H7a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2zm-7-3a2 2 0 0 1 4 0v3h-4z"/></svg>
				<?php esc_html_e( 'Payments are secure and encrypted. This is a prototype — no payment will be processed.' ); ?>
			</p>
			<a class="untangling-mkt-button is-primary is-pay" href="<?php echo esc_url( $done ); ?>"><?php echo esc_html( sprintf( __( 'Pay US$%s' ), number_format_i18n( $total, 2 ) ) ); ?></a>
		</div>
		<aside class="untangling-mkt-summary">
			<h2><?php esc_html_e( 'Order summary' ); ?></h2>
			<div class="untangling-mkt-sumrow">
				<?php if ( $addon_gb ) : ?>
					<span class="who"><span><?php echo esc_html( sprintf( __( 'Storage add-on +%d GB' ), $addon_gb ) ); ?><small><?php esc_html_e( 'Per month, billed yearly' ); ?></small></span></span>
				<?php elseif ( $is_renew ) : ?>
					<span class="who"><span><?php echo esc_html( sprintf( __( 'WordPress.com %s — renewal' ), $new_plan ) ); ?><small><?php esc_html_e( 'Two years, billed once' ); ?></small></span></span>
				<?php else : ?>
					<span class="who"><span><?php echo esc_html( sprintf( __( 'WordPress.com %s' ), $new_plan ) ); ?><small><?php esc_html_e( 'Billed monthly' ); ?></small></span></span>
				<?php endif; ?>
				<span><?php echo esc_html( 'US$' . number_format_i18n( $plan_price, 2 ) ); ?></span>
			</div>
			<?php if ( $is_renew ) : ?>
				<div class="untangling-mkt-sumrow">
					<span class="who"><span><?php esc_html_e( 'Two-year renewal discount' ); ?><small><?php esc_html_e( '20% off, applied to this order' ); ?></small></span></span>
					<span class="untangling-mkt-sumfree"><?php echo esc_html( '−US$' . number_format_i18n( $renew_discount, 2 ) ); ?></span>
				</div>
			<?php endif; ?>
			<?php if ( $item ) : ?>
				<div class="untangling-mkt-sumrow">
					<span class="who">
						<?php if ( 'plugin' === $type ) : ?><img src="<?php echo esc_url( $item['image'] ); ?>" alt=""><?php endif; ?>
						<span><?php echo esc_html( $item['name'] ); ?><small><?php echo 'plugin' === $type ? esc_html__( 'Marketplace plugin' ) : esc_html__( 'Theme' ); ?></small></span>
					</span>
					<span><?php echo $item_price ? esc_html( 'US$' . number_format_i18n( $item_price, 2 ) ) : esc_html__( 'Included' ); ?></span>
				</div>
			<?php elseif ( $install_name ) : ?>
				<div class="untangling-mkt-sumrow">
					<span class="who"><span><?php echo esc_html( $install_name ); ?><small><?php esc_html_e( 'Plugin' ); ?></small></span></span>
					<span><?php esc_html_e( 'Included' ); ?></span>
				</div>
			<?php endif; ?>
			<?php if ( $claim ) : ?>
				<div class="untangling-mkt-sumrow">
					<span class="who"><span><?php echo esc_html( $claim ); ?><small><?php esc_html_e( 'Domain registration, first year' ); ?></small></span></span>
					<span class="untangling-mkt-sumfree"><s><?php echo esc_html( 'US$' . number_format_i18n( untangling_claim_domain_price( $claim ), 2 ) ); ?></s> <?php esc_html_e( 'Free' ); ?></span>
				</div>
			<?php endif; ?>
			<div class="untangling-mkt-sumdivider"></div>
			<div class="untangling-mkt-sumtotal">
				<span><?php esc_html_e( 'Total due today' ); ?></span>
				<span><?php echo esc_html( 'US$' . number_format_i18n( $total, 2 ) ); ?></span>
			</div>
		</aside>
	</div>
	<?php
}

function untangling_marketplace_done_step( $type ) {
	$slug  = isset( $_GET['slug'] ) ? sanitize_key( $_GET['slug'] ) : '';
	$item  = untangling_marketplace_find_item( $type, $slug );
	$is_ms = isset( $_GET['ctx'] ) && 'ms' === $_GET['ctx'];
	// Already overridden by untangling_set_plan / untangling_ms_set_plan on this request.
	$plan  = $is_ms ? untangling_ms_get_plan() : untangling_get_plan();
	$addon_gb = ( isset( $_GET['addon'], $_GET['gb'] ) && 'storage' === $_GET['addon'] ) ? (int) $_GET['gb'] : 0;
	$is_renew = ! $item && isset( $_GET['flow'] ) && 'renew' === $_GET['flow'];
	$mkt   = 'plugin' === $type ? 'plugins' : 'themes';
	$install_name = '';
	if ( ! $item && isset( $_GET['flow'] ) && 'install' === $_GET['flow'] ) {
		$install_name = isset( $_GET['pname'] ) ? sanitize_text_field( wp_unslash( $_GET['pname'] ) ) : '';
	}
	?>
	<div class="untangling-mkt-done">
		<span class="untangling-mkt-done-check">
			<svg viewBox="0 0 24 24" width="32" height="32" aria-hidden="true"><path fill="currentColor" d="M9 16.2l-3.5-3.5L4 14.2 9 19 20 8l-1.5-1.5z"/></svg>
		</span>
		<h1 class="untangling-mkt-brandfont"><?php esc_html_e( 'You’re all set!' ); ?></h1>
		<p>
			<?php if ( $addon_gb ) : ?>
				<?php echo esc_html( sprintf( __( '%1$d GB of extra storage is now active on %2$s.' ), $addon_gb, get_bloginfo( 'name' ) ) ); ?>
			<?php elseif ( $is_renew ) : ?>
				<?php echo esc_html( sprintf( __( 'The %1$s plan is renewed for two more years on %2$s, with the 20%% discount applied.' ), $plan, get_bloginfo( 'name' ) ) ); ?>
			<?php else : ?>
				<?php echo esc_html( sprintf( __( 'The %1$s plan is now active on %2$s.' ), $plan, get_bloginfo( 'name' ) ) ); ?>
				<?php $claim = untangling_claim_domain(); ?>
				<?php if ( $claim ) : ?>
					<?php echo esc_html( sprintf( __( '%s is registered and set as your primary address — it can take a few minutes to start working everywhere.' ), $claim ) ); ?>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( $item ) : ?>
				<?php echo esc_html( sprintf( 'theme' === $type ? __( '%s is now included in your plan — head back to the Marketplace to activate it.' ) : __( '%s is now included in your plan — head back to the Marketplace to install it.' ), $item['name'] ) ); ?>
			<?php elseif ( $install_name ) : ?>
				<?php echo esc_html( sprintf( __( '%s is now included in your plan — head back to Add Plugins to install it.' ), $install_name ) ); ?>
			<?php endif; ?>
		</p>
		<div class="untangling-mkt-done-actions">
			<?php if ( $install_name ) : ?>
				<a class="untangling-mkt-button is-primary" href="<?php echo esc_url( admin_url( 'plugin-install.php' ) ); ?>"><?php esc_html_e( 'Back to Add Plugins' ); ?></a>
				<a class="untangling-mkt-button is-secondary" href="<?php echo esc_url( admin_url() ); ?>"><?php esc_html_e( 'Go to WP Admin' ); ?></a>
			<?php elseif ( $item ) : ?>
				<a class="untangling-mkt-button is-primary" href="<?php echo esc_url( untangling_marketplace_url( $mkt ) ); ?>"><?php esc_html_e( 'Back to Marketplace' ); ?></a>
				<a class="untangling-mkt-button is-secondary" href="<?php echo esc_url( admin_url() ); ?>"><?php esc_html_e( 'Go to WP Admin' ); ?></a>
			<?php elseif ( $is_renew ) : ?>
				<a class="untangling-mkt-button is-primary" href="<?php echo esc_url( wp_validate_redirect( ! empty( $_GET['back'] ) ? wp_unslash( $_GET['back'] ) : '', admin_url() ) ); ?>"><?php esc_html_e( 'Back to WP Admin' ); ?></a>
			<?php elseif ( $is_ms ) : ?>
				<?php
				// The ms-context exit follows the live variant: the drawer's
				// home is the My Site plan section, the dashboard variant's is
				// index.php (its widgets are the surface that upsold).
				$ms_is_dashboard = 'dashboard' === untangling_get_variant();
				$ms_fallback     = $ms_is_dashboard ? admin_url( 'index.php' ) : admin_url( 'admin.php?page=untangling-mysite&ms=plan' );
				$ms_label        = $ms_is_dashboard ? __( 'Back to Dashboard' ) : __( 'Back to My Site' );
				?>
				<a class="untangling-mkt-button is-primary" href="<?php echo esc_url( wp_validate_redirect( ! empty( $_GET['back'] ) ? wp_unslash( $_GET['back'] ) : '', $ms_fallback ) ); ?>"><?php echo esc_html( $ms_label ); ?></a>
				<a class="untangling-mkt-button is-secondary" href="<?php echo esc_url( admin_url() ); ?>"><?php esc_html_e( 'Go to WP Admin' ); ?></a>
			<?php else : ?>
				<a class="untangling-mkt-button is-primary" href="<?php echo esc_url( wp_validate_redirect( ! empty( $_GET['back'] ) ? wp_unslash( $_GET['back'] ) : '', untangling_plan_flow_home_url() ) ); ?>"><?php esc_html_e( 'Back to WordPress.com' ); ?></a>
				<a class="untangling-mkt-button is-secondary" href="<?php echo esc_url( admin_url() ); ?>"><?php esc_html_e( 'Go to WP Admin' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

// Copy and canned answers shared by the Support Assistant panel and the
// "Ask about your site" CTA on both Help & Learn surfaces. There is no model
// behind the prototype — these keyword-matched replies mimic what the Help
// Center returns for the questions a demo actually asks. Plan- and
// site-aware: gated features answer honestly about what the current plan
// includes, and the human handoff follows the same support ladder as the
// Help & Learn support cards (forums on Free, contact form on paid).
function untangling_help_panel_data() {
	$plan     = untangling_get_plan();
	$has_woo  = class_exists( 'WooCommerce' );
	$advanced = untangling_plan_rank( $plan ) >= 3; // Business and above: plugins, backups.
	$noun     = $has_woo ? __( 'store' ) : __( 'site' );

	$answers = array(
		array(
			'keys'  => array( 'domain', 'dns', 'nameserver', 'url', 'web address', 'site address' ),
			'text'  => __( 'You can register a new domain or connect one you already own. Open Domains in your dashboard and pick "Add a domain". A registration is live within minutes; a connection waits on DNS to spread, which can take up to 72 hours.' ),
			'links' => array(
				array( __( 'Set up your custom domain' ), 'https://wordpress.com/support/domains/' ),
				array( __( 'Connecting vs transferring a domain' ), 'https://wordpress.com/support/domain-connection-vs-domain-transfer/' ),
			),
		),
		array(
			'keys'  => array( 'slow', 'speed', 'performance', 'load time', 'cache', 'faster' ),
			'text'  => __( 'Three things usually explain it: oversized images, plugins you no longer use, and caching that is turned off. Resize your images, deactivate what you do not need, and leave the edge cache on. Tell me which page feels slow and I can narrow it down further.' ),
			'links' => array(
				array( __( 'Make your site faster' ), 'https://wordpress.com/support/site-speed/' ),
			),
		),
		array(
			'keys'  => array( 'plan', 'upgrade', 'price', 'cost', 'billing', 'refund', 'renew', 'subscription' ),
			/* translators: %s: current plan name. */
			'text'  => sprintf( __( 'You are on the %s plan. The Plans page compares what each tier adds — storage, plugins, themes, and the level of support. Upgrades are prorated against what you already paid, and refunds follow the WordPress.com refund policy.' ), $plan ),
			'links' => array(
				array( __( 'Compare plans' ), 'https://wordpress.com/pricing/' ),
				array( __( 'Refund policy' ), 'https://wordpress.com/support/manage-purchases/#refund-policy' ),
			),
		),
		array(
			'keys'  => array( 'email', 'mailbox', 'inbox', 'forwarding' ),
			'text'  => __( 'Custom email needs a domain on your account. Once you have one, open Emails and choose Professional Email, Google Workspace, or a free forward to an inbox you already use.' ),
			'links' => array(
				array( __( 'Email on WordPress.com' ), 'https://wordpress.com/support/emails/' ),
			),
		),
		array(
			'keys'  => array( 'backup', 'restore', 'rollback', 'lost my', 'deleted' ),
			'text'  => $advanced
				? __( 'Your site is backed up automatically, every day and on every change. Open Backups, pick a restore point, and restore the files, the database, or both. A restore never touches your other restore points.' )
				: __( 'Automated backups and one-click restores come with the Business plan and above. On your current plan you can still take a full copy at any time from Tools → Export, and I can walk you through that.' ),
			'links' => array(
				array( __( 'Backups and restores' ), 'https://wordpress.com/support/backups/' ),
				array( __( 'Export your content' ), 'https://wordpress.com/support/export/' ),
			),
		),
		array(
			'keys'  => array( 'theme', 'design', 'layout', 'color', 'font', 'header', 'footer' ),
			'text'  => __( 'Appearance → Marketplace lists every theme available to you, with a badge on the ones your plan already includes. Activating a theme never deletes your content, and you can switch back whenever you want. For smaller changes, the Site Editor covers colors, fonts, and templates.' ),
			'links' => array(
				array( __( 'Themes' ), 'https://wordpress.com/support/themes/' ),
				array( __( 'Use the Site Editor' ), 'https://wordpress.com/support/site-editor/' ),
			),
		),
		array(
			'keys'  => array( 'product', 'sell', 'payment', 'checkout', 'shipping', 'order', 'tax', 'woocommerce' ),
			'text'  => __( 'Products live under Products → Add new: title, price, image, and stock. Payments handles the gateway — Stripe or PayPal — and the WooCommerce settings cover tax and shipping rules per region.' ),
			'links' => array(
				array( __( 'Accept payments' ), 'https://wordpress.com/support/wordpress-editor/blocks/payments/accept-payments/' ),
				array( __( 'Sell products' ), 'https://wordpress.com/support/introduction-to-woocommerce/' ),
			),
		),
		array(
			'keys'  => array( 'traffic', 'seo', 'visitor', 'google', 'search engine', 'rank', 'audience', 'subscriber' ),
			'text'  => __( 'Start with the basics: a clear title and description on every page, a sitemap submitted to search engines, and links between related posts. Jetpack Stats shows which posts already bring people in, so you know what to write more of.' ),
			'links' => array(
				array( __( 'Optimize for search engines' ), 'https://wordpress.com/support/seo/' ),
				array( __( 'Get more traffic' ), 'https://wordpress.com/support/getting-more-views-and-traffic/' ),
			),
		),
		array(
			'keys'  => array( 'plugin', 'extension' ),
			'text'  => $advanced
				? __( 'Plugins → Marketplace covers both free WordPress.org plugins and paid ones. Install, activate, and you are done. If a plugin misbehaves, deactivate it first, then restore from a backup if anything is left behind.' )
				: __( 'Installing third-party plugins comes with the Business plan and above. Your current plan already includes the built-in Jetpack features: stats, forms, social sharing, spam protection, and site search.' ),
			'links' => array(
				array( __( 'Plugins on WordPress.com' ), 'https://wordpress.com/support/plugins/' ),
			),
		),
		array(
			'keys'  => array( 'password', 'log in', 'login', 'locked out', 'two-factor', '2fa', 'account' ),
			'text'  => __( 'For a lost password, use the reset link on the login screen — it emails a one-time link to the address on your account. If two-step authentication is on and the device is gone, your backup codes get you in.' ),
			'links' => array(
				array( __( 'Reset your password' ), 'https://wordpress.com/support/passwords/' ),
				array( __( 'Two-step authentication' ), 'https://wordpress.com/support/security/two-step-authentication/' ),
			),
		),
	);

	return array(
		'noun'        => $noun,
		/* translators: %s: "site" or "store". */
		'heading'     => sprintf( __( 'Ask about your %s' ), $noun ),
		'lede'        => $advanced
			? __( 'Describe what you need in your own words. The AI assistant answers first, and our team backs it up.' )
			: __( 'Describe what you need in your own words. The AI assistant answers right away, any time of day.' ),
		'placeholder' => $has_woo ? __( 'How do I add a product?' ) : __( 'How do I connect a custom domain?' ),
		'panelHint'   => $has_woo ? __( 'Ask anything about your store' ) : __( 'Ask anything about your site' ),
		'cta'         => __( 'Ask' ),
		// Shown one after another under the dots while the answer is being
		// worked out — the prototype's stand-in for a model thinking.
		'steps'       => array( __( 'Reading your site setup' ), __( 'Checking WordPress.com docs' ), __( 'Writing an answer' ) ),
		'handoffLead' => __( 'Not what you needed?' ),
		'handoff'     => $advanced
			? array( __( 'Contact support' ), 'https://wordpress.com/help/contact/' )
			: array( __( 'Ask in the forums' ), 'https://wordpress.com/forums/' ),
		'answers'     => $answers,
		'fallback'    => array(
			'text'  => __( 'I need a little more to work with. Tell me what you were doing and what you expected to happen, and I will point you at the exact steps. You can hand this to a person at any point.' ),
			'links' => array(
				array( __( 'Browse all guides' ), 'https://wordpress.com/support/guides/' ),
			),
		),
	);
}

// Help Center mimic — the Support Assistant panel (geometry from
// packages/help-center: 410×80vh, radius 16, bottom/right 50). The panel is a
// working conversation: a question typed here (or handed over from the
// Help & Learn CTA) posts a user bubble, runs the thinking states, then
// reveals a canned answer word by word. Behavior and data ship with the
// markup so every surface that prints the panel gets the same assistant.
function untangling_marketplace_help_panel() {
	$user = wp_get_current_user();
	$data = untangling_help_panel_data();
	?>
	<div class="untangling-mkt-help" hidden>
		<header>
			<button type="button" class="untangling-mkt-help-back" aria-label="<?php esc_attr_e( 'Back' ); ?>"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M14.6 7l-1.2-1L8 12l5.4 6 1.2-1-4.6-5z"/></svg></button>
			<span class="title"><?php esc_html_e( 'Support Assistant' ); ?></span>
			<span class="spacer"></span>
			<button type="button" aria-label="<?php esc_attr_e( 'More options' ); ?>"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12 8a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm0 5.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm0 5.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/></svg></button>
			<button type="button" class="untangling-mkt-help-close" aria-label="<?php esc_attr_e( 'Close' ); ?>"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M13 11.8l6.1-6.3-1-1-6.1 6.2-6.1-6.2-1 1 6.1 6.3-6.5 6.7 1 1 6.5-6.6 6.5 6.6 1-1z"/></svg></button>
		</header>
		<div class="untangling-mkt-help-body">
			<div class="untangling-mkt-help-scroll">
				<div class="untangling-mkt-help-intro">
					<svg viewBox="0 0 24 24" width="32" height="32" aria-hidden="true"><path fill="#3858e9" d="M12 2l2.2 7.8L22 12l-7.8 2.2L12 22l-2.2-7.8L2 12l7.8-2.2z"/></svg>
					<h3><?php echo esc_html( sprintf( __( 'Howdy %s' ), $user->display_name ) ); ?> 👋</h3>
					<p><?php esc_html_e( 'I’m your personal Support Assistant. I can help with any questions about your site or account.' ); ?></p>
				</div>
				<div class="untangling-mkt-help-thread" role="log" aria-live="polite" aria-label="<?php esc_attr_e( 'Conversation' ); ?>"></div>
			</div>
			<div class="untangling-mkt-help-foot">
				<form class="untangling-mkt-ask">
					<input type="text" placeholder="<?php echo esc_attr( $data['panelHint'] ); ?>" aria-label="<?php esc_attr_e( 'Ask the Support Assistant' ); ?>" autocomplete="off">
					<button type="submit" aria-label="<?php esc_attr_e( 'Send' ); ?>"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M12 4l6 6-1.4 1.4L13 7.8V20h-2V7.8l-3.6 3.6L6 10z"/></svg></button>
				</form>
				<p class="untangling-mkt-help-fine"><?php esc_html_e( 'You’re chatting with an AI assistant. Responses may be inaccurate.' ); ?> <a href="#"><?php esc_html_e( 'Learn more' ); ?> ↗</a></p>
			</div>
		</div>
	</div>
	<script>
	window.untanglingHelpData = <?php echo wp_json_encode( $data ); ?>;
	<?php echo untangling_help_panel_js(); ?>
	</script>
	<?php
}

// The assistant itself. Exposes window.untanglingHelp so any surface — the
// Marketplace "Need help?" link, the Help & Learn CTA, a React card — can
// open the panel, optionally with a question already in flight.
function untangling_help_panel_js() {
	return <<<'JS'
( function () {
	var help = document.querySelector( '.untangling-mkt-help' );
	if ( ! help || help.dataset.untanglingWired ) {
		return;
	}
	help.dataset.untanglingWired = '1';

	var data = window.untanglingHelpData || {};
	var answers = data.answers || [];
	var steps = data.steps || [ 'Thinking' ];
	var intro = help.querySelector( '.untangling-mkt-help-intro' );
	var thread = help.querySelector( '.untangling-mkt-help-thread' );
	var scroller = help.querySelector( '.untangling-mkt-help-scroll' );
	var form = help.querySelector( '.untangling-mkt-ask' );
	var input = form.querySelector( 'input' );
	var send = form.querySelector( 'button' );
	// One flag for the whole animation budget: reduced motion skips the
	// thinking beats and the word-by-word reveal, answering at once.
	var calm = window.matchMedia && window.matchMedia( '( prefers-reduced-motion: reduce )' ).matches;
	var busy = false;

	function toEnd() {
		scroller.scrollTop = scroller.scrollHeight;
	}

	function node( tag, className, text ) {
		var element = document.createElement( tag );
		if ( className ) {
			element.className = className;
		}
		if ( text ) {
			element.textContent = text;
		}
		return element;
	}

	function avatar() {
		var span = node( 'span', 'untangling-mkt-avatar' );
		span.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M12 2l2.2 7.8L22 12l-7.8 2.2L12 22l-2.2-7.8L2 12l7.8-2.2z"/></svg>';
		return span;
	}

	// First keyword hit wins, so the answer list is ordered by how specific
	// each topic is. Everything unmatched gets the fallback.
	function match( question ) {
		var text = question.toLowerCase();
		for ( var i = 0; i < answers.length; i++ ) {
			var keys = answers[ i ].keys || [];
			for ( var j = 0; j < keys.length; j++ ) {
				if ( -1 !== text.indexOf( keys[ j ] ) ) {
					return answers[ i ];
				}
			}
		}
		return data.fallback || { text: '', links: [] };
	}

	function addQuestion( text ) {
		var row = node( 'div', 'untangling-mkt-msg is-user' );
		row.appendChild( node( 'p', 'untangling-mkt-bubble', text ) );
		thread.appendChild( row );
		toEnd();
	}

	function addThinking() {
		var row = node( 'div', 'untangling-mkt-msg is-bot is-thinking' );
		row.appendChild( avatar() );
		var body = node( 'div', 'untangling-mkt-msg-body' );
		var dots = node( 'span', 'untangling-mkt-dots' );
		dots.innerHTML = '<i></i><i></i><i></i>';
		body.appendChild( dots );
		body.appendChild( node( 'span', 'untangling-mkt-step', steps[ 0 ] ) );
		row.appendChild( body );
		thread.appendChild( row );
		toEnd();
		return row;
	}

	// Reveal on a wall-clock budget rather than one word per tick: a per-tick
	// reveal stretched to ten seconds whenever the browser throttled the
	// timer. Progress is measured against elapsed time, so a coarse tick just
	// reveals a bigger chunk and the answer still lands in REVEAL_MS.
	// (setInterval, not requestAnimationFrame — frames stop altogether in a
	// backgrounded tab, which would leave an answer frozen mid-sentence.)
	var REVEAL_MS = 900;

	function reveal( target, text, done ) {
		var words = text.split( ' ' );
		if ( calm || words.length < 2 ) {
			target.textContent = text;
			done();
			return;
		}
		var start = Date.now();
		var tick = window.setInterval( function () {
			var progress = Math.min( 1, ( Date.now() - start ) / REVEAL_MS );
			target.textContent = words.slice( 0, Math.max( 1, Math.round( progress * words.length ) ) ).join( ' ' );
			toEnd();
			if ( progress >= 1 ) {
				window.clearInterval( tick );
				done();
			}
		}, 40 );
	}

	function addAnswer( answer, row ) {
		row.classList.remove( 'is-thinking' );
		var body = row.querySelector( '.untangling-mkt-msg-body' );
		body.textContent = '';
		var paragraph = node( 'p', 'untangling-mkt-bubble' );
		body.appendChild( paragraph );
		reveal( paragraph, answer.text || '', function () {
			var links = answer.links || [];
			if ( links.length ) {
				var list = node( 'span', 'untangling-mkt-msg-links' );
				links.forEach( function ( link ) {
					var anchor = node( 'a', null, link[ 0 ] + ' ↗' );
					anchor.href = link[ 1 ];
					anchor.target = '_blank';
					anchor.rel = 'noreferrer';
					list.appendChild( anchor );
				} );
				body.appendChild( list );
			}
			if ( data.handoff ) {
				var handoff = node( 'span', 'untangling-mkt-msg-handoff' );
				handoff.appendChild( node( 'span', null, data.handoffLead || '' ) );
				var contact = node( 'a', null, data.handoff[ 0 ] );
				contact.href = data.handoff[ 1 ];
				contact.target = '_blank';
				contact.rel = 'noreferrer';
				handoff.appendChild( contact );
				body.appendChild( handoff );
			}
			busy = false;
			send.disabled = false;
			toEnd();
		} );
	}

	function ask( text ) {
		var question = ( text || '' ).trim();
		if ( ! question || busy ) {
			return;
		}
		busy = true;
		send.disabled = true;
		input.value = '';
		if ( intro ) {
			intro.hidden = true;
		}
		help.classList.add( 'is-chatting' );
		addQuestion( question );
		var row = addThinking();
		var label = row.querySelector( '.untangling-mkt-step' );
		var at = 1;
		var beat = window.setInterval( function () {
			if ( at >= steps.length ) {
				window.clearInterval( beat );
				return;
			}
			label.textContent = steps[ at ];
			at += 1;
			toEnd();
		}, 750 );
		window.setTimeout( function () {
			window.clearInterval( beat );
			addAnswer( match( question ), row );
		}, calm ? 200 : 750 * steps.length + 250 );
	}

	function reset() {
		thread.textContent = '';
		help.classList.remove( 'is-chatting' );
		if ( intro ) {
			intro.hidden = false;
		}
		busy = false;
		send.disabled = false;
	}

	window.untanglingHelp = {
		open: function ( text ) {
			help.hidden = false;
			if ( text ) {
				ask( text );
			} else {
				input.focus();
			}
		},
		close: function () {
			help.hidden = true;
		},
		ask: ask,
		reset: reset,
	};

	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();
		ask( input.value );
	} );
	help.querySelector( '.untangling-mkt-help-back' ).addEventListener( 'click', function () {
		if ( help.classList.contains( 'is-chatting' ) ) {
			reset();
		} else {
			help.hidden = true;
		}
	} );
	help.querySelector( '.untangling-mkt-help-close' ).addEventListener( 'click', function () {
		help.hidden = true;
	} );
	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key ) {
			help.hidden = true;
		}
	} );
} )();
JS;
}

// Support Assistant panel styles, shared by the Marketplace and Hosting
// pages (geometry from packages/help-center: 410 × 80vh, max 800, radius 16,
// right/bottom 50). Self-contained: grays and font are declared on the panel
// itself so it works outside the .untangling-mkt scope.
function untangling_help_panel_css() {
	return <<<'CSS'
	.untangling-mkt-help { --mkt-gray-0: #f6f7f7; --mkt-gray-10: #c3c4c7; --mkt-gray-50: #646970; --mkt-gray-60: #50575e; --mkt-gray-80: #2c3338; --mkt-gray-100: #101517; --mkt-blue: #3858e9; --mkt-blue-tint: #ebeefc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; position: fixed; right: 50px; bottom: 50px; width: 410px; max-width: calc( 100vw - 32px ); height: 80vh; max-height: 800px; background: #fff; border-radius: 16px; box-shadow: 0 3px 8px rgba(0,0,0,0.12), 0 12px 32px rgba(0,0,0,0.14); z-index: 999990; display: flex; flex-direction: column; overflow: hidden; }
	.untangling-mkt-help[hidden] { display: none; }
	.untangling-mkt-help header { height: 56px; display: flex; align-items: center; gap: 4px; padding: 0 12px; border-bottom: 1px solid var(--mkt-gray-0); flex-shrink: 0; }
	.untangling-mkt-help header .title { font-size: 16px; font-weight: 500; color: var(--mkt-gray-100); margin-left: 4px; }
	.untangling-mkt-help header .spacer { flex: 1; }
	.untangling-mkt-help header button { background: none; border: 0; padding: 8px; cursor: pointer; color: var(--mkt-gray-100); display: inline-flex; }
	.untangling-mkt-help-body { flex: 1; min-height: 0; display: flex; flex-direction: column; }
	/* Greeting sits at the bottom of an empty panel, the way the real Help
	   Center opens; once a conversation starts the thread grows downward. */
	.untangling-mkt-help-scroll { flex: 1; min-height: 0; overflow-y: auto; display: flex; flex-direction: column; justify-content: flex-end; gap: 16px; padding: 24px 24px 8px; }
	.untangling-mkt-help.is-chatting .untangling-mkt-help-scroll { justify-content: flex-start; }
	.untangling-mkt-help-intro[hidden] { display: none; }
	.untangling-mkt-help-intro h3 { font-size: 16px; font-weight: 600; margin: 16px 0 8px; color: var(--mkt-gray-100); }
	.untangling-mkt-help-intro p { font-size: 16px; margin: 0; color: var(--mkt-gray-80); }
	.untangling-mkt-help-thread { display: flex; flex-direction: column; gap: 16px; }
	.untangling-mkt-help-thread:empty { display: none; }
	.untangling-mkt-msg { display: flex; align-items: flex-start; gap: 8px; }
	.untangling-mkt-msg.is-user { justify-content: flex-end; }
	.untangling-mkt-msg.is-user .untangling-mkt-bubble { max-width: 85%; margin: 0; padding: 8px 12px; border-radius: 12px 12px 2px 12px; background: var(--mkt-blue-tint); color: var(--mkt-gray-100); font-size: 14px; line-height: 1.5; }
	.untangling-mkt-avatar { flex: none; width: 24px; height: 24px; margin-top: 2px; border-radius: 999px; background: var(--mkt-blue-tint); color: var(--mkt-blue); display: inline-flex; align-items: center; justify-content: center; }
	.untangling-mkt-msg-body { min-width: 0; display: flex; flex-direction: column; gap: 8px; }
	.untangling-mkt-msg-body .untangling-mkt-bubble { margin: 0; font-size: 14px; line-height: 1.6; color: var(--mkt-gray-80); }
	.untangling-mkt-msg.is-thinking .untangling-mkt-msg-body { flex-direction: row; align-items: center; gap: 8px; }
	.untangling-mkt-step { font-size: 13px; color: var(--mkt-gray-50); }
	.untangling-mkt-dots { display: inline-flex; align-items: center; gap: 4px; height: 20px; }
	.untangling-mkt-dots i { width: 6px; height: 6px; border-radius: 999px; background: var(--mkt-blue); animation: untangling-mkt-dot 1.2s ease-in-out infinite; }
	.untangling-mkt-dots i:nth-child(2) { animation-delay: 0.15s; }
	.untangling-mkt-dots i:nth-child(3) { animation-delay: 0.3s; }
	@keyframes untangling-mkt-dot { 0%, 80%, 100% { opacity: 0.25; transform: translateY(0); } 40% { opacity: 1; transform: translateY(-2px); } }
	@media ( prefers-reduced-motion: reduce ) { .untangling-mkt-dots i { animation: none; opacity: 0.6; } }
	.untangling-mkt-msg-links { display: flex; flex-direction: column; gap: 4px; }
	.untangling-mkt-msg-links a { font-size: 13px; color: var(--mkt-blue); text-decoration: none; }
	.untangling-mkt-msg-links a:hover { text-decoration: underline; }
	.untangling-mkt-msg-handoff { display: flex; flex-wrap: wrap; align-items: baseline; gap: 6px; margin-top: 4px; padding-top: 8px; border-top: 1px solid var(--mkt-gray-0); font-size: 12px; color: var(--mkt-gray-50); }
	.untangling-mkt-msg-handoff a { font-size: 12px; color: var(--mkt-blue); }
	/* Composer pinned below the scroller so the input never scrolls away. */
	.untangling-mkt-help-foot { flex: none; padding: 8px 24px 24px; background: #fff; }
	.untangling-mkt-ask { display: flex; align-items: center; border: 1px solid var(--mkt-gray-10); border-radius: 12px; padding: 8px 8px 8px 16px; gap: 8px; }
	.untangling-mkt-ask:focus-within { border-color: var(--mkt-blue); box-shadow: 0 0 0 1px var(--mkt-blue); }
	/* :focus included so wp-admin's own input focus ring does not draw a
	   second box inside the composer's rounded one. */
	.untangling-mkt-ask input,
	.untangling-mkt-ask input:focus { border: 0; outline: 0; box-shadow: none; flex: 1; font-size: 14px; color: var(--mkt-gray-100); background: none; }
	.untangling-mkt-ask button { width: 32px; height: 32px; border-radius: 50%; border: 0; background: var(--mkt-gray-0); color: var(--mkt-gray-60); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
	.untangling-mkt-ask button:hover:not(:disabled) { background: var(--mkt-blue); color: #fff; }
	.untangling-mkt-ask button:disabled { cursor: default; opacity: 0.5; }
	.untangling-mkt-help-fine { font-size: 12px; color: var(--mkt-gray-50); text-align: center; margin: 12px 0 0; }
	.untangling-mkt-help-fine a { color: var(--mkt-gray-50); }
CSS;
}

// The Hosting page and the My Site drawer get the same Support Assistant
// panel as the Marketplace, behavior included — the Help & Learn CTA on both
// pages hands its question to window.untanglingHelp.open().
add_action( 'admin_footer', function () {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || ! in_array( $screen->id, array( 'toplevel_page_untangling-hosting', 'toplevel_page_untangling-mysite' ), true ) ) {
		return;
	}
	echo '<style>' . untangling_help_panel_css() . '</style>';
	untangling_marketplace_help_panel();
} );

// Values traced from production: step-container-v2 (top bar, Recoleta
// heading scale), theme showcase + plugins marketplace (1220px content,
// pills, card grids), plans-grid-next (280px plan columns), help-center
// (410px panel). Studio grays / WP blue throughout.
/**
 * @param bool $chromeless Whether the caller is the fullscreen Marketplace
 *                        page, which strips wp-admin's chrome. The in-admin
 *                        Themes screen (V2 split) reuses the same catalog
 *                        styles inside the normal admin layout, so it must
 *                        not inherit those resets.
 */
function untangling_marketplace_styles( $chromeless = true ) {
	?>
	<style>
	@font-face {
		font-display: swap;
		font-family: Recoleta;
		font-weight: 400;
		src: url(https://s1.wp.com/i/fonts/recoleta/400.woff2) format("woff2"), url(https://s1.wp.com/i/fonts/recoleta/400.woff) format("woff");
	}
	</style>
	<?php if ( $chromeless ) : ?>
	<style>
	/* Chromeless: this page hides every piece of wp-admin chrome. */
	body.toplevel_page_untangling-marketplace #adminmenumain,
	body.toplevel_page_untangling-marketplace #adminmenuback,
	body.toplevel_page_untangling-marketplace #wpadminbar,
	body.toplevel_page_untangling-marketplace #wpfooter,
	body.toplevel_page_untangling-marketplace #screen-meta,
	body.toplevel_page_untangling-marketplace #screen-meta-links,
	body.toplevel_page_untangling-marketplace .notice,
	body.toplevel_page_untangling-marketplace .update-nag { display: none !important; }
	html.wp-toolbar { padding-top: 0 !important; }
	body.toplevel_page_untangling-marketplace { background: #fff; }
	body.toplevel_page_untangling-marketplace #wpcontent { margin-left: 0 !important; padding: 0 !important; }
	body.toplevel_page_untangling-marketplace #wpbody-content { padding-bottom: 0 !important; float: none; }
	</style>
	<?php endif; ?>
	<style>
	.untangling-mkt {
		--mkt-gray-0: #f6f7f7; --mkt-gray-5: #dcdcde; --mkt-gray-10: #c3c4c7;
		--mkt-gray-50: #646970; --mkt-gray-60: #50575e; --mkt-gray-80: #2c3338; --mkt-gray-100: #101517;
		--mkt-blue: #3858e9; --mkt-blue-active: #2e49d9; --mkt-blue-tint: #ebeefc;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
		font-size: 14px; line-height: 1.5; color: var(--mkt-gray-80);
		-webkit-font-smoothing: antialiased;
		min-height: 100vh; display: flex; flex-direction: column;
	}
	.untangling-mkt-brandfont { font-family: Recoleta, "Noto Serif", Georgia, "Times New Roman", Times, serif; font-weight: 400; letter-spacing: -0.4px; }

	/* Top bar — 24px content + 16px padding = 56px, inline 24px ≥600px. */
	.untangling-mkt-topbar { display: flex; align-items: center; gap: 16px; width: 100%; padding: 16px; box-sizing: border-box; position: sticky; top: 0; background: #fff; z-index: 100; border-bottom: 1px solid transparent; }
	@media ( min-width: 600px ) { .untangling-mkt-topbar { padding-inline: 24px; } }
	.untangling-mkt-brand { display: inline-flex; align-items: center; gap: 8px; height: 24px; color: var(--mkt-gray-100); text-decoration: none; font-size: 15px; font-weight: 500; }
	.untangling-mkt-switch { display: flex; gap: 8px; margin-left: 16px; align-self: stretch; align-items: center; }
	.untangling-mkt-switch a { display: inline-flex; align-items: center; height: 24px; padding: 0 10px; font-size: 14px; font-weight: 500; color: var(--mkt-gray-60); text-decoration: none; position: relative; }
	.untangling-mkt-switch a:hover { color: var(--mkt-gray-100); }
	.untangling-mkt-switch a.is-active { color: var(--mkt-blue); }
	.untangling-mkt-switch a.is-active::after { content: ""; position: absolute; left: 10px; right: 10px; bottom: -16px; height: 3px; background: var(--mkt-blue); }
	.untangling-mkt-back { display: inline-flex; align-items: center; gap: 4px; font-size: 14px; font-weight: 500; line-height: 1; text-decoration: underline; color: var(--mkt-gray-100); background: none; border: 0; padding: 0; cursor: pointer; }
	.untangling-mkt-topbar-right { margin-left: auto; display: flex; align-items: center; gap: 16px; font-size: 14px; line-height: 1; }
	.untangling-mkt-help-toggle { background: none; border: 0; padding: 0; cursor: pointer; font-size: 14px; font-weight: 500; text-decoration: underline; color: var(--mkt-gray-100); }
	.untangling-mkt-help-toggle:hover, .untangling-mkt-exit:hover, .untangling-mkt-back:hover { color: var(--mkt-blue); }
	.untangling-mkt-topbar-divider { width: 1px; height: 20px; background: var(--mkt-gray-5); }
	.untangling-mkt-exit { display: inline-flex; color: var(--mkt-gray-100); }

	/* Content shell — both catalogs share the showcase width (1220px) so the
	   Themes ↔ Plugins switch keeps the exact same container. */
	.untangling-mkt-main { width: 100%; max-width: 1268px; margin: 0 auto; padding: 32px 24px 96px; box-sizing: border-box; flex: 1; }

	/* Hero — onboarding heading scale: Recoleta 32/1.25 → 44/1.15 ≥960px.
	   Unconstrained width so title and subtitle each stay on one line. */
	.untangling-mkt-hero { margin: 0 auto; text-align: center; }
	.untangling-mkt-hero h1 { font-size: 32px; line-height: 1.25; font-weight: 400; color: var(--mkt-gray-100); margin: 0; }
	@media ( min-width: 960px ) { .untangling-mkt-hero h1 { font-size: 44px; line-height: 1.15; } }
	.untangling-mkt-hero p { font-size: 14px; max-width: 660px; margin: 8px auto 0; color: var(--mkt-gray-80); text-wrap: balance; }
	@media ( min-width: 960px ) { .untangling-mkt-hero p { font-size: 16px; } }
	/* 3rem heading→content gap, like the stepper's ContentWrapper. */
	.untangling-mkt-search { position: relative; max-width: 680px; margin: 48px auto 0; color: var(--mkt-gray-50); }
	.untangling-mkt-search svg { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); }
	.untangling-mkt-search input { width: 100%; height: 56px; box-sizing: border-box; padding: 0 16px 0 48px; border: 1px solid var(--mkt-gray-10); border-radius: 4px; font-size: 16px; color: var(--mkt-gray-100); background: #fff; }
	.untangling-mkt-search input:focus { border-color: var(--mkt-blue); box-shadow: 0 0 0 1px var(--mkt-blue); outline: none; }

	.untangling-mkt-catalog { display: none; }
	.untangling-mkt-catalog.is-active { display: block; }

	/* Category pills — 44px, #f2f2f2 resting, gray-80 selected (production
	   segmented control / category pills). */
	/* Filter bar — production showcase: category scroller capped at the search
	   width, chevrons on white gradients, and the View plan dropdown. */
	.untangling-mkt-filterbar { display: flex; align-items: center; gap: 16px; max-width: 680px; margin: 32px auto 48px; }
	.untangling-mkt-pillscroll { position: relative; flex: 1; min-width: 0; }
	.untangling-mkt-pills { display: flex; gap: 12px; flex-wrap: nowrap; overflow-x: auto; margin: 0; scrollbar-width: none; -ms-overflow-style: none; }
	.untangling-mkt-pills::-webkit-scrollbar { display: none; }
	.untangling-mkt-pills button { height: 44px; padding: 0 16px; border-radius: 4px; border: 0; background: #f2f2f2; color: var(--mkt-gray-80); font-size: 14px; letter-spacing: -0.15px; cursor: pointer; white-space: nowrap; flex-shrink: 0; }
	.untangling-mkt-pills button:hover { background: var(--mkt-gray-5); }
	.untangling-mkt-pills button.is-active { background: var(--mkt-gray-80); color: #fff; }
	.untangling-mkt-pillnav { position: absolute; top: 0; bottom: 0; width: 56px; padding: 0; border: 0; cursor: pointer; z-index: 1; display: flex; align-items: center; color: var(--mkt-gray-100); background: transparent; }
	.untangling-mkt-pillnav[hidden] { display: none; }
	.untangling-mkt-pillnav.is-prev { left: 0; justify-content: flex-start; background: linear-gradient( to right, #fff 40%, rgba( 255, 255, 255, 0 ) ); }
	.untangling-mkt-pillnav.is-next { right: 0; justify-content: flex-end; background: linear-gradient( to left, #fff 40%, rgba( 255, 255, 255, 0 ) ); }
	.untangling-mkt-view { display: flex; flex-direction: column; justify-content: center; gap: 2px; position: relative; border: 1px solid var(--mkt-gray-10, #c3c4c7); border-radius: 4px; padding: 6px 12px; background: #fff; cursor: pointer; flex-shrink: 0; }
	.untangling-mkt-view:focus-within { border-color: var(--mkt-blue); box-shadow: 0 0 0 1px var(--mkt-blue); }
	.untangling-mkt-view > span { font-size: 12px; color: var(--mkt-gray-50); line-height: 1; }
	/* #wpcontent beats a class chain, and core styles selects (border, arrow
	   image, min-height) — match its specificity to fully unstyle ours. */
	#wpcontent .untangling-mkt-view select { appearance: none; -webkit-appearance: none; border: 0; border-radius: 0; background: transparent none; box-shadow: none; min-height: 0; height: auto; max-width: none; font-size: 14px; font-weight: 500; line-height: 1.3; color: var(--mkt-gray-100); margin: 0; padding: 0 20px 0 0; cursor: pointer; outline: none; vertical-align: baseline; }
	.untangling-mkt-view > svg { position: absolute; right: 10px; top: 50%; transform: translateY( -50% ); pointer-events: none; color: var(--mkt-gray-80); }

	/* Theme cards — 74% ratio shot, radius 8, modern showcase shadow. */
	.untangling-mkt-theme-grid { display: grid; grid-template-columns: repeat( auto-fill, minmax( 300px, 1fr ) ); gap: 48px 32px; }
	.untangling-mkt-theme-card { min-width: 0; }
	.untangling-mkt-shot { position: relative; padding-top: 74%; overflow: hidden; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.25); background: var(--mkt-gray-0); }
	.untangling-mkt-theme-card.is-current .untangling-mkt-shot { box-shadow: 0 0 0 2px var(--mkt-blue), 0 1px 4px rgba(0,0,0,0.15); }
	.untangling-mkt-shot img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: top; transition: transform 0.3s; }
	.untangling-mkt-theme-card:hover .untangling-mkt-shot img { transform: scale(1.03); }
	.untangling-mkt-shot-overlay { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; background: rgba(0,0,0,0.5); opacity: 0; transition: opacity 0.2s; }
	.untangling-mkt-theme-card:hover .untangling-mkt-shot-overlay { opacity: 1; }
	.untangling-mkt-shot-cta { background: var(--mkt-blue); color: #fff; border-radius: 4px; padding: 10px 24px; font-size: 14px; font-weight: 500; text-decoration: none; width: 180px; text-align: center; box-sizing: border-box; }
	.untangling-mkt-shot-cta:hover { background: var(--mkt-blue-active); color: #fff; }
	.untangling-mkt-shot-cta.is-ghost { background: transparent; color: #fff; box-shadow: inset 0 0 0 1px #fff; }
	.untangling-mkt-shot-cta.is-ghost:hover { background: rgba(255,255,255,0.15); color: #fff; }
	.untangling-mkt-theme-info { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 16px; min-height: 24px; }
	.untangling-mkt-theme-info h3 { font-size: 16px; font-weight: 500; line-height: 24px; margin: 0; color: var(--mkt-gray-100); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
	.untangling-mkt-theme-info h3 a { color: inherit; text-decoration: none; }
	.untangling-mkt-theme-info h3 a:hover { color: var(--mkt-blue); }
	.untangling-mkt-theme-meta { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
	.untangling-mkt-pricenote { font-size: 12px; color: var(--mkt-gray-60); }
	.untangling-mkt-badge { display: inline-block; font-size: 12px; line-height: 20px; padding: 0 8px; border-radius: 4px; white-space: nowrap; }
	.untangling-mkt-badge.is-included, .untangling-mkt-badge.is-activebadge { background: var(--mkt-blue-tint); color: var(--mkt-blue); }
	.untangling-mkt-badge.is-tier { background: var(--mkt-gray-0); color: var(--mkt-gray-60); }

	/* Theme details — production wordpress.com/theme/{slug} layout. */
	.untangling-mkt-crumbs { display: flex; gap: 8px; font-size: 14px; color: var(--mkt-gray-50); margin: 8px 0 48px; }
	.untangling-mkt-crumbs a { color: var(--mkt-gray-50); text-decoration: none; }
	.untangling-mkt-crumbs a:hover { color: var(--mkt-blue); }
	.untangling-mkt-crumbs .is-current { color: var(--mkt-gray-100); }
	.untangling-mkt-detail { display: grid; grid-template-columns: 1fr; gap: 48px; }
	@media ( min-width: 960px ) { .untangling-mkt-detail { grid-template-columns: minmax( 0, 480px ) 1fr; gap: 64px; } }
	.untangling-mkt-detail-tierpill { display: inline-flex; align-items: center; gap: 6px; background: var(--mkt-gray-100); color: #fff; border-radius: 4px; font-size: 12px; font-weight: 500; line-height: 1; padding: 6px 10px; margin-bottom: 16px; }
	.untangling-mkt-detail-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
	.untangling-mkt-detail-head h1 { font-size: 44px; line-height: 1.1; margin: 0; font-weight: 400; color: var(--mkt-gray-100); }
	.untangling-mkt-detail-by { margin: 6px 0 0; font-size: 16px; color: var(--mkt-gray-60); }
	.untangling-mkt-detail-actions { display: flex; gap: 8px; align-items: center; }
	.untangling-mkt-detail-desc { margin-top: 40px; font-size: 16px; line-height: 1.65; color: var(--mkt-gray-80); }
	.untangling-mkt-detail-desc p { margin: 0 0 16px; }
	.untangling-mkt-detail-info h2 { font-size: 20px; font-weight: 500; margin: 40px 0 16px; color: var(--mkt-gray-100); }
	.untangling-mkt-detail-feats { display: flex; flex-wrap: wrap; gap: 8px 12px; }
	.untangling-mkt-detail-feats span { background: #f0f0f0; color: var(--mkt-gray-80); font-size: 14px; padding: 8px 14px; border-radius: 4px; }
	.untangling-mkt-detail-support { border: 1px solid var(--mkt-gray-5); border-radius: 8px; padding: 4px 24px; margin-top: 40px; }
	.untangling-mkt-detail-support-row { display: flex; justify-content: space-between; align-items: center; gap: 24px; padding: 20px 0; border-bottom: 1px solid #f0f0f0; }
	.untangling-mkt-detail-support-row:last-child { border-bottom: 0; }
	.untangling-mkt-detail-support-row h3 { font-size: 15px; font-weight: 500; margin: 0 0 4px; color: var(--mkt-gray-100); }
	.untangling-mkt-detail-support-row p { font-size: 14px; color: var(--mkt-gray-50); margin: 0; }
	.untangling-mkt-detail-support-row .untangling-mkt-button { flex-shrink: 0; }
	.untangling-mkt-detail-shot { align-self: start; position: sticky; top: 88px; }
	.untangling-mkt-detail-shot img { display: block; width: 100%; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.12); }

	/* Plugin details — wordpress.com/plugins/{slug}: info column + purchase box. */
	.untangling-mkt-plugdetail { display: grid; grid-template-columns: 1fr; gap: 48px; align-items: start; }
	@media ( min-width: 960px ) { .untangling-mkt-plugdetail { grid-template-columns: minmax( 0, 1fr ) 400px; gap: 64px; } }
	.untangling-mkt-plugdetail-head { display: flex; gap: 24px; align-items: center; }
	.untangling-mkt-plugdetail-head img { width: 72px; height: 72px; border-radius: 8px; flex-shrink: 0; }
	.untangling-mkt-plugdetail-head h1 { font-size: 40px; line-height: 1.1; margin: 0; font-weight: 400; color: var(--mkt-gray-100); }
	.untangling-mkt-plugdetail-tagline { margin: 28px 0 0; font-size: 17px; line-height: 1.6; color: var(--mkt-gray-80); }
	.untangling-mkt-plugdetail-tags { display: flex; gap: 8px; margin-top: 20px; }
	.untangling-mkt-plugdetail-tags span { background: #f0f0f0; color: var(--mkt-gray-80); font-size: 13px; padding: 5px 12px; border-radius: 4px; }
	.untangling-mkt-plugdetail-meta { display: flex; gap: 56px; margin-top: 28px; }
	.untangling-mkt-plugdetail-meta span { display: block; font-size: 13px; color: var(--mkt-gray-50); margin-bottom: 4px; }
	.untangling-mkt-plugdetail-meta strong { display: block; font-size: 15px; font-weight: 500; color: var(--mkt-gray-100); }
	.untangling-mkt-plugdetail-meta a { display: inline-block; margin-top: 4px; font-size: 14px; color: var(--mkt-blue); }
	.untangling-mkt-plugdetail-banner { margin-top: 32px; }
	.untangling-mkt-plugdetail-banner img { display: block; width: 100%; border-radius: 8px; }
	.untangling-mkt-plugdetail-info h2 { font-size: 20px; font-weight: 600; margin: 40px 0 12px; color: var(--mkt-gray-100); }
	.untangling-mkt-plugdetail-para { font-size: 16px; line-height: 1.65; color: var(--mkt-gray-80); margin: 0 0 16px; }
	.untangling-mkt-plugdetail-buy { background: #f6f7f7; border-radius: 8px; padding: 32px; position: sticky; top: 88px; }
	.untangling-mkt-plugdetail-price { margin: 0; }
	.untangling-mkt-plugdetail-price strong { font-size: 40px; font-weight: 400; color: var(--mkt-gray-100); }
	.untangling-mkt-plugdetail-price span { font-size: 14px; color: var(--mkt-gray-50); }
	.untangling-mkt-plugdetail-billing { display: flex; gap: 24px; margin-top: 16px; font-size: 14px; color: var(--mkt-gray-80); }
	.untangling-mkt-plugdetail-billing label { display: flex; align-items: center; gap: 6px; cursor: pointer; }
	.untangling-mkt-plugdetail-billing em { font-style: normal; color: #008a20; }
	.untangling-mkt-plugdetail-buy .untangling-mkt-button.is-block { display: flex; width: 100%; box-sizing: border-box; margin-top: 20px; }
	.untangling-mkt-plugdetail-buy hr { border: 0; border-top: 1px solid var(--mkt-gray-5); margin: 28px 0; }
	.untangling-mkt-plugdetail-buy h3 { font-size: 15px; font-weight: 600; margin: 24px 0 12px; color: var(--mkt-gray-100); }
	.untangling-mkt-plugdetail-buy ul { list-style: none; margin: 0; padding: 0; }
	.untangling-mkt-plugdetail-buy li { position: relative; padding-left: 26px; margin-bottom: 10px; font-size: 14px; color: var(--mkt-gray-80); }
	.untangling-mkt-plugdetail-buy li::before { content: "✓"; position: absolute; left: 0; color: #008a20; font-weight: 600; }
	.untangling-mkt-plugdetail-buy p { font-size: 14px; margin: 0 0 10px; color: var(--mkt-gray-80); }
	.untangling-mkt-plugdetail-buy a { color: var(--mkt-blue); text-decoration: none; }
	.untangling-mkt-plugdetail-buy a:hover { text-decoration: underline; }
	.untangling-mkt-plugdetail-supportline { color: var(--mkt-gray-80); }

	/* Plugins: spotlight, section head, 3-column cards (18px gutter). */
	.untangling-mkt-spotlight { display: flex; align-items: center; background: #fff; border: 1px solid var(--mkt-gray-5); border-radius: 5px; padding: 30px; }
	.untangling-mkt-spotlight img { height: 75px; width: 75px; }
	.untangling-mkt-spotlight-text { margin-left: 20px; display: flex; flex-direction: column; gap: 2px; }
	.untangling-mkt-spotlight-text span { font-size: 12px; font-weight: 600; color: var(--mkt-gray-50); }
	.untangling-mkt-spotlight-text strong { font-size: 16px; font-weight: 600; color: #3c434a; }
	.untangling-mkt-spotlight .untangling-mkt-button { margin-left: auto; }
	.untangling-mkt-section-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; padding: 24px 0; }
	.untangling-mkt-section-head h2 { font-size: 20px; line-height: 28px; font-weight: 500; margin: 0; color: var(--mkt-gray-80); }
	.untangling-mkt-section-head p { margin: 2px 0 0; font-size: 14px; color: var(--mkt-gray-60); }
	.untangling-mkt-browse-all { background: none; border: 0; padding: 0; color: var(--mkt-blue); font-size: 14px; cursor: pointer; text-decoration: underline; white-space: nowrap; }
	.untangling-mkt-plugin-grid { display: grid; grid-template-columns: 1fr; gap: 18px; }
	@media ( min-width: 960px ) { .untangling-mkt-plugin-grid { grid-template-columns: repeat( 2, 1fr ); } }
	@media ( min-width: 1280px ) { .untangling-mkt-plugin-grid { grid-template-columns: repeat( 3, 1fr ); } }
	.untangling-mkt-plugin-card { display: flex; flex-direction: column; background: #fff; border: 1px solid var(--mkt-gray-5); border-radius: 8px; padding: 24px; text-decoration: none; color: inherit; transition: border-color 0.15s; min-width: 0; }
	.untangling-mkt-plugin-card:hover { border-color: var(--mkt-blue); }
	.untangling-mkt-plugin-card.is-installed { cursor: default; }
	.untangling-mkt-plugin-card.is-installed:hover { border-color: var(--mkt-gray-5); }
	.untangling-mkt-chip { align-self: flex-start; font-size: 12px; line-height: 20px; padding: 0 8px; border-radius: 4px; margin-bottom: 16px; }
	.untangling-mkt-chip.is-included { background: var(--mkt-blue-tint); color: var(--mkt-blue); }
	.untangling-mkt-chip.is-tier { background: var(--mkt-gray-0); color: var(--mkt-gray-60); }
	.untangling-mkt-chip.is-installedchip { background: #abe8bc; color: #004533; }
	.untangling-mkt-plugin-head { display: flex; gap: 16px; align-items: flex-start; min-height: 52px; }
	.untangling-mkt-plugin-head img { width: 44px; height: 44px; border-radius: 4px; flex-shrink: 0; }
	.untangling-mkt-plugin-head h3 { font-size: 16px; font-weight: 500; line-height: 20px; margin: 0; color: var(--mkt-gray-100); }
	.untangling-mkt-plugin-by { font-size: 14px; line-height: 20px; color: var(--mkt-gray-60); }
	.untangling-mkt-plugin-by em { font-style: normal; color: var(--mkt-blue); }
	.untangling-mkt-plugin-desc { font-size: 14px; line-height: 20px; color: var(--mkt-gray-80); margin: 12px 0 24px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; min-height: 60px; }
	.untangling-mkt-plugin-foot { margin-top: auto; display: flex; justify-content: space-between; align-items: center; gap: 8px; }
	.untangling-mkt-plugin-price { font-size: 14px; font-weight: 500; color: var(--mkt-gray-80); }
	.untangling-mkt-plugin-price span { font-weight: 400; color: var(--mkt-gray-50); }
	.untangling-mkt-plugin-rating { font-size: 12px; font-weight: 500; color: var(--mkt-gray-80); }
	.untangling-mkt-plugin-rating span { font-weight: 400; color: var(--mkt-gray-60); }
	.untangling-mkt-empty { text-align: center; color: var(--mkt-gray-50); margin: 48px 0; font-size: 14px; }

	/* Buttons (stepper primary: 14px/500, radius 4). */
	.untangling-mkt-button { display: inline-flex; align-items: center; justify-content: center; border-radius: 4px; font-size: 14px; font-weight: 500; line-height: 1; padding: 12px 24px; text-decoration: none; cursor: pointer; border: 0; }
	.untangling-mkt-button.is-primary { background: var(--mkt-blue); color: #fff; }
	.untangling-mkt-button.is-primary:hover { background: var(--mkt-blue-active); color: #fff; }
	.untangling-mkt-button.is-secondary { background: #fff; color: var(--mkt-blue); box-shadow: inset 0 0 0 1px var(--mkt-blue); }
	.untangling-mkt-button.is-secondary:hover { background: var(--mkt-blue-tint); color: var(--mkt-blue); }
	.untangling-mkt-button.is-disabled { background: var(--mkt-gray-0); color: var(--mkt-gray-50); cursor: default; }

	/* Pricing — plans-grid-next: 280px columns, radius 8, #e0e0e0 border,
	   Recoleta plan names (32px) and prices (44px), badge pill. */
	/* Production-style joined grid: one row, cards flush with shared 1px
	   borders; only the highlighted plan keeps its own rounded blue ring. */
	.untangling-mkt-plans { display: flex; flex-wrap: nowrap; gap: 0; justify-content: center; margin-top: 48px; align-items: stretch; }
	.untangling-mkt-plan { flex: 1 1 0; min-width: 0; max-width: 300px; box-sizing: border-box; border: 1px solid #e0e0e0; border-radius: 0; margin-left: -1px; padding: 24px; display: flex; flex-direction: column; position: relative; background: #fff; }
	.untangling-mkt-plan:first-child { margin-left: 0; border-top-left-radius: 8px; border-bottom-left-radius: 8px; }
	.untangling-mkt-plan:last-child { border-top-right-radius: 8px; border-bottom-right-radius: 8px; }
	@media ( max-width: 1100px ) {
		.untangling-mkt-plans { flex-wrap: wrap; gap: 16px; }
		.untangling-mkt-plan { flex: 0 0 280px; margin-left: 0; border-radius: 8px; }
	}
	.untangling-mkt-plan .untangling-feature-tip { position: relative; cursor: default; }
	.untangling-mkt-plan .untangling-feature-tip::after { content: attr(data-tip); position: absolute; bottom: calc( 100% + 8px ); left: var(--untangling-tip-x, 50%); transform: translateX( -50% ); width: max-content; max-width: 300px; background: #1e1e1e; color: #f0f0f0; font-size: 12px; font-weight: 400; line-height: 1.4; padding: 4px 8px; border-radius: 2px; opacity: 0; pointer-events: none; transition: opacity 0.15s; z-index: 10; }
	.untangling-mkt-plan .untangling-feature-tip:hover::after,
	.untangling-mkt-plan .untangling-feature-tip:focus-visible::after { opacity: 1; }
	/* Onboarding-grid badges: static chips inside the card top, with the
	   row always reserved so plan names align across columns. */
	/* Fixed-height flex row: the inline pill otherwise adds baseline space
	   below itself, pushing badge-less cards 6px out of line. */
	.untangling-mkt-plan-badges { display: flex; align-items: flex-start; height: 24px; margin-bottom: 12px; }
	.untangling-mkt-plan-pill { display: inline-block; font-size: 12px; font-weight: 500; line-height: 24px; padding: 0 10px; border-radius: 4px; letter-spacing: 0.2px; background: var(--mkt-gray-80); color: #fff; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; box-sizing: border-box; }
	.untangling-mkt-plan.is-current .untangling-mkt-plan-pill { background: #e0e0e0; color: var(--mkt-gray-100); }
	.untangling-mkt-plan h2 { font-size: 32px; line-height: 1.2; margin: 8px 0 16px; color: var(--mkt-gray-100); }
	.untangling-mkt-plan-price { display: flex; align-items: flex-start; font-family: Recoleta, "Noto Serif", Georgia, serif; color: var(--mkt-gray-100); margin: 0 0 24px; line-height: 1; }
	.untangling-mkt-plan-price sup { font-size: 14px; margin-top: 6px; }
	.untangling-mkt-plan-price span { font-size: 44px; }
	.untangling-mkt-plan-price em { font-style: normal; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 12px; color: var(--mkt-gray-50); align-self: flex-end; margin: 0 0 4px 4px; }
	.untangling-mkt-sumfree s { color: var(--mkt-gray-50); margin-right: 6px; }
	.untangling-mkt-plan ul { list-style: none; margin: 16px 0 0; padding: 0; font-size: 14px; line-height: 20px; color: var(--mkt-gray-80); display: flex; flex-direction: column; gap: 8px; }
	.untangling-mkt-plan li.is-highlight { font-weight: 600; }
	/* Onboarding grid: the CTA sits right below the price, features follow. */
	.untangling-mkt-plan .untangling-mkt-button { width: 100%; box-sizing: border-box; }
	.untangling-mkt-plan-cta.is-plan-personal { background: #3858e9; }
	.untangling-mkt-plan-cta.is-plan-personal:hover { background: #2e49d9; }
	.untangling-mkt-plan-cta.is-plan-premium { background: #1d2db8; }
	.untangling-mkt-plan-cta.is-plan-premium:hover { background: #16249a; }
	.untangling-mkt-plan-cta.is-plan-business { background: #7f00d4; }
	.untangling-mkt-plan-cta.is-plan-business:hover { background: #6a00b2; }
	.untangling-mkt-plan-cta.is-plan-commerce { background: #9d37f2; }
	.untangling-mkt-plan-cta.is-plan-commerce:hover { background: #8722d8; }
	.untangling-mkt-plan-note { font-size: 12px; color: var(--mkt-gray-50); margin: 8px 0 0; text-align: center; }

	/* Checkout — stepper 8+4 columns: form + 432px summary, 4rem gap. */
	.untangling-mkt-checkout { display: grid; grid-template-columns: 1fr; gap: 48px; max-width: 1160px; margin: 24px auto 0; }
	@media ( min-width: 960px ) { .untangling-mkt-checkout { grid-template-columns: minmax( 0, 1fr ) 432px; gap: 64px; } }
	.untangling-mkt-checkout h1 { font-size: 44px; line-height: 1; margin: 0 0 24px; color: var(--mkt-gray-100); }
	.untangling-mkt-paywith { display: flex; gap: 8px; margin: 0 0 24px; }
	.untangling-mkt-paywith span { border: 1px solid var(--mkt-gray-5); border-radius: 4px; padding: 10px 16px; font-size: 14px; color: var(--mkt-gray-50); }
	.untangling-mkt-paywith span.is-selected { border-color: var(--mkt-blue); box-shadow: 0 0 0 1px var(--mkt-blue); color: var(--mkt-gray-100); font-weight: 500; }
	.untangling-mkt-field { margin-bottom: 16px; }
	.untangling-mkt-field label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 6px; color: var(--mkt-gray-80); }
	.untangling-mkt-field input { width: 100%; height: 44px; box-sizing: border-box; border: 1px solid var(--mkt-gray-10); border-radius: 4px; padding: 0 12px; font-size: 14px; color: var(--mkt-gray-100); background: #fff; }
	.untangling-mkt-field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
	.untangling-mkt-paynote { display: flex; gap: 8px; align-items: center; font-size: 12px; color: var(--mkt-gray-50); margin: 16px 0 24px; }
	.untangling-mkt-button.is-pay { width: 100%; box-sizing: border-box; padding: 16px 24px; font-size: 16px; }
	.untangling-mkt-summary { border: 1px solid var(--mkt-gray-5); border-radius: 8px; padding: 24px; align-self: start; background: #fff; }
	.untangling-mkt-summary h2 { font-size: 16px; font-weight: 500; margin: 0 0 16px; color: var(--mkt-gray-100); }
	.untangling-mkt-sumrow { display: flex; justify-content: space-between; align-items: center; gap: 16px; font-size: 14px; margin-bottom: 12px; }
	.untangling-mkt-sumrow .who { display: flex; gap: 8px; align-items: center; min-width: 0; }
	.untangling-mkt-sumrow img { width: 24px; height: 24px; border-radius: 4px; flex-shrink: 0; }
	.untangling-mkt-sumrow small { display: block; color: var(--mkt-gray-50); font-size: 12px; }
	.untangling-mkt-sumdivider { border-top: 1px solid var(--mkt-gray-5); margin: 16px 0; }
	.untangling-mkt-sumtotal { display: flex; justify-content: space-between; font-size: 16px; font-weight: 500; color: var(--mkt-gray-100); }

	/* Done */
	.untangling-mkt-done { text-align: center; max-width: 660px; margin: 96px auto 0; }
	.untangling-mkt-done-check { width: 64px; height: 64px; border-radius: 50%; background: #00ba37; display: inline-flex; align-items: center; justify-content: center; color: #fff; margin-bottom: 24px; }
	.untangling-mkt-done h1 { font-size: 44px; line-height: 1.15; margin: 0 0 16px; color: var(--mkt-gray-100); }
	.untangling-mkt-done p { font-size: 16px; color: var(--mkt-gray-80); margin: 0 0 32px; }
	.untangling-mkt-done-actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }

	<?php echo untangling_help_panel_css(); ?>

	</style>
	<?php
}

function untangling_marketplace_js() {
	?>
	<script>
	( function () {
		var root = document.querySelector( '.untangling-mkt' );
		if ( ! root ) {
			return;
		}

		// Instant Themes ↔ Plugins switch: both catalogs are pre-rendered, the
		// header tabs just swap them and update the URL (no reload).
		var switchLinks = root.querySelectorAll( '.untangling-mkt-switch a' );
		var catalogs = root.querySelectorAll( '.untangling-mkt-catalog' );
		switchLinks.forEach( function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				if ( ! catalogs.length ) {
					return;
				}
				event.preventDefault();
				var target = link.dataset.mkt;
				switchLinks.forEach( function ( other ) {
					other.classList.toggle( 'is-active', other === link );
					if ( other === link ) { other.setAttribute( 'aria-current', 'page' ); } else { other.removeAttribute( 'aria-current' ); }
				} );
				catalogs.forEach( function ( catalog ) {
					catalog.classList.toggle( 'is-active', catalog.dataset.catalog === target );
				} );
				root.dataset.mkt = target;
				window.history.replaceState( null, '', link.href );
				window.scrollTo( 0, 0 );
			} );
		} );

		// Per-catalog pills + search. Search overrides the pill filter, like
		// the production showcase.
		catalogs.forEach( function ( catalog ) {
			var pills = catalog.querySelectorAll( '.untangling-mkt-pills button' );
			var cards = catalog.querySelectorAll( '[data-name]' );
			var input = catalog.querySelector( '.untangling-mkt-search input' );
			var empty = catalog.querySelector( '.untangling-mkt-empty' );
			var extras = catalog.querySelectorAll( '.untangling-mkt-spotlight, .untangling-mkt-section-head' );
			var active = catalog.querySelector( '.untangling-mkt-pills .is-active' );
			var filter = active ? active.dataset.filter : 'all';
			var tierSelect = catalog.querySelector( '[data-tier-filter]' );
			var tier = 'all';

			function matches( card ) {
				var query = input && input.value ? input.value.toLowerCase().trim() : '';
				// The View plan filter applies on top of both search and pills.
				if ( 'all' !== tier && card.dataset.tier !== tier ) {
					return false;
				}
				if ( query ) {
					return card.dataset.name.indexOf( query ) !== -1;
				}
				if ( 'mine' === filter ) {
					return '1' === card.dataset.mine;
				}
				if ( 'recommended' === filter ) {
					return '1' === card.dataset.recommended;
				}
				if ( 'all' === filter ) {
					return true;
				}
				return ( card.dataset.subject || card.dataset.category ) === filter;
			}

			function apply() {
				var query = input && input.value ? input.value.trim() : '';
				var visible = 0;
				cards.forEach( function ( card ) {
					var show = matches( card );
					card.style.display = show ? '' : 'none';
					if ( show ) {
						visible++;
					}
				} );
				// The spotlight + section header only belong to the default view.
				extras.forEach( function ( extra ) {
					extra.style.display = ( query || 'all' !== filter || 'all' !== tier ) ? 'none' : '';
				} );
				if ( empty ) {
					empty.hidden = 0 !== visible;
				}
			}

			if ( tierSelect ) {
				tierSelect.addEventListener( 'change', function () {
					tier = tierSelect.value;
					apply();
				} );
			}

			// Category scroller: chevrons appear only when there is more to
			// scroll on that side (production showcase pattern).
			var scroller = catalog.querySelector( '.untangling-mkt-pills' );
			var navPrev = catalog.querySelector( '.untangling-mkt-pillnav.is-prev' );
			var navNext = catalog.querySelector( '.untangling-mkt-pillnav.is-next' );
			if ( scroller && navPrev && navNext ) {
				var updateNav = function () {
					navPrev.hidden = scroller.scrollLeft < 8;
					navNext.hidden = scroller.scrollLeft > scroller.scrollWidth - scroller.clientWidth - 8;
				};
				navPrev.addEventListener( 'click', function () { scroller.scrollBy( { left: -240, behavior: 'smooth' } ); } );
				navNext.addEventListener( 'click', function () { scroller.scrollBy( { left: 240, behavior: 'smooth' } ); } );
				scroller.addEventListener( 'scroll', updateNav, { passive: true } );
				window.addEventListener( 'resize', updateNav );
				updateNav();
			}

			pills.forEach( function ( pill ) {
				pill.addEventListener( 'click', function () {
					filter = pill.dataset.filter;
					pills.forEach( function ( other ) {
						other.classList.toggle( 'is-active', other === pill );
					} );
					if ( input ) {
						input.value = '';
					}
					apply();
				} );
			} );
			if ( input ) {
				input.addEventListener( 'input', apply );
			}
			var browseAll = catalog.querySelector( '[data-filter-jump]' );
			if ( browseAll ) {
				browseAll.addEventListener( 'click', function () {
					var all = catalog.querySelector( '.untangling-mkt-pills button[data-filter="all"]' );
					if ( all ) {
						all.click();
					}
				} );
			}
			apply();
		} );

		// Help Center mimic — close, Escape, and the conversation itself are
		// wired by untangling_help_panel_js(); this only opens it.
		var help = document.querySelector( '.untangling-mkt-help' );
		var helpToggle = document.querySelector( '.untangling-mkt-help-toggle' );
		if ( help && helpToggle ) {
			helpToggle.addEventListener( 'click', function () {
				if ( help.hidden ) {
					window.untanglingHelp.open();
				} else {
					window.untanglingHelp.close();
				}
			} );
			document.querySelectorAll( '[data-open-help]' ).forEach( function ( link ) {
				link.addEventListener( 'click', function ( event ) {
					event.preventDefault();
					window.untanglingHelp.open();
				} );
			} );
		}

	} )();
	</script>
	<?php
}

/* -------------------------------------------------------------------------
 * 3f. Split (V2): the production-Atomic themes experience. Appearance →
 * Themes stays core's installed-themes screen (banner-free), and a separate
 * Appearance → Theme Showcase item renders the catalog in the wp-admin
 * content area, mirroring the WordPress.com themes screen Atomic sites
 * already ship: page heading + "Install new theme", a search field with the
 * tier select, the category tabs, and the three-column grid with plan
 * badges. Cards, gating and badges come from untangling_theme_grid_cards();
 * filtering reuses untangling_marketplace_js(). Theme details render on this
 * same page inside the admin chrome (ustep=details); the fullscreen shell
 * only serves pricing/checkout/done (and plugin details).
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
	if ( 'split' !== untangling_get_marketplace_mode() ) {
		return;
	}
	// Production Atomic submenu order is Themes · Editor · Theme Showcase, so
	// core's own Themes item stays and the showcase sits after the Editor.
	add_submenu_page( 'themes.php', __( 'Themes' ), __( 'Theme Showcase' ), 'switch_themes', 'untangling-themes', 'untangling_render_themes_screen', 2 );
}, 12 );

// V2 (split) browses themes in-admin and renders theme details there too, so
// the fullscreen shell only serves pricing/checkout/done (and plugin
// details). Old fullscreen browse/details links (persisted URLs, a mid-flow
// version switch) land on the in-admin equivalents instead of a dead step.
// Headers are still open on admin_init; the page callback runs after
// admin-header.php, too late to redirect.
add_action( 'admin_init', function () {
	if ( 'split' !== untangling_get_marketplace_mode() ) {
		return;
	}
	if ( empty( $_GET['page'] ) || 'untangling-marketplace' !== $_GET['page'] ) {
		return;
	}
	$step = isset( $_GET['ustep'] ) ? $_GET['ustep'] : '';
	if ( in_array( $step, array( 'pricing', 'checkout', 'done' ), true ) ) {
		return;
	}
	if ( 'details' === $step ) {
		// Plugin details keep the fullscreen shell; theme details moved into
		// the admin chrome, so old fullscreen theme-details links follow.
		if ( isset( $_GET['type'] ) && 'plugin' === $_GET['type'] ) {
			return;
		}
		if ( ! empty( $_GET['slug'] ) ) {
			wp_safe_redirect( untangling_themes_screen_url( array( 'ustep' => 'details', 'slug' => sanitize_key( wp_unslash( $_GET['slug'] ) ) ) ) );
			exit;
		}
	}
	wp_safe_redirect( untangling_themes_screen_url() );
	exit;
} );

function untangling_themes_screen_url( $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => 'untangling-themes' ), $args ), admin_url( 'themes.php' ) );
}

function untangling_render_themes_screen() {
	$plan       = untangling_get_plan();
	$is_details = isset( $_GET['ustep'] ) && 'details' === $_GET['ustep'];
	untangling_marketplace_styles( false );
	?>
	<style>
	/* Production Theme Showcase sits on a white canvas, not the admin gray —
	   #wpwrap included, or its gray peeks out between content and footer. */
	body, #wpwrap, #wpcontent, #wpfooter { background: #fff; }
	.untangling-themes-screen { padding: 12px 24px 64px; }
	/* The catalog styles assume the fullscreen shell — undo the parts that
	   only make sense there and let the grid use the admin content width. */
	.untangling-themes-screen .untangling-mkt { min-height: 0; display: block; }
	.untangling-themes-screen .untangling-mkt-filterbar { max-width: none; margin: 0 0 32px; }
	.untangling-themes-screen .untangling-mkt-pillnav.is-prev { background: linear-gradient( to right, #fff 40%, rgba( 255, 255, 255, 0 ) ); }
	.untangling-themes-screen .untangling-mkt-pillnav.is-next { background: linear-gradient( to left, #fff 40%, rgba( 255, 255, 255, 0 ) ); }
	/* Production category tabs: resting = plain text, active = black pill. */
	.untangling-themes-screen .untangling-mkt-pills button { height: 36px; padding: 0 14px; border-radius: 9999px; background: transparent; }
	.untangling-themes-screen .untangling-mkt-pills button:hover { background: var(--mkt-gray-5); }
	.untangling-themes-screen .untangling-mkt-pills button.is-active { background: var(--mkt-gray-100); color: #fff; }
	/* Hover CTAs: one line, diamond and label centered together — both
	   buttons share the same 180px width from the shared styles. */
	.untangling-themes-screen .untangling-mkt-shot-cta { display: inline-flex; align-items: center; justify-content: center; white-space: nowrap; }
	/* Production cards: hairline border instead of the fullscreen drop shadow;
	   the active theme keeps its blue ring, and its badge is blue-filled. */
	.untangling-themes-screen .untangling-mkt-shot { box-shadow: none; border: 1px solid rgba( 0, 0, 0, 0.12 ); }
	.untangling-themes-screen .untangling-mkt-theme-card.is-current .untangling-mkt-shot { border-color: transparent; box-shadow: 0 0 0 2px var(--mkt-blue); }
	.untangling-themes-screen .untangling-mkt-badge.is-activebadge { background: var(--mkt-blue); color: #fff; }
	/* Three columns like the production screen; the auto-fill default would
	   run to four or five at admin content widths. */
	.untangling-themes-screen .untangling-mkt-theme-grid { grid-template-columns: repeat( 3, minmax( 0, 1fr ) ); gap: 48px 32px; }
	@media ( max-width: 1200px ) { .untangling-themes-screen .untangling-mkt-theme-grid { grid-template-columns: repeat( 2, minmax( 0, 1fr ) ); } }
	@media ( max-width: 782px ) { .untangling-themes-screen .untangling-mkt-theme-grid { grid-template-columns: 1fr; } }

	/* Page head — core's .wrap h1 metrics with the production subtitle and a
	   secondary Install new theme action on the right. */
	.untangling-themes-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; margin: 16px 0 28px; }
	.untangling-themes-screen .untangling-themes-head h1 { font-size: 24px; font-weight: 400; line-height: 1.3; margin: 0; padding: 0; color: var(--mkt-gray-100); }
	.untangling-themes-head p { margin: 4px 0 0; font-size: 14px; color: var(--mkt-gray-60); }
	.untangling-themes-head a.untangling-themes-install { display: inline-flex; align-items: center; height: 44px; padding: 0 20px; box-sizing: border-box; flex-shrink: 0; background: #fff; color: var(--mkt-gray-100); border: 1px solid var(--mkt-gray-10); border-radius: 4px; font-size: 14px; font-weight: 500; text-decoration: none; }
	.untangling-themes-head a.untangling-themes-install:hover { border-color: var(--mkt-gray-50); color: var(--mkt-gray-100); }

	/* Search + tier select share one row above the pills (the fullscreen
	   showcase stacks them differently — hero search, then filter bar).
	   Production keeps the pair left-aligned: search capped at ~600px with
	   the plain single-row select right beside it. */
	.untangling-themes-controls { display: flex; align-items: stretch; gap: 16px; margin: 0 0 24px; }
	.untangling-themes-screen .untangling-themes-controls .untangling-mkt-search { flex: 0 1 600px; max-width: 600px; margin: 0; }
	.untangling-themes-screen .untangling-themes-controls .untangling-mkt-search input { height: 48px; font-size: 14px; }
	.untangling-themes-screen .untangling-themes-controls .untangling-mkt-view { min-width: 280px; flex-direction: row; align-items: center; padding: 0 12px; }
	#wpcontent .untangling-themes-screen .untangling-mkt-view select { font-weight: 400; flex: 1; }
	@media ( max-width: 782px ) {
		.untangling-themes-head { flex-direction: column; }
		.untangling-themes-controls { flex-direction: column; }
	}

	/* Theme details inside the admin chrome (ustep=details). */
	.untangling-themes-screen.is-details .untangling-mkt-crumbs { margin: 8px 0 24px; }
	.untangling-themes-screen.is-details .untangling-mkt-detail-shot { top: 64px; }
	.untangling-mkt-caret { margin-left: 4px; }
	.untangling-mkt-detail-styles { margin: 28px 0 4px; }
	.untangling-mkt-detail-styles h2 { margin: 0 0 2px; }
	.untangling-mkt-detail-styles > p { margin: 0 0 12px; font-size: 14px; color: var(--mkt-gray-60); }
	.untangling-mkt-style-chips { display: flex; gap: 12px; }
	.untangling-mkt-style-chip { display: inline-flex; align-items: center; justify-content: center; gap: 10px; width: 88px; height: 60px; border: 1px solid var(--mkt-gray-10); border-radius: 6px; cursor: pointer; }
	.untangling-mkt-style-chip.is-selected { border-color: var(--mkt-gray-100); box-shadow: inset 0 0 0 1px var(--mkt-gray-100); }
	.untangling-mkt-style-aa { font-family: Georgia, serif; font-size: 22px; line-height: 1; }
	.untangling-mkt-style-dots { display: flex; flex-direction: column; gap: 5px; }
	.untangling-mkt-style-dots i { width: 10px; height: 10px; border-radius: 50%; display: block; }
	</style>
	<?php
	if ( $is_details ) {
		// Production Atomic renders theme details inside the admin chrome —
		// same markup as the fullscreen details step, in-admin variant.
		?>
		<div class="wrap untangling-themes-screen is-details">
			<div class="untangling-mkt">
				<?php untangling_marketplace_details_step( $plan, true ); ?>
			</div>
		</div>
		<script>
		( function () {
			var chips = document.querySelectorAll( '.untangling-mkt-style-chip' );
			chips.forEach( function ( chip ) {
				chip.addEventListener( 'click', function () {
					chips.forEach( function ( c ) { c.classList.remove( 'is-selected' ); } );
					chip.classList.add( 'is-selected' );
				} );
			} );
			// No Help Center shell in-admin — keep the support link from
			// jumping to # (visual placeholder, like the styles above).
			document.querySelectorAll( '[data-open-help]' ).forEach( function ( link ) {
				link.addEventListener( 'click', function ( event ) { event.preventDefault(); } );
			} );
		} )();
		</script>
		<?php
		return;
	}
	?>
	<div class="wrap untangling-themes-screen">
		<div class="untangling-mkt">
			<div class="untangling-mkt-catalog is-themes is-active" data-catalog="themes">
				<div class="untangling-themes-head">
					<div>
						<h1><?php esc_html_e( 'Themes' ); ?></h1>
						<p>
							<?php esc_html_e( 'Select or update the visual design for your site.' ); ?>
							<a href="https://wordpress.com/support/themes/" target="_blank" rel="noreferrer"><?php esc_html_e( 'Learn more' ); ?></a>.
						</p>
					</div>
					<a class="untangling-themes-install" href="<?php echo esc_url( admin_url( 'theme-install.php' ) ); ?>"><?php esc_html_e( 'Install new theme' ); ?></a>
				</div>
				<div class="untangling-themes-controls">
					<div class="untangling-mkt-search">
						<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.5" d="M13 5a6 6 0 1 1-6 6 6 6 0 0 1 6-6zm-4.5 10.5L4 20"/></svg>
						<input type="search" placeholder="<?php esc_attr_e( 'Search themes…' ); ?>" aria-label="<?php esc_attr_e( 'Search themes' ); ?>">
					</div>
					<label class="untangling-mkt-view" aria-label="<?php esc_attr_e( 'Filter themes by tier' ); ?>">
						<select data-tier-filter>
							<option value="all"><?php esc_html_e( 'All' ); ?></option>
							<option value="free"><?php esc_html_e( 'Free' ); ?></option>
							<option value="partner"><?php esc_html_e( 'Partner' ); ?></option>
							<option value="personal"><?php esc_html_e( 'Personal' ); ?></option>
							<option value="premium"><?php esc_html_e( 'Premium' ); ?></option>
						</select>
						<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M17.1 9.5 12 14.6 6.9 9.5l1.1-1.1 4 4.1 4-4.1z"/></svg>
					</label>
				</div>
				<div class="untangling-mkt-filterbar">
					<div class="untangling-mkt-pillscroll">
						<button type="button" class="untangling-mkt-pillnav is-prev" aria-label="<?php esc_attr_e( 'Scroll categories back' ); ?>" hidden><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M14.6 7l-1.2-1L8 12l5.4 6 1.2-1-4.6-5z"/></svg></button>
						<div class="untangling-mkt-pills" role="tablist">
							<button type="button" data-filter="mine"><?php esc_html_e( 'My Themes' ); ?></button>
							<button type="button" data-filter="recommended" class="is-active"><?php esc_html_e( 'Recommended' ); ?></button>
							<button type="button" data-filter="all"><?php esc_html_e( 'All' ); ?></button>
							<button type="button" data-filter="blog"><?php esc_html_e( 'Blog' ); ?></button>
							<button type="button" data-filter="portfolio"><?php esc_html_e( 'Portfolio' ); ?></button>
							<button type="button" data-filter="business"><?php esc_html_e( 'Business' ); ?></button>
							<button type="button" data-filter="store"><?php esc_html_e( 'Store' ); ?></button>
							<button type="button" data-filter="photography"><?php esc_html_e( 'Photography' ); ?></button>
						</div>
						<button type="button" class="untangling-mkt-pillnav is-next" aria-label="<?php esc_attr_e( 'Scroll categories forward' ); ?>"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M9.4 7l1.2-1 5.4 6-5.4 6-1.2-1 4.6-5z"/></svg></button>
					</div>
				</div>
				<div class="untangling-mkt-theme-grid">
					<?php untangling_theme_grid_cards( $plan, untangling_themes_screen_url() ); ?>
				</div>
				<p class="untangling-mkt-empty" hidden><?php esc_html_e( 'No themes match your search.' ); ?></p>
			</div>
		</div>
	</div>
	<?php
	untangling_marketplace_js();
}


/* -------------------------------------------------------------------------
 * 3d. Global Prototype controls — the fab/panel shows on every wp-admin
 * screen, carrying the site-wide toggles (site state, site type,
 * marketplace version) plus demo reset and copy-link. The WordPress.com
 * page keeps its richer React panel (plan card + my-site layout live there),
 * so it is skipped here.
 * ---------------------------------------------------------------------- */

add_action( 'admin_footer', function () {
	if ( untangling_is_locked_demo() ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && 'toplevel_page_untangling-hosting' === $screen->id ) {
		return;
	}
	$is_mkt   = $screen && 'toplevel_page_untangling-marketplace' === $screen->id;
	$type     = untangling_get_site_type();
	$mode     = untangling_get_marketplace_mode();
	$plan     = untangling_get_plan();
	$override = (bool) get_option( 'untangling_plan_override' );
	$ms_plan_override = (bool) get_option( 'untangling_ms_plan_override' );
	$ms_dirty = $ms_plan_override || get_option( 'untangling_ms_lp_done' ) || get_option( 'untangling_ms_lp_complete' ) || get_option( 'untangling_ms_state' ) || get_option( 'untangling_ms_storage_addon' ) || get_option( 'untangling_ms_hosting' );

	// One control: label, segments, and the line explaining the current choice.
	// The hint is hidden by default — seven of them stacked made the panel taller
	// than a laptop viewport, which pushed its own close button off the top of
	// the screen — so it also rides on the label's title, where a hover still
	// answers the question at no cost in height.
	// $extra: 'is-grid' wraps more than three options into a 2×2 block —
	// four segments do not fit across the panel.
	$seg = function ( $label, $key, $options, $current, $hint = '', $extra = '' ) {
		echo '<label' . ( $hint ? ' title="' . esc_attr( $hint ) . '"' : '' ) . '>' . esc_html( $label ) . '</label>';
		echo '<div class="untangling-gproto-seg' . ( $extra ? ' ' . esc_attr( $extra ) : '' ) . '" data-key="' . esc_attr( $key ) . '">';
		foreach ( $options as $value => $text ) {
			echo '<button type="button" data-value="' . esc_attr( $value ) . '"' . ( $value === $current ? ' class="is-active"' : '' ) . '>' . esc_html( $text ) . '</button>';
		}
		echo '</div>';
		if ( $hint ) {
			echo '<p class="untangling-gproto-hint">' . esc_html( $hint ) . '</p>';
		}
	};
	// Controls are grouped by the surface they change, so one is found by where
	// its effect shows rather than by reading the whole list.
	$group = function ( $title ) {
		echo '<p class="untangling-gproto-group">' . esc_html( $title ) . '</p>';
	};
	?>
	<style>
	.untangling-gproto { position: fixed; right: 16px; bottom: 16px; z-index: 999991; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
	.untangling-gproto-fab { width: 44px; height: 44px; border-radius: 50%; border: 1px solid #dcdcde; background: #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.15); cursor: pointer; color: #3858e9; display: inline-flex; align-items: center; justify-content: center; padding: 0; }
	/* The panel grows upward from the fab, so an unbounded one pushes its own
	   header — and the close button in it — past the top of the screen. Cap it
	   against the viewport and scroll the middle: header and footer stay
	   reachable at any height. */
	.untangling-gproto-panel { position: absolute; right: 0; bottom: 0; width: 300px; max-height: calc(100vh - 72px); display: flex; flex-direction: column; background: #fff; border: 1px solid #dcdcde; border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.15); box-sizing: border-box; }
	.untangling-gproto-panel[hidden] { display: none; }
	.untangling-gproto-head { flex: none; display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 8px 8px 16px; border-bottom: 1px solid #f0f0f0; }
	.untangling-gproto-head > span:first-child { font-size: 11px; font-weight: 500; letter-spacing: 0.5px; text-transform: uppercase; color: #757575; }
	.untangling-gproto-headbtns { display: flex; align-items: center; gap: 2px; }
	.untangling-gproto-headbtns button { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; background: none; border: 0; border-radius: 4px; padding: 0; cursor: pointer; color: #757575; line-height: 1; }
	.untangling-gproto-headbtns button:hover { background: #f0f0f0; color: #1e1e1e; }
	.untangling-gproto-headbtns button[aria-pressed="true"] { background: #e0e6ff; color: #3858e9; }
	.untangling-gproto-min { font-size: 18px; }
	.untangling-gproto-body { flex: 1 1 auto; overflow-y: auto; overscroll-behavior: contain; padding: 14px 16px 16px; }
	.untangling-gproto-group { margin: 18px 0 0; padding-top: 14px; border-top: 1px solid #f0f0f0; font-size: 10px; font-weight: 600; letter-spacing: 0.8px; text-transform: uppercase; color: #949494; }
	.untangling-gproto-group:first-child { margin-top: 0; padding-top: 0; border-top: 0; }
	.untangling-gproto-body label { display: block; font-size: 11px; font-weight: 500; letter-spacing: 0.5px; text-transform: uppercase; color: #1e1e1e; margin: 12px 0 6px; }
	.untangling-gproto-group + label { margin-top: 8px; }
	.untangling-gproto-seg { display: flex; border: 1px solid #c3c4c7; border-radius: 4px; overflow: hidden; }
	.untangling-gproto-seg button { flex: 1; border: 0; background: #fff; padding: 7px 2px; cursor: pointer; font-size: 12px; color: #2c3338; }
	.untangling-gproto-seg button:hover { background: #f6f7f7; }
	.untangling-gproto-seg button.is-active { background: #2c3338; color: #fff; font-weight: 500; }
	.untangling-gproto-seg.is-grid { flex-wrap: wrap; }
	.untangling-gproto-seg.is-grid button { flex: 1 0 50%; box-shadow: inset -1px -1px 0 0 #dcdcde; }
	.untangling-gproto-hint { font-size: 11px; line-height: 1.4; color: #757575; margin: 6px 0 0; }
	/* Lean is the default: labels alone, explanations on hover. The header
	   toggle brings them back for anyone reading the panel rather than driving
	   it, and the choice is remembered. */
	.untangling-gproto-body.is-lean .untangling-gproto-hint { display: none; }
	.untangling-gproto-foot { flex: none; display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 10px 16px; border-top: 1px solid #f0f0f0; }
	.untangling-gproto-foot button { background: none; border: 0; padding: 0; cursor: pointer; font-size: 12px; text-decoration: underline; }
	.untangling-gproto-copy { color: #3858e9; }
	.untangling-gproto-reset { color: #b32d2e; }
	</style>
	<div class="untangling-gproto<?php echo $is_mkt ? ' is-mkt' : ''; ?>">
		<button type="button" class="untangling-gproto-fab" aria-label="<?php esc_attr_e( 'Prototype controls' ); ?>">
			<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM3.5 12c0-1.23.26-2.4.73-3.46L8.25 19.6C5.44 18.23 3.5 15.34 3.5 12zm8.5 8.5c-.83 0-1.64-.12-2.4-.35l2.55-7.4 2.61 7.15c.02.04.04.08.06.12-.9.31-1.85.48-2.82.48zm1.17-12.49c.51-.03.97-.08.97-.08.46-.05.4-.73-.05-.7 0 0-1.38.11-2.27.11-.84 0-2.24-.11-2.24-.11-.46-.03-.51.68-.06.7 0 0 .43.05.89.08l1.32 3.62-1.86 5.57-3.09-9.19c.51-.03.97-.08.97-.08.46-.05.4-.73-.05-.7 0 0-1.38.11-2.27.11-.16 0-.35 0-.55-.01C6.42 5.09 9.04 3.5 12 3.5c2.21 0 4.22.84 5.73 2.23-.04 0-.07-.01-.11-.01-.84 0-1.43.73-1.43 1.51 0 .7.4 1.29.84 1.99.33.57.71 1.3.71 2.35 0 .73-.28 1.58-.65 2.76l-.85 2.84-3.07-9.16zm3.1 11.36l2.6-7.51c.49-1.21.65-2.19.65-3.05 0-.31-.02-.6-.06-.87.66 1.21 1.04 2.6 1.04 4.06 0 3.13-1.7 5.86-4.23 7.37z"/></svg>
		</button>
		<div class="untangling-gproto-panel" hidden>
			<div class="untangling-gproto-head">
				<span><?php esc_html_e( 'Prototype controls' ); ?></span>
				<span class="untangling-gproto-headbtns">
					<button type="button" class="untangling-gproto-hints" aria-pressed="false" title="<?php esc_attr_e( 'Explain each choice' ); ?>" aria-label="<?php esc_attr_e( 'Explain each choice' ); ?>">
						<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 3.2c-4.8 0-8.8 3.9-8.8 8.8 0 4.8 3.9 8.8 8.8 8.8 4.8 0 8.8-3.9 8.8-8.8 0-4.8-4-8.8-8.8-8.8zm0 16c-4 0-7.2-3.3-7.2-7.2C4.8 8 8 4.8 12 4.8s7.2 3.3 7.2 7.2c0 4-3.2 7.2-7.2 7.2zM11 17h2v-6h-2v6zm0-8h2V7h-2v2z"/></svg>
					</button>
					<button type="button" class="untangling-gproto-min" aria-label="<?php esc_attr_e( 'Close' ); ?>" title="<?php esc_attr_e( 'Close (Esc)' ); ?>">&times;</button>
				</span>
			</div>
			<div class="untangling-gproto-body is-lean">
				<?php
				$variant = untangling_get_variant();
				$group( __( 'Layout' ) );
				$variant_hints = array(
					'dashboard' => __( 'All-in Dashboard: next steps, activity, backups, and hosting as widgets on the core Dashboard; Upgrades and Help & Learn in the sidebar.' ),
					'drawer'    => __( 'My Site drawer: a My Site item below Dashboard with Next steps, Plan & products, Hosting, and Help & Learn.' ),
				);
				$seg( __( 'Variant' ), 'untangling_variant', array(
					'dashboard' => __( 'Dashboard' ),
					'drawer'    => __( 'My Site' ),
				), $variant, isset( $variant_hints[ $variant ] ) ? $variant_hints[ $variant ] : '' );

				$group( 'dashboard' === $variant ? __( 'Dashboard widgets' ) : __( 'My Site' ) );
				$ms_state = untangling_ms_get_state();
				$seg( __( 'Site state' ), 'untangling_ms_state', array( 'new' => __( 'Just created' ), 'established' => __( 'Established' ) ), $ms_state, 'new' === $ms_state
					? __( 'Setup unfinished: Next steps leads with the launchpad.' )
					: __( 'Setup behind you: Next steps shows growth and vitals.' ) . ( 'dashboard' === $variant ? ' ' . __( 'WooCommerce, Yoast SEO, and Elementor widgets join the dashboard.' ) : '' ) );
				// Hosting health has no real source in the prototype — no backup
				// runs, no scanner — so the failure branch needs a switch to be
				// demoable at all. Free plans gate both cards, so the segment
				// only means something on Atomic.
				$ms_hosting = untangling_ms_hosting_state();
				$seg( __( 'Hosting state' ), 'untangling_ms_hosting', array( 'ok' => __( 'All good' ), 'attention' => __( 'Needs attention' ) ), $ms_hosting, 'attention' === $ms_hosting
					? __( 'Hosting: a failed backup and threats found — both cards turn red.' )
					: __( 'Hosting: backups current and no threats — both cards turn green.' ) );
				$group( __( 'wp-admin' ) );
				$seg( __( 'Site type' ), 'untangling_site_type', array( 'atomic' => __( 'Atomic' ), 'simple' => __( 'Simple' ) ), $type, __( 'Simple = Free plan · Atomic = Business plan in My Site.' ) );
				// Placement of the upsell nudge. What it sells follows the
				// site type: the free domain on Simple, the two-year renewal
				// on Atomic — same card, same placements.
				$upsell       = untangling_get_upsell_placement();
				$upsell_hints = array(
					'none'      => __( 'No upsell nudge anywhere in wp-admin.' ),
					'menu-top'  => __( 'Above the menu — today’s position, redrawn for the dark column.' ),
					'menu-foot' => __( 'Below the menu, out of the navigation’s way.' ),
					'omnibar'   => __( 'A pill in the admin bar: no sidebar cost, on every screen.' ),
				);
				$seg( __( 'Upsell' ), 'untangling_upsell', array(
					'none'      => __( 'None' ),
					'menu-top'  => __( 'Menu top' ),
					'menu-foot' => __( 'Menu foot' ),
					'omnibar'   => __( 'Omnibar' ),
				), $upsell, $upsell_hints[ $upsell ]
					. ( 'none' === $upsell ? '' : ' ' . ( 'simple' === $type ? __( 'Selling the free domain here.' ) : __( 'Selling the 2-year renewal here.' ) ) ), 'is-grid' );

				$group( __( 'Plugins and Themes' ) );
				// One line about the selected mode only — the segments reload the
				// page, so the hint re-renders with each choice.
				$mode_hints = array(
					'fullscreen' => __( 'Themes + plugins in the chromeless Marketplace.' ),
					'split'      => __( 'Production Atomic: core Themes screen + Appearance → Theme Showcase; plugins keep the Add Plugins tab.' ),
					'tabs'       => __( 'Marketplace tabs in Add Plugins and Add Themes, plus plans-upsell banners.' ),
				);
				$seg( __( 'Version' ), 'untangling_marketplace', array( 'fullscreen' => __( 'Fullscreen' ), 'split' => __( 'Split' ), 'tabs' => __( 'Tabs' ) ), $mode, $mode_hints[ $mode ] );
				if ( 'tabs' === $mode ) {
					$filter_hints = array(
						'included' => __( '“Included with my plan” links on both Marketplace tabs.' ),
						'dropdown' => __( 'A tier dropdown on both Marketplace tabs.' ),
					);
					$filter = untangling_get_plan_filter();
					$seg( __( 'Plan filter' ), 'untangling_plan_filter', array( 'included' => __( 'Included' ), 'dropdown' => __( 'Dropdown' ) ), $filter, $filter_hints[ $filter ] );
				}
				?>
			</div>
			<div class="untangling-gproto-foot">
				<button type="button" class="untangling-gproto-copy"><?php esc_html_e( 'Copy link to this view' ); ?></button>
				<?php if ( $override || $ms_dirty ) : ?>
					<button type="button" class="untangling-gproto-reset" title="<?php echo esc_attr( $override
						? sprintf( __( 'Plan override in effect: %s' ), $plan )
						: ( $ms_plan_override
							? sprintf( __( 'My Site plan override in effect: %s' ), untangling_ms_get_plan() )
							: __( 'Purchases and completed steps are being remembered.' ) ) ); ?>"><?php esc_html_e( 'Reset demo' ); ?></button>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<script>
	( function () {
		var wrap = document.querySelector( '.untangling-gproto' );
		if ( ! wrap ) {
			return;
		}
		var fab = wrap.querySelector( '.untangling-gproto-fab' );
		var panel = wrap.querySelector( '.untangling-gproto-panel' );
		var mkt = document.querySelector( '.untangling-mkt' );
		var body = wrap.querySelector( '.untangling-gproto-body' );

		// Every segment reloads the page, so a panel that forgot it was open shut
		// itself after each change — three toggles meant three trips back to the
		// fab. Both preferences persist instead.
		function remember( key, value ) {
			try {
				window.localStorage.setItem( 'untangling-gproto-' + key, value ? '1' : '0' );
			} catch ( e ) {}
		}
		function recall( key ) {
			try {
				return '1' === window.localStorage.getItem( 'untangling-gproto-' + key );
			} catch ( e ) {
				return false;
			}
		}

		// The open state is tagged with where it was set: fullscreen pages
		// restore only a state opened on a fullscreen page, so a panel left
		// open on a normal wp-admin screen never covers the chromeless flow —
		// while segment-click reloads within it still reopen the panel.
		var isMkt = wrap.classList.contains( 'is-mkt' );
		function rememberOpen( open ) {
			try {
				window.localStorage.setItem( 'untangling-gproto-open', open ? ( isMkt ? 'mkt' : '1' ) : '0' );
			} catch ( e ) {}
		}
		function recallOpen() {
			try {
				var v = window.localStorage.getItem( 'untangling-gproto-open' );
				return isMkt ? 'mkt' === v : ( '1' === v || 'mkt' === v );
			} catch ( e ) {
				return false;
			}
		}

		function show( open ) {
			panel.hidden = ! open;
			fab.style.visibility = open ? 'hidden' : '';
			rememberOpen( open );
		}
		function toggle() {
			show( panel.hidden );
		}
		fab.addEventListener( 'click', toggle );
		wrap.querySelector( '.untangling-gproto-min' ).addEventListener( 'click', toggle );
		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && ! panel.hidden ) {
				show( false );
				fab.focus();
			}
		} );

		// Hints are the panel's own documentation, and seven of them at once are
		// what made it taller than the screen. Off by default, one click away.
		var hints = wrap.querySelector( '.untangling-gproto-hints' );
		function showHints( on ) {
			body.classList.toggle( 'is-lean', ! on );
			hints.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			remember( 'hints', on );
		}
		hints.addEventListener( 'click', function () {
			showHints( body.classList.contains( 'is-lean' ) );
		} );

		if ( recall( 'hints' ) ) {
			showHints( true );
		}
		if ( recallOpen() ) {
			show( true );
		}

		function go( key, value ) {
			var url = new URL( window.location.href );
			url.searchParams.set( key, value );
			window.location.href = url.toString();
		}

		wrap.querySelectorAll( '.untangling-gproto-seg button' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				if ( button.classList.contains( 'is-active' ) ) {
					return;
				}
				var key = button.closest( '.untangling-gproto-seg' ).dataset.key;
				var value = button.dataset.value;
				// A variant switch always lands on that variant's home — the
				// two layouts live on different screens, so staying put would
				// show the old one (or bounce through a redirect).
				if ( 'untangling_variant' === key ) {
					window.location.href = 'drawer' === value
						? <?php echo wp_json_encode( admin_url( 'admin.php?page=untangling-mysite' ) ); ?> + '&untangling_variant=drawer'
						: <?php echo wp_json_encode( admin_url( 'index.php' ) ); ?> + '?untangling_variant=dashboard';
					return;
				}
				// Leaving Split while on its in-admin showcase/details page
				// lands on core Themes — the page only registers in Split.
				if ( 'untangling_marketplace' === key && 'split' !== value && -1 !== window.location.search.indexOf( 'page=untangling-themes' ) ) {
					window.location.href = <?php echo wp_json_encode( admin_url( 'themes.php' ) ); ?> + '?untangling_marketplace=' + value;
					return;
				}
				// Leaving Fullscreen while browsing its catalog lands on the
				// equivalent in-admin home: the Add Plugins Marketplace tab,
				// or (Tabs) the Add Themes Marketplace tab.
				if ( 'untangling_marketplace' === key && mkt ) {
					if ( 'plugins' === mkt.dataset.mkt && ( 'split' === value || 'tabs' === value ) ) {
						window.location.href = <?php echo wp_json_encode( admin_url( 'plugin-install.php?tab=wpcom_marketplace' ) ); ?> + '&untangling_marketplace=' + value;
						return;
					}
					if ( 'themes' === mkt.dataset.mkt && 'tabs' === value ) {
						window.location.href = <?php echo wp_json_encode( admin_url( 'theme-install.php?untangling_browse=marketplace&untangling_marketplace=tabs' ) ); ?>;
						return;
					}
				}
				go( key, value );
			} );
		} );

		var reset = wrap.querySelector( '.untangling-gproto-reset' );
		if ( reset ) {
			reset.addEventListener( 'click', function () {
				go( 'untangling_reset_demo', '1' );
			} );
		}

		var copy = wrap.querySelector( '.untangling-gproto-copy' );
		copy.addEventListener( 'click', function () {
			navigator.clipboard.writeText( window.location.href ).then( function () {
				copy.textContent = <?php echo wp_json_encode( __( 'Copied ✓' ) ); ?>;
				window.setTimeout( function () {
					copy.textContent = <?php echo wp_json_encode( __( 'Copy link to this view' ) ); ?>;
				}, 2000 );
			} );
		} );
	} )();
	</script>
	<?php
}, 999 );

/* -------------------------------------------------------------------------
 * 4. Omnibar mock: make the admin bar match the MSD Omnibar
 * ---------------------------------------------------------------------- */

// Exact icons from the MSD Omnibar (calypso masterbar): ReaderIcon,
// @wordpress/icons help, and the masterbar notifications bell.
const UNTANGLING_READER_SVG = '<svg class="untangling-svg untangling-reader-icon" width="24" height="11" viewBox="0 0 24 11" aria-hidden="true"><path d="M22.8746 4.60676L22.8197 4.3575C22.3347 2.17436 20.276 0.584279 17.9245 0.584279C16.6527 0.584279 15.4358 1.03122 14.5116 1.84775C14.1914 2.13139 13.9443 2.44081 13.743 2.74163C13.1849 2.63849 12.6085 2.56114 12.032 2.56114H12.0046C11.419 2.56114 10.8425 2.64709 10.2753 2.75023C10.0648 2.44081 9.82691 2.13139 9.49752 1.83915C8.57338 1.01403 7.35646 0.575684 6.08463 0.575684C3.72398 0.584279 1.66527 2.17436 1.18033 4.3575L1.12543 4.60676H0V6.00775H1.12543L1.18033 6.257C1.63782 8.44014 3.69653 10.0302 6.07548 10.0302C8.83873 10.0302 11.0804 7.91585 11.0804 5.31155C11.0804 5.31155 11.0896 4.72709 10.8517 3.97072C11.236 3.91915 11.6203 3.87618 12.0046 3.87618C12.3706 3.87618 12.7549 3.91056 13.1483 3.96213C12.9012 4.72709 12.9195 5.31155 12.9195 5.31155C12.9195 7.91585 15.1613 10.0302 17.9245 10.0302C20.3035 10.0302 22.3622 8.44874 22.8197 6.257L22.8746 6.00775H24V4.60676H22.8746ZM6.07548 8.62923C4.13572 8.62923 2.5528 7.14229 2.5528 5.30295C2.5528 3.46362 4.13572 1.97667 6.07548 1.97667C8.01524 1.97667 9.59816 3.46362 9.59816 5.30295C9.59816 7.14229 8.01524 8.62923 6.07548 8.62923ZM17.9245 8.62923C15.9847 8.62923 14.4018 7.14229 14.4018 5.30295C14.4018 3.46362 15.9847 1.97667 17.9245 1.97667C19.8643 1.97667 21.4472 3.46362 21.4472 5.30295C21.4472 7.14229 19.8643 8.62923 17.9245 8.62923Z"/></svg>';

// The filled help icon the Omnibar's Help Center button uses (@wordpress/icons helpFilled).
const UNTANGLING_HELP_SVG = '<svg class="untangling-svg untangling-help-icon" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 4c-4.4 0-8 3.6-8 8s3.6 8 8 8 8-3.6 8-8-3.6-8-8-8Zm.8 12.5h-1.5V15h1.5v1.5Zm2.1-5.6c-.1.5-.4 1.1-.8 1.5-.4.4-.9.7-1.4.8v.8h-1.5v-1.2c0-.6.5-1 .9-1s.7-.2 1-.5c.2-.3.4-.7.4-1 0-.4-.2-.7-.5-1-.3-.3-.6-.4-1-.4s-.8.2-1.1.4c-.3.3-.4.7-.4 1.1H9c0-.6.2-1.1.5-1.6s.7-.9 1.2-1.1c.5-.2 1.1-.3 1.6-.3s1.1.3 1.5.6c.4.3.8.8 1 1.3.2.5.2 1.1.1 1.6Z"/></svg>';

const UNTANGLING_BELL_SVG = '<svg class="untangling-svg untangling-bell-icon" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path d="M9.9,20h4c0,0.5-0.2,1-0.6,1.4 c-0.8,0.8-2,0.8-2.8,0C10.1,21,9.9,20.5,9.9,20z M20,17.5v1H4v-1l0.9-0.7C5.5,16.3,6,15.5,6,15l0-5.5c0-3.3,2.7-6,6-6 c3.3,0,6,2.7,6,6V15c0,0.5,0.5,1.4,1.1,1.8L20,17.5z"/></svg>';

// The WordPress.com mark the masterbar renders (logged-in.jsx wordpressIcon).
const UNTANGLING_WPCOM_LOGO_SVG = '<svg class="untangling-svg untangling-wpcom-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="24" width="24" aria-hidden="true"><g><path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zM3.5 12c0-1.232.264-2.402.736-3.459L8.291 19.65A8.5 8.5 0 013.5 12zm8.5 8.501c-.834 0-1.64-.122-2.401-.346l2.551-7.411 2.613 7.158a.718.718 0 00.061.117 8.497 8.497 0 01-2.824.482zm1.172-12.486c.512-.027.973-.081.973-.081.458-.054.404-.727-.054-.701 0 0-1.377.108-2.266.108-.835 0-2.239-.108-2.239-.108-.459-.026-.512.674-.054.701 0 0 .434.054.892.081l1.324 3.629-1.86 5.579-3.096-9.208c.512-.027.973-.081.973-.081.458-.054.403-.727-.055-.701 0 0-1.376.108-2.265.108-.16 0-.347-.004-.547-.01A8.491 8.491 0 0112 3.5c2.213 0 4.228.846 5.74 2.232-.037-.002-.072-.007-.11-.007-.835 0-1.427.727-1.427 1.509 0 .701.404 1.293.835 1.994.323.566.701 1.293.701 2.344 0 .727-.28 1.572-.647 2.748l-.848 2.833-3.072-9.138zm3.101 11.332l2.596-7.506c.485-1.213.646-2.182.646-3.045 0-.313-.021-.603-.057-.874A8.455 8.455 0 0120.5 12a8.493 8.493 0 01-4.227 7.347z"/></g></svg>';

// Left cluster: W-logo dropdown (Discover, Sites, Domains, Emails / About,
// Get Involved — same items as the MSD Omnibar), then site → updates →
// search ⌘K → comments → +New.
add_action( 'admin_bar_menu', function ( $bar ) {
	$msd = UNTANGLING_MSD_URL;

	foreach ( array( 'wporg', 'documentation', 'learn', 'support-forums', 'feedback', 'contribute', 'about' ) as $id ) {
		$bar->remove_node( $id );
	}
	$bar->add_node( array(
		'id'    => 'wp-logo',
		'title' => UNTANGLING_WPCOM_LOGO_SVG . '<span class="screen-reader-text">' . esc_html__( 'WordPress.com' ) . '</span>',
		'href'  => $msd . '/sites',
	) );
	$bar->add_node( array( 'id' => 'untangling-logo-discover', 'parent' => 'wp-logo', 'title' => __( 'Discover' ), 'href' => $msd . '/discover' ) );
	$bar->add_node( array( 'id' => 'untangling-logo-sites', 'parent' => 'wp-logo', 'title' => __( 'Sites' ), 'href' => $msd . '/sites' ) );
	$bar->add_node( array( 'id' => 'untangling-logo-domains', 'parent' => 'wp-logo', 'title' => __( 'Domains' ), 'href' => $msd . '/domains' ) );
	$bar->add_node( array( 'id' => 'untangling-logo-emails', 'parent' => 'wp-logo', 'title' => __( 'Emails' ), 'href' => $msd . '/emails' ) );
	$bar->add_group( array( 'id' => 'untangling-logo-secondary', 'parent' => 'wp-logo', 'meta' => array( 'class' => 'ab-sub-secondary' ) ) );
	$bar->add_node( array( 'id' => 'untangling-about', 'parent' => 'untangling-logo-secondary', 'title' => __( 'About WordPress' ), 'href' => admin_url( 'about.php' ) ) );
	$bar->add_node( array( 'id' => 'untangling-get-involved', 'parent' => 'untangling-logo-secondary', 'title' => __( 'Get Involved' ), 'href' => admin_url( 'contribute.php' ) ) );

	$bar->add_node( array( 'id' => 'untangling-site-dashboard', 'parent' => 'site-name', 'title' => __( 'Dashboard' ), 'href' => admin_url() ) );
	// The dashboard variant folds My Site into the core Dashboard, so its
	// dropdown entry would only bounce through a redirect — drawer only.
	if ( 'drawer' === untangling_get_variant() ) {
		$bar->add_node( array( 'id' => 'untangling-site-mysite', 'parent' => 'site-name', 'title' => __( 'My Site' ), 'href' => admin_url( 'admin.php?page=untangling-mysite' ) ) );
	}
	$bar->add_node( array( 'id' => 'untangling-site-stats', 'parent' => 'site-name', 'title' => __( 'Stats' ), 'href' => $msd . '/stats' ) );
	$bar->add_node( array( 'id' => 'untangling-site-plan', 'parent' => 'site-name', 'title' => __( 'Plan' ) . '<span class="untangling-chip">' . esc_html( untangling_get_plan() ) . '</span>', 'href' => admin_url( 'admin.php?page=untangling-mysite&ms=plan' ) ) );

	// Reorder to match the Omnibar: site → updates → comments → +New.
	// The core ⌘K command palette node is hidden (the Omnibar has no button
	// for it), but the keyboard shortcut still works.
	$comments    = $bar->get_node( 'comments' );
	$new_content = $bar->get_node( 'new-content' );
	$bar->remove_node( 'command-palette' );
	$bar->remove_node( 'comments' );
	$bar->remove_node( 'new-content' );

	if ( ! $bar->get_node( 'updates' ) ) {
		$bar->add_node( array(
			'id'    => 'untangling-updates',
			'title' => '<span class="ab-icon dashicons dashicons-update"></span>5',
			'href'  => $msd . '/sites',
			'meta'  => array( 'title' => __( 'Updates across your sites' ) ),
		) );
	}
	if ( $comments ) {
		$bar->add_node( (array) $comments );
	}
	if ( $new_content ) {
		$bar->add_node( (array) $new_content );
	}

	// Right cluster, left of Howdy: Reader, help, notifications bell.
	$bar->add_node( array(
		'id'     => 'untangling-reader',
		'parent' => 'top-secondary',
		'title'  => UNTANGLING_READER_SVG . '<span class="ab-label">' . esc_html__( 'Reader' ) . '</span>',
		'href'   => $msd . '/reader',
		'meta'   => array( 'class' => 'untangling-reader' ),
	) );
	$bar->add_node( array(
		'id'     => 'untangling-help',
		'parent' => 'top-secondary',
		'title'  => UNTANGLING_HELP_SVG,
		'href'   => '#',
		'meta'   => array( 'title' => __( 'Help' ) ),
	) );
	$bar->add_node( array(
		'id'     => 'untangling-notifications',
		'parent' => 'top-secondary',
		'title'  => UNTANGLING_BELL_SVG,
		'href'   => '#',
		'meta'   => array( 'title' => __( 'Notifications' ) ),
	) );
}, 999 );

// Account dropdown, matching the MSD Omnibar Howdy panel: core user info +
// Edit Profile, Workspace row with switcher flyout, Log Out, then the
// separated footer with the blue "My WordPress.com Account" button.
add_action( 'admin_bar_menu', function ( $bar ) {
	$logout = $bar->get_node( 'logout' );
	$bar->remove_node( 'logout' );

	$chevron = '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M10.8622 8.04053L14.2805 12.0286L10.8622 16.0167L9.72327 15.0405L12.3049 12.0286L9.72327 9.01672L10.8622 8.04053Z"/></svg>';
	$check   = '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M16.7 7.1l-6.3 8.5-3.3-2.5-.9 1.2 4.5 3.4L17.9 8z"/></svg>';
	$bar->add_node( array(
		'id'     => 'untangling-workspace',
		'parent' => 'user-actions',
		'title'  => '<span class="untangling-workspace-label">' . esc_html__( 'Workspace' ) . '</span><span class="untangling-workspace-current">' . esc_html__( 'Essential' ) . $chevron . '</span>',
		'href'   => '#',
	) );
	$workspaces = array(
		'essential' => array( __( 'Essential' ), __( 'For bloggers and creators' ), true ),
		'advanced'  => array( __( 'Advanced' ), __( 'For developers and agencies' ), false ),
		'commerce'  => array( __( 'Commerce' ), __( 'For e-commerce stores and Woo' ), false ),
	);
	foreach ( $workspaces as $slug => list( $label, $description, $is_current ) ) {
		$bar->add_node( array(
			'id'     => 'untangling-workspace-' . $slug,
			'parent' => 'untangling-workspace',
			'title'  => '<span class="untangling-ws-option"><span class="untangling-ws-check">' . ( $is_current ? $check : '' ) . '</span><span class="untangling-ws-text"><span class="untangling-ws-label">' . esc_html( $label ) . '</span><span class="untangling-ws-desc">' . esc_html( $description ) . '</span></span></span>',
			'href'   => '#',
			'meta'   => $is_current ? array( 'class' => 'untangling-ws-is-current' ) : array(),
		) );
	}

	if ( $logout ) {
		$bar->add_node( (array) $logout );
	}

	$bar->add_group( array(
		'id'     => 'untangling-account-footer',
		'parent' => 'my-account',
		'meta'   => array( 'class' => 'ab-sub-secondary' ),
	) );
	$bar->add_node( array(
		'id'     => 'untangling-wpcom-account',
		'parent' => 'untangling-account-footer',
		'title'  => '<span class="untangling-wpcom-btn">' . __( 'My' ) . ' <svg width="19" height="19" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" style="vertical-align:text-bottom"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM3.5 12c0-1.23.26-2.4.73-3.46L8.25 19.6C5.44 18.23 3.5 15.34 3.5 12zm8.5 8.5c-.83 0-1.64-.12-2.4-.35l2.55-7.4 2.61 7.15c.02.04.04.08.06.12-.9.31-1.85.48-2.82.48zm1.17-12.49c.51-.03.97-.08.97-.08.46-.05.4-.73-.05-.7 0 0-1.38.11-2.27.11-.84 0-2.24-.11-2.24-.11-.46-.03-.51.68-.06.7 0 0 .43.05.89.08l1.32 3.62-1.86 5.57-3.09-9.19c.51-.03.97-.08.97-.08.46-.05.4-.73-.05-.7 0 0-1.38.11-2.27.11-.16 0-.35 0-.55-.01C6.42 5.09 9.04 3.5 12 3.5c2.21 0 4.22.84 5.73 2.23-.04 0-.07-.01-.11-.01-.84 0-1.43.73-1.43 1.51 0 .7.4 1.29.84 1.99.33.57.71 1.3.71 2.35 0 .73-.28 1.58-.65 2.76l-.85 2.84-3.07-9.16zm3.1 11.36l2.6-7.51c.49-1.21.65-2.19.65-3.05 0-.31-.02-.6-.06-.87.66 1.21 1.04 2.6 1.04 4.06 0 3.13-1.7 5.86-4.23 7.37z"/></svg> ' . __( 'WordPress.com Account' ) . '</span>',
		'href'   => UNTANGLING_MSD_URL . '/me',
	) );
}, 99999 );

/* -------------------------------------------------------------------------
 * 4b. Upsell — one nudge, three homes to compare.
 *
 * The version that ships today is a white card stacked above the menu: in a
 * 160px dark column it reads as a foreign object, pushes the whole navigation
 * down, and wraps its button. Each placement here answers that differently.
 *   menu-top   keep the position, lose the white sheet — a dark card that
 *              belongs to the sidebar, one line of copy, full-width button.
 *   menu-foot  same card, moved below the menu so it costs the navigation
 *              nothing; it is the first thing under the last menu item.
 *   omnibar    drop the card — a pill in the admin bar, always visible on
 *              every screen and zero pixels of sidebar.
 * The offer follows the site type (untangling_upsell_offer): Simple sells
 * the annual plan (free domain), Atomic the two-year renewal — same card,
 * same button, different words.
 * ---------------------------------------------------------------------- */

// The sidebar card wants the glyph cropped tight; the omnibar pill renders the
// full 24-grid frame at 16px, whose ~3px intrinsic whitespace the pill's
// asymmetric padding counts on (matches the MSD masterbar chip).
function untangling_upsell_diamond_svg( $full = false ) {
	$viewbox = $full ? '0 0 24 24' : '4.4 5.4 15.2 13.2';
	return '<svg class="untangling-nudge-gem" viewBox="' . $viewbox . '" aria-hidden="true"><path d="M18.9397 9.87999L15.4197 6.06999L15.3597 6.00999C15.2897 5.93999 15.1997 5.89999 15.0997 5.89999H8.87973C8.77973 5.89999 8.68973 5.93999 8.61973 6.00999L5.05973 9.87999C4.93973 10.01 4.93973 10.21 5.05973 10.34L11.5397 17.86C11.6497 17.99 11.8197 18.07 11.9997 18.07C12.1797 18.07 12.3397 17.99 12.4597 17.86L18.9397 10.34C19.0597 10.21 19.0497 10.01 18.9397 9.87999ZM15.4097 7.53999L17.3297 9.63999H15.1697L15.4097 7.53999ZM14.4297 6.83999L14.1097 9.63999H10.2897L9.64973 6.83999H14.4297ZM8.68973 7.42999L9.19973 9.63999H6.66973L8.68973 7.42999ZM6.61973 10.6H9.42973L10.8397 15.49L6.61973 10.6ZM12.0397 15.87L10.5297 10.6H13.8597L12.0397 15.87ZM14.9697 10.6H17.3797L13.3697 15.24L14.9697 10.6Z"/></svg>';
}

// Both sidebar placements print here. The core `adminmenu` action fires
// *inside* the menu's <ul>, after the last item — so the card ships as an
// <li> (valid there, and already in the right spot for menu-foot). menu-top
// lifts the card out to just above the <ul> and drops the empty slot; both
// nodes are already in the sidebar, so nothing flashes across the page.
add_action( 'adminmenu', function () {
	$placement = untangling_get_active_upsell();
	if ( 'menu-top' !== $placement && 'menu-foot' !== $placement ) {
		return;
	}
	$offer = untangling_upsell_offer();
	?>
	<li id="untangling-nudge-slot">
		<div class="untangling-nudge is-<?php echo esc_attr( $placement ); ?>">
			<p class="untangling-nudge-text"><?php echo esc_html( $offer['text'] ); ?></p>
			<a class="untangling-nudge-cta" href="<?php echo esc_url( untangling_upsell_url( $placement ) ); ?>">
				<?php echo $offer['gem'] ? untangling_upsell_diamond_svg() : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php echo esc_html( $offer['cta'] ); ?>
			</a>
		</div>
	</li>
	<?php
	// Both placements lift the card out of the <ul> and into #adminmenuwrap —
	// above the list for menu-top, below it for menu-foot. Left inside, core's
	// `#adminmenu a` rules out-specify the button's own styles and squash it,
	// so the two placements would not be the same card.
	?>
	<script>
	( function () {
		var slot = document.getElementById( 'untangling-nudge-slot' );
		var nudge = slot && slot.firstElementChild;
		var menu = document.getElementById( 'adminmenu' );
		if ( ! nudge || ! menu || ! menu.parentNode ) {
			return;
		}
		var before = nudge.classList.contains( 'is-menu-top' ) ? menu : menu.nextSibling;
		menu.parentNode.insertBefore( nudge, before );
		slot.parentNode.removeChild( slot );
	} )();
	</script>
	<?php
} );

// The omnibar placement: a pill after the action icons (updates, comments,
// +New), keeping the site's actions together and the offer at the end of the
// row. "Free domain" is all the room there is — the full promise rides in the
// tooltip and on the pricing page it opens.
add_action( 'admin_bar_menu', function ( $bar ) {
	if ( 'omnibar' !== untangling_get_active_upsell() ) {
		return;
	}
	// Root-group nodes render in insertion order, and by now the omnibar mock
	// (999) has already ordered site → updates → comments → +New, so adding
	// the pill here lands it after them.
	$offer = untangling_upsell_offer();
	$bar->add_node( array(
		'id'    => 'untangling-domain-nudge',
		'title' => '<span class="untangling-nudge-pill" data-tip="' . esc_attr( $offer['text'] ) . '">'
			. ( $offer['gem'] ? untangling_upsell_diamond_svg( true ) : '' ) . esc_html( $offer['pill'] ) . '</span>',
		'href'  => untangling_upsell_url( 'omnibar' ),
	) );
}, 1000 );

// The pill ships its CSS apart from the sidebar card: the card only exists
// inside wp-admin, the pill renders anywhere the admin bar does — wp-admin,
// the editor, and the logged-in front end. One palette block up top; every
// rule below reads from it.
function untangling_upsell_omnibar_css() {
	return '
	/* Omnibar pill. Sized to the masterbar row (32px) and kept quiet: the
	   admin bar is chrome, so it borrows the blue for the gem and the border
	   rather than filling solid like a page-level CTA would. */
	#wpadminbar #wp-admin-bar-untangling-domain-nudge {
		--nudge-pill-bg: rgba(56,88,233,0.22);
		--nudge-pill-bg-hover: rgba(56,88,233,0.42);
		--nudge-pill-ring: rgba(120,150,255,0.45);
		--nudge-pill-ring-hover: rgba(150,175,255,0.8);
		--nudge-pill-text: #dcdcde;
		--nudge-pill-text-hover: #fff;
		--nudge-gem: #8ba4ff;
		--nudge-gem-hover: #b8c8ff;
		--nudge-tip-bg: #1e1e1e;
		--nudge-tip-text: #f0f0f1;
	}
	/* Flex-centre the 24px pill in the 32px row — as an inline box it would
	   ride core\'s baseline and sit a couple of pixels high. */
	#wpadminbar #wp-admin-bar-untangling-domain-nudge .ab-item { display: flex; align-items: center; height: 32px; padding: 0 8px; overflow: visible; }
	/* The upsell glyph carries ~3px of intrinsic whitespace at 16px, so the
	   icon-side padding and gap are smaller to read as visually even. */
	#wpadminbar .untangling-nudge-pill { position: relative; display: inline-flex; align-items: center; gap: 4px; height: 24px; padding-block: 0; padding-inline: 4px 8px; border-radius: 4px; background: var(--nudge-pill-bg); box-shadow: inset 0 0 0 1px var(--nudge-pill-ring); color: var(--nudge-pill-text); font-size: 12px; font-weight: 500; line-height: 24px; white-space: nowrap; transition: background .12s linear, box-shadow .12s linear, color .12s linear; }
	#wpadminbar .untangling-nudge-pill .untangling-nudge-gem { width: 16px; height: 16px; fill: var(--nudge-gem); transition: fill .12s linear; }
	/* Hover lifts the same pill instead of flooding it solid — the rest of the
	   admin bar brightens on hover, it does not invert, and a solid blue block
	   in a dark chrome row reads as a page CTA that wandered up here. */
	#wpadminbar #wp-admin-bar-untangling-domain-nudge:hover .ab-item { background: transparent; }
	#wpadminbar #wp-admin-bar-untangling-domain-nudge .ab-item:focus-visible .untangling-nudge-pill,
	#wpadminbar #wp-admin-bar-untangling-domain-nudge:hover .untangling-nudge-pill { background: var(--nudge-pill-bg-hover); box-shadow: inset 0 0 0 1px var(--nudge-pill-ring-hover); color: var(--nudge-pill-text-hover); }
	#wpadminbar #wp-admin-bar-untangling-domain-nudge:hover .untangling-nudge-pill .untangling-nudge-gem { fill: var(--nudge-gem-hover); }
	/* Tooltip: the native title attribute waits about a second and paints an
	   OS box. Same instant data-tip pattern the pricing page uses, centred on
	   the cursor (--untangling-tip-x, set by the delegated listener below) and
	   falling back to the middle of the pill before the first mouseover. */
	#wpadminbar .untangling-nudge-pill::after { content: attr(data-tip); position: absolute; top: calc(100% + 8px); left: var(--untangling-tip-x, 50%); transform: translateX(-50%); width: max-content; max-width: 260px; padding: 4px 8px; border-radius: 2px; background: var(--nudge-tip-bg); box-shadow: 0 2px 6px rgba(0,0,0,0.3); color: var(--nudge-tip-text); font-size: 12px; font-weight: 400; line-height: 1.4; white-space: normal; opacity: 0; pointer-events: none; }
	#wpadminbar #wp-admin-bar-untangling-domain-nudge .ab-item:focus-visible .untangling-nudge-pill::after,
	#wpadminbar #wp-admin-bar-untangling-domain-nudge:hover .untangling-nudge-pill::after { opacity: 1; }
	/* Mobile keeps the full pill. Core hides every top-level bar item that is
	   not on its whitelist, so the node needs its display back explicitly, and
	   the 46px row wants flex-centring — core\'s 3.28 line-height would float
	   the pill — plus its own metrics back from the 14px mobile bump. */
	@media screen and ( max-width: 782px ) {
		#wpadminbar li#wp-admin-bar-untangling-domain-nudge { display: block; }
		#wpadminbar #wp-admin-bar-untangling-domain-nudge .ab-item { display: flex; align-items: center; height: 46px; padding: 0 8px; }
		#wpadminbar .untangling-nudge-pill { font-size: 12px; line-height: 24px; }
		/* The site name gives way before the offer does — core clips it flat,
		   an ellipsis at least says there is more. */
		#wpadminbar #wp-admin-bar-site-name > .ab-item { max-width: 38vw; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
	}
	';
}

// The pricing tips centre their bubble on the cursor with a delegated
// listener, but those live in page bundles that never load out here. One
// global copy, printed wherever the bar can carry the pill.
function untangling_upsell_print_tip_listener() {
	if ( 'omnibar' !== untangling_get_active_upsell() || ! is_admin_bar_showing() ) {
		return;
	}
	?>
	<script>
	document.addEventListener( 'mouseover', function ( event ) {
		var tip = event.target && event.target.closest && event.target.closest( '.untangling-nudge-pill' );
		if ( ! tip ) {
			return;
		}
		/* Position once, where the cursor enters the pill, then freeze. Crossing
		   the pill's children re-fires mouseover; ignore those or the bubble jumps. */
		if ( event.relatedTarget && tip.contains( event.relatedTarget ) ) {
			return;
		}
		/* Clamp: half the 260px bubble plus an 8px gutter, so it never leaves the viewport. */
		var x = Math.max( 138, Math.min( event.clientX, window.innerWidth - 138 ) );
		tip.style.setProperty( '--untangling-tip-x', ( x - tip.getBoundingClientRect().left ) + 'px' );
	} );
	</script>
	<?php
}
add_action( 'admin_footer', 'untangling_upsell_print_tip_listener' );
add_action( 'wp_footer', 'untangling_upsell_print_tip_listener' );

// The admin bar also renders on the logged-in front end, where `common`
// never loads — hang the pill CSS on core's `admin-bar` handle there or the
// chip paints as bare text.
add_action( 'wp_enqueue_scripts', function () {
	if ( 'none' === untangling_get_active_upsell() || ! is_admin_bar_showing() ) {
		return;
	}
	wp_add_inline_style( 'admin-bar', untangling_upsell_omnibar_css() );
}, 11 );

add_action( 'admin_enqueue_scripts', function () {
	if ( 'none' === untangling_get_active_upsell() ) {
		return;
	}
	wp_add_inline_style( 'common', '
	/* Sidebar card. Dark-on-dark so it reads as part of the menu, not an ad
	   dropped on top of it. 160px is the whole budget: the copy gets two
	   lines at 12px, the button the full width, and nothing wraps. */
	.untangling-nudge { margin: 8px; padding: 12px; border-radius: 4px; background: #2c3338; box-shadow: inset 0 0 0 1px rgba(255,255,255,0.08); box-sizing: border-box; }
	.untangling-nudge-text { display: block; margin: 0 0 10px; color: #f0f0f1; font-size: 12px; line-height: 1.4; font-weight: 500; text-wrap: balance; }
	.untangling-nudge-gem { width: 13px; height: 12px; fill: currentColor; flex: none; }
	.untangling-nudge-cta { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 6px 8px; border-radius: 3px; background: #3858e9; color: #fff !important; font-size: 12px; font-weight: 500; line-height: 1.5; text-decoration: none; }
	.untangling-nudge-cta:hover, .untangling-nudge-cta:focus { background: #1d35b4; color: #fff !important; }
	#adminmenu #untangling-nudge-slot { margin: 0; }
	/* Both placements are the same card, down to the margins — the only thing
	   the variations compare is where it sits. */
	/* Folded sidebar (36px) has no room for either card. `auto-fold` is on the
	   body at every width, so it only counts inside the fold breakpoint. */
	.folded .untangling-nudge { display: none; }
	@media screen and ( max-width: 960px ) { .auto-fold .untangling-nudge { display: none; } }
	' . untangling_upsell_omnibar_css() );
}, 11 );

/* -------------------------------------------------------------------------
 * Styles
 * ---------------------------------------------------------------------- */

add_action( 'admin_enqueue_scripts', function () {
	wp_add_inline_style( 'common', '
		.untangling-lede .button { margin-left: 12px; }
		.untangling-upsell-diamond { width: 14px; height: 12px; fill: currentColor; vertical-align: -1px; margin-inline-end: 6px; }
		.untangling-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; margin-top: 16px; max-width: 1080px; }
		.untangling-card { display: block; background: #fff; border: 1px solid #dcdcde; border-radius: 4px; padding: 16px 20px; text-decoration: none; color: inherit; }
		a.untangling-card:hover { border-color: #2271b1; }
		.untangling-card h2 { margin: 0 0 8px; font-size: 14px; color: #646970; }
		.untangling-value { font-size: 18px; font-weight: 600; margin: 0; color: #1d2327; }
		.untangling-meta { color: #646970; margin: 4px 0 0; }
		.untangling-variant-switch { margin-top: 32px; color: #646970; }
		.untangling-tier { font-weight: 600; margin: 8px 0; }
		.untangling-tier.is-upgrade { color: #996800; }
		.untangling-tier.is-included { color: #007017; }
		.untangling-marketplace-intro { max-width: 720px; }
		.untangling-tier-required { color: #996800; }
		.untangling-tier-required strong { color: inherit; }
		/* Core reserves 128px for the action column (120px wide); the wider
		   upgrade CTAs need more room so titles and text never run under
		   them. Page-wide: the Simple-mode Upgrade to install swap hits every
		   Add Plugins tab, not just Marketplace. */
		.plugin-install-php .plugin-card .name,
		.plugin-install-php .plugin-card .desc p { margin-right: 200px; }
		/* The blanked Recommended-tab intro line still renders as an empty <p>. */
		.plugin-install-php .wrap > p:empty { display: none; }
		.plugin-install-php .tablenav .displaying-num { display: none; }
		.plugin-install-php .plugin-card .action-links { width: 190px; }
		/* Match core\'s compact card buttons — the admin refresh inflates
		   .wp-core-ui .button to a 40px min-height. */
		.plugin-install-php .plugin-card .plugin-action-buttons .button { min-height: 30px; line-height: 2.15384615; padding: 0 10px; }
		/* Compatibility cell stacks the core copy and the plan signal; the gap
		   between them matches the Last Updated → Compatible rhythm. */
		.plugin-install-php .plugin-card .column-compatibility .compatibility-compatible,
		.plugin-install-php .plugin-card .column-compatibility .untangling-tier-required { display: block; }
		.plugin-install-php .plugin-card .column-compatibility > span + span { margin-top: 8px; }
		/* Unfloat the subsubsub so the gaps above and below it match; float
		   clearance otherwise swallows the grid top margin. */
		.untangling-cat-filters { float: none; margin: 20px 0; }
		.untangling-marketplace { margin-top: 0; }
		/* One layout for every Add Plugins tab: the marketplace grid, with
		   titles clamped to 2 lines and descriptions to 3 so a single long
		   card cannot stretch its whole row into white space.
		   `.wrap #the-list` (not bare #the-list): newer cores make #the-list
		   its own flex container with the same `.plugin-install-php #the-list`
		   specificity, and loading after us they won — cards shrink-wrapped
		   and wrapped unevenly (seen on Playground). The extra class outranks
		   core on every version, so the grid always decides the layout. */
		.plugin-install-php .wrap #the-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
		.plugin-install-php .wrap .plugin-card { float: none; width: auto; max-width: none; margin: 0; display: flex; flex-direction: column; box-sizing: border-box; }
		.plugin-install-php .plugin-card-top { flex: 1; }
		/* Belt and braces for the same reason: core versions that moved the
		   icon/name rules elsewhere left these cards with giant in-flow
		   icons. Mirror the classic core layout so the cards never depend on
		   which stylesheet a given build ships. */
		.plugin-install-php .wrap .plugin-card .plugin-card-top { position: relative; }
		.plugin-install-php .wrap .plugin-card .plugin-icon { position: absolute; top: 20px; left: 20px; width: 128px; height: 128px; }
		.plugin-install-php .wrap .plugin-card .name,
		.plugin-install-php .wrap .plugin-card .desc > p { margin-left: 148px; }
		@media ( min-width: 1900px ) { .plugin-install-php .wrap #the-list { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
		@media ( max-width: 800px ) { .plugin-install-php .wrap #the-list { grid-template-columns: 1fr; } }
		.plugin-install-php .plugin-card .name h3 { display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden; }
		.plugin-install-php .plugin-card .column-description p:first-of-type { display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 3; overflow: hidden; }
		.untangling-plugins-upsell { display: flex; align-items: center; gap: 20px; padding: 16px 20px; border-left-color: #2271b1; }
		.untangling-plugins-upsell.inline { margin: 20px 0; }
		.plugin-install-php .plugin-card .name h3 { font-size: 16px; }
		/* With the item count gone and one-page pagination auto-hidden, the
		   top tablenav is an empty box that only adds a gap above the cards;
		   the bottom one keeps pagination for long search results. */
		.plugin-install-php .tablenav.top { display: none; }
		.untangling-plugins-upsell-icon.dashicons { flex: none; width: 44px; height: 44px; border-radius: 50%; background: #2271b1; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 24px; }
		.untangling-plugins-upsell-copy { flex: 1; }
		.untangling-plugins-upsell h2 { margin: 0 0 2px; font-size: 15px; }
		.untangling-plugins-upsell p { margin: 0; color: #646970; }

		/* Unified upgrade overlay (placeholder) */
		.untangling-overlay-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.55); z-index: 100001; }
		.untangling-overlay-modal { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; border-radius: 8px; padding: 32px; z-index: 100002; width: min(720px, calc(100vw - 48px)); box-shadow: 0 12px 32px rgba(0,0,0,.25); }
		.untangling-overlay-modal h1 { margin-top: 0; }
		.untangling-overlay-close { position: absolute; top: 10px; right: 14px; border: none; background: none; font-size: 26px; line-height: 1; cursor: pointer; color: #646970; }
		.untangling-overlay-note { color: #646970; max-width: 600px; }
		.untangling-overlay-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 24px; }
		.untangling-overlay-plan { border: 1px solid #dcdcde; border-radius: 4px; padding: 16px 20px; }
		.untangling-overlay-plan.is-current { border-color: #3858e9; box-shadow: inset 0 0 0 1px #3858e9; }
		.untangling-overlay-plan h2 { margin: 0; font-size: 14px; }
		.untangling-overlay-price { font-size: 24px; font-weight: 600; margin: 4px 0; }
		.untangling-overlay-price span { font-size: 12px; color: #646970; font-weight: 400; }
		.untangling-card .untangling-upgrade { margin-top: 8px; }

		/* Omnibar mock — spacing matched to .masterbar__item (padding 0 8px, 24px svgs) */
		#wpadminbar .ab-top-menu > li > .ab-item,
		#wpadminbar .ab-top-secondary > li > .ab-item { padding: 0 8px; }
		#wpadminbar #wp-admin-bar-wp-logo > .ab-item { padding: 0 8px; }
		#wpadminbar .untangling-wpcom-logo { margin-top: -3px; }
		#wpadminbar #wp-admin-bar-comments .count-0 { opacity: .5; }
		#wpadminbar #wp-admin-bar-untangling-updates .ab-icon:before { top: 2px; margin-right: 4px; }
		#wpadminbar .untangling-svg { fill: rgba(240,246,252,.6); vertical-align: middle; }
		#wpadminbar .ab-top-menu > li:hover > .ab-item .untangling-svg,
		#wpadminbar .ab-top-menu > li > .ab-item:focus .untangling-svg { fill: #72aee6; }
		#wpadminbar .untangling-reader-icon { margin-right: 6px; margin-top: -2px; }
		#wpadminbar .untangling-help-icon,
		#wpadminbar .untangling-bell-icon { margin-top: -3px; }
		#wpadminbar .untangling-chip { float: right; margin-left: 16px; padding: 0 8px; border-radius: 10px; background: rgba(255,255,255,.15); font-size: 11px; line-height: 20px; margin-top: 3px; }
		#wpadminbar #wp-admin-bar-my-account .ab-sub-wrapper { min-width: 300px; }
		#wpadminbar #wp-admin-bar-untangling-wpcom-account > .ab-item { height: auto; padding: 10px 12px; }
		/* Same look as the DS primary Button (components-button is-primary) in the Omnibar panel */
		#wpadminbar .untangling-wpcom-btn { display: block; text-align: center; background: #3858e9; color: #fff; border-radius: 2px; padding: 0 14px; height: 34px; line-height: 34px; font-size: 14px; }
		#wpadminbar #wp-admin-bar-untangling-wpcom-account > .ab-item:hover .untangling-wpcom-btn { background: #4664eb; }
		#wpadminbar .untangling-wpcom-btn .dashicons { font-size: 18px; width: 18px; height: 18px; vertical-align: text-bottom; }

		/* Workspace row + switcher flyout, mirroring the Omnibar workspace item */
		/* Core indents user-actions rows under the avatar; the Omnibar panel has
		   Workspace and Log Out spanning the full panel width instead. */
		#wpadminbar #wp-admin-bar-my-account.with-avatar #wp-admin-bar-user-actions > li#wp-admin-bar-untangling-workspace,
		#wpadminbar #wp-admin-bar-my-account.with-avatar #wp-admin-bar-user-actions > li#wp-admin-bar-logout { margin-left: 0; margin-right: 0; }
		#wpadminbar #wp-admin-bar-untangling-workspace > .ab-item,
		#wpadminbar #wp-admin-bar-logout > .ab-item { padding: 0 16px; }
		#wpadminbar #wp-admin-bar-untangling-workspace > .ab-item { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
		#wpadminbar #wp-admin-bar-untangling-workspace > .ab-item:before { content: none; }
		#wpadminbar #wp-admin-bar-untangling-workspace .wp-admin-bar-arrow { display: none; }
		#wpadminbar .untangling-workspace-current { display: inline-flex; align-items: center; color: rgba(240,246,252,.6); }
		#wpadminbar .untangling-workspace-current svg { fill: currentColor; }
		/* Core gives every submenu ul z-index 99999; lift the user-actions ul so the
		   workspace flyout stacks above the account footer that follows it in the DOM. */
		#wpadminbar #wp-admin-bar-user-actions { z-index: 100000; }
		/* Flyout box: computed values lifted from the Omnibar flyout (#0c0c0c bg,
		   6px vertical padding, 220px min width, 3px/5px shadow, -6px top offset). */
		#wpadminbar #wp-admin-bar-untangling-workspace { position: relative; }
		#wpadminbar #wp-admin-bar-untangling-workspace .ab-sub-wrapper { position: absolute; min-width: 220px; top: -6px; right: 100%; margin: 0; box-shadow: 0 3px 5px rgba(0,0,0,.2); }
		#wpadminbar #wp-admin-bar-untangling-workspace .ab-submenu { background: #0c0c0c; padding: 6px 0; }
		#wpadminbar #wp-admin-bar-untangling-workspace li > .ab-item { height: auto; padding: 6px 12px 6px 6px; color: #bcbcbc; }
		/* The Omnibar options render in the browser default <button> font, not the
		   masterbar font — reproduce it (Arial 13.3333px/20px on this stack), and
		   undo core admin-bar 13px/32px font forced on every descendant. */
		#wpadminbar .untangling-ws-option,
		#wpadminbar .untangling-ws-option * { font-family: Arial, sans-serif; font-size: 13.3333px; font-weight: 400; line-height: 20px; }
		#wpadminbar .untangling-ws-option { display: flex; gap: 4px; align-items: flex-start; }
		#wpadminbar .untangling-ws-check { width: 18px; height: 18px; flex: 0 0 18px; }
		#wpadminbar .untangling-ws-check svg { fill: #7b90ff; }
		#wpadminbar .untangling-ws-text { display: flex; flex-direction: column; }
		#wpadminbar .untangling-ws-option .untangling-ws-desc { font-size: 11px; line-height: 16.5px; opacity: .7; }
		#wpadminbar li.untangling-ws-is-current .untangling-ws-label { font-weight: 600; color: #7b90ff; }
		/* Hover matches the Omnibar: text goes highlight blue, background stays. */
		#wpadminbar #wp-admin-bar-untangling-workspace li:hover > .ab-item,
		#wpadminbar #wp-admin-bar-untangling-workspace li > .ab-item:focus { color: #7b90ff; background: #0c0c0c; }
	' );
} );

/* -------------------------------------------------------------------------
 * 5. My Site drawer — the Untangle Calypso IA variant. One item directly
 * below Dashboard; Next steps / Plan & products / Hosting / Help are sidebar
 * children (no tabs). Pages share one shell that mirrors the MSD PageLayout:
 * 1344px column, Recoleta 32px/40px H1, 24px rhythm. All demo state is
 * namespaced untangling_ms_* — see the helpers near untangling_ms_get_plan().
 * ---------------------------------------------------------------------- */

function untangling_render_mysite_page() {
	// .untangling-app scopes the vendored --wpds-* tokens onto this page too.
	echo '<div class="untangling-app untangling-ms"><div id="untangling-ms-root"></div></div>';
}

// Everything the four sections need, resolved server-side once.
function untangling_ms_data() {
	$section = isset( $_GET['ms'] ) && in_array( $_GET['ms'], array( 'plan', 'hosting', 'help' ), true ) ? $_GET['ms'] : 'next';
	$plan    = untangling_ms_get_plan();
	$meta    = untangling_get_plan_meta( $plan );
	// The compare card's upgrade column needs its own checkout link — the
	// shared checkoutUrl below is pinned to Premium for the Free-plan CTAs.
	$compare = untangling_plan_compare( $plan );
	if ( $compare['next'] ) {
		$compare['next']['checkoutUrl'] = untangling_marketplace_url(
			'themes',
			array( 'ustep' => 'checkout', 'plan' => $compare['next']['name'], 'ctx' => 'ms', 'back' => rawurlencode( untangling_current_admin_url() ) )
		);
	}
	$free    = 'Free' === $plan;

	// A purchased storage add-on stretches the meter and retires the
	// space-pressure note.
	$storage_addon = (int) get_option( 'untangling_ms_storage_addon', 0 );
	if ( $storage_addon ) {
		$meta['storage'][1] += $storage_addon;
		$meta['storage'][2]  = sprintf( __( 'Includes the +%d GB add-on.' ), $storage_addon );
		$meta['storage'][3]  = false;
	}

	// Signals: the seeded content the Next steps pool grounds its "why" in.
	$comments       = get_comments( array( 'number' => 1, 'status' => 'approve' ) );
	$signal_comment = null;
	if ( $comments ) {
		$signal_comment = array(
			'author'   => $comments[0]->comment_author,
			'post'     => get_the_title( $comments[0]->comment_post_ID ),
			'time'     => sprintf( __( '%s ago' ), human_time_diff( strtotime( $comments[0]->comment_date_gmt ) ) ),
			'replyUrl' => admin_url( 'comment.php?action=editcomment&c=' . $comments[0]->comment_ID ),
		);
	}
	$recent_posts = get_posts( array( 'numberposts' => 1, 'post_status' => 'publish' ) );
	$signal_post  = $recent_posts ? array(
		'title' => get_the_title( $recent_posts[0] ),
		'time'  => sprintf( __( '%s ago' ), human_time_diff( strtotime( $recent_posts[0]->post_date_gmt ) ) ),
	) : null;
	$products       = get_posts( array( 'numberposts' => 1, 'post_type' => 'product' ) );
	$signal_product = $products ? get_the_title( $products[0] ) : null;

	// Needs attention: empty by design unless something is genuinely wrong.
	$attention = array();
	if ( ! empty( $meta['storage'][3] ) ) {
		$attention[] = array(
			'title'  => __( 'Storage is almost full' ),
			'text'   => sprintf( __( 'You’ve used %1$s GB of %2$s GB. New uploads may fail soon.' ), $meta['storage'][0], $meta['storage'][1] ),
			'action' => __( 'Add storage' ),
			'href'   => untangling_marketplace_url( 'themes', array( 'ustep' => 'pricing', 'ctx' => 'ms', 'back' => rawurlencode( untangling_current_admin_url() ) ) ),
		);
	}

	// PHP log mimic — plausible rows, newest first, deterministic offsets.
	$log_seed = array(
		array( 'error', 'Uncaught Error: Call to undefined function get_field() in wp-content/themes/child-theme/single.php:27', 320 ),
		array( 'warning', 'Undefined array key "utm_source" in wp-content/themes/child-theme/functions.php on line 112', 1210 ),
		array( 'notice', 'Function _load_textdomain_just_in_time was called incorrectly. Translation loading triggered too early.', 3660 ),
		array( 'warning', 'Attempt to read property "post_title" on null in wp-content/plugins/related-posts/render.php on line 54', 7420 ),
		array( 'notice', 'wp_enqueue_script() called incorrectly — scripts should be registered on the wp_enqueue_scripts hook.', 12300 ),
		array( 'warning', 'Cannot modify header information — headers already sent by output started at functions.php:9', 25800 ),
		array( 'notice', 'Deprecated: strpos(): Passing null to parameter #1 ($haystack) in inc/meta.php on line 88', 41200 ),
		array( 'warning', 'Undefined variable $args in wp-content/themes/child-theme/archive.php on line 19', 66300 ),
		array( 'notice', 'Automatic conversion of false to array is deprecated in wp-content/plugins/gallery/gallery.php:203', 90100 ),
		array( 'error', 'Maximum execution time of 30 seconds exceeded in wp-content/plugins/importer/import.php on line 342', 172810 ),
	);
	$logs = array();
	$now  = time();
	foreach ( $log_seed as $row ) {
		$logs[] = array(
			'severity' => $row[0],
			'message'  => $row[1],
			'time'     => gmdate( 'M j, Y, g:i A', $now - $row[2] ),
		);
	}

	// Activity + web-server rows for the Logs card, mirroring the MSD's three
	// log views (Activity / PHP errors / Web server). Icons are PATHS keys.
	$user_name = wp_get_current_user()->display_name;
	if ( ! $user_name ) {
		$user_name = 'Site owner';
	}
	// Each row links to the wp-admin screen that owns the event. The post and
	// comment rows come from the site's real content — the latest published
	// post, and the post behind the newest approved comment — so the link
	// lands on the thing named. Sites with no content keep placeholder copy
	// and land on the list screens.
	$latest = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1 ) );
	$latest = $latest ? $latest[0] : null;
	$recent_comment = get_comments( array( 'status' => 'approve', 'post_type' => 'post', 'number' => 1 ) );
	$recent_comment = $recent_comment ? $recent_comment[0] : null;
	$post_summary    = $latest ? '“' . $latest->post_title . '”' : '“Golden hour at the pier”';
	$post_href       = $latest ? get_edit_post_link( $latest->ID, 'raw' ) : admin_url( 'edit.php' );
	$comment_summary = $recent_comment ? 'On “' . get_the_title( $recent_comment->comment_post_ID ) . '”' : 'On “Fog over the marina”';
	$comment_href    = $recent_comment ? admin_url( 'edit-comments.php?p=' . (int) $recent_comment->comment_post_ID . '&comment_status=approved' ) : admin_url( 'edit-comments.php?comment_status=approved' );
	$activity_seed = array(
		array( 'plugin', 'Plugin update available', 'Jetpack 14.8 is ready to install.', 'WordPress', 7200, admin_url( 'plugins.php?plugin_status=upgrade' ) ),
		array( 'post', 'Post published', $post_summary, $user_name, 28800, $post_href ),
		array( 'comment', 'Comment approved', $comment_summary, $user_name, 93600, $comment_href ),
		array( 'pencil', 'Theme customized', 'Colors and typography updated.', $user_name, 121000, admin_url( 'site-editor.php?p=%2Fstyles' ) ),
		array( 'login', 'Login succeeded', 'From Safari on macOS.', $user_name, 205000, admin_url( 'profile.php' ) ),
		array( 'plugin', 'Plugin activated', 'Jetpack', $user_name, 292000, admin_url( 'plugins.php?plugin_status=active' ) ),
	);
	$activity = array();
	foreach ( $activity_seed as $row ) {
		$activity[] = array(
			'icon'    => $row[0],
			'title'   => $row[1],
			'summary' => $row[2],
			'actor'   => $row[3],
			'href'    => $row[5],
			'time'    => gmdate( 'M j, Y, g:i A', $now - $row[4] ),
			// The Dashboard widget's feed rows keep a short relative stamp on
			// the right (the Logs table keeps the full UTC column).
			'ago'     => sprintf( __( '%s ago' ), human_time_diff( $now - $row[4], $now ) ),
		);
	}
	$server_seed = array(
		array( 200, 'GET', '/wp-json/wp/v2/posts?per_page=10&_embed=1', 60 ),
		array( 200, 'POST', '/wp-admin/admin-ajax.php', 340 ),
		array( 404, 'GET', '/apple-touch-icon.png', 1220 ),
		array( 200, 'GET', '/?feed=rss2', 4100 ),
		array( 301, 'GET', '/gallery', 9800 ),
		array( 200, 'GET', '/wp-content/uploads/2026/08/pier-golden-hour-1600.jpg', 15600 ),
	);
	$server_logs = array();
	foreach ( $server_seed as $row ) {
		$server_logs[] = array(
			'status' => $row[0],
			'method' => $row[1],
			'url'    => $row[2],
			'time'   => gmdate( 'M j, Y, g:i A', $now - $row[3] ),
		);
	}

	return array(
		'msd'          => UNTANGLING_MSD_URL,
		'adminUrl'     => admin_url(),
		'pageUrl'      => admin_url( 'admin.php?page=untangling-mysite' ),
		'section'      => $section,
		'locked'       => untangling_is_locked_demo(),
		'plan'         => $plan,
		'planMeta'     => $meta,
		'planCompare'  => $compare,
		'planOverride' => (bool) get_option( 'untangling_ms_plan_override' ),
		'state'        => untangling_ms_get_state(),
		'lpDone'       => array_values( (array) get_option( 'untangling_ms_lp_done', array( 'design' ) ) ),
		'lpComplete'   => (bool) get_option( 'untangling_ms_lp_complete' ),
		'siteName'     => get_bloginfo( 'name' ),
		'siteIcon'     => get_site_icon_url( 64 ),
		'siteUrl'      => home_url( '/' ),
		'siteSlug'     => untangling_get_site_slug(),
		'domain'       => untangling_get_primary_domain(),
		'domainUpsell' => untangling_get_domain_upsell(),
		'visibility'   => untangling_get_visibility(),
		'siteType'     => untangling_get_site_type(),
		// Store-flavored content follows WooCommerce presence, not the plan —
		// a store on the Business plan with Woo installed is the real-world case.
		'hasWoo'       => class_exists( 'WooCommerce' ),
		'aboutEditUrl' => ( $about = get_page_by_path( 'about' ) ) ? admin_url( 'post.php?post=' . $about->ID . '&action=edit' ) : admin_url( 'edit.php?post_type=page' ),
		// `back` matters here: without it the pricing page's ✕ exits to the
		// retired pre-drawer Hosting page, which is the one screen nobody should
		// be able to reach. With it, ✕ returns to the section they left.
		'plansUrl'     => untangling_marketplace_url( 'themes', array( 'ustep' => 'pricing', 'ctx' => 'ms', 'back' => rawurlencode( untangling_current_admin_url() ) ) ),
		'checkoutUrl'  => untangling_marketplace_url( 'themes', array( 'ustep' => 'checkout', 'plan' => 'Premium', 'ctx' => 'ms', 'back' => rawurlencode( untangling_current_admin_url() ) ) ),
		'domainSearchUrl' => untangling_domain_search_url(),
		'domainClaimUrl'  => untangling_marketplace_url( 'themes', array( 'ustep' => 'pricing', 'ctx' => 'ms', 'domain' => untangling_get_domain_upsell(), 'back' => rawurlencode( untangling_current_admin_url() ) ) ),
		'storageAddon'    => $storage_addon,
		'storagePricing'  => untangling_storage_addon_pricing(),
		'storageAddonUrl' => untangling_marketplace_url( 'themes', array( 'ustep' => 'checkout', 'addon' => 'storage', 'ctx' => 'ms', 'back' => rawurlencode( untangling_current_admin_url() ) ) ),
		'signals'      => array(
			'comment'    => $signal_comment,
			'lastPost'   => $signal_post,
			'topProduct' => $signal_product,
		),
		'attention'    => $attention,
		'hosting'      => untangling_ms_hosting_state(),
		'logs'         => $logs,
		'activity'     => $activity,
		'serverLogs'   => $server_logs,
		// Dashboard-variant extras. Deterministic mock traffic (last 7 days,
		// deltas vs the week before) — same spirit as the perf series; content
		// counts and versions feed the Site details widget.
		'variant'      => untangling_get_variant(),
		'planPageUrl'  => admin_url( 'admin.php?page=untangling-mysite&ms=plan' ),
		'wpVersion'    => get_bloginfo( 'version' ),
		// A pending core update, real when WordPress knows one; the demo
		// otherwise shows the next minor so the row's CTA is always exercised.
		'wpUpdate'     => untangling_dw_core_update_version(),
		'updateUrl'    => admin_url( 'update-core.php' ),
		'phpVersion'   => implode( '.', array_slice( explode( '.', PHP_VERSION ), 0, 2 ) ),
		'counts'       => array(
			'posts'    => (int) wp_count_posts()->publish,
			'pages'    => (int) wp_count_posts( 'page' )->publish,
			'comments' => (int) wp_count_comments()->approved,
		),
		'stats'        => array(
			'views'         => array( 286, 312, 264, 341, 298, 372, 409 ),
			'visitors'      => array( 178, 195, 160, 208, 181, 224, 246 ),
			'viewsTotal'    => 2282,
			'visitorsTotal' => 1392,
			'viewsDelta'    => '+12%',
			'visitorsDelta' => '+9%',
		),
		// The Plan widget's one promo slot on paid plans: the same two-year
		// renewal deal the omnibar pill sells, through the same checkout mimic.
		'renewUrl'     => untangling_marketplace_url( 'themes', array( 'ustep' => 'checkout', 'plan' => $plan, 'flow' => 'renew', 'ctx' => 'ms', 'back' => rawurlencode( untangling_current_admin_url() ) ) ),
	);
}

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'toplevel_page_untangling-mysite' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'wp-components' );
	wp_register_script( 'untangling-ms-app', '', array( 'wp-element', 'wp-components', 'wp-i18n' ), '0.1.0', true );
	wp_enqueue_script( 'untangling-ms-app' );
	wp_add_inline_script( 'untangling-ms-app', 'window.untanglingMsData = ' . wp_json_encode( untangling_ms_data() ) . ';', 'before' );
	wp_add_inline_script( 'untangling-ms-app', untangling_ms_app_js() );
	// The shared stylesheet carries the --wpds-* tokens; the ms sheet only
	// adds this page's own classes on top.
	wp_add_inline_style( 'wp-components', untangling_app_css() . untangling_ms_app_css() );
} );

/* -------------------------------------------------------------------------
 * 8b. All-in Dashboard variant: the My Site content as core Dashboard
 *     widgets ("push to explore all-in on dashboard" — activity log, backups,
 *     checklist etc. as widgets). Every widget is a preview that links to the
 *     MSD as the one full management surface. Postbox chrome is kept on
 *     purpose: Screen Options, drag-reorder, and collapse come free from core
 *     and are exactly the "integrated with Core" feel.
 * ---------------------------------------------------------------------- */

// The user's saved layout preference (1 / 2 / 3), defaulting to the designed
// three-column look. Registration reads it so each layout gets a designed
// default distribution, not a core-JS afterthought.
function untangling_dw_columns() {
	$columns = (int) get_user_option( 'screen_layout_dashboard' );
	return in_array( $columns, array( 1, 2, 3 ), true ) ? $columns : 3;
}

// Default IA at 3 columns (the designed first look): column 1 (normal) is
// the site itself — plan, vitals, history; column 2 (side) is the one thing
// to do — Next steps, alone, so the action has the page's centre; column 3
// is what the site is doing — traffic, protection, infrastructure. At 1–2
// columns: action → traffic → history on the left, plan + machine on the
// right. Users can rearrange; untangling_dw_layout() is the one map both
// registration and the order-snap filter read.
// Jetpack-backed widgets carry the mark in the postbox title (mark + name),
// the one place the credit lives — no "Powered by Jetpack" line in the body
// or footer. The title string also feeds the Screen Options checkbox label,
// where the mark is hidden via CSS.
function untangling_dw_jetpack_title( $title ) {
	$mark = '<svg class="ms-jp-mark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="16" height="16" aria-hidden="true" focusable="false">'
		. '<path fill="#069e08" d="M16,0C7.2,0,0,7.2,0,16s7.2,16,16,16s16-7.2,16-16S24.8,0,16,0z"/>'
		. '<polygon fill="#ffffff" points="15,19 7,19 15,3 "/><polygon fill="#ffffff" points="17,29 17,13 25,13 "/></svg>';
	return '<span class="untangling-dw-title">' . $mark . '<span>' . esc_html( $title ) . '</span></span>';
}

add_action( 'wp_dashboard_setup', function () {
	if ( 'dashboard' !== untangling_get_variant() ) {
		return;
	}

	$mount = function ( $id ) {
		return function () use ( $id ) {
			// .untangling-app scopes the vendored --wpds-* tokens,
			// .untangling-ms the component styles, .untangling-dw the
			// postbox-fit overrides.
			echo '<div class="untangling-app untangling-ms untangling-dw untangling-dw-mount" data-widget="' . esc_attr( $id ) . '"></div>';
		};
	};

	$titles = array(
		'untangling_dw_next_steps' => array( __( 'Next steps' ), 'next' ),
		'untangling_dw_stats'      => array( untangling_dw_jetpack_title( __( 'Stats' ) ), 'stats' ),
		'untangling_dw_activity'   => array( untangling_dw_jetpack_title( __( 'Activity' ) ), 'activity' ),
		'untangling_dw_glance'     => array( __( 'Site details' ), 'glance' ),
		'untangling_dw_protection' => array( untangling_dw_jetpack_title( __( 'Protection' ) ), 'protection' ),
		'untangling_dw_hosting'    => array( __( 'Hosting' ), 'hosting' ),
		'untangling_dw_plan'       => array( __( 'Plan' ), 'plan' ),
	);
	// Walk the designed map well by well; the priority ladder (high → core →
	// default → low) keeps each well's order. A saved drag order (user meta)
	// still wins over this.
	$ladder = array( 'high', 'core', 'default', 'low' );
	foreach ( untangling_dw_layout() as $context => $ids ) {
		foreach ( array_values( $ids ) as $i => $id ) {
			wp_add_dashboard_widget( $id, $titles[ $id ][0], $mount( $titles[ $id ][1] ), null, null, $context, $ladder[ min( $i, 3 ) ] );
		}
	}

	// The welcome panel's job (first look at your site, next actions) is the
	// Next steps widget now. No clean filter exists for its user-meta
	// switch, so the render hook goes and the CSS below hides the shell.
	remove_action( 'welcome_panel', 'wp_welcome_panel' );

	// Core's Activity widget shares its name with our replacement. It stays
	// available, but a re-enabled one must not read as a duplicate — in
	// Screen Options or in its postbox header — so the legacy one is marked
	// "(classic)". (Core's At a Glance keeps its name: ours is "Site details".)
	// Core has already registered it by the time this action fires.
	global $wp_meta_boxes;
	if ( isset( $wp_meta_boxes['dashboard'] ) ) {
		foreach ( $wp_meta_boxes['dashboard'] as $context => $priorities ) {
			foreach ( $priorities as $priority => $boxes ) {
				foreach ( array( 'dashboard_activity' ) as $box_id ) {
					if ( ! empty( $boxes[ $box_id ]['title'] ) ) {
						$wp_meta_boxes['dashboard'][ $context ][ $priority ][ $box_id ]['title'] .= ' ' . __( '(classic)' );
					}
				}
			}
		}
	}
} );

// The designed distribution per layout: well => widget ids, top to bottom.
// Read by registration (context + priority) and by the order-snap filter, so
// the two never disagree.
// The pending core version for the Site details widget: what WordPress
// reports when an update is queued, else the next minor as demo data so the
// "Update to …" CTA is always on screen. Empty string = nothing to show.
function untangling_dw_core_update_version() {
	$current = get_bloginfo( 'version' );
	$updates = get_site_transient( 'update_core' );
	if ( ! empty( $updates->updates ) ) {
		foreach ( $updates->updates as $u ) {
			if ( isset( $u->response, $u->current ) && 'upgrade' === $u->response && version_compare( $u->current, $current, '>' ) ) {
				return $u->current;
			}
		}
	}
	$parts = explode( '.', $current );
	return $parts[0] . '.' . ( (int) ( isset( $parts[1] ) ? $parts[1] : 0 ) + 1 );
}

function untangling_dw_layout( $columns = null ) {
	$columns = $columns ?: untangling_dw_columns();
	if ( 3 === $columns ) {
		return array(
			'normal'  => array( 'untangling_dw_plan', 'untangling_dw_glance', 'untangling_dw_activity' ),
			'side'    => array( 'untangling_dw_next_steps' ),
			'column3' => array( 'untangling_dw_stats', 'untangling_dw_protection', 'untangling_dw_hosting' ),
		);
	}
	return array(
		'normal' => array( 'untangling_dw_next_steps', 'untangling_dw_stats', 'untangling_dw_activity' ),
		'side'   => array( 'untangling_dw_plan', 'untangling_dw_glance', 'untangling_dw_protection', 'untangling_dw_hosting' ),
	);
}

// Layout offers 1 / 2 / 3 columns (core's own Screen Options radio). The
// designed default is three columns — the user's pick persists in user meta
// and "Reset demo" clears it back. Core's fourth column is left out: seven widgets
// spread over four wells reads as debris, not a dashboard.
add_filter( 'screen_layout_columns', function ( $columns ) {
	if ( 'dashboard' === untangling_get_variant() ) {
		$columns['dashboard'] = 3;
	}
	return $columns;
} );
add_filter( 'get_user_option_screen_layout_dashboard', function ( $value ) {
	if ( 'dashboard' === untangling_get_variant() && ! in_array( (int) $value, array( 1, 2, 3 ), true ) ) {
		return 3;
	}
	return $value;
} );

// A saved drag order replays verbatim across layout switches — and core's
// postboxes JS saves the current arrangement whenever the column count
// changes — so widgets get stranded in wells the new layout doesn't show:
// an empty third column at 3, a populated overflow column back at 2. When
// the saved wells don't match the current layout, snap the untangling
// widgets to this layout's designed distribution and leave every other
// widget where the user put it. A drag done within the current layout
// leaves the wells consistent and is respected as saved.
add_filter( 'get_user_option_meta-box-order_dashboard', function ( $order ) {
	if ( 'dashboard' !== untangling_get_variant() || ! is_array( $order ) ) {
		return $order;
	}
	$columns = untangling_dw_columns();
	$lists   = array();
	foreach ( array( 'normal', 'side', 'column3', 'column4' ) as $area ) {
		$lists[ $area ] = array_values( array_filter( explode( ',', (string) ( $order[ $area ] ?? '' ) ) ) );
	}
	$mismatch = ( 3 === $columns ) ? ! $lists['column3'] : (bool) ( $lists['column3'] || $lists['column4'] );
	if ( ! $mismatch ) {
		return $order;
	}
	$layout = untangling_dw_layout( $columns );
	$ours   = array_merge( ...array_values( $layout ) );
	foreach ( $lists as $area => $ids ) {
		$lists[ $area ] = array_values( array_diff( $ids, $ours ) );
	}
	if ( 3 === $columns ) {
		$lists['normal']  = array_merge( $layout['normal'], $lists['normal'] );
		$lists['side']    = array_merge( $layout['side'], $lists['side'] );
		$lists['column3'] = array_merge( $layout['column3'], $lists['column3'] );
	} else {
		// Anything else stranded in the overflow wells folds into the side.
		$lists['normal']  = array_merge( $layout['normal'], $lists['normal'] );
		$lists['side']    = array_merge( $layout['side'], $lists['side'], $lists['column3'], $lists['column4'] );
		$lists['column3'] = array();
		$lists['column4'] = array();
	}
	foreach ( $lists as $area => $ids ) {
		$order[ $area ] = implode( ',', $ids );
	}
	return $order;
} );

// Core (and Woo) widgets are curated, not removed: unchecked by default,
// one Screen Options checkbox away. The filter only supplies defaults —
// the moment someone touches Screen Options their own user meta wins, and
// "Reset demo" clears that meta to restore the designed first look.
add_filter( 'default_hidden_meta_boxes', function ( $hidden, $screen ) {
	if ( 'dashboard' !== $screen->id || 'dashboard' !== untangling_get_variant() ) {
		return $hidden;
	}
	return array_unique( array_merge( $hidden, array(
		'dashboard_right_now',
		'dashboard_activity',
		'dashboard_quick_press',
		'dashboard_primary',
		'dashboard_site_health',
		// Woo registers these on the two store sites; absent ids are harmless.
		'woocommerce_dashboard_status',
		'woocommerce_dashboard_recent_reviews',
		'wc_admin_dashboard_setup',
	) ) );
}, 10, 2 );

/* -------------------------------------------------------------------------
 * 8c. Foreign widgets. The median Business site is not the pristine
 *     screenshot: it runs WooCommerce, Yoast SEO, and Elementor, and each of
 *     them drops a widget on the dashboard. An Established site carries the
 *     three, rendered the way those plugins render them (their markup, their
 *     colors, their copy — not ours), so every layout is judged with them
 *     present. They register like any plugin's widget: default context and
 *     priority, so they land where a real install would put them (column 1,
 *     between Site details and Activity, pushing the history down), and Screen Options,
 *     drag, and collapse all work. Just created sites have no plugins yet, so
 *     nothing registers there.
 * ---------------------------------------------------------------------- */

function untangling_fw_active() {
	return 'dashboard' === untangling_get_variant() && 'established' === untangling_ms_get_state();
}

function untangling_fw_ids() {
	return array( 'untangling_fw_woo', 'untangling_fw_yoast', 'untangling_fw_elementor' );
}

add_action( 'wp_dashboard_setup', function () {
	if ( ! untangling_fw_active() ) {
		return;
	}
	// Titles and contexts as the plugins register them.
	wp_add_dashboard_widget( 'untangling_fw_woo', 'WooCommerce Status', 'untangling_fw_render_woo' );
	wp_add_dashboard_widget( 'untangling_fw_yoast', 'Yoast SEO Posts Overview', 'untangling_fw_render_yoast' );
	wp_add_dashboard_widget( 'untangling_fw_elementor', 'Elementor Overview', 'untangling_fw_render_elementor' );
}, 20 );

// WooCommerce Status: the status list. Numbers are a demo month; the top
// seller is the store's first real product when Woo is around.
function untangling_fw_render_woo() {
	$product = class_exists( 'WooCommerce' ) ? get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 1, 'orderby' => 'title', 'order' => 'ASC' ) ) : array();
	$seller  = $product ? $product[0]->post_title : 'Linen tote bag';
	$rows    = array(
		array( 'sales-this-month', admin_url( 'admin.php?page=wc-admin&path=%2Fanalytics%2Frevenue' ), '<strong>$1,284.00</strong> net sales this month' ),
		array( 'best-seller-this-month', admin_url( 'admin.php?page=wc-admin&path=%2Fanalytics%2Fproducts' ), '<strong>' . esc_html( $seller ) . '</strong> top seller this month (sold 18)' ),
		array( 'processing-orders', admin_url( 'admin.php?page=wc-orders&status=wc-processing' ), '<strong>4 orders</strong> awaiting processing' ),
		array( 'on-hold-orders', admin_url( 'admin.php?page=wc-orders&status=wc-on-hold' ), '<strong>1 order</strong> on-hold' ),
		array( 'low-in-stock', admin_url( 'admin.php?page=wc-reports&tab=stock&report=low_in_stock' ), '<strong>2 products</strong> low in stock' ),
		array( 'out-of-stock', admin_url( 'admin.php?page=wc-reports&tab=stock&report=out_of_stock' ), '<strong>1 product</strong> out of stock' ),
	);
	echo '<div class="untangling-fw untangling-fw-woo"><ul class="wc_status_list">';
	foreach ( $rows as $row ) {
		echo '<li class="' . esc_attr( $row[0] ) . '"><a href="' . esc_url( $row[1] ) . '">' . $row[2] . '</a></li>';
	}
	echo '</ul></div>';
}

// Yoast SEO Posts Overview: SEO and readability score tallies over the
// site's published posts, split deterministically so the bars never lie
// about the count.
function untangling_fw_render_yoast() {
	$total = (int) wp_count_posts( 'post' )->publish;
	if ( $total < 4 ) {
		$total = 12;
	}
	$good = (int) round( $total * 0.42 );
	$ok   = (int) round( $total * 0.25 );
	$bad  = (int) round( $total * 0.17 );
	$na   = max( 0, $total - $good - $ok - $bad );
	$list = function ( $rows ) {
		echo '<ul class="wpseo-dashboard-overview__scores">';
		foreach ( $rows as $row ) {
			echo '<li><a href="' . esc_url( admin_url( 'edit.php?' . $row[2] ) ) . '"><span class="wpseo-score-icon ' . esc_attr( $row[0] ) . '"></span>' . esc_html( $row[1] ) . '</a></li>';
		}
		echo '</ul>';
	};
	echo '<div class="untangling-fw untangling-fw-yoast">';
	echo '<p>Below are your published posts’ SEO scores. Now is as good a time as any to start improving some of your posts!</p>';
	$list( array(
		array( 'good', "Posts with a good SEO score: $good", 'seo_filter=good' ),
		array( 'ok', "Posts with an OK SEO score: $ok", 'seo_filter=ok' ),
		array( 'bad', "Posts that need improvement: $bad", 'seo_filter=bad' ),
		array( 'na', "Posts without a focus keyphrase: $na", 'seo_filter=na' ),
	) );
	echo '<p>Below are your published posts’ Readability Scores.</p>';
	$list( array(
		array( 'good', 'Posts with a good readability score: ' . ( $good + $ok ), 'readability_filter=good' ),
		array( 'ok', 'Posts with an OK readability score: ' . $bad, 'readability_filter=ok' ),
		array( 'bad', 'Posts that need improvement: ' . $na, 'readability_filter=bad' ),
	) );
	echo '</div>';
}

// Elementor Overview: version header with the Create New Page button, the
// site's real recently edited entries, the news list, the footer links.
function untangling_fw_render_elementor() {
	$recent = get_posts( array( 'post_type' => array( 'page', 'post' ), 'post_status' => 'publish', 'numberposts' => 3, 'orderby' => 'modified', 'order' => 'DESC' ) );
	$news   = array(
		array( 'Introducing Elementor 3.31: faster editing and new layout controls', 'https://elementor.com/blog/' ),
		array( 'Getting started with Elementor: the core concepts', 'https://elementor.com/help/' ),
		array( 'How to build a custom header and footer with Elementor Pro', 'https://elementor.com/help/' ),
	);
	$logo = '<svg viewBox="0 0 32 32" width="28" height="28" aria-hidden="true" focusable="false"><circle cx="16" cy="16" r="16" fill="#d30c5c"/><path fill="#fff" d="M10 9h3v14h-3zM15 9h8v3h-8zM15 14.5h8v3h-8zM15 20h8v3h-8z"/></svg>';
	echo '<div class="untangling-fw untangling-fw-elementor">';
	echo '<div class="e-overview__header"><div class="e-overview__logo">' . $logo . '</div><div class="e-overview__versions"><span class="e-overview__version">Elementor v3.31.2</span></div>';
	echo '<div class="e-overview__create"><a href="' . esc_url( admin_url( 'post-new.php?post_type=page' ) ) . '" class="button">Create New Page</a></div></div>';
	echo '<div class="e-overview__recently-edited"><h3 class="e-heading">Recently Edited</h3><ul class="e-overview__posts">';
	foreach ( $recent as $post ) {
		echo '<li class="e-overview__post"><a href="' . esc_url( get_edit_post_link( $post->ID, 'raw' ) ) . '" class="e-overview__post-link">' . esc_html( get_the_title( $post ) ) . ' <span class="dashicons dashicons-edit"></span></a> <span class="e-overview__post-description">' . esc_html( get_the_modified_date( 'M j, Y, g:i a', $post ) ) . '</span></li>';
	}
	echo '</ul></div>';
	echo '<div class="e-overview__feed"><h3 class="e-heading">News &amp; Updates</h3><ul class="e-overview__posts">';
	foreach ( $news as $item ) {
		echo '<li class="e-overview__post"><a href="' . esc_url( $item[1] ) . '" class="e-overview__post-link" target="_blank" rel="noopener">' . esc_html( $item[0] ) . '</a></li>';
	}
	echo '</ul></div>';
	echo '<div class="e-overview__footer"><ul><li><a href="https://elementor.com/blog/" target="_blank" rel="noopener">Blog</a></li><li><a href="https://elementor.com/help/" target="_blank" rel="noopener">Help</a></li><li class="e-overview__go-pro"><a href="https://elementor.com/pro/" target="_blank" rel="noopener">Go Pro</a></li></ul></div>';
	echo '</div>';
}

// The three plugins' own dashboard styles, reduced to what their widgets use.
function untangling_fw_css() {
	return <<<'CSS'
/* WooCommerce Status: the two-up status list with dashicon bullets. */
.untangling-fw-woo { margin: -11px -12px -12px; }
.untangling-fw-woo .wc_status_list { overflow: hidden; margin: 0; }
.untangling-fw-woo .wc_status_list li { width: 50%; float: left; padding: 9px 12px 9px 40px; box-sizing: border-box; margin: 0; border-top: 1px solid #ececec; color: #757575; font-size: 12px; line-height: 1.4; position: relative; }
.untangling-fw-woo .wc_status_list li:nth-child(-n+2) { border-top: 0; }
.untangling-fw-woo .wc_status_list li:nth-child(odd) { border-right: 1px solid #ececec; }
.untangling-fw-woo .wc_status_list li a { display: block; color: inherit; text-decoration: none; position: relative; }
.untangling-fw-woo .wc_status_list li a:hover { color: #7f54b3; }
.untangling-fw-woo .wc_status_list li strong { display: block; font-size: 18px; line-height: 1.2; color: #1e1e1e; }
.untangling-fw-woo .wc_status_list li::before { font-family: dashicons; font-size: 16px; width: 16px; height: 16px; position: absolute; top: 12px; left: 12px; color: #7f54b3; }
.untangling-fw-woo .sales-this-month::before { content: "\f185"; }
.untangling-fw-woo .best-seller-this-month::before { content: "\f155"; }
.untangling-fw-woo .processing-orders::before { content: "\f174"; }
.untangling-fw-woo .on-hold-orders::before { content: "\f469"; }
.untangling-fw-woo .low-in-stock::before { content: "\f534"; }
.untangling-fw-woo .out-of-stock::before { content: "\f153"; }

/* Yoast SEO Posts Overview: score bullets. */
.untangling-fw-yoast p { margin: 0 0 8px; }
.untangling-fw-yoast .wpseo-dashboard-overview__scores { list-style: none; margin: 0 0 16px; padding: 0; }
.untangling-fw-yoast .wpseo-dashboard-overview__scores li { margin: 0 0 6px; }
.untangling-fw-yoast .wpseo-dashboard-overview__scores a { text-decoration: none; color: #1e1e1e; }
.untangling-fw-yoast .wpseo-dashboard-overview__scores a:hover { color: #a4286a; text-decoration: underline; }
.untangling-fw-yoast .wpseo-score-icon { display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin: 0 8px -1px 0; background: #888; }
.untangling-fw-yoast .wpseo-score-icon.good { background: #7ad03a; }
.untangling-fw-yoast .wpseo-score-icon.ok { background: #ee7c1b; }
.untangling-fw-yoast .wpseo-score-icon.bad { background: #dc3232; }
.untangling-fw-yoast .wpseo-score-icon.na { background: #888; }

/* Elementor Overview: header band, recently edited, news, footer. */
.untangling-fw-elementor { margin: -11px -12px -12px; }
.untangling-fw-elementor .e-overview__header { display: flex; align-items: center; gap: 12px; padding: 12px; border-bottom: 1px solid #ececec; }
.untangling-fw-elementor .e-overview__logo { display: flex; }
.untangling-fw-elementor .e-overview__versions { flex: 1; color: #1e1e1e; font-size: 13px; }
.untangling-fw-elementor .e-overview__create .button { border-color: #d30c5c; color: #d30c5c; background: #fff; }
.untangling-fw-elementor .e-overview__create .button:hover { background: #d30c5c; color: #fff; }
.untangling-fw-elementor .e-heading { margin: 0 0 6px; font-size: 13px; font-weight: 600; color: #1e1e1e; }
.untangling-fw-elementor .e-overview__recently-edited, .untangling-fw-elementor .e-overview__feed { padding: 12px; border-bottom: 1px solid #ececec; }
.untangling-fw-elementor .e-overview__posts { margin: 0; }
.untangling-fw-elementor .e-overview__post { margin: 0 0 6px; display: flex; justify-content: space-between; gap: 12px; }
.untangling-fw-elementor .e-overview__post-link { text-decoration: none; }
.untangling-fw-elementor .e-overview__post-link .dashicons { font-size: 14px; width: 14px; height: 14px; vertical-align: -2px; color: #757575; }
.untangling-fw-elementor .e-overview__post-description { color: #757575; font-size: 12px; white-space: nowrap; }
.untangling-fw-elementor .e-overview__footer ul { display: flex; margin: 0; padding: 10px 12px; }
.untangling-fw-elementor .e-overview__footer li { margin: 0; padding: 0 10px; border-left: 1px solid #ececec; }
.untangling-fw-elementor .e-overview__footer li:first-child { padding-left: 0; border-left: 0; }
.untangling-fw-elementor .e-overview__footer a { text-decoration: none; }
.untangling-fw-elementor .e-overview__go-pro a { color: #d30c5c; font-weight: 600; }
CSS;
}

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'index.php' !== $hook || 'dashboard' !== untangling_get_variant() ) {
		return;
	}
	wp_enqueue_style( 'wp-components' );
	wp_register_script( 'untangling-ms-app', '', array( 'wp-element', 'wp-components', 'wp-i18n' ), '0.1.0', true );
	wp_enqueue_script( 'untangling-ms-app' );
	wp_add_inline_script( 'untangling-ms-app', 'window.untanglingMsData = ' . wp_json_encode( untangling_ms_data() ) . ';', 'before' );
	// The same app as the My Site page: the shared components render the
	// widgets, and each surface's mount loop no-ops on the other.
	wp_add_inline_script( 'untangling-ms-app', untangling_ms_app_js() );
	wp_add_inline_style( 'wp-components', untangling_app_css() . untangling_ms_app_css() . untangling_dw_css() . untangling_fw_css() );
} );

// Screen Options, grouped. Core prints one flat run of checkboxes; the
// re-parenting below moves the SAME label nodes (never clones — core's
// show/hide bindings live on them) into fieldsets with headings, so the
// panel reads as a small map of the page instead of a word soup.
add_action( 'admin_footer-index.php', function () {
	if ( 'dashboard' !== untangling_get_variant() ) {
		return;
	}
	?>
	<script>
	( function () {
		var prefs = document.querySelector( '#adv-settings .metabox-prefs' );
		if ( ! prefs ) {
			return;
		}
		var GROUPS = [
			{ title: <?php echo wp_json_encode( __( 'Site' ) ); ?>, ids: [ 'untangling_dw_glance', 'untangling_dw_next_steps' ] },
			{ title: <?php echo wp_json_encode( __( 'Traffic and activity' ) ); ?>, ids: [ 'untangling_dw_stats', 'untangling_dw_activity' ] },
			{ title: <?php echo wp_json_encode( __( 'Protection and hosting' ) ); ?>, ids: [ 'untangling_dw_protection', 'untangling_dw_hosting' ] },
			{ title: <?php echo wp_json_encode( __( 'Plan' ) ); ?>, ids: [ 'untangling_dw_plan' ] },
			{ title: <?php echo wp_json_encode( __( 'Plugins' ) ); ?>, ids: [ 'untangling_fw_woo', 'untangling_fw_yoast', 'untangling_fw_elementor', 'woocommerce_dashboard_status', 'woocommerce_dashboard_recent_reviews', 'wc_admin_dashboard_setup' ] },
			{ title: <?php echo wp_json_encode( __( 'More WordPress' ) ); ?>, ids: [] },
		];
		var labels = Array.prototype.slice.call( prefs.querySelectorAll( 'label[for$="-hide"]' ) );
		if ( ! labels.length ) {
			return;
		}
		var byId = {};
		labels.forEach( function ( label ) {
			byId[ label.getAttribute( 'for' ).replace( /-hide$/, '' ) ] = label;
		} );
		var claimed = {};
		var anchor = labels[ 0 ];
		var host = anchor.parentNode;
		GROUPS.forEach( function ( group ) {
			var members = group.ids.map( function ( id ) {
				claimed[ id ] = true;
				return byId[ id ];
			} ).filter( Boolean );
			if ( 'More WordPress' !== group.title ) {
				group.nodes = members;
			}
		} );
		// Everything unclaimed — core widgets and any plugin's — falls into
		// the last group, in its original order.
		GROUPS[ GROUPS.length - 1 ].nodes = labels.filter( function ( label ) {
			return ! claimed[ label.getAttribute( 'for' ).replace( /-hide$/, '' ) ];
		} );
		// One grid wrapper, groups as columns — the panel spreads across the
		// available width instead of stacking six short rows.
		var wrap = document.createElement( 'div' );
		wrap.className = 'untangling-dw-optgroups';
		GROUPS.forEach( function ( group ) {
			if ( ! group.nodes || ! group.nodes.length ) {
				return;
			}
			var fieldset = document.createElement( 'fieldset' );
			fieldset.className = 'untangling-dw-optgroup';
			var legend = document.createElement( 'legend' );
			legend.textContent = group.title;
			fieldset.appendChild( legend );
			group.nodes.forEach( function ( node ) {
				fieldset.appendChild( node );
			} );
			wrap.appendChild( fieldset );
		} );
		host.insertBefore( wrap, anchor.parentNode === host ? anchor : null );
		// The moved labels leave the original run empty; the welcome-panel
		// checkbox is hidden in CSS (its widget is replaced, not curated).
	} )();
	</script>
	<?php
}, 999 );

// Postbox-fit styles for the widgets, plus the curated-dashboard chrome.
// Content cards stay flat (hairline, no shadow) — elevation is reserved for
// the checklist's current step, the one "act on this" in the design.
function untangling_dw_css() {
	return <<<'CSS'
/* Curated chrome: the welcome panel's job moved into the Site + Next steps
   widgets; its Screen Options checkbox goes with it. */
#welcome-panel { display: none !important; }
#adv-settings label[for="wp_welcome_panel-hide"] { display: none; }

/* Core's wide-viewport media rules (3 columns at 1500-1800px, 4 above that)
   restyle .postbox-container widths past the columns-N class — column 1
   shrinks while column 2 keeps floating right, leaving a dead gutter.
   Re-assert each chosen layout at every width above mobile. */
@media only screen and (min-width: 800px) {
	#wpbody #wpbody-content #dashboard-widgets.columns-2 .postbox-container { width: 49.5%; }
	#wpbody #wpbody-content #dashboard-widgets.columns-2 #postbox-container-2,
	#wpbody #wpbody-content #dashboard-widgets.columns-2 #postbox-container-3,
	#wpbody #wpbody-content #dashboard-widgets.columns-2 #postbox-container-4 { float: right; width: 50.5%; }
	#wpbody #wpbody-content #dashboard-widgets.columns-3 .postbox-container { float: left; width: 33.33%; }
	#wpbody #wpbody-content #dashboard-widgets.columns-1 .postbox-container { float: none; width: 100%; }
}

/* One column: a single centered reading column — every widget the same
   width, the reference measure being 704px (the Core editor's wide size). */
#wpbody #wpbody-content #dashboard-widgets.columns-1 { max-width: 704px; margin-left: auto; margin-right: auto; float: none; }

/* Empty extra sortables containers read as debris beside the designed grid —
   core's dashboard sheet keeps their dashed "drag boxes here" wells visible
   at rest. Show them only while a widget is actually being dragged. */
#dashboard-widgets .postbox-container .empty-container { outline: none; min-height: 0; visibility: hidden; }
body.is-dragging-metaboxes #dashboard-widgets .postbox-container .empty-container { visibility: visible; outline: 3px dashed #c3c4c7; min-height: 250px; }

/* Screen Options groups: columns across the panel width, checkboxes stacked
   under each heading — a map of the page, not a tall list of short rows. */
.untangling-dw-optgroups { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px 24px; margin: 12px 0 4px; max-width: 1400px; }
.untangling-dw-optgroup { margin: 0; }
.untangling-dw-optgroup legend { font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: #757575; margin: 0 0 6px; }
.untangling-dw-optgroup label { display: block; margin: 0 0 8px; }

/* Widgets inside postboxes: the postbox supplies chrome and title, so the
   inner components drop their own card shells. Core's .inside carries
   margin: 11px 0 + padding: 0 12px 12px (dashboard.css zeroes the bottom
   margin); the mount cancels all of it — top included, otherwise the 11px
   stacks on the body's own padding and every widget opens with a 23px gap
   against 12px on the other three sides. */
#dashboard-widgets .postbox .untangling-dw { margin: -11px -12px -12px; }
.untangling-dw { font-size: 13px; }
.untangling-dw .components-card { box-shadow: none; border-radius: 0; }
.untangling-dw > * { padding: 12px; }
.untangling-dw > .ms-linkfooter,
.untangling-dw > .ms-dw-body { padding: 12px; }

/* Generic widget body + footer. The footer mirrors .ms-linkfooter but sits on
   a hairline inside the postbox. */
.untangling-dw .ms-dw-body { display: flex; flex-direction: column; gap: 12px; }
/* Children space through the flex gap alone; trailing text margins would stack on it. */
.untangling-dw .ms-dw-body > * > :last-child { margin-bottom: 0; }
.untangling-dw .ms-linkfooter { border-top: 1px solid #f0f0f0; padding: 12px; font-size: 13px; }
.untangling-dw .ms-linkfooter .ms-logs-credit { position: static; transform: none; margin-left: auto; margin-right: 10px; }

/* Jetpack-backed widgets: mark + name in the postbox title, the single credit.
   Hidden in the Screen Options checkbox label, where core reuses the title. */
.postbox .untangling-dw-title { display: inline-flex; align-items: center; gap: 8px; }
.postbox .untangling-dw-title .ms-jp-mark { flex-shrink: 0; width: 16px; height: 16px; }
.metabox-prefs .untangling-dw-title { display: inline; }
.metabox-prefs .untangling-dw-title .ms-jp-mark { display: none; }

/* Site details: label/value rows split by hairlines (the settings-card model). */
.untangling-dw .ms-dw-grid { display: flex; flex-direction: column; }
.untangling-dw .ms-dw-grid-row { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin: 0 -12px; padding: 9px 12px; border-bottom: 1px solid #f0f0f0; }
.untangling-dw .ms-dw-grid-row:last-child { border-bottom: 0; }
.untangling-dw .ms-dw-grid-label { color: #757575; flex-shrink: 0; }
.untangling-dw .ms-dw-grid-value { text-align: right; color: #1e1e1e; }
.untangling-dw .ms-dw-grid-value a { text-decoration: none; }
.untangling-dw .ms-dw-grid-value a:hover { text-decoration: underline; }
.untangling-dw .ms-dw-grid-row.is-block { display: block; }
.untangling-dw .ms-dw-grid-row.is-block .ms-storage { margin: 6px 0 0; }
.untangling-dw .ms-storage-used { margin-top: 6px; }
.untangling-dw .ms-storage-used.has-cta { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; }
/* Both inline CTAs inherit their line's weight: regular in the value rows
   (same as the Plan link), 500 on the storage numbers line. */
.untangling-dw .ms-storage-cta, .untangling-dw .ms-dw-update { text-decoration: none; font-weight: inherit; white-space: nowrap; }
.untangling-dw .ms-storage-cta:hover, .untangling-dw .ms-dw-update:hover { text-decoration: underline; }

/* Stats: KPI pair, the selected one underlined (it drives the sparkline). */
.untangling-dw .ms-dw-kpis { display: flex; border-bottom: 1px solid #f0f0f0; }
.untangling-dw .ms-dw-kpi { appearance: none; background: none; border: 0; border-bottom: 2px solid transparent; margin-bottom: -1px; cursor: pointer; font: inherit; text-align: left; flex: 1; padding: 4px 12px 10px 0; }
.untangling-dw .ms-dw-kpi + .ms-dw-kpi { border-left: 1px solid #f0f0f0; padding-left: 16px; }
.untangling-dw .ms-dw-kpi.is-active { border-bottom-color: #1e1e1e; }
.untangling-dw .ms-dw-kpi-label { display: block; font-size: 12px; color: #757575; margin-bottom: 2px; }
.untangling-dw .ms-dw-kpi-value { font-size: 28px; font-weight: 600; line-height: 1.1; color: #1e1e1e; }
.untangling-dw .ms-dw-kpi-delta { display: inline-block; margin-left: 8px; padding: 1px 8px; border-radius: 999px; background: #f0f0f0; font-size: 11px; font-weight: 500; color: #1e1e1e; vertical-align: 4px; }
.untangling-dw .ms-dw-spark { display: block; width: 100%; height: 64px; margin-top: 10px; }

/* Activity feed rows: icon · title/summary · relative time. Empty state is a
   grey circle and one sentence, and the card keeps its height. */
.untangling-dw .ms-dw-feed { display: flex; flex-direction: column; }
.untangling-dw .ms-dw-feed-row { display: flex; align-items: flex-start; gap: 10px; padding: 9px 0; border-bottom: 1px solid #f0f0f0; }
.untangling-dw .ms-dw-feed-row:last-child { border-bottom: 0; }
/* Rows are links to the screen that owns the event: the whole row is the
   target, the hover tints the title the way the Hosting rows do. */
.untangling-dw a.ms-dw-feed-row { text-decoration: none; color: inherit; margin: 0 -12px; padding-left: 12px; padding-right: 12px; transition: background .15s ease; }
.untangling-dw a.ms-dw-feed-row:hover, .untangling-dw a.ms-dw-feed-row:focus-visible { background: none; }
.untangling-dw a.ms-dw-feed-row:hover .ms-dw-feed-title, .untangling-dw a.ms-dw-feed-row:focus-visible .ms-dw-feed-title { color: #3858e9; }
.untangling-dw a.ms-dw-feed-row:hover .ms-dw-feed-icon svg { fill: #3858e9; }
.untangling-dw a.ms-dw-feed-row:focus-visible { outline: 2px solid #3858e9; outline-offset: -2px; }
.untangling-dw .ms-dw-feed-icon { flex-shrink: 0; display: flex; margin-top: 1px; }
.untangling-dw .ms-dw-feed-icon svg { fill: #757575; }
.untangling-dw .ms-dw-feed-main { flex: 1; min-width: 0; }
.untangling-dw .ms-dw-feed-title { display: block; font-weight: 500; color: #1e1e1e; }
.untangling-dw .ms-dw-feed-summary { display: block; color: #757575; }
.untangling-dw .ms-dw-feed-time { flex-shrink: 0; color: #757575; font-size: 12px; margin-top: 2px; }
.untangling-dw .ms-dw-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; min-height: 120px; text-align: center; color: #757575; }
.untangling-dw .ms-dw-empty-icon { display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 50%; background: #f0f0f0; }
.untangling-dw .ms-dw-empty-icon svg { fill: #949494; }

/* Backups / Scan: the OvCard drops its own shell inside the postbox. Link
   hover follows the widget row pattern (see the Activity feed): faint brand
   tint, heading + icons brand, description stays quiet. */
.untangling-dw .ms-ovcard { box-shadow: none; padding: 0; }
/* Stacked state cards (Protection: Backups + Security) bleed to the postbox
   edges like the feed rows, and split on the same full-width hairline. */
.untangling-dw .ms-dw-body > .ms-ovcard { margin: 0 -12px; padding: 0 12px; border-radius: 0; }
.untangling-dw .ms-dw-body > .ms-ovcard + .ms-ovcard { border-top: 1px solid #f0f0f0; padding-top: 12px; }
.untangling-dw a.ms-ovcard:hover, .untangling-dw a.ms-ovcard:focus-visible { box-shadow: none; background: none; }
.untangling-dw a.ms-ovcard:hover .ms-ovcard-heading { color: #3858e9; }
.untangling-dw a.ms-ovcard:hover .ms-ovcard-desc { color: #757575; }
.untangling-dw a.ms-ovcard.is-warning:hover .ms-ovcard-desc { color: #b36100; }
.untangling-dw a.ms-ovcard.is-error:hover .ms-ovcard-desc { color: #cc1818; }

/* Needs attention. The postbox title carries an "Action needed" pill; the
   rows sit on the error surface edge to edge (the wrapper eats the body
   padding), split on an error-tinted hairline. The heading takes the error
   color; the description stays dark — red on a red tint reads worse, not
   louder. The eyebrow's link glyph becomes a pill naming the fix: the whole
   row is still the link, the pill says what clicking it does. */
#dashboard-widgets .postbox.is-attention .untangling-dw-title::after { content: 'Action needed'; display: inline-block; margin-left: 4px; padding: 1px 8px; border-radius: 999px; background: var(--wpds-color-background-interactive-error-strong, #cc1818); color: #fff; font-size: 11px; font-weight: 500; line-height: 16px; letter-spacing: 0; text-transform: none; }
.untangling-dw .ms-dw-issues { display: flex; flex-direction: column; margin: -12px; background: var(--wpds-color-background-surface-error-weak, #fcf0ef); }
.untangling-dw .ms-dw-issues > .ms-ovcard { margin: 0; padding: 12px; border-radius: 0; background: transparent; box-shadow: none; transition: background .15s ease; }
.untangling-dw .ms-dw-issues > .ms-ovcard + .ms-ovcard { border-top: 1px solid color-mix(in srgb, var(--wpds-color-stroke-surface-error-strong, #cc1818) 14%, transparent); }
.untangling-dw .ms-dw-issues > a.ms-ovcard:hover, .untangling-dw .ms-dw-issues > a.ms-ovcard:focus-visible { background: var(--wpds-color-background-surface-error, #f6e6e3); }
.untangling-dw .ms-dw-issues > a.ms-ovcard:focus-visible { outline: 2px solid var(--wpds-color-stroke-focus, #3858e9); outline-offset: -2px; }
.untangling-dw .ms-ovcard.is-error .ms-ovcard-label { color: var(--wpds-color-foreground-content-error-weak, #cc1818); }
.untangling-dw .ms-ovcard.is-error .ms-ovcard-heading,
.untangling-dw a.ms-ovcard.is-error:hover .ms-ovcard-heading { color: var(--wpds-color-foreground-content-error-weak, #cc1818); }
.untangling-dw .ms-ovcard.is-error .ms-ovcard-desc,
.untangling-dw a.ms-ovcard.is-error:hover .ms-ovcard-desc { color: #1e1e1e; }
.untangling-dw .ms-ovcard-action { margin-left: auto; padding: 2px 10px; border-radius: 999px; background: var(--wpds-color-background-interactive-error-strong, #cc1818); color: #fff; font-size: 11px; font-weight: 500; line-height: 16px; letter-spacing: 0; text-transform: none; white-space: nowrap; }
.untangling-dw a.ms-ovcard:hover .ms-ovcard-action { background: var(--wpds-color-background-interactive-error-strong-active, #a10f0f); }

/* Checklist: the open (current) step is the page's only elevation. */
.untangling-dw .ms-tl-tasks { background: #f6f7f7; }
.untangling-dw .ms-tl-card { box-shadow: none; border: 1px solid transparent; }
.untangling-dw .ms-tl-card.is-open { box-shadow: 0 4px 12px rgba(0, 0, 0, .08), 0 0 0 1px #e0e0e0; }
.untangling-dw .ms-tl-progress { margin: 0 0 10px; font-size: 12px; color: #757575; }
/* Provenance line, widget edition: a footer on the hairline like every other
   widget's, left-aligned with the cards above it. The gradient shimmer and
   the sparks carry over from the page unchanged — overflow stays visible so
   the sparks can float above the line. */
.untangling-dw .ms-dw-body > * > .ms-madefor.is-compact { position: relative; display: flex; align-items: center; gap: 6px; margin: 12px -12px -12px; padding: 12px; border-top: 1px solid #f0f0f0; text-align: left; font-size: 12px; line-height: 16px; color: #757575; white-space: nowrap; }
.untangling-dw .ms-madefor.is-compact > span:last-child { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; }
.untangling-dw .ms-madefor.is-compact .ms-ai { flex-shrink: 0; }
.untangling-dw .ms-hero { box-shadow: none; border: 1px solid #e0e0e0; padding: 16px; }
.untangling-dw .ms-hero-title { font-size: 18px; }

/* Upsell callouts stack in the narrow column. */
.untangling-dw .ms-upsell { flex-direction: column; align-items: flex-start; gap: 10px; padding: 8px 0; }

/* Hosting rows reuse the advanced-row recipe minus its card shell. The page's
   own row hover (shadow ring + icon recolor) half-leaked in here, leaving
   only the icon reacting — restate the full widget row pattern instead. */
.untangling-dw .ms-advanced-row { box-shadow: none; margin: 0 -12px; padding: 9px 12px; border-radius: 0; border-bottom: 1px solid #f0f0f0; }
.untangling-dw .ms-advanced-row:last-child { border-bottom: 0; }
.untangling-dw .ms-advanced-row:hover, .untangling-dw .ms-advanced-row:focus-visible { box-shadow: none; background: none; }
.untangling-dw .ms-advanced-row:hover .ms-grow-title, .untangling-dw .ms-advanced-row:focus-visible .ms-grow-title { color: #3858e9; }
.untangling-dw .ms-advanced-row:hover .ms-grow-chevron svg, .untangling-dw .ms-advanced-row:focus-visible .ms-grow-chevron svg { fill: #3858e9; }

/* Plan widget: name row, the top features, then the one promo slot. The id
   selector outranks dashboard.css's postbox h3 margin, which otherwise adds
   8px inside the name row and throws the header rhythm off. */
.untangling-dw .ms-plan-namerow { display: flex; align-items: center; gap: 8px; margin: 0 0 2px; }
#dashboard-widgets .postbox .untangling-dw .ms-plan-namerow .ms-card-title { font-size: 14px; margin: 0; line-height: 20px; }
.untangling-dw .ms-plan-namerow + .ms-card-desc { margin-top: 2px; }
.untangling-dw .ms-plan-features { margin: 0 -12px; gap: 8px 16px; border-top: 1px solid #f0f0f0; padding: 12px 12px 0; }
.untangling-dw .ms-plan-features li { align-items: flex-start; }
.untangling-dw .ms-plan-features .ms-plan-check { margin-top: 1px; }
.untangling-dw .ms-dw-offer { display: flex; align-items: center; gap: 10px; padding: 12px; border: 1px solid #e0e0e0; border-radius: 8px; }
.untangling-dw .ms-dw-offer svg { flex-shrink: 0; fill: #3858e9; }
.untangling-dw .ms-dw-offer-text { flex: 1; }
.untangling-dw .ms-dw-offer-title { display: block; font-weight: 500; color: #1e1e1e; }
.untangling-dw .ms-dw-offer-desc { display: block; color: #757575; font-size: 12px; }
CSS;
}

function untangling_ms_app_js() {
	return <<<'JS'
( function () {
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useRef = wp.element.useRef;
	var useLayoutEffect = wp.element.useLayoutEffect;
	var Fragment = wp.element.Fragment;
	var C = wp.components;
	var Button = C.Button;
	var Card = C.Card, CardBody = C.CardBody, CardDivider = C.CardDivider;
	var HStack = C.__experimentalHStack, VStack = C.__experimentalVStack;
	var Text = C.__experimentalText;
	var Badge = C.Badge || function ( p ) {
		return el( 'span', { className: 'untangling-fallback-badge' + ( p.intent && 'default' !== p.intent ? ' is-' + p.intent : '' ) }, p.children );
	};

	// The DS ToggleGroupControl mounts without its emotion styles in this
	// environment, so the segmented control is drawn by hand: quiet track,
	// white pill sliding under the active option. Radio semantics like the
	// real control — one tab stop, arrows move the selection.
	function Segmented( props ) {
		var ref = useRef( null );
		var pillState = useState( null );
		var pill = pillState[ 0 ], setPill = pillState[ 1 ];
		useLayoutEffect( function () {
			var node = ref.current && ref.current.querySelector( '[aria-checked="true"]' );
			if ( ! node ) { return; }
			setPill( { left: node.offsetLeft + 'px', width: node.offsetWidth + 'px' } );
			if ( ref.current.contains( document.activeElement ) && document.activeElement !== node ) {
				node.focus();
			}
		}, [ props.value ] );
		function step( delta ) {
			var i = props.options.findIndex( function ( o ) { return o.value === props.value; } );
			props.onChange( props.options[ ( i + delta + props.options.length ) % props.options.length ].value );
		}
		return el( 'div', { ref: ref, className: 'ms-segmented', role: 'radiogroup', 'aria-label': props.label },
			pill && el( 'span', { className: 'ms-segmented-pill', 'aria-hidden': true, style: pill } ),
			props.options.map( function ( option ) {
				var active = option.value === props.value;
				return el( 'button', {
					key: option.value,
					type: 'button',
					role: 'radio',
					'aria-checked': active,
					tabIndex: active ? 0 : -1,
					className: 'ms-segmented-option' + ( active ? ' is-active' : '' ),
					onClick: function () { props.onChange( option.value ); },
					onKeyDown: function ( event ) {
						if ( 'ArrowRight' === event.key || 'ArrowDown' === event.key ) { event.preventDefault(); step( 1 ); }
						else if ( 'ArrowLeft' === event.key || 'ArrowUp' === event.key ) { event.preventDefault(); step( -1 ); }
					},
				}, option.label );
			} )
		);
	}

	var data = window.untanglingMsData || {};
	var msd = data.msd || '#';
	var isFree = 'Free' === data.plan;
	// Top of the plan ladder: nothing left to upgrade to.
	var isTopTier = 'Commerce' === data.plan;
	// Store-flavored content follows WooCommerce presence, not the plan name.
	var isCommerce = !! data.hasWoo;
	var meta = data.planMeta || { features: [], storage: [ 0, 1, null ] };

	// Contextual pricing entry. The promise the visitor clicked travels with
	// them, so the plan page opens on that promise, shows only the plans that
	// can deliver it, and bolds the rows that answer it. Keys are the PHP
	// untangling_hosting_needs() map; an unknown one falls back to the generic
	// page rather than breaking the link.
	function plansUrlFor( need ) {
		return data.plansUrl + ( need ? '&need=' + encodeURIComponent( need ) : '' );
	}

	/* ---- icons: @wordpress/icons paths, inlined (wp.icons is not exposed) ---- */

	function icon( path, viewBox, size ) {
		return el( 'svg', {
			xmlns: 'http://www.w3.org/2000/svg',
			viewBox: viewBox || '0 0 24 24',
			width: size || 24,
			height: size || 24,
			'aria-hidden': true,
		}, el( 'path', { d: path, 'fill-rule': 'evenodd', 'clip-rule': 'evenodd' } ) );
	}

	var PATHS = {
		// The production launchpad checkmark (@wordpress/icons `check`, rendered at 25).
		check: 'M16.5 7.5 10 13.9l-2.5-2.4-1 1 3.5 3.6 7.5-7.6z',
		chevron: 'M10.6 6 9.4 7l4.6 5-4.6 5 1.2 1 5.4-6z',
		external: 'M19.5 4.5h-7V6h4.44l-5.97 5.97 1.06 1.06L18 7.06v4.44h1.5v-7ZM6 6.75c0-.41.34-.75.75-.75H11V4.5H6.75c-1.24 0-2.25 1.01-2.25 2.25v10.5c0 1.24 1.01 2.25 2.25 2.25h10.5c1.24 0 2.25-1.01 2.25-2.25V13H18v4.25c0 .41-.34.75-.75.75H6.75c-.41 0-.75-.34-.75-.75V6.75Z',
		cloud: 'M17.3 10.1c-.3-2.9-2.8-5.1-5.8-5.1-2.2 0-4.2 1.2-5.2 3.1-2.4.3-4.3 2.4-4.3 4.9 0 2.8 2.2 5 5 5h10c2.2 0 4-1.8 4-4 0-2-1.6-3.7-3.7-3.9ZM17 16.5H7c-1.9 0-3.5-1.6-3.5-3.5 0-1.8 1.4-3.3 3.2-3.5l.6-.1.3-.6c.7-1.4 2.2-2.3 3.9-2.3 2.3 0 4.2 1.7 4.4 4l.1.9h1c1.4 0 2.5 1.1 2.5 2.5s-1.1 2.6-2.5 2.6Z',
		shield: 'M12 3.2 5.5 5.9v4.4c0 4.1 2.6 7.9 6.5 9.5 3.9-1.6 6.5-5.4 6.5-9.5V5.9L12 3.2Zm5 7.1c0 3.3-2 6.4-5 7.9-3-1.5-5-4.6-5-7.9V6.9l5-2.1 5 2.1v3.4Z',
		layout: 'M18 5.5H6a.5.5 0 0 0-.5.5v3h13V6a.5.5 0 0 0-.5-.5Zm.5 5H10v8h8a.5.5 0 0 0 .5-.5v-7.5Zm-10 8v-8h-3v7.5c0 .28.22.5.5.5h2.5ZM6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z',
		stats: 'M11.25 5h1.5v15h-1.5V5ZM6 10h1.5v10H6V10Zm12 4h-1.5v6H18v-6Z',
		globe: 'M12 4c-4.4 0-8 3.6-8 8s3.6 8 8 8 8-3.6 8-8-3.6-8-8-8Zm6.5 8c0 .6 0 1.2-.2 1.8h-2.7c0-.6.2-1.1.2-1.8s0-1.2-.2-1.8h2.7c.2.6.2 1.1.2 1.8Zm-.9-3.2h-2.4c-.3-.9-.7-1.8-1.1-2.4-.1-.2-.2-.4-.3-.5 1.6.5 3 1.6 3.8 3ZM12.8 17c-.3.5-.6 1-.8 1.3-.2-.3-.5-.8-.8-1.3-.3-.5-.6-1.1-.8-1.7h3.3c-.2.6-.5 1.2-.8 1.7Zm-2.9-3.2c-.1-.6-.2-1.1-.2-1.8s0-1.2.2-1.8H14c.1.6.2 1.1.2 1.8s0 1.2-.2 1.8H9.9ZM11.2 7c.3-.5.6-1 .8-1.3.2.3.5.8.8 1.3.3.5.6 1.1.8 1.7h-3.3c.2-.6.5-1.2.8-1.7Zm-1-1.2c-.1.2-.2.3-.3.5-.4.7-.8 1.5-1.1 2.4H6.4c.8-1.4 2.2-2.5 3.8-3Zm-1.8 8H5.7c-.2-.6-.2-1.1-.2-1.8s0-1.2.2-1.8h2.7c0 .6-.2 1.1-.2 1.8s0 1.2.2 1.8Zm-2 1.4h2.4c.3.9.7 1.8 1.1 2.4.1.2.2.4.3.5-1.6-.5-3-1.6-3.8-3Zm7.4 3c.1-.2.2-.3.3-.5.4-.7.8-1.5 1.1-2.4h2.4c-.8 1.4-2.2 2.5-3.8 3Z',
		pencil: 'm19 7-3-3-8.5 8.5-1 4 4-1L19 7Zm-7 11.5H5V20h7v-1.5Z',
		email: 'M3 7c0-1.1.9-2 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Zm2-.5h14c.3 0 .5.2.5.5v1L12 13.5 4.5 7.9V7c0-.3.2-.5.5-.5Zm-.5 3.3V17c0 .3.2.5.5.5h14c.3 0 .5-.2.5-.5V9.8L12 15.4 4.5 9.8Z',
		storage: 'M19 6.5H5c-.8 0-1.5.7-1.5 1.5v8c0 .8.7 1.5 1.5 1.5h14c.8 0 1.5-.7 1.5-1.5V8c0-.8-.7-1.5-1.5-1.5ZM19 16H5V8h14v8ZM7 13h10v1.5H7V13Z',
		performance: 'M3.445 16.505a.75.75 0 0 0 1.06.05l5.005-4.55 4.024 3.521 4.716-4.715V14h1.5V8.25H14v1.5h3.19l-3.724 3.723L9.49 9.995l-5.995 5.45a.75.75 0 0 0-.05 1.06Z',
		comment: 'M18 4H6c-1.1 0-2 .9-2 2v12.9c0 .6.5 1.1 1.1 1.1.3 0 .5-.1.7-.3l2.8-2.8H18c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2Zm.5 10.9c0 .3-.2.5-.5.5H8l-2.5 2.5V6c0-.3.2-.5.5-.5h12c.3 0 .5.2.5.5v8.9Z',
		// Vertical key: ring on top, stem with two teeth. The old diagonal
		// circle-and-handle glyph read as a magnifier next to PATHS.search.
		key: 'M12 3.75a3.25 3.25 0 1 0 0 6.5 3.25 3.25 0 1 0 0-6.5Zm0 1.75a1.5 1.5 0 1 1 0 3 1.5 1.5 0 1 1 0-3ZM11.25 10.1v9.4h1.5v-9.4ZM12.6 14.75v1.5h2.4v-1.5ZM12.6 17.25v1.5h1.9v-1.5Z',
		code: 'm8.9 7.1-1-1L3 11l4.9 4.9 1-1L5.1 11l3.8-3.9Zm6.2 0 1-1L21 11l-4.9 4.9-1-1 3.8-3.9-3.8-3.9Z',
		search: 'M13 5c-3.3 0-6 2.7-6 6 0 1.4.5 2.7 1.3 3.7l-3.8 3.8 1 1 3.8-3.8c1 .8 2.3 1.3 3.7 1.3 3.3 0 6-2.7 6-6s-2.7-6-6-6Zm0 10.5c-2.5 0-4.5-2-4.5-4.5s2-4.5 4.5-4.5 4.5 2 4.5 4.5-2 4.5-4.5 4.5Z',
		help: 'M12 4c-4.4 0-8 3.6-8 8s3.6 8 8 8 8-3.6 8-8-3.6-8-8-8Zm.8 12.5h-1.5V15h1.5v1.5Zm2.1-5.6c-.1.5-.4 1.1-.8 1.5-.4.4-.9.7-1.4.8v.8h-1.5v-1.2c0-.6.5-1 .9-1s.7-.2 1-.5c.2-.3.4-.7.4-1 0-.4-.2-.7-.5-1-.3-.3-.6-.4-1-.4s-.8.2-1.1.4c-.3.3-.4.7-.4 1.1H9c0-.6.2-1.1.5-1.6s.7-.9 1.2-1.1c.5-.2 1.1-.3 1.6-.3s1.1.3 1.5.6c.4.3.8.8 1 1.3.2.5.2 1.1.1 1.6Z',
		megaphone: 'M12 3.5 5.5 8H3c-.6 0-1 .4-1 1v6c0 .6.4 1 1 1h2.5L12 20.5V3.5ZM14 7.6v8.7c1.5-.8 2.5-2.4 2.5-4.3 0-2-1-3.6-2.5-4.4Z',
		login: 'M12 4a4 4 0 1 0 0 8 4 4 0 0 0 0-8Zm0 6.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5ZM5 19.2c.6-2.9 3.5-4.7 7-4.7s6.4 1.8 7 4.7l-1.5.3c-.4-2-2.6-3.5-5.5-3.5s-5.1 1.5-5.5 3.5L5 19.2Z',
		seen: 'M12 6.5c-3 0-5.9 1.4-7.9 3.9L3.5 11l.6.6c2 2.5 4.9 3.9 7.9 3.9s5.9-1.4 7.9-3.9l.6-.6-.6-.6c-2-2.5-4.9-3.9-7.9-3.9Zm0 7.5c-2.3 0-4.6-1-6.3-2.9C7.4 9.2 9.7 8 12 8s4.6 1.2 6.3 3.1C16.6 13 14.3 14 12 14Zm0-5a1.9 1.9 0 1 0 0 3.8A1.9 1.9 0 0 0 12 9Z',
		plugin: 'M10.5 4v4h3V4H15v4h1.5a1 1 0 0 1 1 1v4l-3 4v2a1 1 0 0 1-1 1h-3a1 1 0 0 1-1-1v-2l-3-4V9a1 1 0 0 1 1-1H9V4h1.5Zm.5 12.5v2h2v-2l3-4v-3H8v3l3 4Z',
		post: 'M6 4h12c.6 0 1 .4 1 1v14c0 .6-.4 1-1 1H6c-.6 0-1-.4-1-1V5c0-.6.4-1 1-1Zm.5 1.5v13h11v-13h-11ZM8 8h8v1.5H8V8Zm0 3.5h8V13H8v-1.5Zm0 3.5h5v1.5H8V15Z',
		tip: 'M12 15.8c-3.7 0-6.8-3-6.8-6.8s3-6.8 6.8-6.8c3.7 0 6.8 3 6.8 6.8s-3.1 6.8-6.8 6.8zm0-12C9.1 3.8 6.8 6.1 6.8 9s2.4 5.2 5.2 5.2c2.9 0 5.2-2.4 5.2-5.2S14.9 3.8 12 3.8zM8 17.5h8V19H8zM10 20.5h4V22h-4z',
		dollar: 'M10.7 9.6c.3-.2.8-.4 1.3-.4s1 .2 1.3.4c.3.2.4.5.4.6 0 .4.3.8.8.8s.8-.3.8-.8c0-.8-.5-1.4-1.1-1.9-.4-.3-.9-.5-1.4-.6v-.3c0-.4-.3-.8-.8-.8s-.8.3-.8.8v.3c-.5 0-1 .3-1.4.6-.6.4-1.1 1.1-1.1 1.9s.5 1.4 1.1 1.9c.6.4 1.4.6 2.2.6h.2c.5 0 .9.2 1.1.4.3.2.4.5.4.6s0 .4-.4.6c-.3.2-.8.4-1.3.4s-1-.2-1.3-.4c-.3-.2-.4-.5-.4-.6 0-.4-.3-.8-.8-.8s-.8.3-.8.8c0 .8.5 1.4 1.1 1.9.4.3.9.5 1.4.6v.3c0 .4.3.8.8.8s.8-.3.8-.8v-.3c.5 0 1-.3 1.4-.6.6-.4 1.1-1.1 1.1-1.9s-.5-1.4-1.1-1.9c-.5-.4-1.2-.6-1.9-.6H12c-.6 0-1-.2-1.3-.4-.3-.2-.4-.5-.4-.6s0-.4.4-.6ZM12 4c-4.4 0-8 3.6-8 8s3.6 8 8 8 8-3.6 8-8-3.6-8-8-8Zm0 14.5c-3.6 0-6.5-2.9-6.5-6.5S8.4 5.5 12 5.5s6.5 2.9 6.5 6.5-2.9 6.5-6.5 6.5Z',
	};

	// The MSD upsell diamond, cropped to its glyph for tiny inline uses.
	var UPSELL_GLYPH = 'M18.9397 9.87999 15.4197 6.06999 15.3597 6.00999C15.2897 5.93999 15.1997 5.89999 15.0997 5.89999H8.87973C8.77973 5.89999 8.68973 5.93999 8.61973 6.00999L5.05973 9.87999C4.93973 10.01 4.93973 10.21 5.05973 10.34L11.5397 17.86C11.6497 17.99 11.8197 18.07 11.9997 18.07C12.1797 18.07 12.3397 17.99 12.4597 17.86L18.9397 10.34C19.0597 10.21 19.0497 10.01 18.9397 9.87999ZM15.4097 7.53999 17.3297 9.63999H15.1697L15.4097 7.53999ZM14.4297 6.83999 14.1097 9.63999H10.2897L9.64973 6.83999H14.4297ZM8.68973 7.42999 9.19973 9.63999H6.66973L8.68973 7.42999ZM6.61973 10.6H9.42973L10.8397 15.49 6.61973 10.6ZM12.0397 15.87 10.5297 10.6H13.8597L12.0397 15.87ZM14.9697 10.6H17.3797L13.3697 15.24 14.9697 10.6Z';

	function upsellDiamond() {
		return icon( UPSELL_GLYPH, '4.4 5.4 15.2 13.2', 14 );
	}

	// The Jetpack mark — same geometry and green as Calypso's
	// client/dashboard/sites/site-fields/jetpack-logo.tsx (16px beside a label
	// is that file's own usage). Two-tone, so it can't go through icon().
	// Only features that are genuinely Jetpack products carry it: Backup
	// (VaultPress, rewind__* activity), Scan (/alerts, cloud.jetpack.com/scan)
	// and the activity log (wpcom/v2 /sites/:id/activity). The Atomic hosting
	// features do not: hosting/metrics, hosting/error-logs + hosting/logs,
	// hosting/edge-cache, staging-site, hosting/sftp + ssh.
	function jetpackLogo( size ) {
		return el( 'svg', {
			xmlns: 'http://www.w3.org/2000/svg',
			className: 'ms-jp-logo',
			viewBox: '0 0 32 32',
			width: size || 16,
			height: size || 16,
			'aria-hidden': true,
		},
			el( 'path', { fill: '#069e08', d: 'M16,0C7.2,0,0,7.2,0,16s7.2,16,16,16s16-7.2,16-16S24.8,0,16,0z' } ),
			el( 'polygon', { fill: '#ffffff', points: '15,19 7,19 15,3 ' } ),
			el( 'polygon', { fill: '#ffffff', points: '17,29 17,13 25,13 ' } )
		);
	}

	// The credit line: mark plus the words, always the same three words wherever
	// it lands. tag lets it sit inside the card links (span — a <p> in an <a>
	// would be legal but the cards are spans throughout).
	function jetpackCredit( className, tag ) {
		return el( tag || 'p', { className: 'ms-jp-credit' + ( className ? ' ' + className : '' ) },
			jetpackLogo( 14 ),
			el( 'span', null, 'Powered by Jetpack' )
		);
	}

	/* ---- shared shell: same width and heading treatment on every page ----
	   The heading mirrors the tailored launchpad's: 32px sans title + grey
	   subline, actions right-aligned on the title row. Pages that carry their
	   own heading (the new-state launchpad) pass no title and get no header. */

	function Shell( props ) {
		return el( 'div', { className: 'ms-page' },
			props.title && el( 'header', { className: 'ms-header' },
				el( 'div', { className: 'ms-header-row' },
					el( 'div', { className: 'ms-header-text' },
						el( 'h1', { className: 'ms-title' }, props.title ),
						props.description && el( 'p', { className: 'ms-desc' }, props.description )
					),
					props.actions && el( 'div', { className: 'ms-header-actions' }, props.actions )
				)
			),
			el( 'div', { className: 'ms-content' }, props.children )
		);
	}

	function cardTitle( text, badge ) {
		return el( 'div', { className: 'ms-card-titlerow' },
			el( 'h2', { className: 'ms-card-title' }, text ),
			badge || null
		);
	}

	function cardDesc( text ) {
		return el( 'p', { className: 'ms-card-desc' }, text );
	}

	function extLink( href, label ) {
		return el( 'a', { className: 'ms-extlink', href: href },
			label,
			icon( PATHS.external, '0 0 24 24', 16 )
		);
	}

	// The MSD's HostingFeatureGatedWithCallout upsell, in the ms idiom — but a
	// row, not a centered empty state: icon chip, title + one-line description,
	// CTA on the right. The old layout stacked four blocks of centered text in
	// a full-width card, so three gated cards read as three broken pages. The
	// plan name lives in the CTA ("Upgrade to Business") instead of a separate
	// availability sentence repeated in every card.
	// props.inline renders the bordered in-card variant (Free activity log).
	function UpsellCallout( props ) {
		return el( 'div', { className: 'ms-upsell' + ( props.inline ? ' is-inline' : '' ) + ( props.stacked ? ' is-stacked' : '' ) },
			el( 'span', { className: 'ms-upsell-icon', 'aria-hidden': true }, icon( PATHS[ props.icon || 'plugin' ], '0 0 24 24', 24 ) ),
			el( 'span', { className: 'ms-upsell-main' },
				el( 'h3', { className: 'ms-upsell-title' }, props.title ),
				el( 'p', { className: 'ms-upsell-desc' }, props.desc )
			),
			// Secondary on purpose. These only ever render on Free, where a page can
			// carry three or four of them at once — as primaries they outshouted
			// the page's one real primary action and each other. The diamond and
			// the blue label still read as an upgrade.
			el( Button, { variant: 'secondary', icon: upsellDiamond(), iconSize: 14, href: plansUrlFor( props.need ), __next40pxDefaultSize: true }, props.cta || 'Upgrade plan' )
		);
	}

	/* ---- charts: hand-rolled SVG in the @automattic/charts visual language ---- */

	function smoothPath( points ) {
		// Catmull-Rom → cubic bézier, the monotone-ish curve the MSD charts use.
		if ( points.length < 2 ) {
			return '';
		}
		var d = 'M' + points[ 0 ][ 0 ] + ',' + points[ 0 ][ 1 ];
		for ( var i = 0; i < points.length - 1; i++ ) {
			var p0 = points[ Math.max( 0, i - 1 ) ];
			var p1 = points[ i ];
			var p2 = points[ i + 1 ];
			var p3 = points[ Math.min( points.length - 1, i + 2 ) ];
			var c1x = p1[ 0 ] + ( p2[ 0 ] - p0[ 0 ] ) / 6;
			var c1y = p1[ 1 ] + ( p2[ 1 ] - p0[ 1 ] ) / 6;
			var c2x = p2[ 0 ] - ( p3[ 0 ] - p1[ 0 ] ) / 6;
			var c2y = p2[ 1 ] - ( p3[ 1 ] - p1[ 1 ] ) / 6;
			d += 'C' + c1x.toFixed( 1 ) + ',' + c1y.toFixed( 1 ) + ' ' + c2x.toFixed( 1 ) + ',' + c2y.toFixed( 1 ) + ' ' + p2[ 0 ] + ',' + p2[ 1 ];
		}
		return d;
	}

	function toPoints( values, width, height, max, pad ) {
		var points = [];
		var innerW = width - pad * 2;
		var innerH = height - pad * 2;
		for ( var i = 0; i < values.length; i++ ) {
			points.push( [
				Math.round( pad + ( innerW * i ) / ( values.length - 1 ) ),
				Math.round( pad + innerH - ( innerH * values[ i ] ) / max ),
			] );
		}
		return points;
	}

	// Area chart with 1–2 series, gradient fills fading to transparent —
	// the monitoring-card look (withGradientFill, curveType monotone). Each
	// series is normalized to its own scale, like the MSD's dual-axis
	// performance chart — requests and milliseconds share a canvas, not a max.
	function AreaChart( props ) {
		var width = 640;
		var height = props.height || 200;
		var pad = 8;
		var gid = 'msgrad-' + ( props.id || 'chart' );
		return el( 'svg', {
			className: 'ms-chart',
			viewBox: '0 0 ' + width + ' ' + height,
			preserveAspectRatio: 'none',
			style: { width: '100%', height: height + 'px' },
			'aria-hidden': true,
		},
			el( 'defs', null, props.series.map( function ( s, si ) {
				return el( 'linearGradient', { key: si, id: gid + si, x1: 0, y1: 0, x2: 0, y2: 1 },
					el( 'stop', { offset: '0%', stopColor: s.color, stopOpacity: 0.2 } ),
					el( 'stop', { offset: '100%', stopColor: s.color, stopOpacity: 0 } )
				);
			} ) ),
			props.series.map( function ( s, si ) {
				// sameScale: series share units (visitors/views). Otherwise each
				// series gets its own scale (requests vs milliseconds).
				var max = 1;
				var pool = props.sameScale ? props.series : [ s ];
				pool.forEach( function ( ps ) {
					ps.values.forEach( function ( v ) { max = Math.max( max, v ); } );
				} );
				var points = toPoints( s.values, width, height, max * 1.15, pad );
				var line = smoothPath( points );
				var area = line + 'L' + points[ points.length - 1 ][ 0 ] + ',' + ( height - pad ) + 'L' + points[ 0 ][ 0 ] + ',' + ( height - pad ) + 'Z';
				return el( 'g', { key: si },
					el( 'path', { d: area, fill: 'url(#' + gid + si + ')' } ),
					el( 'path', { d: line, fill: 'none', stroke: s.color, strokeWidth: 2, strokeLinecap: 'round', 'vector-effect': 'non-scaling-stroke' } )
				);
			} )
		);
	}

	function chartLegend( series ) {
		return el( 'div', { className: 'ms-legend' }, series.map( function ( s, i ) {
			return el( 'span', { key: i, className: 'ms-legend-item' },
				el( 'span', { className: 'ms-legend-dot', style: { background: s.color } } ),
				s.label
			);
		} ) );
	}

	/* ---- launchpad: the AI Launchpad tailored list (jetpack-mu-wpcom), faithfully ----
	   Task set, subtitles, and CTA labels follow the ai-launchpad tailored-list:
	   goal-aware tasks, accordion cards in a grey group, site preview on the right. */

	var LP_TASKS = isCommerce ? [
		{ id: 'design', label: 'Choose a theme', subtitle: 'Pick a theme that puts your products front and center.', cta: 'Browse themes' },
		{ id: 'product', label: 'Add your first product', subtitle: 'Add photos, a price, and a description to start selling.', cta: 'Add product' },
		{ id: 'payments', label: 'Set up payments', subtitle: 'Choose how customers pay you.', cta: 'Set up payments' },
		{ id: 'shipping', label: 'Set your shipping rates', subtitle: 'Tell customers what delivery costs before checkout.', cta: 'Set rates' },
		{ id: 'domain', label: 'Pick a custom domain', subtitle: 'A short address customers can remember.', cta: 'Search domains' },
		{ id: 'launch', label: 'Launch your store', subtitle: 'When everything feels ready, open your store to customers.', cta: 'Launch store' },
	] : [
		{ id: 'design', label: 'Choose a theme', subtitle: 'Choose a theme that highlights your stunning photos.', cta: 'Browse themes' },
		{ id: 'first-post', label: 'Write your first post', subtitle: 'Say hello and share the story behind your photos.', cta: 'Write post' },
		{ id: 'social', label: 'Connect your social media accounts', subtitle: 'Connect your accounts to share new posts automatically.', cta: 'Connect socials' },
		{ id: 'traffic', label: 'Drive traffic to your site', subtitle: 'Turn on the basics that help new readers find your site.', cta: 'Get started' },
		{ id: 'gallery', label: 'Create your first gallery', subtitle: 'Show your best photos together on one page.', cta: 'Create gallery' },
		{ id: 'launch', label: 'Launch your blog', subtitle: 'When everything feels ready, make your blog public.', cta: 'Launch site' },
	];

	function lpInitialDone() {
		var map = {};
		( data.lpDone || [] ).forEach( function ( id ) {
			map[ id ] = true;
		} );
		return map;
	}

	function lpPersist( done, complete ) {
		var ids = LP_TASKS.filter( function ( t ) { return done[ t.id ]; } ).map( function ( t ) { return t.id; } );
		try {
			window.fetch(
				'admin.php?page=untangling-mysite&untangling_ms_lp_done=' + ids.join( ',' ) + ( complete ? '&untangling_ms_lp_complete=1' : '' ),
				{ credentials: 'same-origin' }
			);
		} catch ( e ) {}
	}

	// The ai-launchpad "done" glyph (@wordpress/icons `published`: circled check).
	var TL_DONE_PATH = 'M12 3.25a8.75 8.75 0 1 0 0 17.5 8.75 8.75 0 0 0 0-17.5Zm0 16a7.25 7.25 0 1 1 0-14.5 7.25 7.25 0 0 1 0 14.5Zm3.69-10.06-4.44 4.44-1.94-1.94-1.06 1.06 3 3 5.5-5.5-1.06-1.06Z';

	// The ai-launchpad "to-do" glyph (@wordpress/icons `border`: dashed circle).
	function tlPendingIcon() {
		return el( 'svg', {
			xmlns: 'http://www.w3.org/2000/svg',
			viewBox: '0 0 24 24',
			width: 24,
			height: 24,
			'aria-hidden': true,
		}, el( 'circle', {
			cx: 12, cy: 12, r: 8,
			fill: 'none',
			stroke: 'currentColor',
			strokeWidth: 1.5,
			strokeLinecap: 'round',
			strokeDasharray: '2.2 3.2',
		} ) );
	}

	// A single tailored-list task: completed cards are plain and inert with a
	// struck-through title; pending cards expand (accordion) to the subtitle
	// plus the primary CTA and Skip — both complete the task in this mimic.
	function TailoredTaskCard( props ) {
		var task = props.task;
		if ( props.done ) {
			return el( 'div', { className: 'ms-tl-card is-done' },
				el( 'div', { className: 'ms-tl-card-header' },
					el( 'span', { className: 'ms-tl-icon is-done', 'aria-hidden': true }, icon( TL_DONE_PATH ) ),
					el( 'span', { className: 'ms-tl-card-title is-done' }, task.label )
				)
			);
		}
		return el( 'div', { className: 'ms-tl-card' + ( props.open ? ' is-open' : '' ) },
			el( 'button', {
				className: 'ms-tl-card-header',
				'aria-expanded': props.open,
				'data-task': task.id,
				onClick: props.onToggle,
			},
				el( 'span', { className: 'ms-tl-icon', 'aria-hidden': true }, tlPendingIcon() ),
				el( 'span', { className: 'ms-tl-card-title' }, task.label ),
				el( 'span', { className: 'ms-tl-chevron', 'aria-hidden': true }, icon( PATHS.chevron, '0 0 24 24', 20 ) )
			),
			props.open && el( 'div', { className: 'ms-tl-card-content' },
				el( 'p', { className: 'ms-tl-subtitle' }, task.subtitle ),
				el( 'div', { className: 'ms-tl-actions' },
					el( Button, { variant: 'primary', onClick: props.onComplete }, task.cta ),
					el( Button, { variant: 'tertiary', onClick: props.onComplete }, 'Skip' )
				)
			)
		);
	}

	// The site-preview column: a scaled live iframe of the front end (the
	// prototype's ?iframe=true hides the admin bar), an "Edit site" hover
	// overlay, and the site name + domain below.
	function TailoredSitePreview() {
		return el( 'aside', { className: 'ms-tl-preview' },
			el( 'div', { className: 'ms-tl-preview-frame' },
				el( 'iframe', {
					className: 'ms-tl-preview-iframe',
					title: data.siteName || data.domain || 'Site preview',
					src: ( data.siteUrl || '/' ) + '?iframe=true',
					tabIndex: -1,
				} ),
				el( 'span', { className: 'ms-tl-preview-edit' },
					el( Button, { variant: 'primary', href: data.adminUrl + 'site-editor.php' }, 'Edit site' )
				)
			),
			el( 'p', { className: 'ms-tl-preview-title' }, data.siteName || data.domain ),
			el( 'a', { className: 'ms-tl-preview-link', href: data.siteUrl, target: '_blank', rel: 'noreferrer' },
				data.domain,
				el( 'span', { 'aria-hidden': true }, ' ↗' )
			)
		);
	}

	function firstIncomplete( done ) {
		var next = LP_TASKS.filter( function ( t ) { return ! done[ t.id ]; } )[ 0 ];
		return next ? next.id : null;
	}

	// The AI Launchpad tailored list: heading + progress line, the grey task
	// group with single-open accordion cards, and the site preview column.
	function LaunchpadCard( props ) {
		var doneState = useState( lpInitialDone );
		var done = doneState[ 0 ], setDone = doneState[ 1 ];
		var openState = useState( function () { return firstIncomplete( lpInitialDone() ); } );
		var openId = openState[ 0 ], setOpenId = openState[ 1 ];
		var count = LP_TASKS.filter( function ( t ) { return done[ t.id ]; } ).length;

		function complete( id ) {
			if ( done[ id ] ) {
				return;
			}
			var next = Object.assign( {}, done );
			next[ id ] = true;
			setDone( next );
			setOpenId( firstIncomplete( next ) );
			var doneCount = LP_TASKS.filter( function ( t ) { return next[ t.id ]; } ).length;
			var isComplete = doneCount === LP_TASKS.length;
			lpPersist( next, isComplete );
			if ( isComplete && props.onComplete ) {
				props.onComplete();
			}
		}

		return el( 'div', { className: 'ms-tl' + ( props.leaving ? ' is-leaving' : '' ) },
			el( 'header', { className: 'ms-tl-heading' },
				el( 'h1', { className: 'ms-tl-title' }, 'Get the most out of WordPress' ),
				el( 'p', { className: 'ms-tl-progress' }, count + ' of ' + LP_TASKS.length + ' completed' )
			),
			el( 'div', { className: 'ms-tl-columns' },
				el( 'div', { className: 'ms-tl-tasks', 'aria-label': 'Launchpad checklist' },
					LP_TASKS.map( function ( task ) {
						return el( TailoredTaskCard, {
							key: task.id,
							task: task,
							done: !! done[ task.id ],
							open: openId === task.id,
							onToggle: function () { setOpenId( openId === task.id ? null : task.id ); },
							onComplete: function () { complete( task.id ); },
						} );
					} )
				),
				el( TailoredSitePreview )
			),
			el( MadeForLine )
		);
	}

	/* ---- the completion moment: confetti (MSD recipe), collapse, reveal ---- */

	function confettiBurst() {
		var canvas = document.createElement( 'canvas' );
		canvas.className = 'ms-confetti-canvas';
		canvas.width = window.innerWidth;
		canvas.height = window.innerHeight;
		document.body.appendChild( canvas );
		var ctx = canvas.getContext( '2d' );
		var colors = [ '#31CC9F', '#618DF2', '#6AB3D0', '#B35EB1', '#F2D76B', '#FAA754', '#E34C84' ];
		var particles = [];
		// Five bursts with widening spreads — the MSD celebration recipe.
		var bursts = [ [ 30, 26 ], [ 24, 60 ], [ 42, 100 ], [ 12, 120 ], [ 12, 120 ] ];
		bursts.forEach( function ( burst ) {
			for ( var i = 0; i < burst[ 0 ]; i++ ) {
				var angle = ( -90 + ( Math.random() - 0.5 ) * burst[ 1 ] ) * Math.PI / 180;
				var speed = 7 + Math.random() * 9;
				particles.push( {
					x: canvas.width / 2,
					y: canvas.height * 0.4,
					vx: Math.cos( angle ) * speed,
					vy: Math.sin( angle ) * speed,
					size: 5 + Math.random() * 5,
					color: colors[ Math.floor( Math.random() * colors.length ) ],
					rotation: Math.random() * Math.PI,
					vr: ( Math.random() - 0.5 ) * 0.3,
					life: 1,
				} );
			}
		} );
		var start = null;
		function frame( ts ) {
			if ( ! start ) {
				start = ts;
			}
			var t = ( ts - start ) / 1600;
			ctx.clearRect( 0, 0, canvas.width, canvas.height );
			particles.forEach( function ( p ) {
				p.x += p.vx;
				p.y += p.vy;
				p.vy += 0.35;
				p.vx *= 0.99;
				p.rotation += p.vr;
				p.life = Math.max( 0, 1 - t );
				ctx.save();
				ctx.globalAlpha = p.life;
				ctx.translate( p.x, p.y );
				ctx.rotate( p.rotation );
				ctx.fillStyle = p.color;
				ctx.fillRect( -p.size / 2, -p.size / 2, p.size, p.size * 0.6 );
				ctx.restore();
			} );
			if ( t < 1 ) {
				window.requestAnimationFrame( frame );
			} else {
				canvas.remove();
			}
		}
		window.requestAnimationFrame( frame );
	}

	/* ---- Next steps: just created ---- */

	function NewState( props ) {
		return el( 'div', { className: 'ms-new' },
			el( LaunchpadCard, { onComplete: props.onComplete, leaving: props.leaving } )
		);
	}

	/* ---- Next steps: established ----
	   The page is one ordered pool of steps per site type. Every step is
	   grounded in something the site actually has (data.signals: latest
	   comment, latest post, top product) and says why now — the Dia-brief
	   grounding. Done/skipped ids persist per browser, the hero shows the
	   first unhandled step, and the queue below moves up as you go, so the
	   page always has a next step. Upsells sit in the pool like any other
	   step, each tied to the moment that earns it. */

	var sig = data.signals || {};

	// Do-steps: timely actions with a grounded why, all in the launchpad's
	// accordion — the first pending one opens by default.
	var DO_STEPS = ( isCommerce ? [
		sig.comment && {
			id: 'reply',
			title: 'Answer ' + sig.comment.author + '’s question',
			why: sig.comment.author + ' commented on “' + sig.comment.post + '” ' + sig.comment.time + '. Quick answers turn browsers into buyers.',
			cta: 'Reply', href: sig.comment.replyUrl,
		},
		{
			id: 'bestseller',
			title: 'Put your bestseller to work',
			why: '“' + ( sig.topProduct || 'Your top product' ) + '” got 214 views this month — more than anything else in the store. Feature it on your homepage, or give it a Blaze boost.',
			cta: 'Promote it', href: 'https://wordpress.com/advertising/',
		},
		{
			id: 'product-seo',
			title: 'Add descriptions Google loves',
			why: 'Several products are missing the descriptions search engines rely on. A few honest sentences each can lift your search traffic.',
			cta: 'Edit products', href: data.adminUrl + 'edit.php?post_type=product',
		},
		{
			id: 'coupon',
			title: 'Welcome first-time buyers',
			why: 'Most visitors leave without buying anything. A small first-order coupon turns lookers into customers.',
			cta: 'Create a coupon', href: data.adminUrl + 'edit.php?post_type=shop_coupon',
		},
		{
			id: 'reviews',
			title: 'Turn on product reviews',
			why: 'Shoppers trust other shoppers. Reviews on your product pages help new visitors buy with confidence.',
			cta: 'Enable reviews', href: data.adminUrl + 'admin.php?page=wc-settings&tab=products',
		},
		{
			id: 'social',
			title: 'Share new products automatically',
			why: 'Connect your social accounts once and every new product reaches them as soon as it goes live.',
			cta: 'Connect accounts', href: 'https://wordpress.com/support/publicize/',
		},
		{
			id: 'repeat',
			title: 'Thank your repeat customers',
			why: 'A short note or a small coupon after a second order turns customers into regulars.',
			cta: 'View orders', href: data.adminUrl + 'edit.php?post_type=shop_order',
		},
	] : [
		sig.comment && {
			id: 'reply',
			title: 'Reply to ' + sig.comment.author,
			why: sig.comment.author + ' commented on “' + sig.comment.post + '” ' + sig.comment.time + '. A reply doubles the chance they come back.',
			cta: 'Reply', href: sig.comment.replyUrl,
		},
		{
			id: 'next-post',
			title: 'Keep the rhythm — write the next post',
			why: '“' + ( sig.lastPost ? sig.lastPost.title : 'Your last post' ) + '” went out ' + ( sig.lastPost ? sig.lastPost.time : 'a while ago' ) + '. Sites that publish weekly hold on to twice as many readers.',
			cta: 'Write post', href: data.adminUrl + 'post-new.php',
		},
		{
			id: 'subscribers',
			title: 'Reach your first 100 subscribers',
			why: '12 people follow ' + data.siteName + ' so far. Add a subscribe block to your posts so readers can sign up where they already are.',
			cta: 'Add a subscribe block', href: data.adminUrl + 'post-new.php',
		},
		{
			id: 'blaze',
			title: 'Give your top post a push',
			why: '“' + ( sig.lastPost ? sig.lastPost.title : 'Your top post' ) + '” got 214 views — your best this month. Blaze can put it in front of new readers for a few dollars.',
			cta: 'Promote with Blaze', href: 'https://wordpress.com/advertising/',
		},
		{
			id: 'about',
			title: 'Introduce yourself on your About page',
			why: 'About pages are among the most-visited on any site. A short introduction tells new readers who’s behind ' + data.siteName + '.',
			cta: 'Edit About page', href: data.aboutEditUrl,
		},
		{
			id: 'social',
			title: 'Share new posts automatically',
			why: 'Connect your social accounts once and every new post reaches them the moment you publish.',
			cta: 'Connect accounts', href: 'https://wordpress.com/support/publicize/',
		},
		{
			id: 'tags',
			title: 'Help readers find related posts',
			why: 'Tags connect your posts to each other and to Reader topics, where new readers browse for sites like yours.',
			cta: 'Add tags', href: data.adminUrl + 'edit-tags.php?taxonomy=post_tag',
		},
	] ).filter( Boolean );

	// Grow: the standing picks — upsells and guides, each tied to the moment
	// that earns it. Titles stay short enough to share a line with their
	// badge; descriptions run ~10 words so the cards read as one set.
	var GROW_ITEMS = isCommerce ? [
		{ icon: 'email', title: 'Start a newsletter', desc: 'Email every new product and post straight to your customers.', href: 'https://wordpress.com/support/newsletter/', external: true, badge: 'Included' },
		{ icon: 'performance', title: 'Recover abandoned carts', desc: '3 carts were left behind this week — a reminder wins some back.', href: data.adminUrl + 'admin.php?page=wc-admin&path=%2Fmarketing' },
		{ icon: 'seen', title: 'Get products on Google', desc: 'Free listings put your catalog in front of active shoppers.', href: 'https://woocommerce.com/document/google-for-woocommerce/', external: true, badge: 'Guide' },
		{ icon: 'post', title: 'Show delivery times', desc: 'Unclear shipping is the top reason carts get abandoned.', href: 'https://woocommerce.com/document/setting-up-shipping-zones/', external: true, badge: 'Guide' },
	] : [
		isFree
			? { icon: 'globe', title: 'Claim your domain', desc: data.domainUpsell + ' is available — trust for the readers you’re gaining.', href: data.plansUrl, upsell: true }
			: { icon: 'performance', title: 'Set up payments', desc: 'Accept one-time or recurring payments from your readers.', href: 'https://wordpress.com/support/wordpress-editor/blocks/payments/accept-payments/', external: true },
		// Newsletters are included on every plan, Free included — no gate.
		{ icon: 'email', title: 'Start a newsletter', desc: 'Your 12 subscribers already want to hear from you, by email.', href: 'https://wordpress.com/support/newsletter/', external: true, badge: 'Included' },
		{ icon: 'seen', title: 'Get found on Google', desc: 'Search descriptions help new readers find you — quick to add.', href: 'https://wordpress.com/support/seo/', external: true, badge: 'Guide' },
		{ icon: 'post', title: 'Add alt text to photos', desc: 'Alt text helps screen readers and search engines see your galleries.', href: 'https://wordpress.com/support/accessibility/', external: true, badge: 'Guide' },
	];

	var UPNEXT_KEY = 'untangling_ms_upnext';
	function upnextHandled() {
		try { return JSON.parse( window.localStorage.getItem( UPNEXT_KEY ) || '[]' ); } catch ( e ) { return []; }
	}
	function upnextSave( ids ) {
		try { window.localStorage.setItem( UPNEXT_KEY, JSON.stringify( ids ) ); } catch ( e ) {}
	}

	function stepBadge( step ) {
		if ( step.upsell ) {
			return el( 'span', { className: 'ms-grow-badge' }, upsellDiamond(), 'Upgrade' );
		}
		if ( step.badge ) {
			return el( 'span', { className: 'ms-grow-badge is-included' }, step.badge );
		}
		return null;
	}

	// The launchpad's task card, reused for the living pool so the page keeps
	// one component before and after setup: same classes, same accordion, same
	// done treatment — plus badges and a real link CTA.
	function NextStepCard( props ) {
		var step = props.step;
		if ( props.done ) {
			return el( 'div', { className: 'ms-tl-card is-done' },
				el( 'div', { className: 'ms-tl-card-header' },
					el( 'span', { className: 'ms-tl-icon is-done', 'aria-hidden': true }, icon( TL_DONE_PATH ) ),
					el( 'span', { className: 'ms-tl-card-title is-done' }, step.title )
				)
			);
		}
		return el( 'div', { className: 'ms-tl-card' + ( props.open ? ' is-open' : '' ) },
			el( 'button', {
				className: 'ms-tl-card-header',
				'aria-expanded': props.open,
				'data-step': step.id,
				onClick: props.onToggle,
			},
				el( 'span', { className: 'ms-tl-icon', 'aria-hidden': true }, tlPendingIcon() ),
				el( 'span', { className: 'ms-tl-card-title' }, step.title, stepBadge( step ) ),
				el( 'span', { className: 'ms-tl-chevron', 'aria-hidden': true }, icon( PATHS.chevron, '0 0 24 24', 20 ) )
			),
			props.open && el( 'div', { className: 'ms-tl-card-content' },
					el( 'p', { className: 'ms-tl-subtitle' }, step.why ),
				el( 'div', { className: 'ms-tl-actions' },
					el( Button, {
						variant: 'primary', href: step.href,
						target: step.external ? '_blank' : undefined,
						onClick: function () { props.onHandle( step.id ); },
					}, step.cta ),
					el( Button, { variant: 'tertiary', onClick: function () { props.onHandle( step.id ); } }, 'Skip for now' )
				)
			)
		);
	}

	// Shown at the top of the group once every step is handled (with a
	// confetti burst on the final one) — the handled list stays below,
	// launchpad-style.
	function CaughtUpCard( props ) {
		return el( 'div', { className: 'ms-hero' },
			el( 'p', { className: 'ms-hero-eyebrow' }, 'All caught up' ),
			el( 'h3', { className: 'ms-hero-title' }, 'That’s everything for now' ),
			el( 'p', { className: 'ms-hero-why' }, 'You handled every step. New ones appear as ' + data.siteName + ' picks up ' + ( isCommerce ? 'orders, reviews, and visitors.' : 'readers, comments, and posts.' ) ),
			el( 'div', { className: 'ms-hero-actions' },
				el( Button, { variant: 'tertiary', onClick: props.onReset }, 'Start over' )
			)
		);
	}

	// Grow: the standing picks in the earlier grid recipe.
	function GrowGrid() {
		return el( 'div', { className: 'ms-grow-grid' },
			GROW_ITEMS.map( function ( item, i ) {
				return el( 'a', { key: i, className: 'ms-grow-item', href: item.href, target: item.external ? '_blank' : undefined },
					el( 'span', { className: 'ms-grow-icon' }, icon( PATHS[ item.icon ] ) ),
					el( 'span', { className: 'ms-grow-main' },
						el( 'span', { className: 'ms-grow-title' }, item.title, stepBadge( item ) ),
						el( 'span', { className: 'ms-grow-desc' }, item.desc )
					),
					el( 'span', { className: 'ms-grow-chevron' }, icon( PATHS.chevron, '0 0 24 24', 20 ) )
				);
			} )
		);
	}

	// The provenance line — tailored with AI, from this site's own signals.
	// Moving the mouse across it scatters little sparks (skipped under
	// prefers-reduced-motion); the AI phrase carries a gradient shimmer.
	var SPARK_COLORS = [ '#3858e9', '#b35eb1', '#e34c84', '#f2a33c', '#31cc9f' ];
	function MadeForLine( props ) {
		var sources = isCommerce ? 'stats, products, and orders' : 'stats, comments, and posts';
		function spawnSpark( e ) {
			if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
				return;
			}
			if ( Math.random() > 0.45 ) {
				return;
			}
			var host = e.currentTarget;
			var rect = host.getBoundingClientRect();
			var spark = document.createElement( 'span' );
			spark.className = 'ms-spark';
			spark.textContent = '✦';
			spark.style.color = SPARK_COLORS[ Math.floor( Math.random() * SPARK_COLORS.length ) ];
			spark.style.left = ( e.clientX - rect.left + ( Math.random() * 16 - 8 ) ) + 'px';
			spark.style.top = ( e.clientY - rect.top + ( Math.random() * 10 - 5 ) ) + 'px';
			spark.style.fontSize = ( 8 + Math.random() * 7 ) + 'px';
			host.appendChild( spark );
			window.setTimeout( function () { spark.remove(); }, 900 );
		}
		// Widget edition: one left-aligned line on the footer hairline. The
		// page's centered sign-off floated under the task cards like a stray
		// caption inside a 400px postbox. The shimmer and the sparks stay —
		// they are the fun part.
		if ( props && props.compact ) {
			return el( 'p', { className: 'ms-madefor is-compact', onMouseMove: spawnSpark },
				el( 'span', { className: 'ms-ai' }, '\u2726 Tailored with AI' ),
				el( 'span', null, 'from your ' + sources + '.' )
			);
		}
		return el( 'p', { className: 'ms-madefor', onMouseMove: spawnSpark },
			el( 'span', { className: 'ms-ai' }, '✦ Tailored with AI' ),
			' for ' + data.siteName + ' — from your ' + sources + '. New steps appear as your site changes.'
		);
	}

	function AttentionCard() {
		if ( ! data.attention || ! data.attention.length ) {
			return null;
		}
		return el( Card, { className: 'ms-rise ms-attention' },
			el( CardBody, null,
				cardTitle( 'Needs attention' ),
				data.attention.map( function ( item, i ) {
					return el( 'div', { key: i, className: 'ms-attention-item' },
						el( 'p', { className: 'ms-attention-title' }, item.title ),
						el( 'p', { className: 'ms-attention-text' }, item.text ),
						el( Button, { variant: 'secondary', size: 'compact', href: item.href }, item.action )
					);
				} )
			)
		);
	}

	function EstablishedState() {
		var handledState = useState( upnextHandled );
		var handled = handledState[ 0 ], setHandled = handledState[ 1 ];
		function pendingOf( ids ) {
			return DO_STEPS.filter( function ( s ) { return ids.indexOf( s.id ) === -1; } );
		}
		var openState = useState( function () {
			var first = pendingOf( upnextHandled() )[ 0 ];
			return first ? first.id : '';
		} );
		var openId = openState[ 0 ], setOpenId = openState[ 1 ];
		function onHandle( id ) {
			var next = handled.concat( [ id ] );
			setHandled( next );
			upnextSave( next );
			var first = pendingOf( next )[ 0 ];
			setOpenId( first ? first.id : '' );
			if ( ! pendingOf( next ).length ) {
				confettiBurst();
			}
		}
		function onReset() {
			setHandled( [] );
			upnextSave( [] );
			setOpenId( DO_STEPS.length ? DO_STEPS[ 0 ].id : '' );
		}
		var pending = pendingOf( handled );
		var hasAttention = data.attention && data.attention.length;
		return el( 'div', { className: 'ms-next' },
			hasAttention ? el( AttentionCard ) : null,
			el( 'div', { className: 'ms-tl-columns' },
				el( 'div', { className: 'ms-next-main' },
					el( 'div', { className: 'ms-tl-tasks ms-next-flow', 'aria-label': 'Next steps' },
						! pending.length && el( CaughtUpCard, { onReset: onReset } ),
						DO_STEPS.map( function ( step ) {
							var isDone = handled.indexOf( step.id ) !== -1;
							return el( NextStepCard, {
								key: step.id,
								step: step,
								done: isDone,
								open: ! isDone && openId === step.id,
								onToggle: function () { setOpenId( openId === step.id ? '' : step.id ); },
								onHandle: onHandle,
							} );
						} )
					),
					el( 'section', null,
						el( 'h2', { className: 'ms-next-h2' }, isCommerce ? 'Grow your store' : 'Grow your site' ),
						el( GrowGrid )
					)
				),
				el( TailoredSitePreview )
			),
			el( MadeForLine )
		);
	}

	function NextStepsPage() {
		// launchpad → celebrating (confetti, checklist collapses) → established.
		var phaseState = useState( 'established' === data.state ? 'established' : 'launchpad' );
		var phase = phaseState[ 0 ], setPhase = phaseState[ 1 ];

		function onComplete() {
			confettiBurst();
			setPhase( 'celebrating' );
			window.setTimeout( function () {
				setPhase( 'leaving' );
			}, 1100 );
			window.setTimeout( function () {
				setPhase( 'established' );
			}, 1650 );
		}

		var isNew = 'established' !== phase;
		// The new state carries its own heading (the launchpad's), so the Shell
		// header only appears once the site is established.
		// Continues the launchpad's "Get the most out of WordPress": setup is
		// closed, the ongoing mode begins. The subline explains the living list.
		return el( Shell, isNew ? {} : {
			title: 'Keep the momentum',
			description: 'New steps appear as ' + data.siteName + ' picks up ' + ( isCommerce ? 'orders, reviews, and visitors.' : 'readers, comments, and posts.' ),
		},
			isNew
				? el( NewState, { onComplete: onComplete, leaving: 'leaving' === phase } )
				: el( EstablishedState )
		);
	}

	/* ---- Hosting ---- */

	// props.muted marks a gated card, and follows MSD's overview-card upsell
	// intent (components/overview-card + hosting-feature-gated-with-overview-
	// card): the label row itself becomes the CTA — upsell diamond + blue
	// "Upgrade to unlock" + chevron — while the heading keeps the promise and
	// the description the benefit. The label slot swaps meaning between
	// states there too: BACKUPS / "Backed up 2 hours ago" when it's live.
	//
	// props.intent is MSD's OverviewCard `intent` prop, same name and same
	// values ('success' | 'warning' | 'error'): it colors the eyebrow icon and
	// nothing else, except that warning and error also tint the description.
	// No intent means "nothing to report" — the icon stays in the admin theme
	// color, which is what MSD shows for its own "No backups yet" card.
	function OvCard( props ) {
		var tag = props.href ? 'a' : 'div';
		return el( tag, { className: 'ms-ovcard' + ( props.muted ? ' is-upsell' : '' ) + ( props.intent ? ' is-' + props.intent : '' ), href: props.href },
			el( 'span', { className: 'ms-ovcard-label' },
				props.muted ? upsellDiamond() : icon( PATHS[ props.icon ], '0 0 24 24', 20 ),
				el( 'span', null, props.muted ? 'Upgrade to unlock' : props.label ),
				props.action
					? el( 'span', { className: 'ms-ovcard-action' }, props.action )
					: props.href && el( 'span', { className: 'ms-ovcard-linkicon' },
						props.muted ? icon( PATHS.chevron, '0 0 24 24', 20 ) : icon( PATHS.external, '0 0 24 24', 16 )
					)
			),
			el( 'span', { className: 'ms-ovcard-heading' }, props.heading ),
			el( 'span', { className: 'ms-ovcard-desc' }, props.desc )
		);
	}

	function perfSeries( range ) {
		// Deterministic mock series shaped per range (points, wobble, daily wave).
		var shapes = {
			'6h': [ 24, 3 ], '24h': [ 24, 8 ], '3d': [ 36, 12 ], '7d': [ 42, 18 ],
		};
		var shape = shapes[ range ] || shapes[ '24h' ];
		var requests = [];
		var response = [];
		for ( var i = 0; i < shape[ 0 ]; i++ ) {
			var wave = Math.sin( i / shape[ 1 ] * Math.PI * 2 );
			var jitter = Math.sin( i * 2.7 ) * 0.5 + Math.sin( i * 1.3 ) * 0.3;
			requests.push( Math.round( 46 + wave * 18 + jitter * 10 ) );
			response.push( Math.round( 180 + wave * -30 + jitter * 26 ) );
		}
		return [
			{ label: 'Requests per minute', color: '#3858e9', values: requests },
			{ label: 'Average response time (ms)', color: '#5ba300', values: response },
		];
	}

	var RANGE_LABELS = { '6h': 'Last 6 hours', '24h': 'Last 24 hours', '3d': 'Last 3 days', '7d': 'Last 7 days' };

	function PerformanceCard() {
		var rangeState = useState( '24h' );
		var range = rangeState[ 0 ], setRange = rangeState[ 1 ];
		var series = perfSeries( range );
		// Real product: the whole Performance page is plan-gated
		// (HostingFeatures.PERFORMANCE) — Free sites get the upsell callout.
		if ( isFree ) {
			return el( Card, { className: 'ms-span2' },
				el( CardBody, null,
					cardTitle( 'Performance' ),
					el( UpsellCallout, {
						icon: 'performance',
						title: 'Track speed and traffic',
						desc: 'Requests per minute and average response time, over any range.',
						cta: 'Upgrade to Business',
						need: 'performance',
					} )
				)
			);
		}
		return el( Card, { className: 'ms-span2' },
			el( CardBody, null,
				el( 'div', { className: 'ms-card-titlerow ms-card-linkrow' },
					el( 'div', null,
						el( 'h2', { className: 'ms-card-title' }, 'Performance' ),
						cardDesc( 'How the server is holding up — ' + RANGE_LABELS[ range ].toLowerCase() + '.' )
					),
					el( Segmented, {
						label: 'Time range',
						value: range,
						onChange: setRange,
						options: [
							{ value: '6h', label: '6H' },
							{ value: '24h', label: '24H' },
							{ value: '3d', label: '3D' },
							{ value: '7d', label: '7D' },
						],
					} )
				),
				el( AreaChart, { id: 'perf-' + range, series: series, height: 220 } ),
				chartLegend( series )
			)
		);
	}

	function severityBadge( severity ) {
		var intents = { error: 'error', warning: 'warning', notice: 'default' };
		return el( Badge, { intent: intents[ severity ] || 'default' }, severity.charAt( 0 ).toUpperCase() + severity.slice( 1 ) );
	}

	// The MSD's three log views (Activity / PHP errors / Web server), column
	// order and cell recipes matching the Hosting Dashboard tables.
	function actorCell( name ) {
		return el( 'span', { className: 'ms-activity-actor' },
			el( 'span', { className: 'ms-activity-avatar', 'aria-hidden': true }, name.charAt( 0 ) ),
			name
		);
	}

	// The Download logs mimic lived here (CSV of the visible tab). Dropped with
	// the button — the card foot is the credit line now.
	function LogsCard() {
		var kindState = useState( 'activity' );
		var kind = kindState[ 0 ], setKind = kindState[ 1 ];
		// PHP and web-server logs stay a paid feature, as they are in the real
		// product. What the logs-first designs change is where the ask sits: the
		// card opens on Activity, which Free owns outright, so the page still
		// leads with something that works and the two gated tabs only make their
		// case once someone asks for them.
		var gated = isFree;
		// Where "see more" goes, per tab: the MSD's own log pages. Simple sites
		// reach all three routes too — there the gate is by plan inside the
		// page, not by site type — but on Free the two paid tabs show the
		// upsell instead of a table, so only Activity offers the link.
		var LOG_LINKS = {
			activity: { label: 'See all activity', route: '/logs/activity' },
			php: { label: 'See all PHP errors', route: '/logs/php' },
			server: { label: 'See all requests', route: '/logs/server' },
		};
		var logsLink = ( 'activity' === kind || ! gated )
			? { label: LOG_LINKS[ kind ].label, href: msd + '/sites/' + data.siteSlug + LOG_LINKS[ kind ].route }
			: null;
		return el( Card, { className: 'ms-span2' },
			// Header band over a rule, then the body — the shell the Plan
			// upgrade card uses. Header and footer carry the same 15/20 text,
			// the header at 500 and the footer at regular, so the card is
			// bracketed by two matching rows.
			el( CardBody, { className: 'ms-cardhead' },
				el( 'h2', { className: 'ms-card-title' }, 'Logs' ),
				el( Segmented, {
					label: 'Log type',
					value: kind,
					onChange: setKind,
					options: [
						{ value: 'activity', label: 'Activity' },
						{ value: 'php', label: 'PHP errors' },
						{ value: 'server', label: 'Web server' },
					],
				} )
			),
			el( CardDivider ),
			el( CardBody, null,
				// Real product: PHP + web-server logs are Business/Commerce only;
				// the activity log stays on Free, capped at the 20 newest events
				// with an inline upsell below it (our seed is 6 rows, under the cap).
				// One gate, one purchase — so the icon, title, CTA and shape stay
				// put across both tabs and only the promise follows the tab you
				// clicked. Listing both logs on both tabs told the visitor the
				// tabs were interchangeable, which is the opposite of the pitch.
				//
				// One tab, one ask, centered on the empty table's own
				// axis — the same stacked treatment the Email card uses — and
				// each tab names what its own log answers instead of sharing a
				// title. A visitor who clicked "Web server" asked a different
				// question than one who clicked "PHP errors", and the reason to
				// pay is the answer to the question they actually asked.
				( 'php' === kind || 'server' === kind ) && gated && el( UpsellCallout, {
					stacked: true,
					icon: 'php' === kind ? 'code' : 'globe',
					title: 'php' === kind
						? 'Find what broke a page'
						: 'See every request to your site',
					desc: 'php' === kind
						? 'Fatal errors, warnings, and notices, each with the file and line behind it.'
						: 'Status codes and response times for every page a visitor loads.',
					cta: 'Upgrade to Business',
					need: 'php' === kind ? 'logs-php' : 'logs-server',
				} ),
				'activity' === kind && el( 'table', { className: 'ms-logs' },
					el( 'thead', null, el( 'tr', null,
						el( 'th', { className: 'ms-logs-time' }, 'Date & time (UTC)' ),
						el( 'th', null, 'Event' ),
						el( 'th', { className: 'ms-activity-user' }, 'User' )
					) ),
					el( 'tbody', null, ( data.activity || [] ).map( function ( row, i ) {
						return el( 'tr', { key: i },
							el( 'td', { className: 'ms-logs-time' }, row.time ),
							el( 'td', null,
								el( 'span', { className: 'ms-activity-event' },
									el( 'span', { className: 'ms-activity-icon', 'aria-hidden': true }, icon( PATHS[ row.icon ] || PATHS.post, '0 0 24 24', 20 ) ),
									el( 'span', { className: 'ms-activity-main' },
										el( 'span', { className: 'ms-activity-title' }, row.title ),
										el( 'span', { className: 'ms-activity-summary' }, row.summary )
									)
								)
							),
							el( 'td', { className: 'ms-activity-user' }, actorCell( row.actor ) )
						);
					} ) )
				),
				// Activity is the tab the card opens on, and on Free it is
				// capped at the 20 newest events — so the retention nudge is
				// fine print about the table right above it, not one of the
				// feature CTAs the logs-first designs set out to remove. It
				// shows on every hosting design, Simple/Free only.
				'activity' === kind && gated && el( UpsellCallout, {
					inline: true,
					icon: 'stats',
					title: 'Get 30 days of activity history',
					desc: 'Free plans keep the last 20 events. Paid plans add 30 days of history, filters, and date ranges.',
					need: 'activity',
				} ),
				'php' === kind && ! gated && el( 'table', { className: 'ms-logs' },
					el( 'thead', null, el( 'tr', null,
						el( 'th', { className: 'ms-logs-sev' }, 'Severity' ),
						el( 'th', { className: 'ms-logs-time' }, 'Date & time (UTC)' ),
						el( 'th', null, 'Message' )
					) ),
					el( 'tbody', null, ( data.logs || [] ).map( function ( row, i ) {
						return el( 'tr', { key: i },
							el( 'td', { className: 'ms-logs-sev' }, severityBadge( row.severity ) ),
							el( 'td', { className: 'ms-logs-time' }, row.time ),
							el( 'td', { className: 'ms-logs-msg' }, row.message )
						);
					} ) )
				),
				'server' === kind && ! gated && el( 'table', { className: 'ms-logs' },
					el( 'thead', null, el( 'tr', null,
						el( 'th', { className: 'ms-logs-status' }, 'Status' ),
						el( 'th', { className: 'ms-logs-time' }, 'Date & time (UTC)' ),
						el( 'th', { className: 'ms-logs-type' }, 'Request type' ),
						el( 'th', null, 'Request URL' )
					) ),
					el( 'tbody', null, ( data.serverLogs || [] ).map( function ( row, i ) {
						return el( 'tr', { key: i },
							el( 'td', { className: 'ms-logs-status' }, row.status ),
							el( 'td', { className: 'ms-logs-time' }, row.time ),
							el( 'td', { className: 'ms-logs-type' }, el( 'span', { className: 'ms-logs-method is-' + row.method.toLowerCase() }, row.method ) ),
							el( 'td', { className: 'ms-logs-url' }, row.url )
						);
					} ) )
				),
			),
			// Six rows is a card, not the log. The CTA hands over the full one
			// in the Hosting Dashboard, pointed at the tab being read rather
			// than a generic index — the external icon because it leaves
			// wp-admin.
			logsLink && el( CardDivider ),
			logsLink && el( 'a', { className: 'ms-linkfooter', href: logsLink.href },
				el( 'span', null, logsLink.label ),
				// Whose log this is, centered on the card between the link and
				// the external icon. Activity only — the PHP and web-server logs
				// come off the Atomic host, not Jetpack.
				'activity' === kind && jetpackCredit( 'ms-logs-credit', 'span' ),
				icon( PATHS.external, '0 0 24 24', 20 )
			)
		);
	}

	// Caching — the layer every developer reaches for when a change won’t
	// show up. Statuses mirror the MSD caching settings; Clear is a mimic.
	var CACHE_ROWS = [
		{ title: 'Global edge cache', desc: 'Pages cached and served from the closest data center.', state: 'Active', intent: 'success' },
		{ title: 'Object cache', desc: 'Repeated database queries answered from memory.', state: 'Active', intent: 'success' },
		{ title: 'Defensive mode', desc: 'Extra caching during traffic spikes. Turn it on for a set time.', state: 'Off', intent: 'default' },
	];

	// Free has no caching controls, but "managed for you" on its own left a
	// full-width card holding one sentence. Same rows as the paid card, minus
	// Defensive mode (Business-only) and minus the buttons — so the card shows
	// what is running instead of only saying that something is.
	var FREE_CACHE_ROWS = [
		{ title: 'Global edge cache', desc: 'Pages served from the data center closest to each visitor.', state: 'Automatic', intent: 'success' },
		{ title: 'Cache clearing', desc: 'Cleared for you whenever you publish or update.', state: 'Automatic', intent: 'success' },
	];

	function CacheCard() {
		var clearedState = useState( false );
		var cleared = clearedState[ 0 ], setCleared = clearedState[ 1 ];
		// Real product: caching is managed on Simple/Free — no controls, just
		// the settings-caching page's managed-for-you line.
		if ( isFree ) {
			return el( Card, { className: 'ms-span2' },
				el( CardBody, null,
					cardTitle( 'Caching' ),
					cardDesc( 'Managed for you — nothing to configure.' ),
					el( 'div', { className: 'ms-cache-list' },
						FREE_CACHE_ROWS.map( function ( row, i ) {
							return el( 'div', { key: i, className: 'ms-cache-row' },
								el( 'span', { className: 'ms-grow-main' },
									el( 'span', { className: 'ms-grow-title' }, row.title ),
									el( 'span', { className: 'ms-grow-desc' }, row.desc )
								),
								el( Badge, { intent: row.intent }, row.state )
							);
						} )
					)
				)
			);
		}
		return el( Card, { className: 'ms-span2' },
			el( CardBody, null,
				cardTitle( 'Caching' ),
				cardDesc( 'Edge and object caches keep the site fast. Clear them when a change won’t show up.' ),
				el( 'div', { className: 'ms-cache-list' },
					CACHE_ROWS.map( function ( row, i ) {
						return el( 'div', { key: i, className: 'ms-cache-row' },
							el( 'span', { className: 'ms-grow-main' },
								el( 'span', { className: 'ms-grow-title' }, row.title ),
								el( 'span', { className: 'ms-grow-desc' }, row.desc )
							),
							el( Badge, { intent: row.intent }, row.state )
						);
					} )
				),
				el( 'div', { className: 'ms-logs-foot' },
					el( Button, { variant: 'secondary', size: 'compact', onClick: function () { setCleared( true ); } }, 'Clear all caches' ),
					el( 'span', { className: 'ms-logs-note' + ( cleared ? ' is-cleared' : '' ) }, cleared ? 'Cleared. The next visit rebuilds them.' : 'Cleared automatically on every update' )
				)
			)
		);
	}

	// Each row deep-links its real Hosting Dashboard page
	// (/sites/:site/settings/…); Server settings covers two pages, so it
	// lands on the settings hub.
	var ADVANCED_ROWS = [
		{ icon: 'key', title: 'SFTP/SSH credentials', desc: 'Direct file access for developers.', route: '/settings/sftp-ssh' },
		{ icon: 'storage', title: 'Database', desc: 'Browse tables with phpMyAdmin.', route: '/settings/database' },
		{ icon: 'code', title: 'PHP version', desc: 'Running PHP 8.3 — managed for you.', route: '/settings/php' },
		{ icon: 'globe', title: 'Server settings', desc: 'Primary data center and static file 404s.', route: '/settings' },
	];

	function AdvancedCard() {
		// Real product: SFTP/SSH, phpMyAdmin, PHP version and server settings
		// all sit behind the Business plan — Free gets the generic hosting
		// upsell from the settings pages.
		if ( isFree ) {
			return el( Card, { className: 'ms-span2' },
				el( CardBody, null,
					cardTitle( 'Advanced' ),
					el( UpsellCallout, {
						icon: 'code',
						title: 'Get server-level access',
						desc: 'SFTP/SSH, database, PHP version, and server settings.',
						cta: 'Upgrade to Business',
						need: 'advanced',
					} )
				)
			);
		}
		return el( Card, { className: 'ms-span2' },
			el( CardBody, null,
				cardTitle( 'Advanced' ),
				cardDesc( 'Genuinely hosting-level things. These open the Hosting Dashboard.' ),
				el( 'div', { className: 'ms-advanced-grid' },
					ADVANCED_ROWS.map( function ( row, i ) {
						return el( 'a', { key: i, className: 'ms-advanced-row', href: msd + '/sites/' + data.siteSlug + ( row.route || '' ) },
							el( 'span', { className: 'ms-grow-icon' }, icon( PATHS[ row.icon ] ) ),
							el( 'span', { className: 'ms-grow-main' },
								el( 'span', { className: 'ms-grow-title' }, row.title ),
								el( 'span', { className: 'ms-grow-desc' }, row.desc )
							),
							el( 'span', { className: 'ms-grow-chevron' }, icon( PATHS.external, '0 0 24 24', 18 ) )
						);
					} )
				)
			)
		);
	}

	/* ---- Hosting, logs-first ---- */

	// What Business adds, framed as the moment you would reach for it. The
	// title is the situation, not the feature — a creator recognizes the
	// situation first. Production's own list
	// (client/dashboard/sites/hosting-feature-list) opens with Git deployments
	// and server monitoring, close to the reverse of what someone running a
	// blog or a shop asks for first.
	var HOSTING_OUTCOMES = [
		{ icon: 'cloud', title: 'An update breaks a page', desc: 'Restore your site to any moment, in one click.' },
		{ icon: 'shield', title: 'Malware reaches your files', desc: 'Daily scans find it, and most fixes run on their own.' },
		{ icon: 'layout', title: 'You want to try a redesign', desc: 'Work on a private copy first, then publish it.' },
		{ icon: 'plugin', title: 'The tool you need is a plugin', desc: 'Install any plugin or theme, or upload your own.' },
	];

	// One card, one CTA. Replacing five upgrade buttons with one is the whole
	// point, so nothing in here may grow a second one. Two other shapes of this
	// card (have / get columns, and safety/speed/control groups) were tried and
	// dropped, as was the original five-CTA page.
	function MissingCard() {
		var heading = 'If something goes wrong';

		function row( item, i ) {
			return el( 'li', { className: 'ms-missing-row', key: i },
				el( 'span', { className: 'ms-missing-icon', 'aria-hidden': true }, icon( PATHS[ item.icon ], '0 0 24 24', 20 ) ),
				el( 'span', { className: 'ms-missing-text' },
					el( 'span', { className: 'ms-missing-row-title' }, item.title ),
					el( 'span', { className: 'ms-missing-row-desc' }, item.desc )
				)
			);
		}

		var body = el( 'ul', { className: 'ms-missing-list is-wide' }, HOSTING_OUTCOMES.map( row ) );

		return el( Card, { className: 'ms-span2 ms-missing' },
			el( CardBody, null,
				el( 'div', { className: 'ms-card-titlerow' },
					el( 'div', null,
						el( 'h2', { className: 'ms-card-title' }, heading ),
						cardDesc( 'Business adds the parts of hosting you cannot reach from here yet.' )
					)
				),
				body,
				el( 'div', { className: 'ms-missing-foot' },
					el( Button, { variant: 'primary', icon: upsellDiamond(), iconSize: 14, href: plansUrlFor( 'hosting' ), __next40pxDefaultSize: true }, 'See plans' )
				)
			)
		);
	}

	// Logs first, then one card. On a paid site nothing is gated, so the page
	// keeps its tool cards and only the order changes — the logs are what a
	// person opens this page to read.
	function HostingPageCreator() {
		var bad = 'attention' === data.hosting;
		var stateCards = [
			bad
				? { icon: 'cloud', label: 'Backups', heading: 'Backup failed', desc: 'Last successful backup was 3 days ago.', intent: 'error', href: msd + '/sites/' + data.siteSlug + '/backups' }
				: { icon: 'cloud', label: 'Backups', heading: 'Backed up 2 hours ago', desc: 'Automatic, every day. Restore any moment with one click.', intent: 'success', href: msd + '/sites/' + data.siteSlug + '/backups' },
			bad
				? { icon: 'shield', label: 'Security', heading: '2 risks found', desc: 'Auto fixes are available.', intent: 'error', href: msd + '/sites/' + data.siteSlug + '/scan' }
				: { icon: 'shield', label: 'Security', heading: 'No threats found', desc: 'Last scan finished this morning. Scans run daily.', intent: 'success', href: msd + '/sites/' + data.siteSlug + '/scan' },
			{ icon: 'layout', label: 'Staging', heading: 'No staging site yet', desc: 'Test changes on a private copy before they go live.', href: msd + '/sites/' + data.siteSlug },
		];
		return el( Shell, {
			title: 'Hosting',
			description: 'What your site and server have been doing. The Hosting Dashboard keeps the multi-site view.',
			// Secondary on Free: the summary card owns the only primary on the
			// page. On a paid site there is no summary card to outshout.
			actions: el( Button, { variant: isFree ? 'secondary' : 'primary', href: msd + '/overview', __next40pxDefaultSize: true, icon: icon( PATHS.external, '0 0 24 24', 20 ), iconPosition: 'right' }, 'Hosting Dashboard' ),
		},
			el( 'div', { className: 'ms-grid' },
				! isFree && el( 'div', { className: 'ms-span2 ms-ovcard-row' },
					stateCards.map( function ( card, i ) {
						return el( OvCard, Object.assign( { key: i }, card ) );
					} )
				),
				el( LogsCard ),
				! isFree && el( PerformanceCard ),
				! isFree && el( CacheCard ),
				! isFree && el( AdvancedCard ),
				isFree && el( MissingCard )
			)
		);
	}

	function HostingPage() {
		return HostingPageCreator();
	}

	/* ---- Plan & products ---- */

	// The plan-upgrade card design from the WP.com page (V5), now on every plan
	// that has a tier above it: each column pairs row for row — storage, design,
	// reach, support — so the upgrade reads as a line-by-line answer to what you
	// have. Both columns come from untangling_plan_compare() in PHP, which also
	// picks the recommended target (Free → Premium, Business → Commerce).
	// Same CSS tooltips as that page (span.untangling-feature-tip + data-tip);
	// copy follows wordpress.com/pricing.
	function compareColMs( name, chip, price, features, muted, cta, recommended ) {
		return el( 'div', { className: 'ms-plancompare-col' + ( recommended ? ' is-recommended' : '' ) },
			el( 'div', { className: 'ms-plancompare-name' },
				el( 'span', null, name ),
				chip
			),
			el( 'div', { className: 'ms-plancompare-price' }, price ),
			el( 'ul', { className: 'ms-plancompare-list' + ( muted ? ' is-muted' : '' ) },
				features.map( function ( feature, i ) {
					return el( 'li', { key: i },
						el( 'span', { className: 'untangling-feature-tip', tabIndex: 0, 'data-tip': feature.tip }, feature.label )
					);
				} )
			),
			el( 'div', { className: 'ms-plancompare-cta' }, cta )
		);
	}

	function PlanUpgradeCardMs() {
		var compare = data.planCompare || {};
		var mine = compare.current, theirs = compare.next;
		if ( ! mine || ! theirs ) {
			return null;
		}
		return el( Card, { className: 'ms-span2' },
			el( CardBody, { className: 'ms-cardhead' },
				el( 'h2', { className: 'ms-card-title' }, 'Plan upgrade' )
			),
			el( CardDivider ),
			el( CardBody, null,
				el( 'div', { className: 'ms-plancompare' },
					// No badge on the plan you are on: the CTA itself carries
					// "Current plan", and the button is inert (disabled, no href)
					// rather than a link to a page with nothing to do. Managing the
					// plan lives in the Hosting Dashboard, one click down in the footer.
					compareColMs( mine.name, null, mine.price, mine.features, true,
						el( Button, { variant: 'secondary', disabled: true, __next40pxDefaultSize: true }, 'Current plan' ), false ),
					// Badge text comes from untangling_plan_compare(), per target
					// plan, so Commerce can name the job it is for.
					compareColMs( theirs.name, el( 'span', { className: 'ms-chip-dark' }, theirs.pill || 'Recommended' ), theirs.price, theirs.features, false,
						// iconSize beats Button's Icon wrapper, which otherwise
						// blows the cropped glyph up to 24×24 beside 13px text.
						el( Button, { variant: 'primary', icon: upsellDiamond(), iconSize: 14, href: theirs.checkoutUrl || data.plansUrl, __next40pxDefaultSize: true }, 'Upgrade to ' + theirs.name ), true )
				)
			),
			el( CardDivider ),
			el( 'a', { className: 'ms-linkfooter', href: data.plansUrl },
				el( 'span', null, 'See plans' ),
				icon( PATHS.chevron, '0 0 24 24', 20 )
			)
		);
	}

	// Every plan with a tier above it gets the compare card — the upgrade story
	// is the same job on Free and on Business, only the pair changes. Commerce
	// has nothing above it, so it keeps the feature-checklist card, where
	// Manage takes the primary and there is nothing to compare against.
	function PlanCardMs() {
		var compare = data.planCompare || {};
		if ( compare.current && compare.next ) {
			return el( PlanUpgradeCardMs );
		}
		return el( Card, { className: 'ms-span2' },
			el( CardBody, null,
				el( 'div', { className: 'ms-card-titlerow ms-card-linkrow' },
					el( 'div', null,
						el( 'div', { className: 'ms-plan-namerow' },
							el( 'h2', { className: 'ms-card-title' }, 'WordPress.com ' + data.plan ),
							el( Badge, { intent: 'success' }, 'Active' )
						),
						cardDesc( meta.renew )
					),
					// Two jobs, two buttons: Upgrade opens the same pricing step
					// Compare plans used to, and Manage hands the plan itself over
					// to the Hosting Dashboard. Nothing to upgrade to on the top
					// tier, so Manage takes the primary there.
					el( HStack, { justify: 'flex-end', spacing: 2, expanded: false },
						! isTopTier && el( Button, { variant: 'primary', href: data.plansUrl, __next40pxDefaultSize: true }, 'Upgrade' ),
						el( Button, {
							variant: isTopTier ? 'primary' : 'secondary',
							href: msd + '/sites/' + data.siteSlug + '/plans',
							__next40pxDefaultSize: true,
							icon: icon( PATHS.external, '0 0 24 24', 20 ),
							iconPosition: 'right',
						}, 'Manage' )
					)
				),
				el( CardDivider ),
				el( 'ul', { className: 'ms-plan-features' },
					( meta.features || [] ).map( function ( feature, i ) {
						return el( 'li', { key: i },
							el( 'span', { className: 'ms-plan-check' }, icon( PATHS.check, '0 0 24 24', 18 ) ),
							el( 'span', { className: 'untangling-feature-tip', tabIndex: 0, 'data-tip': feature.tip }, feature.label )
						);
					} )
				)
			)
		);
	}

	// Two lines, two jobs: the numbers stay neutral, and storage[2] carries the
	// state (caution when the bar is in warning territory, otherwise a plain
	// note — a purchased add-on reuses the same slot).
	// props.ctaHref / props.ctaLabel (optional): a link on the right of the
	// numbers line, shown only while the bar is in warning territory — the
	// place that has no room for a full add-on picker (the dashboard widget).
	function StorageMeter( props ) {
		props = props || {};
		var storage = meta.storage || [ 0, 1, null ];
		var pct = Math.min( 100, Math.round( ( storage[ 0 ] / storage[ 1 ] ) * 100 ) );
		var isTight = pct > 80;
		var showCta = isTight && props.ctaHref;
		return el( 'div', { className: 'ms-storage' },
			el( 'div', { className: 'ms-storage-bar' },
				el( 'div', { className: 'ms-storage-fill' + ( isTight ? ' is-warning' : '' ), style: { width: pct + '%' } } )
			),
			el( 'p', { className: 'ms-storage-used' + ( showCta ? ' has-cta' : '' ) },
				el( 'span', null, storage[ 0 ] + ' GB of ' + storage[ 1 ] + ' GB used' ),
				showCta && el( 'a', { className: 'ms-storage-cta', href: props.ctaHref }, props.ctaLabel || 'Add storage' )
			),
			// storage[2] carries a caution when space is tight and a plain note
			// once an add-on is bought. The amber bar already says "almost
			// full", so only the neutral note is worth a line of its own.
			storage[ 2 ] && ! isTight && el( 'p', { className: 'ms-storage-note' }, storage[ 2 ] )
		);
	}

	function DomainsCardMs() {
		var sub = data.siteSlug.replace( /\..*$/, '' ) + '.wordpress.com';
		// Sites configured with a wordpress.com primary (Free identities) show
		// the upsell domain as the custom one once the drawer plan is paid.
		var custom = /\.wordpress\.com$/.test( data.domain ) ? data.domainUpsell : data.domain;
		var rows = isFree ? [
			{ domain: sub, badge: [ 'default', 'Primary' ], note: 'Free forever' },
			{ domain: data.domainUpsell, badge: [ 'success', 'Available' ], note: 'Free for a year on paid plans', href: data.domainClaimUrl || data.plansUrl },
		] : [
			{ domain: custom, badge: [ 'success', 'Primary' ], note: 'Renews with your plan' },
			{ domain: sub, badge: [ 'default', 'Redirects' ], note: 'Free forever' },
		];
		return el( Card, null,
			el( CardBody, null,
				el( 'div', { className: 'ms-card-titlerow ms-card-linkrow' },
					el( 'h2', { className: 'ms-card-title' }, 'Domains' ),
					extLink( msd + '/domains', 'Manage' )
				),
				el( 'ul', { className: 'ms-domains' }, rows.map( function ( row, i ) {
					return el( 'li', { key: i, className: 'ms-domain-row' },
						el( 'span', { className: 'ms-domain-name' }, row.domain ),
						el( Badge, { intent: row.badge[ 0 ] }, row.badge[ 1 ] ),
						el( 'span', { className: 'ms-domain-note' },
							row.href ? el( 'a', { href: row.href }, row.note ) : row.note
						)
					);
				} ) )
			)
		);
	}

	// Most sites don't have a mailbox with us, so the card leads with the offer
	// rather than a filled-in "Active" row — that state was the rare one, and it
	// left nothing to do on the card. No mailbox means nothing to manage either,
	// so the Manage link gives way to the CTA. Free has no custom domain to hang
	// an address on and points at a domain first; paid plans can buy one now.
	function EmailCardMs() {
		var addr = 'hello@' + ( /\.wordpress\.com$/.test( data.domain ) ? data.domainUpsell : data.domain );
		return el( Card, null,
			el( CardBody, null,
				cardTitle( 'Email' ),
				el( 'div', { className: 'ms-upsell is-stacked' },
					el( 'span', { className: 'ms-upsell-icon', 'aria-hidden': true }, icon( PATHS.email, '0 0 24 24', 24 ) ),
					el( 'span', { className: 'ms-upsell-main' },
						el( 'h3', { className: 'ms-upsell-title' }, isFree ? 'Get an address at your own domain' : 'Send from ' + addr ),
						// One line, one idea: the domain is what buys the address. The
						// old line spent 70 characters on "A custom address like … starts
						// with a domain", which wrapped to a stub last line once the card
						// centered, and said the cause after the effect.
						el( 'p', { className: 'ms-upsell-desc' }, isFree
							? 'A domain gets you ' + addr + '.'
							: 'One mailbox, on the domain you already own.'
						)
					),
					el( Button, {
						variant: 'secondary',
						__next40pxDefaultSize: true,
						// Free has no custom domain yet, so the address starts with
						// buying one: straight into the production domain search
						// (/setup/domain), not the plan pricing page.
						href: isFree ? ( data.domainSearchUrl || data.plansUrl ) : msd + '/emails',
					}, isFree ? 'Add a domain' : 'Add email' )
				)
			)
		);
	}

	// Storage tiers mirror the Add-ons page dropdown; Buy lands on the
	// checkout mimic with addon=storage so the purchase stretches the meter.
	function StorageCardMs() {
		var SelectControl = C.SelectControl;
		var gbState = useState( '50' );
		var gb = gbState[ 0 ], setGb = gbState[ 1 ];
		var pricing = data.storagePricing || {};
		// The option text ("+50 GB — $50.00/month") already says what the field
		// does, so the label is screen-reader only and the billing cadence sits
		// once under the row instead of repeating in all seven options.
		var options = Object.keys( pricing ).map( function ( tier ) {
			return { value: tier, label: '+' + tier + ' GB — $' + Number( pricing[ tier ] ).toFixed( 2 ) + '/month' };
		} );
		return el( Card, null,
			el( CardBody, null,
				cardTitle( 'Storage' ),
				el( StorageMeter ),
				el( 'div', { className: 'ms-storage-buy' },
					el( 'div', { className: 'ms-storage-buy-row' },
						SelectControl && el( SelectControl, {
							label: 'Storage add-on size',
							hideLabelFromVision: true,
							value: gb,
							options: options,
							onChange: setGb,
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						} ),
						el( Button, { variant: 'secondary', __next40pxDefaultSize: true, href: data.storageAddonUrl + '&gb=' + gb }, 'Add storage' )
					),
					el( 'p', { className: 'ms-storage-fineprint' }, 'Billed yearly. Applies to this site only.' )
				)
			)
		);
	}

	// A billing line rendered as one link. Same shape as the domains-card row
	// (name · badge · right-aligned note) so the two cards still read alike;
	// the anchor wraps the row rather than the name so the whole strip is the
	// hit target. At rest the row reads as plain text; hovering turns it blue
	// and reveals the external icon that marks the jump out to the MSD.
	function billingRow( row ) {
		return el( 'li', { key: row.href, className: 'ms-domain-row is-linked' },
			el( 'a', { className: 'ms-domain-link', href: row.href },
				el( 'span', { className: 'ms-domain-name' }, row.name ),
				row.badge || null,
				el( 'span', { className: 'ms-domain-note' },
					row.note,
					icon( PATHS.external, '0 0 24 24', 14 )
				)
			)
		);
	}

	// Billing pointer — history stays with the account (per the page intro);
	// this card carries what's site-relevant and links out for the rest.
	function BillingCardMs() {
		return el( Card, null,
			el( CardBody, null,
				el( 'div', { className: 'ms-card-titlerow ms-card-linkrow' },
					el( 'h2', { className: 'ms-card-title' }, 'Billing' ),
					extLink( msd + '/me/billing', 'Manage' )
				),
				isFree
					? cardDesc( 'No payments needed — the Free plan is free forever. Receipts for any upgrade will show up here.' )
					: el( 'ul', { className: 'ms-domains' },
						// Every row is a link to the MSD screen that actually owns it:
						// the plan and the card live on the account (billing), the
						// domain on its own page. The whole row is the target — the
						// renewal date on the right is as likely a click as the name.
						billingRow( {
							name: 'WordPress.com ' + data.plan,
							badge: el( Badge, { intent: 'success' }, 'Active' ),
							note: meta.renew,
							href: msd + '/me/billing/purchases',
						} ),
						// The custom domain is its own billing line in the real
						// product (free the first year, renews with the plan after).
						billingRow( {
							name: /\.wordpress\.com$/.test( data.domain ) ? data.domainUpsell : data.domain,
							badge: el( Badge, { intent: 'default' }, 'Domain' ),
							note: meta.renew,
							href: msd + '/domains/' + ( /\.wordpress\.com$/.test( data.domain ) ? data.domainUpsell : data.domain ),
						} ),
						billingRow( {
							name: 'Visa ending in 4242',
							note: 'Payment method',
							href: msd + '/me/billing/payment-methods',
						} )
					)
			)
		);
	}

	function PlanPage() {
		return el( Shell, {
			title: 'Plan & products',
			description: 'What you’re on, what’s attached to this site, and what could help. Billing history stays with your account.',
		},
			el( 'div', { className: 'ms-grid' },
				el( PlanCardMs ),
				// Storage sits beside Domains: both are "what this site has
				// right now". Email and Billing make the second row.
				el( DomainsCardMs ),
				el( StorageCardMs ),
				el( EmailCardMs ),
				el( BillingCardMs )
			)
		);
	}

	/* ---- Help & Learn ---- */

	// Mirrors the hosting page's Help & Learn tab — the same real videos,
	// courses, guides, and support links — inside the My Site shell. The
	// media/topic/support card styles ride in with untangling_app_css()
	// (scoped under .untangling-app, which the wrapper carries).
	var LEARN_VIDEOS = [
		[ 'wm0jPV234zc', 'The New WordPress Editor Built for Writing', '3:56' ],
		[ '9rCal5dxMiM', 'Turn a rough draft into a finished post with AI', '3:31' ],
		[ 'KDLqEd_QAD8', 'Connect Claude to your WordPress.com site', '3:30' ],
		[ 'OFRAPMPQMeA', 'Create and edit navigation menus', '6:12' ],
		[ 'wHz4uiaBbOE', 'Connect an existing domain to WordPress.com', '10:12' ],
		[ 'i0zK9qNIj_g', 'WordPress Forms Made Simple', '8:14' ],
	];

	var LEARN_COURSES = [
		[ 'https://wordpress.com/support/courses/create-your-blog/', 'https://en.support.wordpress.com/wp-content/uploads/2025/06/blogging-course.jpg', 'Create your blog', '1 hour', 'Set up your blog, publish posts, and grow your audience.' ],
		[ 'https://wordpress.com/support/courses/grow-your-audience/', 'https://en.support.wordpress.com/wp-content/uploads/2025/11/final.png', 'Grow your audience', '1 hour', 'Attract visitors, build subscribers, and engage your community.' ],
		[ 'https://wordpress.com/support/courses/monetize-your-website/', 'https://en.support.wordpress.com/wp-content/uploads/2026/02/thumbnail-monetize-your-site.png', 'Monetize your site', '1 hour', 'Charge for content, sell products, and grow recurring revenue.' ],
	];

	var LEARN_GUIDES = [
		[ 'tip', 'Get started', [
			[ 'Build a website with AI', 'https://wordpress.com/support/ai-website-builder/' ],
			[ 'Build your website in five steps', 'https://wordpress.com/support/five-step-website-setup/' ],
			[ 'Set up your blog in five steps', 'https://wordpress.com/support/five-step-blog-setup/' ],
		] ],
		[ 'globe', 'Domains', [
			[ 'Register a new domain name', 'https://wordpress.com/support/domains/register-domain/' ],
			[ 'Connecting vs transferring a domain', 'https://wordpress.com/support/domain-connection-vs-domain-transfer/' ],
			[ 'Set up your custom domain', 'https://wordpress.com/support/domains/' ],
		] ],
		[ 'pencil', 'Create content', [
			[ 'About the WordPress editors', 'https://wordpress.com/support/editors/' ],
			[ 'Start from pre-built content', 'https://wordpress.com/support/starter-content/' ],
			[ 'Improve a post with Jetpack AI', 'https://wordpress.com/support/wordpress-editor/jetpack-ai/' ],
		] ],
		[ 'stats', 'Grow your audience', [
			[ 'Increase your site’s traffic', 'https://wordpress.com/support/getting-more-views-and-traffic/' ],
			[ 'Optimize for search engines (SEO)', 'https://wordpress.com/support/seo/' ],
			[ 'Advertise your content with Blaze', 'https://wordpress.com/support/promote-a-post/' ],
		] ],
		[ 'dollar', 'Monetize', [
			[ 'Earn money from ads', 'https://wordpress.com/support/wordads-and-earn/' ],
			[ 'Accept payments', 'https://wordpress.com/support/wordpress-editor/blocks/payments/accept-payments/' ],
			[ 'Sell digital products', 'https://wordpress.com/support/sell-digital-products/' ],
		] ],
		[ 'cloud', 'Move your site', [
			[ 'Migrate a site to WordPress.com', 'https://wordpress.com/support/import/import-an-entire-wordpress-site/' ],
			[ 'Import a website', 'https://wordpress.com/support/import/' ],
			[ 'Request a free migration', 'https://wordpress.com/support/request-a-free-migration/' ],
		] ],
	];

	var LEARN_SUPPORT = [
		// Support ladder is plan-aware: community-first on Free, 24/7 priority
		// support on Business.
		[ 'comment', 'Contact us', isFree
			? 'Get answers from our AI assistant. 24/7 expert human support comes with paid plans.'
			: 'Get answers from our AI assistant, backed by 24/7 priority support from our expert team.', 'https://wordpress.com/help/contact/' ],
		[ 'login', 'Ask a question in our forum', 'Browse questions and get answers from other experienced users.', 'https://wordpress.com/forums/' ],
	];

	// Help & Learn hero: the same working prompt box as the hosting tab. The
	// question goes to the Support Assistant panel in the admin footer, which
	// posts it, runs the thinking states, and answers. Copy comes from
	// untangling_help_panel_data() so both surfaces stay in sync.
	function AskCard() {
		var help = window.untanglingHelpData || {};
		var draftState = useState( '' );
		var draft = draftState[ 0 ], setDraft = draftState[ 1 ];

		function ask( question ) {
			var text = ( question || '' ).trim();
			if ( ! text || ! window.untanglingHelp ) {
				return;
			}
			setDraft( '' );
			window.untanglingHelp.open( text );
		}

		return el( Card, null,
			el( CardBody, null,
				el( 'div', { className: 'untangling-ask-head' },
					el( 'span', { className: 'untangling-ask-mark', 'aria-hidden': true },
						icon( 'M12 2l2.2 7.8L22 12l-7.8 2.2L12 22l-2.2-7.8L2 12l7.8-2.2z', '0 0 24 24', 20 )
					),
					el( 'div', null,
						cardTitle( help.heading || 'Ask about your ' + ( isCommerce ? 'store' : 'site' ) ),
						cardDesc( help.lede || '' )
					)
				),
				el( 'form', {
					className: 'untangling-ask-form',
					onSubmit: function ( event ) {
						event.preventDefault();
						ask( draft );
					},
				},
					el( 'input', {
						type: 'text',
						className: 'untangling-ask-input',
						value: draft,
						placeholder: help.placeholder || '',
						'aria-label': help.heading || 'Ask about your site',
						autoComplete: 'off',
						onChange: function ( event ) {
							setDraft( event.target.value );
						},
					} ),
					el( Button, { variant: 'primary', type: 'submit', disabled: ! draft.trim() }, help.cta || 'Ask' )
				)
			)
		);
	}

	function learnHead( heading, linkLabel, href ) {
		return el( 'div', { className: 'ms-learn-head' },
			el( 'h2', { className: 'ms-next-h2' }, heading ),
			linkLabel && el( Button, { variant: 'tertiary', href: href, target: '_blank' }, linkLabel + ' ↗' )
		);
	}

	function VideoCardMs( props ) {
		var video = props.video;
		return el( 'a', { className: 'untangling-media-card', href: 'https://www.youtube.com/watch?v=' + video[ 0 ], target: '_blank', rel: 'noreferrer' },
			el( 'span', { className: 'untangling-media-thumb' },
				el( 'img', { src: 'https://i.ytimg.com/vi/' + video[ 0 ] + '/hq720.jpg', alt: '', loading: 'lazy' } ),
				el( 'span', { className: 'untangling-media-play', 'aria-hidden': true } )
			),
			el( 'span', { className: 'untangling-media-body' },
				el( 'span', { className: 'untangling-media-row' },
					el( 'span', { className: 'untangling-media-title' }, video[ 1 ] ),
					el( 'span', { className: 'untangling-media-duration' }, video[ 2 ] )
				)
			)
		);
	}

	function CourseCardMs( props ) {
		var course = props.course;
		return el( 'a', { className: 'untangling-media-card', href: course[ 0 ], target: '_blank', rel: 'noreferrer' },
			el( 'span', { className: 'untangling-media-thumb' },
				el( 'img', { src: course[ 1 ], alt: '', loading: 'lazy' } )
			),
			el( 'span', { className: 'untangling-media-body' },
				el( 'span', { className: 'untangling-media-row' },
					el( 'span', { className: 'untangling-media-title' }, course[ 2 ] ),
					el( 'span', { className: 'untangling-media-duration' }, course[ 3 ] )
				),
				el( 'span', { className: 'untangling-media-desc' }, course[ 4 ] )
			)
		);
	}

	function GuideTopicCardMs( props ) {
		var topic = props.topic;
		return el( 'div', { className: 'untangling-topic-card' },
			el( 'span', { className: 'untangling-topic-icon', 'aria-hidden': true }, icon( PATHS[ topic[ 0 ] ], '0 0 24 24', 20 ) ),
			el( 'span', { className: 'untangling-topic-title' }, topic[ 1 ] ),
			el( 'span', { className: 'untangling-topic-links' },
				topic[ 2 ].map( function ( link, index ) {
					return el( 'a', { key: index, href: link[ 1 ], target: '_blank', rel: 'noreferrer' }, link[ 0 ] );
				} )
			)
		);
	}

	function SupportCardMs( props ) {
		var item = props.item;
		return el( 'a', { className: 'untangling-support-card', href: item[ 3 ], target: '_blank', rel: 'noreferrer' },
			el( 'span', { className: 'untangling-support-icon', 'aria-hidden': true }, icon( PATHS[ item[ 0 ] ], '0 0 24 24', 24 ) ),
			el( 'span', { className: 'untangling-media-title' }, item[ 1 ] ),
			el( 'span', { className: 'untangling-media-desc' }, item[ 2 ] )
		);
	}

	function HelpPage() {
		return el( Shell, {
			title: 'Help & Learn',
			description: 'Answers first, humans when you need them. Same Help Center as the ? up top — just easier to find.',
		},
			el( 'div', { className: 'untangling-app ms-learn' },
				el( AskCard ),
				el( 'section', { className: 'untangling-learn-section' },
					learnHead( 'Video tutorials', 'Visit our YouTube channel', 'https://www.youtube.com/@wordpressdotcom' ),
					el( 'div', { className: 'untangling-media-grid' },
						LEARN_VIDEOS.map( function ( video ) { return el( VideoCardMs, { key: video[ 0 ], video: video } ); } )
					)
				),
				el( 'section', { className: 'untangling-learn-section' },
					learnHead( 'Courses', 'Browse all courses', 'https://wordpress.com/support/courses/' ),
					el( 'div', { className: 'untangling-media-grid' },
						LEARN_COURSES.map( function ( course, index ) { return el( CourseCardMs, { key: index, course: course } ); } )
					)
				),
				el( 'section', { className: 'untangling-learn-section' },
					learnHead( 'Guides', 'View all guides', 'https://wordpress.com/support/guides/' ),
					el( 'div', { className: 'untangling-media-grid' },
						LEARN_GUIDES.map( function ( topic ) { return el( GuideTopicCardMs, { key: topic[ 1 ], topic: topic } ); } )
					)
				),
				el( 'section', { className: 'untangling-learn-section' },
					learnHead( 'Couldn’t find what you needed?' ),
					el( 'div', { className: 'untangling-support-grid' },
						LEARN_SUPPORT.map( function ( item ) { return el( SupportCardMs, { key: item[ 1 ], item: item } ); } )
					)
				)
			)
		);
	}

	/* ---- Dashboard widgets (the all-in dashboard variant) ----
	   Each component mounts into one core postbox on index.php; the postbox
	   supplies the chrome and the title, so these render content only. All of
	   them are previews — the full management surface is the MSD, one footer
	   link away ("one preview + one full management interface per concept"). */

	// Widget footer: one link. The Jetpack credit now lives in the postbox
	// title (see untangling_dw_jetpack_title); credit stays accepted but no
	// dashboard widget passes it. The external icon marks the jump out to the
	// MSD; internal links (the Plan & products page) keep the chevron.
	function dwFooter( href, label, credit, internal ) {
		return el( 'a', { className: 'ms-linkfooter', href: href },
			el( 'span', null, label ),
			credit ? jetpackCredit( 'ms-logs-credit', 'span' ) : null,
			icon( internal ? PATHS.chevron : PATHS.external, '0 0 24 24', internal ? 20 : 18 )
		);
	}

	// Site details: core's At a glance extended with what the site runs on.
	// Storage takes two lines (meter, then the numbers) instead of core's two
	// columns.
	function DwGlance() {
		var counts = data.counts || {};
		function row( label, value ) {
			return el( 'div', { className: 'ms-dw-grid-row' },
				el( 'span', { className: 'ms-dw-grid-label' }, label ),
				el( 'span', { className: 'ms-dw-grid-value' }, value )
			);
		}
		return el( 'div', { className: 'ms-dw-body' },
			el( 'div', { className: 'ms-dw-grid' },
				row( 'Plan', el( 'a', { href: data.planPageUrl }, 'WordPress.com ' + data.plan ) ),
				row( 'WordPress', data.wpUpdate
					? el( Fragment, null, data.wpVersion, ' · ', el( 'a', { className: 'ms-dw-update', href: data.updateUrl }, 'Update to ' + data.wpUpdate ) )
					: data.wpVersion ),
				row( 'PHP', isFree ? data.phpVersion + ' — managed for you' : data.phpVersion ),
				row( 'Content', ( counts.posts || 0 ) + ' posts · ' + ( counts.pages || 0 ) + ' pages · ' + ( counts.comments || 0 ) + ' comments' ),
				el( 'div', { className: 'ms-dw-grid-row is-block' },
					el( 'span', { className: 'ms-dw-grid-label' }, 'Storage' ),
					// The widget has no room for the plan page's add-on picker, so
					// the tight state gets a one-link CTA on the numbers line that
					// opens the Upgrades page, where the picker lives.
					el( StorageMeter, { ctaHref: data.planPageUrl, ctaLabel: 'Add storage' } )
				)
			)
		);
	}

	// Next steps: the launchpad (just created) or the living pool
	// (established), same data and persistence as the My Site page — minus
	// the page heading, the two-column layout, and the preview column; the
	// postbox title already names it, and the admin bar links the site. The
	// open step is the dashboard's only elevation: the one "act on this".
	function DwLaunchpad( props ) {
		var doneState = useState( lpInitialDone );
		var done = doneState[ 0 ], setDone = doneState[ 1 ];
		var openState = useState( function () { return firstIncomplete( lpInitialDone() ); } );
		var openId = openState[ 0 ], setOpenId = openState[ 1 ];
		var count = LP_TASKS.filter( function ( t ) { return done[ t.id ]; } ).length;

		function complete( id ) {
			if ( done[ id ] ) {
				return;
			}
			var next = Object.assign( {}, done );
			next[ id ] = true;
			setDone( next );
			setOpenId( firstIncomplete( next ) );
			var doneCount = LP_TASKS.filter( function ( t ) { return next[ t.id ]; } ).length;
			var isComplete = doneCount === LP_TASKS.length;
			lpPersist( next, isComplete );
			if ( isComplete && props.onComplete ) {
				props.onComplete();
			}
		}

		return el( 'div', null,
			el( 'p', { className: 'ms-tl-progress' }, count + ' of ' + LP_TASKS.length + ' completed' ),
			el( 'div', { className: 'ms-tl-tasks', 'aria-label': 'Launchpad checklist' },
				LP_TASKS.map( function ( task ) {
					return el( TailoredTaskCard, {
						key: task.id,
						task: task,
						done: !! done[ task.id ],
						open: openId === task.id,
						onToggle: function () { setOpenId( openId === task.id ? null : task.id ); },
						onComplete: function () { complete( task.id ); },
					} );
				} )
			),
			el( MadeForLine, { compact: true } )
		);
	}

	function DwChecklistEstablished() {
		var handledState = useState( upnextHandled );
		var handled = handledState[ 0 ], setHandled = handledState[ 1 ];
		function pendingOf( ids ) {
			return DO_STEPS.filter( function ( s ) { return ids.indexOf( s.id ) === -1; } );
		}
		var openState = useState( function () {
			var first = pendingOf( upnextHandled() )[ 0 ];
			return first ? first.id : '';
		} );
		var openId = openState[ 0 ], setOpenId = openState[ 1 ];
		function onHandle( id ) {
			var next = handled.concat( [ id ] );
			setHandled( next );
			upnextSave( next );
			var first = pendingOf( next )[ 0 ];
			setOpenId( first ? first.id : '' );
			if ( ! pendingOf( next ).length ) {
				confettiBurst();
			}
		}
		function onReset() {
			setHandled( [] );
			upnextSave( [] );
			setOpenId( DO_STEPS.length ? DO_STEPS[ 0 ].id : '' );
		}
		var pending = pendingOf( handled );
		return el( 'div', null,
			el( 'div', { className: 'ms-tl-tasks ms-next-flow', 'aria-label': 'Next steps' },
				! pending.length && el( CaughtUpCard, { onReset: onReset } ),
				DO_STEPS.map( function ( step ) {
					var isDone = handled.indexOf( step.id ) !== -1;
					return el( NextStepCard, {
						key: step.id,
						step: step,
						done: isDone,
						open: ! isDone && openId === step.id,
						onToggle: function () { setOpenId( openId === step.id ? '' : step.id ); },
						onHandle: onHandle,
					} );
				} )
			),
			el( MadeForLine, { compact: true } )
		);
	}

	function DwChecklist() {
		var phaseState = useState( 'established' === data.state ? 'established' : 'launchpad' );
		var phase = phaseState[ 0 ], setPhase = phaseState[ 1 ];
		function onComplete() {
			confettiBurst();
			window.setTimeout( function () { setPhase( 'established' ); }, 1100 );
		}
		return el( 'div', { className: 'ms-dw-body' },
			'established' === phase ? el( DwChecklistEstablished ) : el( DwLaunchpad, { onComplete: onComplete } )
		);
	}

	// Stats: two KPIs, the underlined one drives the sparkline (Ghost's KPI
	// strip). Numbers are the last 7 days, deltas vs the week before.
	function Sparkline( props ) {
		var width = 400;
		var height = 64;
		var pad = 4;
		var max = 1;
		( props.values || [] ).forEach( function ( v ) { max = Math.max( max, v ); } );
		var points = toPoints( props.values || [ 0, 0 ], width, height, max * 1.15, pad );
		var line = smoothPath( points );
		var area = line + 'L' + points[ points.length - 1 ][ 0 ] + ',' + ( height - pad ) + 'L' + points[ 0 ][ 0 ] + ',' + ( height - pad ) + 'Z';
		var gid = 'msdwgrad-' + ( props.id || 'spark' );
		return el( 'svg', { className: 'ms-dw-spark', viewBox: '0 0 ' + width + ' ' + height, preserveAspectRatio: 'none', 'aria-hidden': true },
			el( 'defs', null,
				el( 'linearGradient', { id: gid, x1: 0, y1: 0, x2: 0, y2: 1 },
					el( 'stop', { offset: '0%', stopColor: props.color, stopOpacity: 0.2 } ),
					el( 'stop', { offset: '100%', stopColor: props.color, stopOpacity: 0 } )
				)
			),
			el( 'path', { d: area, fill: 'url(#' + gid + ')' } ),
			el( 'path', { d: line, fill: 'none', stroke: props.color, strokeWidth: 2, strokeLinecap: 'round', 'vector-effect': 'non-scaling-stroke' } )
		);
	}

	function DwStats() {
		var kpiState = useState( 'views' );
		var kpi = kpiState[ 0 ], setKpi = kpiState[ 1 ];
		var s = data.stats || {};
		var KPIS = [
			{ key: 'views', label: 'Views · last 7 days', total: s.viewsTotal || 0, delta: s.viewsDelta, color: '#3858e9', values: s.views || [] },
			{ key: 'visitors', label: 'Visitors · last 7 days', total: s.visitorsTotal || 0, delta: s.visitorsDelta, color: '#5ba300', values: s.visitors || [] },
		];
		var active = KPIS.filter( function ( k ) { return k.key === kpi; } )[ 0 ] || KPIS[ 0 ];
		return el( Fragment, null,
			el( 'div', { className: 'ms-dw-body' },
				el( 'div', { className: 'ms-dw-kpis' },
					KPIS.map( function ( k ) {
						return el( 'button', {
							key: k.key,
							type: 'button',
							className: 'ms-dw-kpi' + ( k.key === kpi ? ' is-active' : '' ),
							'aria-pressed': k.key === kpi,
							onClick: function () { setKpi( k.key ); },
						},
							el( 'span', { className: 'ms-dw-kpi-label' }, k.label ),
							el( 'span', { className: 'ms-dw-kpi-value' },
								k.total.toLocaleString(),
								k.delta && el( 'span', { className: 'ms-dw-kpi-delta' }, k.delta )
							)
						);
					} )
				),
				el( Sparkline, { id: active.key, color: active.color, values: active.values } )
			),
			dwFooter( msd + '/stats', 'See all stats' )
		);
	}

	// Activity: the feed rows, quiet — no per-row buttons; the full log (and
	// its history) lives in the MSD.
	function DwActivity() {
		var rows = data.activity || [];
		return el( Fragment, null,
			el( 'div', { className: 'ms-dw-body' },
				rows.length
					? el( 'div', { className: 'ms-dw-feed' },
						rows.map( function ( row, i ) {
							return el( row.href ? 'a' : 'div', { key: i, className: 'ms-dw-feed-row', href: row.href || undefined },
								el( 'span', { className: 'ms-dw-feed-icon', 'aria-hidden': true }, icon( PATHS[ row.icon ] || PATHS.post, '0 0 24 24', 20 ) ),
								el( 'span', { className: 'ms-dw-feed-main' },
									el( 'span', { className: 'ms-dw-feed-title' }, row.title ),
									el( 'span', { className: 'ms-dw-feed-summary' }, row.summary )
								),
								el( 'span', { className: 'ms-dw-feed-time' }, row.ago || row.time )
							);
						} )
					)
					: el( 'div', { className: 'ms-dw-empty' },
						el( 'span', { className: 'ms-dw-empty-icon', 'aria-hidden': true }, icon( PATHS.seen, '0 0 24 24', 20 ) ),
						el( 'span', null, 'Quiet so far. New events show up here.' )
					)
			),
			dwFooter( msd + '/sites/' + data.siteSlug + '/logs/activity', 'See all activity' )
		);
	}

	// Protection: Backups + Scan merged into one widget — both answer "is my
	// site safe", so they share one postbox (two MSD state cards, each keeping
	// its own deep link). Both are Jetpack products; the mark sits in the
	// postbox title, not the body. On Free the pair collapses into a single
	// upsell card — two muted upsells in one box would just repeat themselves.
	// Needs attention: the widget stops whispering. The postbox title gets an
	// "Action needed" pill, the two rows sit on the error surface edge to
	// edge, and each row's external-link glyph becomes the fix it leads to.
	function DwProtection() {
		var bad = 'attention' === data.hosting;
		useEffect( function () {
			var box = document.getElementById( 'untangling_dw_protection' );
			if ( box ) {
				box.classList.toggle( 'is-attention', bad && ! isFree );
			}
		}, [ bad ] );
		if ( isFree ) {
			return el( 'div', { className: 'ms-dw-body' },
				el( OvCard, { icon: 'shield', label: 'Protection', heading: 'Protect your site', desc: 'Daily backups, security scans, and one-click restores come with Business.', muted: true, href: plansUrlFor( 'security' ) } )
			);
		}
		var backups = bad
			? { icon: 'cloud', label: 'Backups', heading: 'Backup failed', desc: 'Your last good backup is 3 days old. Run a new one now.', intent: 'error', action: 'Retry backup', href: msd + '/sites/' + data.siteSlug + '/backups' }
			: { icon: 'cloud', label: 'Backups', heading: 'Backed up 2 hours ago', desc: 'Automatic, every day. Restore any moment with one click.', intent: 'success', href: msd + '/sites/' + data.siteSlug + '/backups' };
		var scan = bad
			? { icon: 'shield', label: 'Security', heading: '2 risks found', desc: 'Both have a one-click fix ready.', intent: 'error', action: 'Fix now', href: msd + '/sites/' + data.siteSlug + '/scan' }
			: { icon: 'shield', label: 'Security', heading: 'No threats found', desc: 'Last scan finished this morning. Scans run daily.', intent: 'success', href: msd + '/sites/' + data.siteSlug + '/scan' };
		if ( bad ) {
			return el( 'div', { className: 'ms-dw-body' },
				el( 'div', { className: 'ms-dw-issues', role: 'group', 'aria-label': 'Needs attention' },
					el( OvCard, backups ),
					el( OvCard, scan )
				)
			);
		}
		return el( 'div', { className: 'ms-dw-body' },
			el( OvCard, backups ),
			el( OvCard, scan )
		);
	}

	// Hosting, compressed into one settings-card: label/value rows that each
	// deep-link the MSD page that owns them. The charts and tables stayed in
	// the MSD on purpose — this is the preview, not a second console. Not a
	// Jetpack surface (Atomic host features), so no credit.
	function DwHosting() {
		if ( isFree ) {
			return el( Fragment, null,
				el( 'div', { className: 'ms-dw-body' },
					el( UpsellCallout, {
						stacked: true,
						icon: 'cloud',
						title: 'If something goes wrong',
						desc: 'Backups, security scans, staging, and server access come with Business.',
						cta: 'See plans',
						need: 'hosting',
					} )
				),
				dwFooter( msd + '/overview', 'Open the Hosting Dashboard' )
			);
		}
		var rows = [
			{ icon: 'code', title: 'PHP ' + data.phpVersion, desc: 'Managed for you.', route: '/settings/php' },
			{ icon: 'performance', title: 'Caching', desc: 'Edge and object caches active.', route: '/settings' },
			{ icon: 'layout', title: 'Staging', desc: 'No staging site yet.', route: '' },
			{ icon: 'key', title: 'SFTP/SSH', desc: 'Direct file access for developers.', route: '/settings/sftp-ssh' },
			{ icon: 'storage', title: 'Database', desc: 'Browse tables with phpMyAdmin.', route: '/settings/database' },
			{ icon: 'globe', title: 'Server logs', desc: 'PHP errors and every request.', route: '/logs/php' },
		];
		return el( Fragment, null,
			el( 'div', { className: 'ms-dw-body' },
				el( 'div', null, rows.map( function ( row, i ) {
					return el( 'a', { key: i, className: 'ms-advanced-row', href: msd + '/sites/' + data.siteSlug + row.route },
						el( 'span', { className: 'ms-grow-icon' }, icon( PATHS[ row.icon ] ) ),
						el( 'span', { className: 'ms-grow-main' },
							el( 'span', { className: 'ms-grow-title' }, row.title ),
							el( 'span', { className: 'ms-grow-desc' }, row.desc )
						),
						el( 'span', { className: 'ms-grow-chevron' }, icon( PATHS.external, '0 0 24 24', 18 ) )
					);
				} ) )
			),
			dwFooter( msd + '/overview', 'Open the Hosting Dashboard' )
		);
	}

	// Plan: the plan at a glance, and the dashboard's ONE promo slot —
	// quarantined here, last in the column, styled secondary. The top six
	// features fill the card; the full list stays on Plan & products.
	function DwPlan() {
		var compare = data.planCompare || {};
		return el( Fragment, null,
			el( 'div', { className: 'ms-dw-body' },
				el( 'div', null,
					el( 'div', { className: 'ms-plan-namerow' },
						el( 'h3', { className: 'ms-card-title' }, 'WordPress.com ' + data.plan ),
						el( Badge, { intent: 'success' }, 'Active' )
					),
					cardDesc( meta.renew )
				),
				el( 'ul', { className: 'ms-plan-features' },
					( meta.features || [] ).slice( 0, 6 ).map( function ( feature, i ) {
						return el( 'li', { key: i },
							el( 'span', { className: 'ms-plan-check' }, icon( PATHS.check, '0 0 24 24', 18 ) ),
							el( 'span', { className: 'untangling-feature-tip', tabIndex: 0, 'data-tip': feature.tip }, feature.label )
						);
					} )
				),
				isFree && compare.next
					? el( UpsellCallout, {
						stacked: true,
						icon: 'performance',
						title: 'Do more with ' + compare.next.name,
						desc: 'More storage, more design control, and room to grow.',
						cta: 'Upgrade to ' + compare.next.name,
					} )
					: el( 'div', { className: 'ms-dw-offer' },
						upsellDiamond(),
						el( 'span', { className: 'ms-dw-offer-text' },
							el( 'span', { className: 'ms-dw-offer-title' }, 'Save 20% with 2-year billing' ),
							el( 'span', { className: 'ms-dw-offer-desc' }, 'One renewal, two years of ' + data.plan + '.' )
						),
						el( Button, { variant: 'secondary', size: 'compact', href: data.renewUrl }, 'Renew and save' )
					)
			),
			dwFooter( data.planPageUrl, 'Plan & products', false, true )
		);
	}

	/* ---- router ---- */

	var PAGES = { next: NextStepsPage, plan: PlanPage, hosting: HostingPage, help: HelpPage };

	function App() {
		var Page = PAGES[ data.section ] || NextStepsPage;
		return el( Page );
	}

	var root = document.getElementById( 'untangling-ms-root' );
	if ( root && wp.element.createRoot ) {
		wp.element.createRoot( root ).render( el( App ) );
	}

	// Dashboard-variant mounts: one root per widget placeholder on index.php.
	// No placeholders on the My Site page (and no #untangling-ms-root on the
	// Dashboard), so each surface's loop no-ops on the other.
	var DW_WIDGETS = { next: DwChecklist, stats: DwStats, activity: DwActivity, glance: DwGlance, protection: DwProtection, hosting: DwHosting, plan: DwPlan };
	document.querySelectorAll( '.untangling-dw-mount' ).forEach( function ( node ) {
		var Widget = DW_WIDGETS[ node.dataset.widget ];
		if ( Widget && wp.element.createRoot ) {
			wp.element.createRoot( node ).render( el( Widget ) );
		}
	} );

	// Feature tooltips open above where the cursor entered, then stay put —
	// same delegated positioning as the WP.com page app.
	document.addEventListener( 'mouseover', function ( event ) {
		var tip = event.target && event.target.closest && event.target.closest( '.untangling-feature-tip' );
		if ( tip ) {
			tip.style.setProperty( '--untangling-tip-x', ( event.clientX - tip.getBoundingClientRect().left ) + 'px' );
		}
	} );
} )();
JS;
}

function untangling_ms_app_css() {
	return <<<'CSS'
/* My Site drawer — page chrome. The MSD canvas color behind a 960px column. */
body.toplevel_page_untangling-mysite,
body.toplevel_page_untangling-mysite #wpcontent { background: #fcfcfc; }
body.toplevel_page_untangling-mysite #wpcontent { padding-left: 0; padding-right: 0; }
body.toplevel_page_untangling-mysite #wpbody-content { padding-bottom: 0; }
body.toplevel_page_untangling-mysite #wpfooter { display: none; }

.untangling-ms {
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
	color: #1e1e1e;
	-webkit-font-smoothing: antialiased;
}

/* Shell — the tailored-launchpad column: 960px content (+24px side paddings),
   heading in the launchpad's style (32px sans, weight 500, grey subline). */
.untangling-ms .ms-page { max-width: 1008px; margin: 0 auto; padding: 32px 24px; box-sizing: border-box; display: flex; flex-direction: column; min-height: calc(100vh - 32px); }
.untangling-ms .ms-header { margin-bottom: 24px; }
.untangling-ms .ms-header-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.untangling-ms .ms-title,
.untangling-ms .ms-tl-title { margin: 0; font-size: 32px; line-height: 1.2; font-weight: 500; color: #1e1e1e; }
.untangling-ms .ms-desc { margin: 8px 0 0; font-size: 14px; line-height: 20px; color: #757575; max-width: 68ch; text-wrap: balance; }
.untangling-ms .ms-content { display: flex; flex-direction: column; gap: 24px; flex: 1; }
/* Grow chain so the "Tailored with AI" line (margin-top: auto) sits at the
   bottom of the viewport when the content is short, after it when tall. */
.untangling-ms .ms-content > .ms-next,
.untangling-ms .ms-content > .ms-new,
.untangling-ms .ms-new > .ms-tl { flex: 1; }
.untangling-ms .ms-new,
.untangling-ms .ms-new > .ms-tl { display: flex; flex-direction: column; }

/* Grid — MSD overview: 2 columns, 24px gap, 1 column under 1100px.
   Rows stretch so side-by-side cards share the same bottom edge. */
.untangling-ms .ms-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; align-items: stretch; }
.untangling-ms .ms-span2 { grid-column: 1 / -1; }
@media (max-width: 1100px) {
	.untangling-ms .ms-grid { grid-template-columns: 1fr; }
}

/* Cards — level-3 section headers on Card bodies, per the monitoring-card shell. */
.untangling-ms .components-card { border-radius: 8px; box-shadow: 0 0 0 1px #e0e0e0; }
.untangling-ms .components-card__body { padding: 24px; }
.untangling-ms .ms-card-titlerow { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin: 0 0 4px; }

/* Segmented control (hand-rolled — see Segmented in the app JS): quiet gray
   track, white pill slides under the active option. Radii hardcoded — the
   vendored token cascade leaves --wpds-border-radius-* at pill values. */
.untangling-ms .ms-segmented { position: relative; display: inline-flex; align-items: center; flex-shrink: 0; padding: 2px; background: var(--wpds-color-background-surface-neutral-weak, #f0f0f0); border-radius: 8px; }
.untangling-ms .ms-segmented-pill { position: absolute; top: 2px; bottom: 2px; left: 0; background: #fff; border-radius: 6px; box-shadow: 0 1px 2px rgba( 0, 0, 0, 0.12 ), 0 0 0 0.5px rgba( 0, 0, 0, 0.04 ); transition: left 0.15s ease-out, width 0.15s ease-out; }
.untangling-ms .ms-segmented-option { position: relative; appearance: none; border: 0; margin: 0; background: transparent; font: inherit; font-size: 12px; font-weight: 500; line-height: 1; color: var(--wpds-color-foreground-content-neutral-weak, #757575); min-height: 28px; padding: 0 10px; border-radius: 6px; cursor: pointer; white-space: nowrap; transition: color 0.15s ease-out; }
.untangling-ms .ms-segmented-option:hover,
.untangling-ms .ms-segmented-option.is-active { color: #1e1e1e; }
.untangling-ms .ms-segmented-option:focus-visible { outline: 1.5px solid var(--wpds-color-stroke-focus, #3858e9); outline-offset: -1px; }
@media ( prefers-reduced-motion: reduce ) {
	.untangling-ms .ms-segmented-pill,
	.untangling-ms .ms-segmented-option { transition: none; }
}
/* Toggle groups in a title row keep their width — squeezing them wraps labels. */
.untangling-ms .ms-card-titlerow .components-toggle-group-control { flex-shrink: 0; }
.untangling-ms .ms-card-title { margin: 0; font-size: 15px; line-height: 20px; font-weight: 500; color: #1e1e1e; }
.untangling-ms .ms-card-desc { margin: 4px 0 16px; font-size: 13px; line-height: 18px; color: #757575; }
.untangling-ms .ms-extlink { display: inline-flex; align-items: center; gap: 4px; font-size: 13px; text-decoration: none; color: #3858e9; }
.untangling-ms .ms-extlink svg { fill: currentColor; }
.untangling-ms .ms-extlink:hover { color: #2145e6; text-decoration: underline; }

/* Reveal animation — the established cards rise in, staggered. */
.untangling-ms .ms-rise { animation: ms-rise .38s cubic-bezier(.2,.7,.3,1) both; }
.untangling-ms .ms-grid > .ms-rise:nth-child(2) { animation-delay: 60ms; }
.untangling-ms .ms-grid > .ms-rise:nth-child(3) { animation-delay: 120ms; }
.untangling-ms .ms-grid > .ms-rise:nth-child(4) { animation-delay: 180ms; }
.untangling-ms .ms-grid > .ms-rise:nth-child(5) { animation-delay: 240ms; }
@keyframes ms-rise {
	from { opacity: 0; transform: translateY(12px); }
	to { opacity: 1; transform: none; }
}
@media (prefers-reduced-motion: reduce) {
	.untangling-ms .ms-rise { animation: none; }
}

/* Just created: the AI Launchpad tailored list (jetpack-mu-wpcom), faithfully.
   Heading + progress line, a grey group of white accordion task cards on the
   left, the site-preview column on the right. */
.untangling-ms .ms-new { display: flex; flex-direction: column; gap: 24px; }
.untangling-ms .ms-tl { transition: opacity .5s ease, transform .5s ease; }
.untangling-ms .ms-tl.is-leaving { opacity: 0; transform: translateY(-14px) scale(.985); }
.untangling-ms .ms-tl-heading { margin-bottom: 24px; }
.untangling-ms .ms-tl-progress { margin: 8px 0 0; color: #757575; font-size: 14px; }
.untangling-ms .ms-tl-columns { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 32px; align-items: start; }

/* The task column: a padded grey container so the white cards read as a
   grouped checklist. */
.untangling-ms .ms-tl-tasks { display: flex; flex-direction: column; gap: 8px; padding: 8px; background: #f6f7f7; border-radius: 8px; }
.untangling-ms .ms-tl-card { background: #fff; border-radius: 8px; box-shadow: 0 1px 2px rgba(0, 0, 0, .05); }
.untangling-ms .ms-tl-card-header { display: flex; align-items: center; gap: 8px; width: 100%; padding: 16px; background: none; border: 0; cursor: pointer; font: inherit; text-align: left; border-radius: 8px; }
.untangling-ms .ms-tl-card.is-done .ms-tl-card-header { cursor: default; }
.untangling-ms .ms-tl-icon { flex-shrink: 0; display: inline-flex; color: #1e1e1e; }
.untangling-ms .ms-tl-icon svg { fill: currentColor; }
.untangling-ms .ms-tl-icon.is-done { color: #949494; }
.untangling-ms .ms-tl-card-title { flex: 1 1 0%; min-width: 0; font-size: 14px; font-weight: 500; color: #1e1e1e; }
.untangling-ms .ms-tl-card-title.is-done { text-decoration: line-through; color: #949494; }
.untangling-ms .ms-tl-chevron { display: inline-flex; flex-shrink: 0; transform: rotate(90deg); transition: transform .15s ease; }
.untangling-ms .ms-tl-chevron svg { fill: #757575; }
.untangling-ms .ms-tl-card.is-open .ms-tl-chevron { transform: rotate(-90deg); }
.untangling-ms .ms-tl-card-content { padding: 0 16px 16px; }
.untangling-ms .ms-tl-subtitle { margin: 0 0 16px; color: #757575; font-size: 13px; }
.untangling-ms .ms-tl-actions { display: flex; align-items: center; gap: 8px; }

/* The site-preview card: a desktop-width iframe scaled to 0.25 (4x the frame),
   with an "Edit site" overlay on hover/focus. */
.untangling-ms .ms-tl-preview { display: flex; flex-direction: column; position: sticky; top: 56px; }
.untangling-ms .ms-tl-preview-frame { position: relative; display: block; width: 100%; max-width: 300px; aspect-ratio: 4 / 3; overflow: hidden; border: 1px solid #e0e0e0; border-radius: 8px; background: #f6f7f7; }
.untangling-ms .ms-tl-preview-iframe { position: absolute; top: 0; left: 0; width: 400%; min-height: 400%; border: 0; transform: scale(.25); transform-origin: top left; pointer-events: none; }
.untangling-ms .ms-tl-preview-edit { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(0, 0, 0, .55); opacity: 0; transition: opacity 120ms ease; }
.untangling-ms .ms-tl-preview-frame:hover .ms-tl-preview-edit,
.untangling-ms .ms-tl-preview-frame:focus-within .ms-tl-preview-edit { opacity: 1; }
.untangling-ms .ms-tl-preview-edit a, .untangling-ms .ms-tl-preview-edit a:hover, .untangling-ms .ms-tl-preview-edit a:focus, .untangling-ms .ms-tl-preview-edit a:active { color: #fff; }
.untangling-ms .ms-tl-preview-title { margin: 12px 0 2px; font-weight: 600; font-size: 15px; }
.untangling-ms .ms-tl-preview-link { font-size: 13px; text-decoration: none; }
.untangling-ms .ms-tl-preview-link:hover { text-decoration: underline; }

/* Stack the preview under the tasks on narrow screens. */
@media (max-width: 782px) {
	.untangling-ms .ms-tl-columns { grid-template-columns: 1fr; }
	.untangling-ms .ms-tl-preview { position: static; }
	.untangling-ms .ms-tl-preview-frame { max-width: none; }
}

.ms-confetti-canvas { position: fixed; inset: 0; z-index: 1000000; pointer-events: none; }

/* Stats + charts */
.untangling-ms .ms-stat-line { margin: 0 0 12px; font-size: 13px; color: #757575; display: flex; align-items: center; gap: 6px; }
.untangling-ms .ms-stat-line strong { font-size: 20px; font-weight: 500; color: #1e1e1e; }
.untangling-ms .ms-chart { display: block; overflow: visible; }
.untangling-ms .ms-legend { display: flex; gap: 16px; flex-wrap: wrap; margin-top: 12px; }
.untangling-ms .ms-legend-item { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: #757575; }
.untangling-ms .ms-legend-dot { width: 8px; height: 8px; border-radius: 50%; }

/* Vitals */
.untangling-ms .ms-vitals { list-style: none; margin: 8px 0 0; padding: 0; }
.untangling-ms .ms-vital { display: flex; align-items: center; gap: 10px; padding: 11px 0; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
.untangling-ms .ms-vital:last-child { border-bottom: none; }
.untangling-ms .ms-vital-icon { display: flex; }
.untangling-ms .ms-vital-icon svg { fill: #757575; }
.untangling-ms .ms-vital-label { color: #757575; flex: 1; }
.untangling-ms .ms-vital-value { color: #1e1e1e; display: inline-flex; align-items: center; gap: 10px; text-align: right; }
.untangling-ms .ms-vital-nudge { display: inline-flex; align-items: center; gap: 4px; color: #3858e9; text-decoration: none; font-weight: 500; }
.untangling-ms .ms-vital-nudge svg { fill: currentColor; }
.untangling-ms .ms-vital-nudge:hover { text-decoration: underline; }

/* Grow grid (also reused by Add to your site and Advanced) */
.untangling-ms .ms-grow-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-top: 16px; }
@media (max-width: 782px) {
	.untangling-ms .ms-grow-grid { grid-template-columns: 1fr; }
}
.untangling-ms .ms-grow-item, .untangling-ms .ms-advanced-row { display: flex; align-items: flex-start; gap: 12px; padding: 14px; border-radius: 8px; background: #fff; box-shadow: 0 0 0 1px #e0e0e0; text-decoration: none; transition: box-shadow .15s ease, background .15s ease; }
.untangling-ms .ms-grow-item:hover, .untangling-ms .ms-advanced-row:hover { box-shadow: 0 0 0 1px color-mix(in srgb, #3858e9 40%, transparent); background: color-mix(in srgb, #3858e9 2%, #fff); }
.untangling-ms .ms-grow-icon { display: flex; flex-shrink: 0; margin-top: 1px; }
.untangling-ms .ms-grow-icon svg { fill: #757575; }
.untangling-ms .ms-grow-item:hover .ms-grow-icon svg, .untangling-ms .ms-advanced-row:hover .ms-grow-icon svg { fill: #3858e9; }
.untangling-ms .ms-grow-main { flex: 1; display: flex; flex-direction: column; gap: 2px; }
.untangling-ms .ms-grow-title { font-size: 14px; font-weight: 500; color: #1e1e1e; display: inline-flex; align-items: center; gap: 8px; flex-wrap: nowrap; min-width: 0; }
.untangling-ms .ms-grow-title .ms-grow-badge { flex-shrink: 0; white-space: nowrap; }
.untangling-ms .ms-grow-desc { font-size: 12.5px; line-height: 17px; color: #757575; }
.untangling-ms .ms-grow-chevron { display: flex; flex-shrink: 0; align-self: center; }
.untangling-ms .ms-grow-chevron svg { fill: #949494; }
.untangling-ms .ms-grow-badge { display: inline-flex; align-items: center; gap: 4px; padding: 1px 8px; border-radius: 4px; background: #f0f0f0; color: #1e1e1e; font-size: 11px; font-weight: 500; line-height: 16px; }
.untangling-ms .ms-grow-badge svg { fill: currentColor; }
.untangling-ms .ms-grow-badge.is-included { background: #e5f5e9; color: #00753d; }
/* Next steps (established): the Dia-brief layout — serif section labels in a
   left rail, content on the right. Four section shapes: the Up-next hero
   panel, Top to-dos (the launchpad card component, compact), numbered New
   updates, and the Grow grid. */
@font-face {
	font-display: swap;
	font-family: Recoleta;
	font-weight: 400;
	src: url(https://s1.wp.com/i/fonts/recoleta/400.woff2) format("woff2"), url(https://s1.wp.com/i/fonts/recoleta/400.woff) format("woff");
}
.untangling-ms .ms-next { display: flex; flex-direction: column; gap: 24px; animation: ms-rise .38s cubic-bezier(.2,.7,.3,1) both; }
.untangling-ms .ms-next-main { display: flex; flex-direction: column; gap: 24px; min-width: 0; }
.untangling-ms .ms-next-h2 { margin: 0 0 14px; font-size: 20px; line-height: 1.3; font-weight: 500; color: #1e1e1e; }

/* The caught-up card, shown in the launchpad group once every step is handled. */
.untangling-ms .ms-hero { background: #fff; border-radius: 8px; box-shadow: 0 1px 2px rgba(0, 0, 0, .05); padding: 24px; animation: ms-rise .35s cubic-bezier(.2,.7,.3,1) both; }
.untangling-ms .ms-hero-eyebrow { margin: 0 0 10px; font-size: 11px; font-weight: 500; line-height: 16px; letter-spacing: .04em; text-transform: uppercase; color: #757575; }
.untangling-ms .ms-hero-title { margin: 0 0 8px; font-size: 24px; line-height: 1.25; font-weight: 500; color: #1e1e1e; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.untangling-ms .ms-hero-why { margin: 0 0 20px; max-width: 60ch; font-size: 14px; line-height: 1.6; color: #555; }
.untangling-ms .ms-hero-actions { display: flex; align-items: center; gap: 12px; }

.untangling-ms .ms-tl-card-title .ms-grow-badge { margin-left: 8px; vertical-align: middle; }

@media (prefers-reduced-motion: reduce) {
	.untangling-ms .ms-next,
	.untangling-ms .ms-hero { animation: none; }
}

/* The provenance line: tailored with AI. The phrase carries a slow gradient
   shimmer on hover, and mousemove scatters ✦ sparks that float up and fade. */
.untangling-ms .ms-madefor { position: relative; margin: auto 0 0; padding: 16px 0 0; text-align: center; font-size: 12.5px; color: #949494; }
.untangling-ms .ms-ai { font-weight: 500; background: linear-gradient(90deg, #3858e9, #b35eb1, #e34c84, #3858e9); background-size: 300% 100%; -webkit-background-clip: text; background-clip: text; color: transparent; }
.untangling-ms .ms-madefor:hover .ms-ai { animation: ms-ai-flow 1.8s linear infinite; }
@keyframes ms-ai-flow {
	to { background-position: 300% 0; }
}
.untangling-ms .ms-spark { position: absolute; pointer-events: none; line-height: 1; animation: ms-spark .85s ease-out forwards; }
@keyframes ms-spark {
	from { opacity: 0; transform: translateY(2px) scale(.4) rotate(0deg); }
	30% { opacity: 1; }
	to { opacity: 0; transform: translateY(-22px) scale(1) rotate(45deg); }
}
@media (prefers-reduced-motion: reduce) {
	.untangling-ms .ms-madefor:hover .ms-ai { animation: none; }
	.untangling-ms .ms-spark { animation: none; opacity: 0; }
}

/* Activity */
.untangling-ms .ms-activity { list-style: none; margin: 8px 0 0; padding: 0; }
.untangling-ms .ms-activity-row { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
.untangling-ms .ms-activity-row:last-child { border-bottom: none; }
.untangling-ms .ms-activity-icon { display: flex; }
.untangling-ms .ms-activity-icon svg { fill: #757575; }
.untangling-ms .ms-activity-text { flex: 1; color: #1e1e1e; }
.untangling-ms .ms-activity-time { color: #949494; font-size: 12px; white-space: nowrap; }

/* Needs attention */
.untangling-ms .ms-attention { box-shadow: 0 0 0 1px #f5c8c4; }
.untangling-ms .ms-attention-item { margin-top: 8px; }
.untangling-ms .ms-attention-title { margin: 0 0 4px; font-size: 14px; font-weight: 600; color: #8a2424; }
.untangling-ms .ms-attention-text { margin: 0 0 12px; font-size: 13px; color: #757575; }

/* Hosting overview cards — the MSD OverviewCard atom. */
.untangling-ms .ms-ovcard-row { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 24px; }
.untangling-ms .ms-ovcard-row.is-two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
@media (max-width: 1100px) {
	.untangling-ms .ms-ovcard-row, .untangling-ms .ms-ovcard-row.is-two { grid-template-columns: 1fr; }
}

/* Upsell callout — the HostingFeatureGatedWithCallout look: centered icon,
   title, muted copy, availability line, primary CTA. is-inline is the
   bordered in-card variant (Free plan activity log). */
/* Gated-feature row: icon chip, text, CTA — one line of card height instead of
   a centered 200px empty state. */
.untangling-ms .ms-upsell { display: flex; align-items: center; gap: 16px; padding: 20px 0 8px; }
.untangling-ms .ms-upsell-icon { flex: none; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 8px; background: color-mix(in srgb, #3858e9 8%, #fff); }
.untangling-ms .ms-upsell-icon svg { fill: #3858e9; }
.untangling-ms .ms-upsell-main { flex: 1 1 auto; min-width: 0; }
.untangling-ms .ms-upsell-title { margin: 0; font-size: 15px; line-height: 20px; font-weight: 500; color: #1e1e1e; }
.untangling-ms .ms-upsell-desc { margin: 2px 0 0; font-size: 13px; line-height: 18px; color: #757575; }
.untangling-ms .ms-upsell .components-button { flex: none; }
.untangling-ms .ms-upsell.is-inline { padding: 16px; margin-top: 16px; border: 1px solid #e0e0e0; border-radius: 8px; }
/* Same chip and text styles, stacked — for the half-width cards where a row
   would squeeze the copy into three wrapped lines. Centered on the card's own
   axis: stacked and left-aligned, the chip, the copy and the button each ended
   on the same left edge with a ragged right, so the card read as an unfinished
   column rather than an offer. */
.untangling-ms .ms-upsell.is-stacked { flex-direction: column; align-items: center; text-align: center; gap: 12px; padding: 16px 0 4px; }
.untangling-ms .ms-upsell.is-stacked .ms-upsell-main { flex: 0 1 auto; }
/* Centered text is where a one-word last line shows. balance on the title;
   pretty on the description, because balance was happy to break the example
   address at its hyphen (hello@slow- / mornings.com), which reads as two things.
   Both are ignored by anything that doesn't support them. */
.untangling-ms .ms-upsell.is-stacked .ms-upsell-title { text-wrap: balance; }
.untangling-ms .ms-upsell.is-stacked .ms-upsell-desc { text-wrap: pretty; }
@media (max-width: 600px) {
	.untangling-ms .ms-upsell { flex-wrap: wrap; gap: 12px; }
	.untangling-ms .ms-upsell-main { flex-basis: 100%; }
}
.untangling-ms .ms-ovcard { display: flex; flex-direction: column; gap: 8px; padding: 24px; border-radius: 8px; background: #fff; box-shadow: 0 0 0 1px #e0e0e0; text-decoration: none; transition: box-shadow .15s ease, background .15s ease; }
/* Hover copies the MSD's .dashboard-overview-card__link:hover verbatim: a 2%
   tint, a 12% ring, and the admin theme color taken by every line of text and
   both icons — the label, the status, the description, the eyebrow icon and the
   link chevron. Ours ringed at 40% and lit the heading alone, so the card
   looked bordered rather than hovered. The theme var (not a hardcoded blue)
   because the MSD follows the user's admin color scheme. */
.untangling-ms a.ms-ovcard:hover { box-shadow: 0 0 0 1px color-mix(in srgb, var(--wp-admin-theme-color, #3858e9) 12%, transparent); background: color-mix(in srgb, var(--wp-admin-theme-color, #3858e9) 2%, transparent); }
.untangling-ms .ms-ovcard-label { display: flex; align-items: center; gap: 8px; font-size: 11px; font-weight: 500; line-height: 16px; letter-spacing: .02em; text-transform: uppercase; color: #757575; }
/* Status is carried by the eyebrow icon alone, copying MSD's
   .dashboard-overview-card__icon rules and its --dashboard__*-color-* values
   (client/dashboard/app-dotcom/style.scss). Two things to keep straight, both
   MSD's calls rather than ours:
   - The un-intented icon is gray, departing from MSD, which paints it the
     admin theme color. In this row color is the verdict — green healthy, red
     broken — so a blue third icon reads as a third verdict, and a favorable
     one, on a card whose whole message is that there is nothing to report.
     MSD's worry about gray reading as disabled does not land here: the card
     is a link, its external-link glyph is already gray, and hover turns the
     whole card blue, so nothing rests on the eyebrow to say it is live.
   - The uppercase label stays muted for success/warning/error, and only turns
     blue for the upsell. The color is a status, not a heading.
   The selectors below are `> svg` on purpose: the link icon is nested inside
   the label span here (MSD keeps it a sibling), so a descendant selector
   repaints the external-link glyph too and the corner of the card starts
   reporting status as well. */
.untangling-ms .ms-ovcard-label > svg { fill: #757575; }
.untangling-ms .ms-ovcard.is-success .ms-ovcard-label > svg { fill: #3ca758; }
.untangling-ms .ms-ovcard.is-warning .ms-ovcard-label > svg { fill: #d47608; }
.untangling-ms .ms-ovcard.is-error .ms-ovcard-label > svg { fill: #cc1818; }
/* Only warning and error take the description with them (MSD passes intent to
   its <Text> for those two only) — a success sentence stays ordinary copy. */
.untangling-ms .ms-ovcard.is-warning .ms-ovcard-desc { color: #b36100; }
.untangling-ms .ms-ovcard.is-error .ms-ovcard-desc { color: #cc1818; }
.untangling-ms .ms-ovcard-linkicon { margin-left: auto; display: flex; }
.untangling-ms .ms-ovcard-linkicon svg { fill: #949494; }
/* Hierarchy: eyebrow (xs) < status (lg + emphasis) > desc (md body). The
   status is content, not a title — it must not outrank the page's real
   section titles (.ms-card-title, lg/500), so it matches their size and
   leans on weight instead. Desc is a sentence: md floor, never sm. */
.untangling-ms .ms-ovcard-heading { font-size: var(--wpds-typography-font-size-lg); line-height: var(--wpds-typography-line-height-sm); font-weight: var(--wpds-typography-font-weight-emphasis); color: #1e1e1e; }
.untangling-ms .ms-ovcard-desc { font-size: var(--wpds-typography-font-size-md); line-height: 18px; color: #757575; }
/* Gated variant, following MSD's upsell intent: the ground stays white and the
   eyebrow alone carries the offer, in the admin theme color. The old treatment
   (tinted card + gray plan badge + second CTA line) stated the gate three times
   and the badge sat gray-on-gray. */
.untangling-ms .ms-ovcard.is-upsell .ms-ovcard-label { color: var(--wp-admin-theme-color, #3858e9); }
.untangling-ms .ms-ovcard.is-upsell .ms-ovcard-label svg { fill: currentColor; }
.untangling-ms .ms-ovcard.is-upsell .ms-ovcard-linkicon svg { fill: #949494; }
/* The diamond is cropped to its glyph, so it needs the shared inline sizing
   rather than the 24-viewBox box the feature icons sit in. */
.untangling-ms .ms-ovcard.is-upsell .ms-ovcard-label > svg { width: 16px; height: 14px; }
.untangling-ms a.ms-ovcard:hover .ms-ovcard-label,
.untangling-ms a.ms-ovcard:hover .ms-ovcard-heading,
.untangling-ms a.ms-ovcard:hover .ms-ovcard-desc { color: var(--wp-admin-theme-color, #3858e9); }
.untangling-ms a.ms-ovcard:hover .ms-ovcard-label > svg,
.untangling-ms a.ms-ovcard:hover .ms-ovcard-linkicon svg { fill: var(--wp-admin-theme-color, #3858e9); }

/* Jetpack credit — the activity log's card foot, and nowhere else. Sentence case
   at fine-print scale: uppercase put it at the same weight as the table column
   headers and it read as a section label. The two-tone mark needs its fills
   pinned to the paths themselves — a direct rule beats an inherited one, so the
   green survives any container that repaints the svgs inside it. */
.untangling-ms .ms-jp-logo path { fill: #069e08; }
.untangling-ms .ms-jp-logo polygon { fill: #fff; }
.untangling-ms .ms-jp-credit { display: flex; align-items: center; gap: 6px; margin: 0; font-size: 12px; line-height: 16px; color: #757575; }

/* Logs table — the DataViews table look: hairline rows, muted mono details. */
.untangling-ms .ms-logs { width: 100%; border-collapse: collapse; margin-top: 8px; }
/* The 8px separated the table from the card description; under the header
   band the body's own padding already does that. */
.untangling-ms .components-card__body > .ms-logs:first-child { margin-top: 0; }
.untangling-ms .ms-logs th { text-align: left; font-size: 11px; font-weight: 500; text-transform: uppercase; letter-spacing: .02em; color: #757575; padding: 8px 12px 8px 0; border-bottom: 1px solid #e0e0e0; }
.untangling-ms .ms-logs td { padding: 10px 12px 10px 0; border-bottom: 1px solid #f0f0f0; vertical-align: top; font-size: 13px; }
.untangling-ms .ms-logs tbody tr:hover { background: #fafafa; }
.untangling-ms .ms-logs-sev { width: 90px; white-space: nowrap; }
.untangling-ms .ms-logs-msg { font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace; font-size: 12px; line-height: 18px; color: #1e1e1e; word-break: break-word; }
.untangling-ms .ms-logs-time { width: 170px; white-space: nowrap; color: #757575; }
.untangling-ms .ms-logs-empty { margin: 24px 0; font-size: 13px; color: #757575; }
.untangling-ms .ms-logs-foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 16px; }
/* Fine-print row: centered as one unit, wrapping to two centered lines on narrow
   screens rather than letting the middot dangle. */
.untangling-ms .ms-logs-credit { color: #949494; }
.untangling-ms .ms-logs-note { font-size: 12px; color: #949494; }

/* Activity view — MSD activity-log row: gray icon tile, bold event title with
   a quiet summary line, actor with an initial avatar. */
.untangling-ms .ms-activity-event { display: flex; gap: 12px; align-items: flex-start; }
.untangling-ms .ms-activity-icon { flex-shrink: 0; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; background: #f0f0f0; border-radius: 4px; }
.untangling-ms .ms-activity-icon svg { fill: #1e1e1e; }
.untangling-ms .ms-activity-main { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.untangling-ms .ms-activity-title { font-weight: 500; color: #1e1e1e; }
.untangling-ms .ms-activity-summary { color: #757575; }
.untangling-ms .ms-activity-user { width: 160px; white-space: nowrap; }
.untangling-ms .ms-activity-actor { display: inline-flex; align-items: center; gap: 8px; color: #757575; }
.untangling-ms .ms-activity-avatar { width: 24px; height: 24px; border-radius: 50%; background: #e0e0e0; color: #1e1e1e; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; }

/* Fallback Badge intent tones (core here ships no C.Badge) — MSD Badge
   colors: success green, warning amber, error red; neutral stays gray. */
.untangling-ms .untangling-fallback-badge { display: inline-block; background: #f4f4f4; border-radius: 4px; padding: 2px 8px; font-size: 12px; font-weight: 500; color: #1e1e1e; white-space: nowrap; }
.untangling-ms .untangling-fallback-badge.is-success { background: #e6f2e8; color: #007017; }
.untangling-ms .untangling-fallback-badge.is-warning { background: #fcf0d4; color: #996800; }
.untangling-ms .untangling-fallback-badge.is-error { background: #fce2e4; color: #b32d2e; }

/* Caching card — layer rows with status badges */
.untangling-ms .ms-cache-list { display: grid; margin-top: 8px; }
.untangling-ms .ms-cache-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
.untangling-ms .ms-cache-row:last-child { border-bottom: 0; }
.untangling-ms .ms-logs-note.is-cleared { color: #008a20; }

/* Web-server view — MSD server-log row: status code, method chip, wrapped URL. */
.untangling-ms .ms-logs-status { width: 70px; color: #757575; }
.untangling-ms .ms-logs-type { width: 110px; }
.untangling-ms .ms-logs-method { display: inline-block; padding: 2px 8px; border-radius: 2px; font-size: 12px; font-weight: 500; color: #1e1e1e; }
.untangling-ms .ms-logs-method.is-get { background: #c8e6cf; }
.untangling-ms .ms-logs-method.is-post { background: #cddef7; }
.untangling-ms .ms-logs-url { font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace; font-size: 12px; line-height: 18px; color: #757575; word-break: break-all; }

/* Advanced */
.untangling-ms .ms-advanced-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-top: 16px; }
@media (max-width: 782px) {
	.untangling-ms .ms-advanced-grid { grid-template-columns: 1fr; }
}

/* The one summary card that replaced five upgrade callouts. Three shapes share
   the shell, the foot, and the single button; only the middle differs. New
   rules here use --wpds-* tokens — the older Hosting CSS above is hand-rolled
   hex and is left alone in this pass. */
.untangling-ms .ms-missing-cols { display: grid; grid-template-columns: minmax(0, 0.85fr) minmax(0, 1fr); gap: var(--wpds-dimension-gap-2xl, 32px); margin-top: var(--wpds-dimension-gap-lg, 20px); }
@media (max-width: 782px) {
	.untangling-ms .ms-missing-cols { grid-template-columns: 1fr; gap: var(--wpds-dimension-gap-xl, 24px); }
}
.untangling-ms .ms-missing-col.is-get { padding-inline-start: var(--wpds-dimension-padding-2xl, 32px); border-inline-start: 1px solid var(--wpds-color-stroke-surface-neutral, #e0e0e0); }
@media (max-width: 782px) {
	.untangling-ms .ms-missing-col.is-get { padding-inline-start: 0; border-inline-start: 0; padding-block-start: var(--wpds-dimension-padding-xl, 24px); border-block-start: 1px solid var(--wpds-color-stroke-surface-neutral, #e0e0e0); }
}
.untangling-ms .ms-missing-collabel { margin: 0 0 var(--wpds-dimension-gap-md, 12px); font-size: var(--wpds-typography-font-size-xs, 12px); line-height: var(--wpds-typography-line-height-xs, 16px); font-weight: var(--wpds-typography-font-weight-emphasis, 500); letter-spacing: .04em; text-transform: uppercase; color: var(--wpds-color-foreground-content-neutral-weak, #757575); }

/* Left column: what the plan already does. Plain text with a check — it is a
   statement of fact, so it must not look as clickable as the right column. */
.untangling-ms .ms-missing-have { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: var(--wpds-dimension-gap-md, 12px); }
.untangling-ms .ms-missing-have li { display: flex; align-items: flex-start; gap: var(--wpds-dimension-gap-sm, 8px); font-size: var(--wpds-typography-font-size-sm, 13px); line-height: var(--wpds-typography-line-height-sm, 18px); color: var(--wpds-color-foreground-content-neutral, #1e1e1e); }
.untangling-ms .ms-missing-check { flex: none; display: inline-flex; }
.untangling-ms .ms-missing-check svg { fill: var(--wpds-color-foreground-content-success, #007f30); }

/* Right column / outcome list: icon, title, one line. */
.untangling-ms .ms-missing-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: var(--wpds-dimension-gap-lg, 16px); }
.untangling-ms .ms-missing-list.is-wide { margin-top: var(--wpds-dimension-gap-lg, 20px); display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--wpds-dimension-gap-lg, 20px) var(--wpds-dimension-gap-2xl, 32px); }
@media (max-width: 782px) {
	.untangling-ms .ms-missing-list.is-wide { grid-template-columns: 1fr; }
}
.untangling-ms .ms-missing-row { display: flex; align-items: flex-start; gap: var(--wpds-dimension-gap-md, 12px); }
.untangling-ms .ms-missing-icon { flex: none; display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: var(--wpds-border-radius-md, 6px); background: var(--wpds-color-background-surface-brand, #ecf0fa); }
.untangling-ms .ms-missing-icon svg { fill: var(--wpds-color-foreground-interactive-brand, #3858e9); }
.untangling-ms .ms-missing-text { display: flex; flex-direction: column; min-width: 0; }
.untangling-ms .ms-missing-row-title { font-size: var(--wpds-typography-font-size-sm, 14px); line-height: var(--wpds-typography-line-height-sm, 20px); font-weight: var(--wpds-typography-font-weight-emphasis, 500); color: var(--wpds-color-foreground-content-neutral, #1e1e1e); }
.untangling-ms .ms-missing-row-desc { margin-top: 2px; font-size: var(--wpds-typography-font-size-xs, 13px); line-height: var(--wpds-typography-line-height-xs, 18px); color: var(--wpds-color-foreground-content-neutral-weak, #757575); }

/* Grouped shape: label rail, one line of capabilities each. */
.untangling-ms .ms-missing-groups { list-style: none; margin: var(--wpds-dimension-gap-lg, 20px) 0 0; padding: 0; display: flex; flex-direction: column; gap: var(--wpds-dimension-gap-md, 12px); }
.untangling-ms .ms-missing-group { display: grid; grid-template-columns: 88px minmax(0, 1fr); gap: var(--wpds-dimension-gap-lg, 16px); align-items: baseline; padding-block: var(--wpds-dimension-padding-sm, 8px); border-block-start: 1px solid var(--wpds-color-stroke-surface-neutral-weak, #f0f0f0); }
.untangling-ms .ms-missing-group:first-child { border-block-start: 0; padding-block-start: 0; }
@media (max-width: 782px) {
	.untangling-ms .ms-missing-group { grid-template-columns: 1fr; gap: var(--wpds-dimension-gap-xs, 4px); }
}
.untangling-ms .ms-missing-grouplabel { font-size: var(--wpds-typography-font-size-xs, 12px); line-height: var(--wpds-typography-line-height-xs, 16px); font-weight: var(--wpds-typography-font-weight-emphasis, 500); letter-spacing: .04em; text-transform: uppercase; color: var(--wpds-color-foreground-content-neutral-weak, #757575); }
.untangling-ms .ms-missing-grouptext { font-size: var(--wpds-typography-font-size-sm, 14px); line-height: var(--wpds-typography-line-height-sm, 20px); color: var(--wpds-color-foreground-content-neutral, #1e1e1e); }

.untangling-ms .ms-missing-foot { margin-top: var(--wpds-dimension-gap-xl, 24px); padding-top: var(--wpds-dimension-padding-lg, 20px); border-top: 1px solid var(--wpds-color-stroke-surface-neutral, #e0e0e0); }

/* Plan & products */
.untangling-ms .ms-plan-namerow { display: flex; align-items: center; gap: 10px; }
.untangling-ms .ms-plan-namerow .ms-card-title { font-size: 20px; line-height: 26px; }
.untangling-ms .ms-plan-features { list-style: none; margin: 16px 0 0; padding: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 24px; }
@media (max-width: 782px) {
	.untangling-ms .ms-plan-features { grid-template-columns: 1fr; }
}
.untangling-ms .ms-plan-features li { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e1e1e; }
/* The shared tooltip centers on the cursor; in this two-column grid that
   pushes the 300px bubble past the card's left edge, so anchor it to the
   start of the label instead. */
.untangling-ms .ms-plan-features .untangling-feature-tip::after { left: 0; transform: none; }
.untangling-ms .ms-plan-check { display: flex; }
.untangling-ms .ms-plan-check svg { fill: #00a32a; }
/* Plan upgrade — Free vs Premium compare, ported from the WP.com page V5 card.
   Subgrid keeps name/price/features/CTA on shared rows across both columns. */
.untangling-ms .ms-plancompare { display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: auto auto 1fr auto; gap: 0 24px; }
.untangling-ms .ms-plancompare-col { min-width: 0; display: grid; grid-template-rows: subgrid; grid-row: 1 / -1; padding: 24px; background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; }
.untangling-ms .ms-plancompare-col.is-recommended { background: #f7f8fe; border-color: #ccd6f9; }
.untangling-ms .ms-plancompare-name { display: flex; align-items: center; gap: 10px; font-size: 20px; line-height: 26px; font-weight: 500; color: #1e1e1e; }
.untangling-ms .ms-plancompare-price { margin: 6px 0 16px; font-size: 13px; font-weight: 500; color: #1e1e1e; }
.untangling-ms .ms-plancompare-list { list-style: none; margin: 0; padding: 0; display: grid; align-content: start; gap: 12px; font-size: 13px; color: #1e1e1e; }
.untangling-ms .ms-plancompare-list.is-muted { color: #757575; }
.untangling-ms .ms-plancompare-cta { margin-top: 20px; display: flex; }
.untangling-ms .ms-plancompare-cta .components-button svg { fill: currentColor; }
/* Button's has-icon recipe trims left padding to 8px for a 24px icon; with
   the 14px diamond that reads lopsided — restore symmetric padding. */
.untangling-ms .components-button.has-icon.has-text { padding-left: 12px; padding-right: 12px; gap: 6px; }
.untangling-ms .ms-chip-dark { display: inline-block; flex: none; font-size: 12px; line-height: 16px; font-weight: 500; border-radius: 2px; padding: 2px 8px; background: #2c3338; color: #fff; white-space: nowrap; }
@media (max-width: 782px) {
	.untangling-ms .ms-plancompare { grid-template-columns: 1fr; grid-template-rows: none; row-gap: 16px; }
	.untangling-ms .ms-plancompare-col { grid-template-rows: auto auto 1fr auto; grid-row: auto; }
}
/* Card header band — the row above the rule, paired with .ms-linkfooter below
   the one at the bottom: same 16px/24px padding and 15/20 text, the header at
   weight 500 and the footer at regular. Anything on the right of the row (the
   Logs segmented control) keeps its width. */
.untangling-ms .components-card__body.ms-cardhead { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 24px; }
.untangling-ms .ms-cardhead > :first-child { min-width: 0; }

/* Card footer link row, MSD "See all activity" style: label left, chevron right. */
/* Same row height as the card header (16px padding + 20px line), title-size
   text at regular weight. */
.untangling-ms .ms-linkfooter { position: relative; display: flex; align-items: center; justify-content: space-between; padding: 16px 24px; font-size: 15px; line-height: 20px; color: #1e1e1e; text-decoration: none; }
/* Fine print a footer can carry (the Logs card's Jetpack credit) sits on the
   card's centre line, not in the flex flow — the label and the icon are
   different widths, so a flex middle child would land off-centre. It keeps
   its own size and color rather than the link's. */
.untangling-ms .ms-linkfooter .ms-logs-credit { position: absolute; left: 50%; transform: translateX( -50% ); }
.untangling-ms .ms-linkfooter:hover { color: #3858e9; }
.untangling-ms .ms-linkfooter svg { fill: #757575; }
.untangling-ms .ms-linkfooter:hover svg { fill: currentColor; }
.untangling-ms .ms-storage { margin: 12px 0 8px; }
.untangling-ms .ms-storage-bar { height: 8px; border-radius: 4px; background: #f0f0f0; overflow: hidden; }
.untangling-ms .ms-storage-fill { height: 100%; border-radius: 4px; background: #3858e9; transition: width .4s ease; }
.untangling-ms .ms-storage-fill.is-warning { background: #d47608; }
.untangling-ms .ms-storage-used { margin: 8px 0 0; font-size: 13px; font-weight: 500; color: #1e1e1e; }
.untangling-ms .ms-storage-note { margin: 2px 0 0; font-size: 13px; color: #757575; }
.untangling-ms .ms-storage-buy { margin-top: 16px; }
/* Select stretches, button keeps its intrinsic width; both are 40px tall so
   they sit on one baseline, and they stack on narrow cards. */
.untangling-ms .ms-storage-buy-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.untangling-ms .ms-storage-buy-row .components-base-control { flex: 1 1 200px; }
.untangling-ms .ms-storage-fineprint { margin: 8px 0 0; font-size: 12px; color: #757575; }
.untangling-ms .ms-domains { list-style: none; margin: 8px 0 0; padding: 0; }
.untangling-ms .ms-domain-row { display: flex; align-items: center; gap: 10px; padding: 11px 0; border-bottom: 1px solid #f0f0f0; font-size: 13px; flex-wrap: wrap; }
.untangling-ms .ms-domain-row:last-child { border-bottom: none; }
.untangling-ms .ms-domain-name { font-weight: 500; color: #1e1e1e; }
.untangling-ms .ms-domain-note { margin-left: auto; color: #757575; font-size: 12px; }
.untangling-ms .ms-domain-note a { color: #3858e9; text-decoration: none; }
.untangling-ms .ms-domain-note a:hover { text-decoration: underline; }
/* Linked billing rows: the anchor carries the row layout so the whole strip is
   clickable, but the hover reads as a link, not a band — the row text turns blue
   and the external icon fades in. The icon holds its slot at rest (opacity 0)
   so nothing shifts when the pointer arrives. */
.untangling-ms .ms-domain-row.is-linked { padding: 0; }
.untangling-ms .ms-domain-link { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; width: 100%; padding: 11px 0; color: inherit; text-decoration: none; transition: color .12s ease-out; }
.untangling-ms .ms-domain-link:focus-visible { outline: 2px solid var(--wp-admin-theme-color, #3858e9); outline-offset: 2px; border-radius: 2px; }
.untangling-ms .ms-domain-link:hover,
.untangling-ms .ms-domain-link:hover .ms-domain-name,
.untangling-ms .ms-domain-link:hover .ms-domain-note { color: var(--wp-admin-theme-color, #3858e9); }
.untangling-ms .ms-domain-link .ms-domain-name,
.untangling-ms .ms-domain-link .ms-domain-note { transition: color .12s ease-out; }
.untangling-ms .ms-domain-link .ms-domain-note { display: inline-flex; align-items: center; gap: 4px; }
.untangling-ms .ms-domain-link .ms-domain-note svg { fill: currentColor; opacity: 0; transition: opacity .12s ease-out; }
.untangling-ms .ms-domain-link:hover .ms-domain-note svg { opacity: 1; }

/* Help & Learn — the hosting tab's learn layout inside the My Site shell.
   Media/topic/support/ask-card styles come from untangling_app_css(); the page
   only adds the wrapper rhythm and the ms-style headings. */
.untangling-ms .ms-learn { display: flex; flex-direction: column; gap: 32px; }
.untangling-ms .ms-learn .untangling-ask-head .ms-card-desc { margin: 4px 0 0; }
.untangling-ms .ms-learn-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.untangling-ms .ms-learn-head .ms-next-h2 { margin: 0; }
.untangling-ms .untangling-topic-icon svg,
.untangling-ms .untangling-support-icon svg { fill: currentColor; display: block; }

/* Narrow screens: keep the 24px rhythm but let paddings breathe less. */
@media (max-width: 782px) {
	.untangling-ms .ms-page { padding: 16px; min-height: calc(100vh - 46px); }
	.untangling-ms .components-card__body { padding: 16px; }
	.untangling-ms .ms-ovcard { padding: 16px; }
}
CSS;
}
