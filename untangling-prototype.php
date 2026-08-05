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

// Where the local MSD prototype lives (Calypso dashboard, `yarn start`).
define( 'UNTANGLING_MSD_URL', 'http://my.localhost:3333' );

// Standalone demos (WordPress Playground shares) have no MSD running.
// The per-site config defines UNTANGLING_STANDALONE; MSD-bound links then
// show a toast instead of navigating to a dead host.
function untangling_is_standalone() {
	return defined( 'UNTANGLING_STANDALONE' ) && UNTANGLING_STANDALONE;
}

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
				toast.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:1000000;background:#1e1e1e;color:#fff;padding:12px 20px;border-radius:4px;font:13px/1.5 -apple-system,BlinkMacSystemFont,sans-serif;box-shadow:0 4px 12px rgba(0,0,0,.25);transition:opacity .3s;max-width:90vw;text-align:center;';
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

// MSD site-overview slug for "Go to Site Overview" links.
function untangling_get_site_slug() {
	return defined( 'UNTANGLING_SITE_SLUG' ) ? UNTANGLING_SITE_SLUG : 'aperture-diaries.com';
}

function untangling_get_primary_domain() {
	return defined( 'UNTANGLING_PRIMARY_DOMAIN' ) ? UNTANGLING_PRIMARY_DOMAIN : 'aperture-diaries.com';
}

function untangling_get_domain_upsell() {
	return defined( 'UNTANGLING_DOMAIN_UPSELL' ) ? UNTANGLING_DOMAIN_UPSELL : 'aperture.blog';
}

// Plan-dependent card data for the WordPress.com page.
function untangling_get_plan_meta() {
	$plans = array(
		'Free'     => array(
			'renew'    => 'No expiration, free forever',
			'features' => array( '1 GB storage', 'Dozens of free themes', 'Community support', 'Free .wordpress.com address' ),
			'storage'  => array( 0.7, 1, 'Filling up. Photos eat space fast.' ),
		),
		'Personal' => array(
			'renew'    => 'Renews March 14, 2027',
			'features' => array( 'Ad-free experience', '6 GB storage', 'Fast support', 'Free domain for one year' ),
			'storage'  => array( 1.4, 6, null ),
		),
		'Premium'  => array(
			'renew'    => 'Renews March 14, 2027',
			'features' => array( 'Premium themes', 'Monetization and payments', '13 GB storage', 'Ad-free experience' ),
			'storage'  => array( 4.2, 13, null ),
		),
		'Business' => array(
			'renew'    => 'Renews March 14, 2027',
			'features' => array( 'Install plugins & themes', 'SFTP/SSH & database access', 'Monetization and payments', '50 GB storage' ),
			'storage'  => array( 41.8, 50, 'Almost full. New photos may not upload.' ),
		),
		'Commerce' => array(
			'renew'    => 'Renews March 14, 2027',
			'features' => array( 'Sell products & subscriptions', 'Premium store design tools', 'Install plugins & themes', '50 GB storage' ),
			'storage'  => array( 22.4, 50, null ),
		),
	);
	return $plans[ untangling_get_plan() ];
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
 * Hosting menu variant: 'submenu' or 'plain'.
 * Switch with ?untangling_variant=submenu|plain (persisted), or from the Hosting page.
 */
function untangling_get_variant() {
	if ( isset( $_GET['untangling_variant'] ) && in_array( $_GET['untangling_variant'], array( 'submenu', 'plain' ), true ) ) {
		update_option( 'untangling_variant', $_GET['untangling_variant'] );
	}
	return get_option( 'untangling_variant', 'submenu' );
}

/**
 * Site type mimic: 'atomic' or 'simple'.
 * Simple sites get the same core screens as Atomic, with install/write
 * actions replaced by upgrade CTAs.
 * Switch with ?untangling_site_type=atomic|simple (persisted).
 */
function untangling_get_site_type() {
	if ( isset( $_GET['untangling_site_type'] ) && in_array( $_GET['untangling_site_type'], array( 'atomic', 'simple' ), true ) ) {
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
 * banners. 'split' (V2): plugins keep the core-unified Marketplace tab in
 * Add Plugins; only themes use the fullscreen page (no switcher).
 * 'tabs' (V3): fully in-admin — Add Themes gets a Marketplace tab like Add
 * Plugins, the Theme Showcase sidebar entry disappears, and both banners
 * upsell plans. The fullscreen page keeps serving the pricing/checkout and
 * theme-details steps only.
 * Switch with ?untangling_marketplace=fullscreen|split|tabs (persisted), or
 * from the Prototype controls.
 */
function untangling_get_marketplace_mode() {
	if ( isset( $_GET['untangling_marketplace'] ) && in_array( $_GET['untangling_marketplace'], array( 'fullscreen', 'split', 'tabs' ), true ) ) {
		update_option( 'untangling_marketplace', $_GET['untangling_marketplace'] );
	}
	return get_option( 'untangling_marketplace', 'fullscreen' );
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
	if ( isset( $_GET['untangling_plan_filter'] ) && in_array( $_GET['untangling_plan_filter'], array( 'included', 'dropdown' ), true ) ) {
		update_option( 'untangling_plan_filter', $_GET['untangling_plan_filter'] );
	}
	return get_option( 'untangling_plan_filter', 'included' );
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
	if ( isset( $_GET['untangling_reset_demo'] ) ) {
		delete_option( 'untangling_plan_override' );
		delete_option( 'untangling_mkt_active_theme' );
		delete_option( 'untangling_mkt_installed' );
	}
} );

/* -------------------------------------------------------------------------
 * 1. "Hosting" top-level menu — the merged Hosting +
 *    Upgrades brand anchor. Replaces My Home and the old Hosting/Upgrades
 *    entries; slug stays `untangling-hosting` so existing links keep working.
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
	$variant = untangling_get_variant();

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
		add_submenu_page( 'untangling-hosting', __( 'Learn' ), __( 'Learn' ), 'manage_options', 'admin.php?page=untangling-hosting&untangling_tab=learn-more' );
	}
} );

// Marketplace entry points. The page itself registers as a (hidden) top-level
// page so it lives at admin.php?page=untangling-marketplace; the sidebar items
// are plain deep links. Appearance → Theme Showcase exists in V1 and V2 (V2
// keeps the fullscreen page for themes); Plugins → Marketplace is V1 only.
// V3 (tabs) drops both — themes get an in-admin Marketplace tab instead.
add_action( 'admin_menu', function () {
	add_menu_page( __( 'Marketplace' ), __( 'Marketplace' ), 'manage_options', 'untangling-marketplace', 'untangling_render_marketplace_page' );
	remove_menu_page( 'untangling-marketplace' );

	if ( 'tabs' !== untangling_get_marketplace_mode() ) {
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
	return $submenu_file;
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
				'plansUrl'     => untangling_marketplace_url( 'themes', array( 'ustep' => 'pricing' ) ),
				'checkoutUrl'  => untangling_marketplace_url( 'themes', array( 'ustep' => 'checkout', 'plan' => 'Premium' ) ),
				'variant'      => untangling_get_variant(),
				'siteType'     => untangling_get_site_type(),
				'marketplace'  => untangling_get_marketplace_mode(),
				'planFilter'   => untangling_get_plan_filter(),
				'planOverride' => (bool) get_option( 'untangling_plan_override' ),
				'plan'         => untangling_get_plan(),
				'planMeta'     => untangling_get_plan_meta(),
				'siteSlug'     => untangling_get_site_slug(),
				'domain'       => untangling_get_primary_domain(),
				'domainUpsell' => untangling_get_domain_upsell(),
				'siteName'     => get_bloginfo( 'name' ),
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
	var RadioControl = C.RadioControl;
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
		{ name: 'learn-more', title: 'Learn' },
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
	function Header() {
		return el( 'div', { className: 'untangling-header' },
			el( HStack, { justify: 'space-between', alignment: 'flex-start', wrap: true, spacing: 4 },
				el( FlexItem, null,
					el( 'div', { className: 'untangling-header-brand' },
						el( 'span', { className: 'untangling-header-icon', dangerouslySetInnerHTML: { __html: WPCOM_MARK } } ),
						el( 'h1', { className: 'untangling-title' }, 'WordPress.com' )
					),
					el( 'p', { className: 'untangling-sub' },
						'See your plan, manage your domain, and learn as you go.' )
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

	function featureList( features ) {
		return el( 'ul', { className: 'untangling-feature-list' },
			features.map( function ( feature, index ) {
				return el( 'li', { key: index }, feature );
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
		function compareCol( name, chipText, chipClass, features, listClass ) {
			return el( 'div', { className: 'untangling-plan-compare-col' },
				el( 'div', { className: 'untangling-plan-compare-name' },
					el( 'span', null, name ),
					el( 'span', { className: 'untangling-plan-chip ' + chipClass }, chipText )
				),
				el( 'ul', { className: 'untangling-plan-compare-list' + ( listClass ? ' ' + listClass : '' ) },
					features.map( function ( feature, index ) {
						return el( 'li', { key: index },
							el( 'span', { className: 'untangling-feature-tip', tabIndex: 0, 'data-tip': feature.tip }, feature.label )
						);
					} )
				)
			);
		}
		return el( Card, { className: 'untangling-plan-card' },
			el( CardHeader, null, title( 'Plan upgrade' ) ),
			el( CardBody, null,
				el( 'div', { className: 'untangling-plan-compare' },
					compareCol( 'Free', 'Current plan', 'is-neutral', freeCol, 'is-muted' ),
					compareCol( 'Premium', 'Recommended', 'is-success', premiumCol, '' )
				)
			),
			el( CardFooter, null,
				el( HStack, { justify: 'flex-start', spacing: 2, expanded: false },
					el( Button, { variant: 'secondary', className: 'untangling-upgrade', href: checkoutUrl }, 'Upgrade to Premium' ),
					el( Button, { variant: 'tertiary', href: plansUrl }, 'See all plans' )
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

	function DomainCard() {
		var upsell = data.domainUpsell;
		return el( Card, null,
			el( CardHeader, null, title( 'Domains' ) ),
			el( CardBody, null,
				el( 'div', { className: 'untangling-email-upsell' },
					el( 'div', { className: 'untangling-stat-line' }, 'Get your custom domain' ),
					meta( upsell + ' is available. Put your site on its own address.' ),
					el( Button, { variant: 'secondary', href: msd + '/domains' }, 'Get ' + upsell )
				)
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

	// "My site" main-column layout variants, switched from the prototype panel.
	// Three directions explore what a Blogger & Creator persona should see
	// first (see competitor audit); Grow · journey is the default.
	var MYSITE_VARIANTS = [
		{ value: 'growth', label: 'Grow · journey' },
		{ value: 'momentum', label: 'Momentum · daily home' },
		{ value: 'earn', label: 'Earn & Reach · business' },
		{ value: 'onecol', label: 'One column · stacked' },
		{ value: 'cards', label: 'Overview cards · clickable rail' },
	];

	function initialMysite() {
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
	// done and advances.
	function GrowthView() {
		var tasks = [
			{ id: 'theme', label: 'Choose a theme' },
			{ id: 'post', label: 'Write your first post', desc: 'Share your inaugural creative piece with readers.', cta: 'Write post', href: 'post-new.php' },
			{ id: 'about', label: 'Add your About page', desc: 'Introduce yourself and your writing journey.', cta: 'Add page', href: 'post-new.php?post_type=page' },
			{ id: 'social', label: 'Connect your social media accounts', desc: 'Share new posts to your social profiles automatically.', cta: 'Connect accounts', href: msd },
			{ id: 'welcome', label: 'Write a welcome message', desc: 'Greet new readers when they land on your blog.', cta: 'Write message', href: msd },
			{ id: 'launch', label: 'Launch your site', desc: 'Make your blog public when you’re ready.', cta: 'Launch site', href: msd },
		];
		var doneState = useState( { theme: true } );
		var done = doneState[ 0 ], setDone = doneState[ 1 ];
		var openState = useState( 'post' );
		var open = openState[ 0 ], setOpen = openState[ 1 ];
		function skip( id ) {
			var next = Object.assign( {}, done );
			next[ id ] = true;
			setDone( next );
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
			[ 'Write a post', 'Draft your next story in the editor.', 'post-new.php' ],
			[ 'Claim your domain', 'Get ' + data.domainUpsell + ' for your site.', msd + '/domains' ],
			[ 'Send a newsletter', 'Email your 214 subscribers.', 'post-new.php' ],
			[ 'View your stats', 'See what readers love most.', msd + '/stats' ],
		];
		return el( 'div', { className: 'untangling-quick-grid' },
			items.map( function ( item, index ) {
				return el( 'a', { key: index, className: 'untangling-quick-card', href: item[ 2 ] },
					el( 'span', null,
						el( 'span', { className: 'untangling-stat-line' }, item[ 0 ] ),
						meta( item[ 1 ] )
					),
					el( 'span', { className: 'untangling-quick-chevron', 'aria-hidden': true }, '›' )
				);
			} )
		);
	}

	function OneColView( props ) {
		return el( Fragment, null,
			sectionTitle( 'Quick start' ),
			el( QuickStartGrid ),
			el( PulseCard ),
			sectionTitle( 'Your site' ),
			el( PlanCard, { variant: props.plancard } ),
			el( StorageCard ),
			el( DomainCard ),
			el( EmailCard ),
			el( HelpCard )
		);
	}

	// MSD overview-card pattern (dashboard-overview-card): whole card is a
	// link — icon + uppercase label + chevron up top, big heading, muted
	// description. Hover tints the card and flips all text to the accent.
	var OV_ICONS = {
		plan: WPCOM_MARK,
		stats: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M11.25 5h1.5v15h-1.5V5zM6 10h1.5v10H6V10zm10.5-2H18v12h-1.5V8z"/></svg>',
		globe: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M12 3.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17zM5.5 12c0-.7.1-1.4.3-2h2.3a17 17 0 0 0 0 4H5.8a6.9 6.9 0 0 1-.3-2zm4.1 0c0-.7 0-1.4.1-2h4.6a15.6 15.6 0 0 1 0 4H9.7a15.6 15.6 0 0 1-.1-2zm6.3-3.5a12 12 0 0 0-1-2.8 7 7 0 0 1 2.9 2.8h-1.9zM12 5.6c.5.7 1 1.7 1.3 2.9h-2.6c.3-1.2.8-2.2 1.3-2.9zM7.2 8.5H5.3a7 7 0 0 1 2.9-2.8 12 12 0 0 0-1 2.8zm-1.9 7h1.9a12 12 0 0 0 1 2.8 7 7 0 0 1-2.9-2.8zm6.7 2.9c-.5-.7-1-1.7-1.3-2.9h2.6c-.3 1.2-.8 2.2-1.3 2.9zm2.9-.1a12 12 0 0 0 1-2.8h1.9a7 7 0 0 1-2.9 2.8zm.6-4.3a17 17 0 0 0 0-4h2.3a6.9 6.9 0 0 1 0 4h-2.3z"/></svg>',
		storage: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M19 6.5H5c-.8 0-1.5.7-1.5 1.5v8c0 .8.7 1.5 1.5 1.5h14c.8 0 1.5-.7 1.5-1.5V8c0-.8-.7-1.5-1.5-1.5zM19 16H5V8h14v8zM7 13h10v1.5H7V13z"/></svg>',
		email: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M19 5H5c-.8 0-1.5.7-1.5 1.5v11c0 .8.7 1.5 1.5 1.5h14c.8 0 1.5-.7 1.5-1.5v-11c0-.8-.7-1.5-1.5-1.5zm0 1.5v.7L12 12 5 7.2v-.7h14zM5 17.5V9l7 4.8L19 9v8.5H5z"/></svg>',
	};

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

	function HelpCard() {
		var askState = useState( false );
		var asking = askState[ 0 ], setAsking = askState[ 1 ];
		return el( Card, { className: 'untangling-help-card' },
			el( CardBody, null,
				el( HStack, { justify: 'space-between', alignment: 'center', wrap: true, spacing: 4 },
					el( FlexBlock, null,
						title( 'Need a hand?' ),
						meta( 'Ask the AI assistant anything about your blog, or browse guides in the Help Center.' )
					),
					el( FlexItem, null,
						el( HStack, { spacing: 2 },
							el( Button, { variant: 'secondary', onClick: function () { setAsking( ! asking ); } }, 'Ask AI' ),
							el( Button, { variant: 'tertiary', href: 'https://wordpress.com/support', target: '_blank' }, 'Help Center ↗' )
						)
					)
				),
				asking && el( 'div', { className: 'untangling-ask-box' },
					el( TextControl, {
						__nextHasNoMarginBottom: true,
						placeholder: 'Ask anything about your blog…',
						onChange: function () {},
						help: 'Prototype placeholder. The assistant lives in the Help Center panel.',
					} )
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
	// Card built from wp.components (Card, RadioControl, ToggleGroupControl,
	// Button). Drag the fab or the panel header to reposition it anywhere on
	// the page; the position lives only for the current page view, so every
	// load starts back at the default bottom-right corner. Plan-card designs
	// switch client-side (localStorage + ?untangling_plancard= for shareable
	// links); the environment switches reload through go() because they're
	// persisted server-side.
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
					el( VStack, { spacing: 4 },
						'Free' === data.plan && el( RadioControl, {
							label: 'Plan card',
							selected: props.plancard,
							options: PLANCARD_VARIANTS,
							onChange: props.onPlancard,
						} ),
						el( RadioControl, {
							label: 'My site layout',
							selected: props.mysite,
							options: MYSITE_VARIANTS,
							onChange: props.onMysite,
						} ),
						ToggleGroup && el( ToggleGroup, {
							label: 'Menu variant',
							value: data.variant,
							isBlock: true,
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) { go( 'untangling_variant', value ); },
						},
							el( ToggleGroupOption, { value: 'submenu', label: 'With submenu' } ),
							el( ToggleGroupOption, { value: 'plain', label: 'Plain' } )
						),
						ToggleGroup && el( ToggleGroup, {
							label: 'Site type',
							value: data.siteType,
							isBlock: true,
							__nextHasNoMarginBottom: true,
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
							help: 'Fullscreen: themes + plugins in the chromeless Marketplace. Split: plugins keep the Add Plugins tab. Tabs: Marketplace tabs in Add Plugins and Add Themes, plans-upsell banners, no Theme Showcase entry.',
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
							help: 'Marketplace tabs: "Included with my plan" links vs a tier dropdown.',
							onChange: function ( value ) { go( 'untangling_plan_filter', value ); },
						},
							el( ToggleGroupOption, { value: 'included', label: 'Included' } ),
							el( ToggleGroupOption, { value: 'dropdown', label: 'Dropdown' } )
						),
						data.planOverride && el( Button, {
							variant: 'link',
							isDestructive: true,
							onClick: function () { go( 'untangling_reset_demo', '1' ); },
						}, 'Reset demo state (plan: ' + data.plan + ')' ),
						el( Button, { variant: 'link', onClick: copyLink }, copied ? 'Copied ✓' : 'Copy link to this view' )
					)
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

		return el( 'div', null,
			el( Header ),
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
					'my-site' === tab.name && 'onecol' !== mysite && el( 'div', { className: 'untangling-grid' },
						el( 'div', { className: 'untangling-col' },
							'momentum' === mysite && el( MomentumView ),
							( 'growth' === mysite || 'cards' === mysite ) && el( GrowthView ),
							'earn' === mysite && el( EarnView ),
							'growth' !== mysite && 'cards' !== mysite && el( HelpCard )
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
			el( ProtoPanel, { plancard: plancard, onPlancard: choosePlancard, mysite: mysite, onMysite: chooseMysite } )
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
/* Full-width header, Jetpack AI Hub pattern: icon + title, subtitle. The DS
   TabPanel tablist below shares the white band and carries the bottom rule —
   tab typography/hover/focus stay stock DS. */
.untangling-app .untangling-header { background: var(--wpds-color-background-surface-neutral-strong, #fff); padding: var(--wpds-dimension-gap-2xl) var(--wpds-dimension-gap-2xl) 0; }
.untangling-app .untangling-header-brand { display: flex; align-items: center; gap: var(--wpds-dimension-gap-xs); }
/* The W mark's artwork is inset 2/24 units inside its viewBox; the negative
   margin puts the visible circle — not the transparent box — on the content
   edge, and the same inset on the right is why the flex gap stays at xs. */
.untangling-app .untangling-header-icon svg { display: block; fill: var(--wpds-color-stroke-surface-brand-strong); margin-left: calc(-2 * 28px / 24); }
/* Page h1 sits one type step above the card titles (lg) so the hierarchy reads. */
.untangling-app .untangling-title { font-size: var(--wpds-typography-font-size-xl); line-height: var(--wpds-typography-line-height-xl); font-weight: var(--wpds-typography-font-weight-emphasis); margin: 0; padding: 0; }
.untangling-app .untangling-sub { color: var(--wpds-color-foreground-content-neutral-weak); margin: var(--wpds-dimension-gap-sm) 0 0; }
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
.untangling-app .untangling-stat-value { font-size: var(--wpds-typography-font-size-xl); font-weight: var(--wpds-typography-font-weight-emphasis); }
.untangling-app .untangling-stat-line { font-size: var(--wpds-typography-font-size-md); font-weight: var(--wpds-typography-font-weight-emphasis); margin-bottom: 2px; }
.untangling-app .untangling-email-upsell { display: grid; justify-items: center; text-align: center; gap: var(--wpds-dimension-gap-xs); padding: var(--wpds-dimension-gap-md) 0; }
.untangling-app .untangling-email-upsell .untangling-meta-text { max-width: 280px; margin-bottom: var(--wpds-dimension-gap-sm); }
.untangling-app .untangling-caution { color: var(--wpds-color-foreground-content-caution-weak); font-size: var(--wpds-typography-font-size-sm); margin: var(--wpds-dimension-gap-xs) 0 0; }
/* Local stand-in for the @wordpress/ui Badge (core doesn't bundle
   @wordpress/ui yet) — styled to the DS Badge 'none' intent: sentence case,
   default weight, sm type on the neutral-weak surface. */
.untangling-app .untangling-fallback-badge { display: inline-block; background: var(--wpds-color-background-surface-neutral-weak); border-radius: var(--wpds-border-radius-sm); padding: 0 var(--wpds-dimension-padding-sm); font-size: var(--wpds-typography-font-size-sm); line-height: var(--wpds-typography-line-height-sm); font-weight: var(--wpds-typography-font-weight-default); color: var(--wpds-color-foreground-content-neutral); }
.untangling-app .untangling-feature-list { margin: var(--wpds-dimension-gap-sm) 0 0; padding: 0; list-style: none; display: grid; gap: var(--wpds-dimension-gap-xs); font-size: var(--wpds-typography-font-size-md); }
.untangling-app .untangling-storage-track { margin-bottom: var(--wpds-dimension-gap-sm); }
.untangling-app .untangling-progress { max-width: none; width: 100%; }
.untangling-app .untangling-progress-fallback { background: var(--wpds-color-background-surface-neutral-weak); border-radius: var(--wpds-border-radius-sm); height: 8px; overflow: hidden; margin-bottom: var(--wpds-dimension-gap-sm); }
.untangling-app .untangling-progress-fallback span { display: block; height: 100%; background: var(--wpds-color-foreground-content-caution-weak); }
.untangling-app .untangling-grow-list { display: grid; gap: var(--wpds-dimension-gap-md); }
.untangling-app .untangling-spark { display: block; width: 100%; height: 48px; margin-bottom: var(--wpds-dimension-gap-md); color: var(--wpds-color-stroke-surface-brand-strong, #3858e9); }
.untangling-app .untangling-idea-lede { margin-top: var(--wpds-dimension-gap-md); }
.untangling-app .untangling-chip-row { display: flex; flex-wrap: wrap; gap: var(--wpds-dimension-gap-xs); margin-top: var(--wpds-dimension-gap-xs); }
/* One-column variant: AI Hub-style section titles + quick-start link cards */
.untangling-app .untangling-section-title { font-size: var(--wpds-typography-font-size-lg); font-weight: var(--wpds-typography-font-weight-emphasis); margin: var(--wpds-dimension-gap-md) 0 calc(-1 * var(--wpds-dimension-gap-sm)); }
.untangling-app .untangling-quick-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--wpds-dimension-gap-md); }
@media ( max-width: 600px ) { .untangling-app .untangling-quick-grid { grid-template-columns: 1fr; } }
.untangling-app .untangling-quick-card { display: flex; align-items: center; justify-content: space-between; gap: var(--wpds-dimension-gap-sm); background: var(--wpds-color-background-surface-neutral-strong, #fff); border: 1px solid var(--wpds-color-stroke-surface-neutral); border-radius: var(--wpds-border-radius-md); padding: var(--wpds-dimension-padding-lg); text-decoration: none; color: inherit; }
.untangling-app .untangling-quick-card:hover { border-color: var(--wpds-color-stroke-surface-brand-strong, #3858e9); }
.untangling-app .untangling-quick-card .untangling-meta-text { margin: 2px 0 0; }
.untangling-app .untangling-quick-chevron { color: var(--wpds-color-foreground-content-neutral-weak); font-size: var(--wpds-typography-font-size-xl); line-height: 1; }
.untangling-app .untangling-narrow { max-width: var(--wpds-dimension-surface-width-xl); margin-inline: auto; margin-top: var(--wpds-dimension-gap-2xl); display: grid; gap: var(--wpds-dimension-gap-lg); }
/* Learn tab: full-width learning hub. Media cards share one shell for
   videos, courses, and guide topics; radius hardcoded like the accordion
   (the vendored token cascade leaves radius-* at pill values). */
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
.untangling-app .untangling-ovcard-heading { display: block; font-size: var(--wpds-typography-font-size-xl); font-weight: var(--wpds-typography-font-weight-emphasis); margin-bottom: var(--wpds-dimension-gap-xs); }
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
.untangling-app .untangling-launchpad-mark { display: inline-flex; align-items: center; justify-content: center; width: var(--wpds-dimension-size-xs); height: var(--wpds-dimension-size-xs); margin-right: var(--wpds-dimension-gap-sm); border: 1px solid var(--wpds-color-stroke-surface-neutral-strong); border-radius: 50%; font-size: var(--wpds-typography-font-size-sm); vertical-align: middle; }
.untangling-app li.is-done .untangling-launchpad-mark { background: var(--wpds-color-background-interactive-brand-strong); border-color: var(--wpds-color-background-interactive-brand-strong); color: var(--wpds-color-foreground-interactive-brand-strong); }
.untangling-app li.is-done .untangling-launchpad-label { color: var(--wpds-color-foreground-content-neutral-weak); }
.untangling-app .untangling-ask-box { margin-top: var(--wpds-dimension-gap-md); }
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
/* The bundled ToggleGroupControl draws its active-segment fill with an
   animated backdrop that never mounts in this environment, so paint the DS
   pressed state from the data-active-item attribute with the wpds tokens. */
.untangling-app .untangling-proto-panel .components-toggle-group-control-option-base { border-radius: var(--wpds-border-radius-xs); }
.untangling-app .untangling-proto-panel .components-toggle-group-control-option-base[data-active-item] { background: var(--wpds-color-background-interactive-neutral-strong, #2d2d2d); color: var(--wpds-color-foreground-interactive-neutral-strong, #f0f0f0); }
/* Plan-card variants (switched from the prototype panel) */
/* Hardcoded surface: the vendored token cascade leaves --wpds-border-radius-*
   at pill values (md = 22px), which balloons this block — 6px is the design. */
.untangling-app .untangling-plan-upsell { margin-top: var(--wpds-dimension-gap-md); background: #f6f7ff; border: 1px solid #dfe5fc; border-radius: 6px; padding: var(--wpds-dimension-padding-lg); }
.untangling-app .untangling-plan-upsell-name { font-size: var(--wpds-typography-font-size-md); font-weight: var(--wpds-typography-font-weight-emphasis); }
.untangling-app .untangling-plan-upsell-price { font-size: var(--wpds-typography-font-size-sm); color: var(--wpds-color-foreground-content-neutral-weak); font-variant-numeric: tabular-nums; }
.untangling-app .untangling-plan-upsell-cta { margin-top: var(--wpds-dimension-gap-md); }
.untangling-app .untangling-feature-tip { position: relative; cursor: default; }
/* Bubble matches wordpress.com/pricing: dark, wrapped at ~240px, 8px radius.
   It centers above the cursor — a mousemove listener feeds --untangling-tip-x. */
.untangling-app .untangling-feature-tip::after { content: attr(data-tip); position: absolute; bottom: calc( 100% + 8px ); left: var(--untangling-tip-x, 50%); transform: translateX( -50% ); width: 240px; background: #101517; color: #fff; font-size: var(--wpds-typography-font-size-md); line-height: var(--wpds-typography-line-height-sm); padding: var(--wpds-dimension-padding-md) var(--wpds-dimension-padding-lg); border-radius: 8px; opacity: 0; pointer-events: none; transition: opacity var(--wpds-motion-duration-sm) var(--wpds-motion-easing-subtle); z-index: 10; }
.untangling-app .untangling-feature-tip:hover::after,
.untangling-app .untangling-feature-tip:focus-visible::after { opacity: 1; }
@media ( prefers-reduced-motion: reduce ) { .untangling-app .untangling-feature-tip::after { transition: none; } }
.untangling-app .untangling-plan-rows { display: grid; }
.untangling-app .untangling-plan-row { display: flex; align-items: center; justify-content: space-between; gap: var(--wpds-dimension-gap-md); padding: var(--wpds-dimension-gap-sm) 0; border-bottom: 1px solid var(--wpds-color-stroke-surface-neutral-weak); }
.untangling-app .untangling-plan-row:last-child { border-bottom: 0; }
.untangling-app .untangling-plan-row-label span { font-size: var(--wpds-typography-font-size-md); }
.untangling-app .untangling-plan-row-label small { display: block; font-size: var(--wpds-typography-font-size-sm); color: var(--wpds-color-foreground-content-neutral-weak); }
.untangling-app .untangling-plan-chip { flex: none; font-size: var(--wpds-typography-font-size-xs); line-height: var(--wpds-typography-line-height-xs); color: var(--wpds-color-foreground-interactive-brand); background: var(--wpds-color-background-surface-brand); border-radius: 999px; padding: 2px var(--wpds-dimension-padding-sm); white-space: nowrap; max-width: 180px; overflow: hidden; text-overflow: ellipsis; }
/* Storage row: the track is the plan's own allowance; amber = used. */
.untangling-app .untangling-plan-row-storage { border-bottom: 0; padding-bottom: 0; }
.untangling-app .untangling-storage-compare { padding: var(--wpds-dimension-gap-xs) 0 var(--wpds-dimension-gap-sm); border-bottom: 1px solid var(--wpds-color-stroke-surface-neutral-weak); }
.untangling-app .untangling-storage-compare-track { position: relative; height: 8px; border-radius: var(--wpds-border-radius-sm); background: var(--wpds-color-background-surface-neutral-weak); overflow: hidden; }
.untangling-app .untangling-storage-compare-used { position: absolute; inset: 0 auto 0 0; background: var(--wpds-color-foreground-content-caution-weak); }
/* Compare variant: Free vs Premium, mirrored rows */
.untangling-app .untangling-plan-compare { display: grid; grid-template-columns: 1fr 1fr; }
.untangling-app .untangling-plan-compare-col { min-width: 0; padding-right: var(--wpds-dimension-padding-md); }
.untangling-app .untangling-plan-compare-col + .untangling-plan-compare-col { border-left: 1px solid var(--wpds-color-stroke-surface-neutral-weak); padding-left: var(--wpds-dimension-padding-md); padding-right: 0; }
.untangling-app .untangling-plan-compare-name { display: flex; align-items: center; gap: var(--wpds-dimension-gap-sm); font-size: var(--wpds-typography-font-size-md); font-weight: var(--wpds-typography-font-weight-emphasis); margin-bottom: var(--wpds-dimension-gap-md); }
.untangling-app .untangling-plan-compare-list { list-style: none; margin: 0; padding: 0; display: grid; gap: var(--wpds-dimension-gap-sm); font-size: var(--wpds-typography-font-size-md); }
.untangling-app .untangling-plan-compare-list.is-muted { color: var(--wpds-color-foreground-content-neutral-weak); }
.untangling-app .untangling-plan-chip.is-neutral { color: var(--wpds-color-foreground-content-neutral); background: var(--wpds-color-background-surface-neutral-weak); }
.untangling-app .untangling-plan-chip.is-success { color: var(--wpds-color-foreground-content-success-weak); background: var(--wpds-color-background-surface-success-weak); }
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
			echo '<li><a class="button button-primary untangling-upgrade" href="' . esc_url( UNTANGLING_MSD_URL . '/plans' ) . '">' . esc_html__( 'Upgrade and Activate' ) . '</a></li>';
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
add_filter( 'plugin_install_action_links', function ( $links ) {
	if ( ! untangling_is_simple() ) {
		return $links;
	}
	// Keep core's More Details link so the card matches the stock layout.
	$details = array_values( array_filter( $links, function ( $link ) {
		return false !== strpos( $link, 'open-plugin-details-modal' );
	} ) );
	return array_merge(
		array( '<a class="button button-primary untangling-upgrade" href="' . esc_url( UNTANGLING_MSD_URL . '/plans' ) . '">' . esc_html__( 'Upgrade to install' ) . '</a>' ),
		$details
	);
}, 20 );

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
		<a class="button button-primary untangling-upgrade" href="<?php echo esc_url( UNTANGLING_MSD_URL . '/plans' ); ?>"><?php esc_html_e( 'Upgrade' ); ?></a>
	</div>
	<?php
}

// Split (V2) and Tabs (V3): the Free-plan upsell renders as a WP.com showcase hero —
// same visual language as the wpcom themes/plugins banners (Recoleta
// heading, logo lockup, rounded dark panel, plugin-tile artwork) instead
// of an admin notice. Same copy and CTA behavior (.untangling-upgrade
// opens the upgrade overlay).
function untangling_plugins_upsell_hero() {
	static $printed = false;
	$assets = 'https://cdn.jsdelivr.net/gh/Automattic/jetpack@trunk/projects/packages/jetpack-mu-wpcom/src/features/wpcom-plugins/images';
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
		.untangling-upsell-hero {
			/* White Recoleta on dark renders heavier than the Themes banner's
			   dark-on-light without antialiasing. */
			-webkit-font-smoothing: antialiased;
			-moz-osx-font-smoothing: grayscale;
			background-color: #242424;
			padding: 48px 32px;
			border-radius: 10px;
			margin: 20px 0 24px;
			background-image: url(<?php echo esc_url( $assets . '/banner-background.webp' ); ?>);
			background-repeat: no-repeat;
			background-position: bottom 12px right 64px;
			background-size: 430px;
		}
		.untangling-upsell-hero__content { width: 540px; }
		.untangling-upsell-hero__content img { height: 21px; width: auto; display: block; }
		#wpcontent .untangling-upsell-hero h3,
		#wpcontent .untangling-upsell-hero p { font-weight: 400; letter-spacing: -0.32px; margin: 10px 0; text-wrap: pretty; }
		.untangling-upsell-hero h3 { font-family: Recoleta, serif; font-size: 32px; line-height: 40px; color: #fff; }
		.untangling-upsell-hero p { font-size: 16px; line-height: 24px; color: #a7aaad; }
		.untangling-upsell-hero a,
		.untangling-upsell-hero a:visited { background-color: #3858e9; color: #fff; border-radius: 4px; padding: 10px 24px; font-size: 14px; line-height: 20px; letter-spacing: 0.32px; text-decoration: none; display: inline-block; margin-top: 24px; }
		.untangling-upsell-hero a:hover,
		.untangling-upsell-hero a:focus { background-color: #fff; color: #1d2327; }
		@media ( max-width: 1260px ) {
			.untangling-upsell-hero { padding: 32px; background-size: 360px; }
			.untangling-upsell-hero a { padding: 10px 20px; margin-top: 12px; }
		}
		@media ( max-width: 1120px ) {
			.untangling-upsell-hero { background-position: bottom right 5px; background-size: 300px; }
		}
		@media ( max-width: 850px ) {
			.untangling-upsell-hero { background-image: none; }
			.untangling-upsell-hero__content { width: auto; }
		}
		@media ( max-width: 782px ) {
			.untangling-upsell-hero { padding: 24px; }
			#wpcontent .untangling-upsell-hero h3,
			#wpcontent .untangling-upsell-hero p { margin: 8px 0; }
			.untangling-upsell-hero h3 { font-size: 24px; line-height: 32px; }
			.untangling-upsell-hero p { font-size: 14px; line-height: 20px; }
		}
		</style>
		<?php
	}
	?>
	<div class="untangling-upsell-hero">
		<div class="untangling-upsell-hero__content">
			<img src="<?php echo esc_url( $assets . '/wpcom-logo.svg' ); ?>" alt="WordPress.com">
			<h3><?php esc_html_e( 'Upgrade your plan to access thousands of plugins' ); ?></h3>
			<p><?php esc_html_e( 'A free domain for the first year is included with any annual plan.' ); ?></p>
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
	$assets  = 'https://cdn.jsdelivr.net/gh/Automattic/jetpack@trunk/projects/packages/jetpack-mu-wpcom/src/features/wpcom-themes/images';
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
	$heading   = $is_tabs ? __( 'Upgrade your plan to access thousands of themes' ) : __( 'Beautiful themes for every idea' );
	$blurb     = $is_tabs ? __( 'A free domain for the first year is included with any annual plan.' ) : __( 'Dive deep into the world of WordPress.com themes. Discover the responsive and stunning designs waiting to bring your site to life.' );
	$cta_label = $is_tabs ? __( 'See all plans' ) : __( 'Explore themes' );
	$cta_url   = $is_tabs
		? untangling_marketplace_url( 'themes', array( 'ustep' => 'pricing', 'ref' => 'themes-upsell-banner', 'back' => rawurlencode( $_SERVER['REQUEST_URI'] ) ) )
		: untangling_marketplace_url( 'themes', array( 'ref' => 'wpcom-themes-banner' ) );
	?>
	<style>
	@font-face {
		font-display: swap;
		font-family: Recoleta;
		font-weight: 400;
		src: url(https://s1.wp.com/i/fonts/recoleta/400.woff2) format("woff2"), url(https://s1.wp.com/i/fonts/recoleta/400.woff) format("woff");
	}
	.wpcom-themes-banner {
		background-color: #dbe0f9;
		/* Vertical metrics mirror .untangling-upsell-hero (the dark plugins
		   hero) so the two Marketplace banners come out the same height. */
		padding: 48px 32px;
		border-radius: 10px;
		margin-bottom: 25px;
		background-image: url(<?php echo esc_url( $assets . '/banner-background.webp' ); ?>);
		background-repeat: no-repeat;
		background-position: center right 10px;
		background-size: 530px;
	}
	.wpcom-themes-banner.hidden { display: none; }
	.wpcom-themes-banner__content { width: 490px; }
	.wpcom-themes-banner__content img { height: 21px; width: auto; display: block; }
	/* themes.php: the installed-themes search moves below the banner (see
	   the script), so the banner tops both this page and the Plugins page
	   at the same spot under the page title. */
	.themes-php .wpcom-themes-banner { margin-top: 25px; }
	.themes-php .search-form.search-themes { float: right; margin: 0 0 16px; }
	.themes-php .theme-browser { clear: both; }
	.wpcom-themes-banner h3,
	.wpcom-themes-banner p { font-weight: 400; letter-spacing: -0.32px; margin: 10px 0; text-wrap: pretty; }
	.wpcom-themes-banner h3 { font-family: Recoleta, serif; font-size: 32px; line-height: 40px; color: #101517; }
	.wpcom-themes-banner p { font-size: 16px; line-height: 24px; color: #2c3338; }
	.wpcom-themes-banner a,
	.wpcom-themes-banner a:visited { background-color: #101517; color: #fff; border-radius: 4px; padding: 10px 24px; font-size: 14px; line-height: 20px; letter-spacing: 0.32px; text-decoration: none; display: inline-block; margin-top: 24px; }
	.wpcom-themes-banner a:hover,
	.wpcom-themes-banner a:focus { background-color: #1d2327; color: #fff; }
	@media ( max-width: 1260px ) {
		.wpcom-themes-banner { padding: 32px; background-size: 400px; }
		.wpcom-themes-banner a { padding: 10px 20px; margin-top: 12px; }
	}
	@media ( max-width: 1120px ) {
		.wpcom-themes-banner { background-position: center right -150px; }
	}
	@media ( max-width: 850px ) {
		.wpcom-themes-banner { background-image: none; }
		.wpcom-themes-banner__content { width: auto; }
	}
	@media ( max-width: 782px ) {
		.wpcom-themes-banner { padding: 24px; }
		.wpcom-themes-banner h3,
		.wpcom-themes-banner p { margin: 8px 0; }
		.wpcom-themes-banner h3 { font-size: 24px; line-height: 32px; }
		.wpcom-themes-banner p { font-size: 14px; line-height: 20px; }
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
			'<div class="wpcom-themes-banner">' +
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
	#untangling-theme-marketplace .theme-browser .theme { float: none; width: auto; margin: 0 0 4%; }
	@media ( max-width: 1120px ) { #untangling-theme-marketplace .untangling-tab-themes { grid-template-columns: repeat( 2, minmax( 0, 1fr ) ); } }
	@media ( max-width: 480px ) { #untangling-theme-marketplace .untangling-tab-themes { grid-template-columns: 1fr; } }
	#untangling-theme-marketplace .untangling-filter-row { margin: 8px 0 24px; }
	#untangling-theme-marketplace .theme { cursor: pointer; }
	/* Plan-tier signal, like the tier pills on wordpress.com/themes. */
	#untangling-theme-marketplace .untangling-tab-badge { opacity: 0; position: absolute; top: 8px; inset-inline-end: 8px; z-index: 2; background: #fff; color: #1d2327; border-radius: 3px; padding: 4px 8px; font-size: 12px; line-height: 1.2; box-shadow: 0 1px 3px rgba(0,0,0,0.25); transition: opacity 0.1s ease-in-out; }
	#untangling-theme-marketplace .theme:hover .untangling-tab-badge,
	#untangling-theme-marketplace .theme:focus-within .untangling-tab-badge { opacity: 1; }
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
	#untangling-theme-overlay .next-theme:focus { background: #dcdcde; border-color: #c3c4c7; color: #000; outline: none; box-shadow: none; }
	#untangling-theme-overlay .close-full-overlay:before { font: normal 22px/1 dashicons; content: "\f335"; position: relative; top: 2px; }
	#untangling-theme-overlay .previous-theme:before { font: normal 20px/1 dashicons; content: "\f341"; position: relative; top: 2px; }
	#untangling-theme-overlay .next-theme:before { font: normal 20px/1 dashicons; content: "\f345"; position: relative; top: 2px; }
	#untangling-theme-overlay .previous-theme.disabled,
	#untangling-theme-overlay .next-theme.disabled { color: #c3c4c7; background: #f0f0f1; cursor: default; pointer-events: none; }
	#untangling-theme-overlay .install-theme-info { display: block; padding: 10px 20px 60px; }
	#untangling-theme-overlay .wp-full-overlay-header .theme-install { float: right; margin: 8px 10px 0 0; }
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
									<a class="button button-primary button-compact" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $included ? __( 'Install' ) : __( 'Upgrade' ) ); ?></a>
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
				ov.cta.textContent = d.cta;
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
 *     themes open the same fullscreen page restricted to themes.
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
	$assets = 'https://cdn.jsdelivr.net/gh/Automattic/jetpack@trunk/projects/packages/jetpack-mu-wpcom/src/features/wpcom-plugins/images';
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
function untangling_plan_pricing() {
	$domain_tip  = sprintf( __( 'Get a custom domain – like %s – free for the first year.' ), untangling_get_domain_upsell() );
	$storage_tip = __( 'Upload more images, videos, audio, and documents to your website.' );

	// Every plan leads with the same aligned rows — domain, storage,
	// support, then the tier-defining themes and plugins rows (bold via the
	// third entry) — so the columns line up on the pricing grid. Tier
	// extras stack below in a stable cumulative order.
	// Three variations: entering from the plugins upsell hero bolds the
	// plugins rows, entering from the themes upsell banner bolds the theme
	// rows; every other entry (the WordPress.com page) shows all features
	// in regular weight.
	$from_plugins     = isset( $_GET['ref'] ) && 'plugins-upsell-hero' === $_GET['ref'];
	$from_themes      = isset( $_GET['ref'] ) && 'themes-upsell-banner' === $_GET['ref'];
	$domain           = array( __( 'Free domain for one year' ), $domain_tip );
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
	$business_tail = array_merge( $premium_tail, array(
		array( __( 'Install plugins and themes' ), __( 'Install any of the thousands of WordPress plugins and themes.' ) ),
		array( __( 'SFTP/SSH and database access' ), __( 'Developer access to your site’s files and database.' ) ),
		array( __( 'Real-time backups and one-click restores' ), __( 'Every change saved; restore any moment with one click.' ) ),
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
	$plan = untangling_get_plan();
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
	$plan_only = in_array( $step, array( 'pricing', 'checkout', 'done' ), true ) && empty( $_GET['slug'] );
	if ( $plan_only ) {
		$exit = admin_url( 'admin.php?page=untangling-hosting' );
		if ( ! empty( $_GET['back'] ) ) {
			$back = wp_validate_redirect( rawurldecode( wp_unslash( $_GET['back'] ) ), '' );
			if ( $back ) {
				$exit = $back;
			}
		}
	}
	$mark = '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM3.5 12c0-1.23.26-2.4.73-3.46L8.25 19.6C5.44 18.23 3.5 15.34 3.5 12zm8.5 8.5c-.83 0-1.64-.12-2.4-.35l2.55-7.4 2.61 7.15c.02.04.04.08.06.12-.9.31-1.85.48-2.82.48zm1.17-12.49c.51-.03.97-.08.97-.08.46-.05.4-.73-.05-.7 0 0-1.38.11-2.27.11-.84 0-2.24-.11-2.24-.11-.46-.03-.51.68-.06.7 0 0 .43.05.89.08l1.32 3.62-1.86 5.57-3.09-9.19c.51-.03.97-.08.97-.08.46-.05.4-.73-.05-.7 0 0-1.38.11-2.27.11-.16 0-.35 0-.55-.01C6.42 5.09 9.04 3.5 12 3.5c2.21 0 4.22.84 5.73 2.23-.04 0-.07-.01-.11-.01-.84 0-1.43.73-1.43 1.51 0 .7.4 1.29.84 1.99.33.57.71 1.3.71 2.35 0 .73-.28 1.58-.65 2.76l-.85 2.84-3.07-9.16zm3.1 11.36l2.6-7.51c.49-1.21.65-2.19.65-3.05 0-.31-.02-.6-.06-.87.66 1.21 1.04 2.6 1.04 4.06 0 3.13-1.7 5.86-4.23 7.37z"/></svg>';
	// V3 (tabs) has no Marketplace destination — this shell only serves the
	// details/pricing/checkout steps, so the brand drops the label and the
	// logo returns to the in-admin Marketplace tab.
	$tabs_mode = 'tabs' === $mode;
	$tab_home  = 'plugins' === $mkt
		? admin_url( 'plugin-install.php?tab=wpcom_marketplace' )
		: admin_url( 'theme-install.php?untangling_browse=marketplace' );
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
	( function () {
		var KEY = 'untanglingMktReturn';
		var ref = document.referrer;
		if ( ref && ref.indexOf( 'untangling-marketplace' ) === -1 ) {
			try {
				if ( new URL( ref ).host === window.location.host ) {
					window.sessionStorage.setItem( KEY, ref );
				}
			} catch ( e ) {}
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

function untangling_marketplace_browse( $mkt, $mode, $plan ) {
	$rank = untangling_plan_rank( $plan );

	/* ---- Themes catalog ---- */
	$active_slug = get_option( 'untangling_mkt_active_theme', '' );
	$in_catalog  = (bool) untangling_marketplace_find_item( 'theme', $active_slug );
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
			<?php
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
					? add_query_arg( 'untangling_activate_theme', $slug, untangling_marketplace_url( 'themes' ) )
					: untangling_marketplace_url( 'themes', array( 'ustep' => 'pricing', 'type' => 'theme', 'slug' => $slug ) );
				?>
				<article class="untangling-mkt-theme-card<?php echo $is_active ? ' is-current' : ''; ?>" data-name="<?php echo esc_attr( strtolower( $name . ' ' . $slug ) ); ?>" data-subject="<?php echo esc_attr( $subject ); ?>" data-recommended="<?php echo $recommended ? '1' : ''; ?>" data-mine="<?php echo $is_active ? '1' : ''; ?>" data-tier="<?php echo esc_attr( $tier ); ?>">
					<?php $details_url = untangling_marketplace_url( 'themes', array( 'ustep' => 'details', 'slug' => $slug ) ); ?>
					<div class="untangling-mkt-shot">
						<img src="<?php echo esc_url( $shot ); ?>" alt="" decoding="async">
						<div class="untangling-mkt-shot-overlay">
							<?php if ( ! $is_active ) : ?>
								<a class="untangling-mkt-shot-cta" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $included ? __( 'Activate' ) : __( 'Unlock this theme' ) ); ?></a>
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
			?>
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
function untangling_marketplace_details_step( $plan ) {
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

	if ( 'free' === $tier ) {
		$pill = __( 'Free theme' );
	} elseif ( $price ) {
		$pill = sprintf( __( 'US$%1$s/month, or included in %2$s' ), $price, $tier_plan );
	} else {
		$pill = sprintf( __( 'Available on %s' ), $tier_plan );
	}
	$cta_url = $included
		? add_query_arg( 'untangling_activate_theme', $slug, untangling_marketplace_url( 'themes' ) )
		: untangling_marketplace_url( 'themes', array( 'ustep' => 'pricing', 'type' => 'theme', 'slug' => $slug ) );
	?>
	<nav class="untangling-mkt-crumbs">
		<a href="<?php echo esc_url( untangling_marketplace_url( 'themes' ) ); ?>"><?php esc_html_e( 'Themes' ); ?></a>
		<span aria-hidden="true">›</span>
		<span class="is-current"><?php echo esc_html( sprintf( __( '%s Theme' ), $name ) ); ?></span>
	</nav>
	<div class="untangling-mkt-detail">
		<div class="untangling-mkt-detail-info">
			<span class="untangling-mkt-detail-tierpill">★ <?php echo esc_html( $pill ); ?></span>
			<div class="untangling-mkt-detail-head">
				<div>
					<h1 class="untangling-mkt-brandfont"><?php echo esc_html( $name ); ?></h1>
					<p class="untangling-mkt-detail-by"><?php echo esc_html( sprintf( __( 'by %s' ), $author ) ); ?></p>
				</div>
				<div class="untangling-mkt-detail-actions">
					<a class="untangling-mkt-button is-secondary" href="<?php echo esc_url( $demo ); ?>" target="_blank" rel="noreferrer"><?php esc_html_e( 'Preview' ); ?></a>
					<?php if ( $is_active ) : ?>
						<span class="untangling-mkt-button is-disabled">✓ <?php esc_html_e( 'Active' ); ?></span>
					<?php else : ?>
						<a class="untangling-mkt-button is-primary" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $included ? __( 'Activate' ) : __( 'Unlock this theme' ) ); ?></a>
					<?php endif; ?>
				</div>
			</div>
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
				<a class="untangling-mkt-button is-primary is-block" href="<?php echo esc_url( $cta_url ); ?>"><?php echo $price ? esc_html__( 'Purchase and activate' ) : esc_html__( 'Upgrade and activate' ); ?></a>
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

function untangling_marketplace_pricing_step( $plan, $type ) {
	$slug = isset( $_GET['slug'] ) ? sanitize_key( $_GET['slug'] ) : '';
	$item = untangling_marketplace_find_item( $type, $slug );
	if ( ! $item && $slug ) {
		echo '<div class="untangling-mkt-hero"><h1 class="untangling-mkt-brandfont">' . esc_html__( 'Item not found' ) . '</h1></div>';
		return;
	}
	// No slug = generic plan-upgrade entry (from the WordPress.com page plan
	// card): same pricing page, Premium highlighted as Recommended.
	$tier_rank = untangling_plan_rank( $item ? $item['tier'] : 'Premium' );
	$rank      = untangling_plan_rank( $plan );
	$mkt       = 'plugin' === $type ? 'plugins' : 'themes';
	?>
	<div class="untangling-mkt-hero">
		<h1 class="untangling-mkt-brandfont"><?php esc_html_e( 'There’s a plan for you' ); ?></h1>
		<p><?php echo esc_html( $item
			? sprintf( __( 'Unlock %1$s with the %2$s plan or higher. You’re currently on the %3$s plan.' ), $item['name'], $item['tier'], $plan )
			: sprintf( __( 'More storage, premium themes, and expert support as you grow. You’re currently on the %s plan.' ), $plan )
		); ?></p>
	</div>
	<div class="untangling-mkt-plans">
		<?php foreach ( untangling_plan_pricing() as $name => $info ) : ?>
			<?php
			$prank      = untangling_plan_rank( $name );
			$is_current = $name === $plan;
			// Show only the current plan and the plans that unlock the item —
			// tiers in between (e.g. Personal for a Premium theme) are noise.
			if ( $prank < $rank || ( $item && ! $is_current && $prank < $tier_rank ) ) {
				continue;
			}
			list( $price, $features ) = $info;
			$is_required = $prank === $tier_rank;
			$checkout    = $item
				? untangling_marketplace_url( $mkt, array( 'ustep' => 'checkout', 'type' => $type, 'slug' => $slug, 'plan' => $name ) )
				: untangling_marketplace_url( $mkt, array( 'ustep' => 'checkout', 'plan' => $name ) );
			?>
			<div class="untangling-mkt-plan<?php echo $is_current ? ' is-current' : ''; ?><?php echo $is_required && ! $is_current ? ' is-required' : ''; ?>">
				<div class="untangling-mkt-plan-badges">
					<?php if ( $is_current ) : ?>
						<span class="untangling-mkt-plan-pill"><?php esc_html_e( 'Your plan' ); ?></span>
					<?php elseif ( $is_required ) : ?>
						<span class="untangling-mkt-plan-pill"><?php echo esc_html( $item ? sprintf( __( 'Unlocks %s' ), $item['name'] ) : __( 'Recommended' ) ); ?></span>
					<?php endif; ?>
				</div>
				<h2 class="untangling-mkt-brandfont"><?php echo esc_html( $name ); ?></h2>
				<p class="untangling-mkt-plan-price"><sup>US$</sup><span><?php echo esc_html( $price ); ?></span><em>/<?php esc_html_e( 'month' ); ?></em></p>
				<?php if ( $is_current ) : ?>
					<span class="untangling-mkt-button is-disabled"><?php esc_html_e( 'Your plan' ); ?></span>
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
	$pricing  = untangling_plan_pricing();
	$new_plan = ( isset( $_GET['plan'] ) && isset( $pricing[ $_GET['plan'] ] ) ) ? $_GET['plan'] : ( $item ? $item['tier'] : 'Premium' );
	$mkt      = 'plugin' === $type ? 'plugins' : 'themes';

	$plan_price = $pricing[ $new_plan ][0];
	$item_price = $item && $item['price'] ? (float) $item['price'] : 0;
	$total      = $plan_price + $item_price;
	$user       = wp_get_current_user();
	$done_args  = array( 'ustep' => 'done', 'untangling_set_plan' => $new_plan );
	if ( $item ) {
		$done_args['type'] = $type;
		$done_args['slug'] = $slug;
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
				<span class="who"><span><?php echo esc_html( sprintf( __( 'WordPress.com %s' ), $new_plan ) ); ?><small><?php esc_html_e( 'Billed monthly' ); ?></small></span></span>
				<span><?php echo esc_html( 'US$' . number_format_i18n( $plan_price, 2 ) ); ?></span>
			</div>
			<?php if ( $item ) : ?>
				<div class="untangling-mkt-sumrow">
					<span class="who">
						<?php if ( 'plugin' === $type ) : ?><img src="<?php echo esc_url( $item['image'] ); ?>" alt=""><?php endif; ?>
						<span><?php echo esc_html( $item['name'] ); ?><small><?php echo 'plugin' === $type ? esc_html__( 'Marketplace plugin' ) : esc_html__( 'Theme' ); ?></small></span>
					</span>
					<span><?php echo $item_price ? esc_html( 'US$' . number_format_i18n( $item_price, 2 ) ) : esc_html__( 'Included' ); ?></span>
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
	$slug = isset( $_GET['slug'] ) ? sanitize_key( $_GET['slug'] ) : '';
	$item = untangling_marketplace_find_item( $type, $slug );
	$plan = untangling_get_plan(); // Already overridden by untangling_set_plan on this request.
	$mkt  = 'plugin' === $type ? 'plugins' : 'themes';
	?>
	<div class="untangling-mkt-done">
		<span class="untangling-mkt-done-check">
			<svg viewBox="0 0 24 24" width="32" height="32" aria-hidden="true"><path fill="currentColor" d="M9 16.2l-3.5-3.5L4 14.2 9 19 20 8l-1.5-1.5z"/></svg>
		</span>
		<h1 class="untangling-mkt-brandfont"><?php esc_html_e( 'You’re all set!' ); ?></h1>
		<p>
			<?php echo esc_html( sprintf( __( 'The %1$s plan is now active on %2$s.' ), $plan, get_bloginfo( 'name' ) ) ); ?>
			<?php if ( $item ) : ?>
				<?php echo esc_html( sprintf( 'theme' === $type ? __( '%s is now included in your plan — head back to the Marketplace to activate it.' ) : __( '%s is now included in your plan — head back to the Marketplace to install it.' ), $item['name'] ) ); ?>
			<?php endif; ?>
		</p>
		<div class="untangling-mkt-done-actions">
			<?php if ( $item ) : ?>
				<a class="untangling-mkt-button is-primary" href="<?php echo esc_url( untangling_marketplace_url( $mkt ) ); ?>"><?php esc_html_e( 'Back to Marketplace' ); ?></a>
				<a class="untangling-mkt-button is-secondary" href="<?php echo esc_url( admin_url() ); ?>"><?php esc_html_e( 'Go to WP Admin' ); ?></a>
			<?php else : ?>
				<a class="untangling-mkt-button is-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=untangling-hosting' ) ); ?>"><?php esc_html_e( 'Back to WordPress.com' ); ?></a>
				<a class="untangling-mkt-button is-secondary" href="<?php echo esc_url( admin_url() ); ?>"><?php esc_html_e( 'Go to WP Admin' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

// Help Center mimic — the Support Assistant panel (geometry from
// packages/help-center: 410×80vh, radius 16, bottom/right 50).
function untangling_marketplace_help_panel() {
	$user = wp_get_current_user();
	?>
	<div class="untangling-mkt-help" hidden>
		<header>
			<button type="button" aria-label="<?php esc_attr_e( 'Back' ); ?>"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M14.6 7l-1.2-1L8 12l5.4 6 1.2-1-4.6-5z"/></svg></button>
			<span class="title"><?php esc_html_e( 'Support Assistant' ); ?></span>
			<span class="spacer"></span>
			<button type="button" aria-label="<?php esc_attr_e( 'More options' ); ?>"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12 8a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm0 5.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm0 5.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/></svg></button>
			<button type="button" class="untangling-mkt-help-close" aria-label="<?php esc_attr_e( 'Close' ); ?>"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M13 11.8l6.1-6.3-1-1-6.1 6.2-6.1-6.2-1 1 6.1 6.3-6.5 6.7 1 1 6.5-6.6 6.5 6.6 1-1z"/></svg></button>
		</header>
		<div class="untangling-mkt-help-body">
			<svg viewBox="0 0 24 24" width="32" height="32" aria-hidden="true"><path fill="#3858e9" d="M12 2l2.2 7.8L22 12l-7.8 2.2L12 22l-2.2-7.8L2 12l7.8-2.2z"/></svg>
			<h3><?php echo esc_html( sprintf( __( 'Howdy %s' ), $user->display_name ) ); ?> 👋</h3>
			<p><?php esc_html_e( 'I’m your personal Support Assistant. I can help with any questions about your site or account.' ); ?></p>
			<div class="untangling-mkt-ask">
				<input type="text" placeholder="<?php esc_attr_e( 'Ask anything…' ); ?>">
				<button type="button" aria-label="<?php esc_attr_e( 'Send' ); ?>"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M12 4l6 6-1.4 1.4L13 7.8V20h-2V7.8l-3.6 3.6L6 10z"/></svg></button>
			</div>
			<p class="untangling-mkt-help-fine"><?php esc_html_e( 'You’re chatting with an AI assistant. Responses may be inaccurate.' ); ?> <a href="#"><?php esc_html_e( 'Learn more' ); ?> ↗</a></p>
		</div>
	</div>
	<?php
}

// Values traced from production: step-container-v2 (top bar, Recoleta
// heading scale), theme showcase + plugins marketplace (1220px content,
// pills, card grids), plans-grid-next (280px plan columns), help-center
// (410px panel). Studio grays / WP blue throughout.
function untangling_marketplace_styles() {
	?>
	<style>
	@font-face {
		font-display: swap;
		font-family: Recoleta;
		font-weight: 400;
		src: url(https://s1.wp.com/i/fonts/recoleta/400.woff2) format("woff2"), url(https://s1.wp.com/i/fonts/recoleta/400.woff) format("woff");
	}
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
	   Recoleta plan names (32px) and prices (44px), "Your plan" pill. */
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
	.untangling-mkt-plan .untangling-feature-tip::after { content: attr(data-tip); position: absolute; bottom: calc( 100% + 8px ); left: var(--untangling-tip-x, 50%); transform: translateX( -50% ); width: 220px; background: #101517; color: #fff; font-size: 13px; font-weight: 400; line-height: 1.4; padding: 8px 12px; border-radius: 8px; opacity: 0; pointer-events: none; transition: opacity 0.15s; z-index: 10; }
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

	/* Help Center mimic — 410 × 80vh (max 800), radius 16, right/bottom 50. */
	.untangling-mkt-help { position: fixed; right: 50px; bottom: 50px; width: 410px; max-width: calc( 100vw - 32px ); height: 80vh; max-height: 800px; background: #fff; border-radius: 16px; box-shadow: 0 3px 8px rgba(0,0,0,0.12), 0 12px 32px rgba(0,0,0,0.14); z-index: 999990; display: flex; flex-direction: column; overflow: hidden; }
	.untangling-mkt-help[hidden] { display: none; }
	.untangling-mkt-help header { height: 56px; display: flex; align-items: center; gap: 4px; padding: 0 12px; border-bottom: 1px solid var(--mkt-gray-0); flex-shrink: 0; }
	.untangling-mkt-help header .title { font-size: 16px; font-weight: 500; color: var(--mkt-gray-100); margin-left: 4px; }
	.untangling-mkt-help header .spacer { flex: 1; }
	.untangling-mkt-help header button { background: none; border: 0; padding: 8px; cursor: pointer; color: var(--mkt-gray-100); display: inline-flex; }
	.untangling-mkt-help-body { flex: 1; display: flex; flex-direction: column; justify-content: flex-end; padding: 24px; }
	.untangling-mkt-help-body h3 { font-size: 16px; font-weight: 600; margin: 16px 0 8px; color: var(--mkt-gray-100); }
	.untangling-mkt-help-body > p { font-size: 16px; margin: 0 0 16px; color: var(--mkt-gray-80); }
	.untangling-mkt-ask { display: flex; align-items: center; border: 1px solid var(--mkt-gray-10); border-radius: 12px; padding: 8px 8px 8px 16px; gap: 8px; }
	.untangling-mkt-ask input { border: 0; outline: 0; flex: 1; font-size: 14px; color: var(--mkt-gray-100); background: none; }
	.untangling-mkt-ask button { width: 32px; height: 32px; border-radius: 50%; border: 0; background: var(--mkt-gray-0); color: var(--mkt-gray-60); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
	.untangling-mkt-help-fine { font-size: 12px; color: var(--mkt-gray-50); text-align: center; margin: 12px 0 0; }
	.untangling-mkt-help-fine a { color: var(--mkt-gray-50); }

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

		// Help Center mimic.
		var help = document.querySelector( '.untangling-mkt-help' );
		var helpToggle = document.querySelector( '.untangling-mkt-help-toggle' );
		if ( help && helpToggle ) {
			helpToggle.addEventListener( 'click', function () {
				help.hidden = ! help.hidden;
			} );
			document.querySelectorAll( '[data-open-help]' ).forEach( function ( link ) {
				link.addEventListener( 'click', function ( event ) {
					event.preventDefault();
					help.hidden = false;
				} );
			} );
			help.querySelector( '.untangling-mkt-help-close' ).addEventListener( 'click', function () {
				help.hidden = true;
			} );
			document.addEventListener( 'keydown', function ( event ) {
				if ( 'Escape' === event.key ) {
					help.hidden = true;
				}
			} );
		}

	} )();
	</script>
	<?php
}

/* -------------------------------------------------------------------------
 * 3d. Global Prototype controls — the fab/panel shows on every wp-admin
 * screen, carrying the site-wide toggles (menu variant, site type,
 * marketplace version) plus demo reset and copy-link. The WordPress.com
 * page keeps its richer React panel (plan card + my-site layout live there),
 * so it is skipped here.
 * ---------------------------------------------------------------------- */

add_action( 'admin_footer', function () {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && 'toplevel_page_untangling-hosting' === $screen->id ) {
		return;
	}
	$is_mkt   = $screen && 'toplevel_page_untangling-marketplace' === $screen->id;
	$variant  = untangling_get_variant();
	$type     = untangling_get_site_type();
	$mode     = untangling_get_marketplace_mode();
	$plan     = untangling_get_plan();
	$override = (bool) get_option( 'untangling_plan_override' );

	$seg = function ( $label, $key, $options, $current ) {
		echo '<label>' . esc_html( $label ) . '</label><div class="untangling-gproto-seg" data-key="' . esc_attr( $key ) . '">';
		foreach ( $options as $value => $text ) {
			echo '<button type="button" data-value="' . esc_attr( $value ) . '"' . ( $value === $current ? ' class="is-active"' : '' ) . '>' . esc_html( $text ) . '</button>';
		}
		echo '</div>';
	};
	?>
	<style>
	.untangling-gproto { position: fixed; right: 16px; bottom: 16px; z-index: 999991; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
	/* The fullscreen Marketplace's help panel owns the bottom-right corner. */
	.untangling-gproto.is-mkt { right: auto; left: 16px; }
	.untangling-gproto.is-mkt .untangling-gproto-panel { right: auto; left: 0; }
	.untangling-gproto-fab { width: 44px; height: 44px; border-radius: 50%; border: 1px solid #dcdcde; background: #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.15); cursor: pointer; color: #3858e9; display: inline-flex; align-items: center; justify-content: center; padding: 0; }
	.untangling-gproto-panel { position: absolute; right: 0; bottom: 0; width: 280px; background: #fff; border: 1px solid #dcdcde; border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.15); box-sizing: border-box; }
	.untangling-gproto-panel[hidden] { display: none; }
	.untangling-gproto-head { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid #f0f0f0; }
	.untangling-gproto-head span { font-size: 11px; font-weight: 500; letter-spacing: 0.5px; text-transform: uppercase; color: #757575; }
	.untangling-gproto-min { background: none; border: 0; padding: 0 2px; cursor: pointer; color: #1e1e1e; line-height: 1; font-size: 18px; }
	.untangling-gproto-body { padding: 16px; }
	.untangling-gproto-body label { display: block; font-size: 11px; font-weight: 500; letter-spacing: 0.5px; text-transform: uppercase; color: #1e1e1e; margin: 16px 0 8px; }
	.untangling-gproto-body label:first-child { margin-top: 0; }
	.untangling-gproto-seg { display: flex; border: 1px solid #c3c4c7; border-radius: 4px; overflow: hidden; }
	.untangling-gproto-seg button { flex: 1; border: 0; background: #fff; padding: 9px 0; cursor: pointer; font-size: 13px; color: #2c3338; }
	.untangling-gproto-seg button.is-active { background: #2c3338; color: #fff; font-weight: 500; }
	.untangling-gproto-hint { font-size: 12px; line-height: 1.4; color: #757575; margin: 8px 0 0; }
	.untangling-gproto-reset { display: block; margin-top: 14px; background: none; border: 0; padding: 0; color: #b32d2e; text-decoration: underline; cursor: pointer; font-size: 13px; }
	.untangling-gproto-copy { display: block; margin-top: 14px; background: none; border: 0; padding: 0; color: #3858e9; text-decoration: underline; cursor: pointer; font-size: 13px; }
	</style>
	<div class="untangling-gproto<?php echo $is_mkt ? ' is-mkt' : ''; ?>">
		<button type="button" class="untangling-gproto-fab" aria-label="<?php esc_attr_e( 'Prototype controls' ); ?>">
			<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM3.5 12c0-1.23.26-2.4.73-3.46L8.25 19.6C5.44 18.23 3.5 15.34 3.5 12zm8.5 8.5c-.83 0-1.64-.12-2.4-.35l2.55-7.4 2.61 7.15c.02.04.04.08.06.12-.9.31-1.85.48-2.82.48zm1.17-12.49c.51-.03.97-.08.97-.08.46-.05.4-.73-.05-.7 0 0-1.38.11-2.27.11-.84 0-2.24-.11-2.24-.11-.46-.03-.51.68-.06.7 0 0 .43.05.89.08l1.32 3.62-1.86 5.57-3.09-9.19c.51-.03.97-.08.97-.08.46-.05.4-.73-.05-.7 0 0-1.38.11-2.27.11-.16 0-.35 0-.55-.01C6.42 5.09 9.04 3.5 12 3.5c2.21 0 4.22.84 5.73 2.23-.04 0-.07-.01-.11-.01-.84 0-1.43.73-1.43 1.51 0 .7.4 1.29.84 1.99.33.57.71 1.3.71 2.35 0 .73-.28 1.58-.65 2.76l-.85 2.84-3.07-9.16zm3.1 11.36l2.6-7.51c.49-1.21.65-2.19.65-3.05 0-.31-.02-.6-.06-.87.66 1.21 1.04 2.6 1.04 4.06 0 3.13-1.7 5.86-4.23 7.37z"/></svg>
		</button>
		<div class="untangling-gproto-panel" hidden>
			<div class="untangling-gproto-head">
				<span><?php esc_html_e( 'Prototype controls' ); ?></span>
				<button type="button" class="untangling-gproto-min" aria-label="<?php esc_attr_e( 'Minimize' ); ?>">–</button>
			</div>
			<div class="untangling-gproto-body">
				<?php
				$seg( __( 'Menu variant' ), 'untangling_variant', array( 'submenu' => __( 'With submenu' ), 'plain' => __( 'Plain' ) ), $variant );
				$seg( __( 'Site type' ), 'untangling_site_type', array( 'atomic' => __( 'Atomic' ), 'simple' => __( 'Simple' ) ), $type );
				$seg( __( 'Marketplace' ), 'untangling_marketplace', array( 'fullscreen' => __( 'Fullscreen' ), 'split' => __( 'Split' ), 'tabs' => __( 'Tabs' ) ), $mode );
				if ( 'tabs' === $mode ) {
					$seg( __( 'Plan filter' ), 'untangling_plan_filter', array( 'included' => __( 'Included' ), 'dropdown' => __( 'Dropdown' ) ), untangling_get_plan_filter() );
				}
				?>
				<p class="untangling-gproto-hint"><?php esc_html_e( 'Fullscreen: themes + plugins in the chromeless Marketplace. Split: plugins keep the Add Plugins tab. Tabs: Marketplace tabs in Add Plugins and Add Themes, plans-upsell banners, no Theme Showcase entry. Plan filter compares the "Included with my plan" links against a tier dropdown on both Marketplace tabs.' ); ?></p>
				<?php if ( $override ) : ?>
					<button type="button" class="untangling-gproto-reset"><?php echo esc_html( sprintf( __( 'Reset demo state (plan override: %s)' ), $plan ) ); ?></button>
				<?php endif; ?>
				<button type="button" class="untangling-gproto-copy"><?php esc_html_e( 'Copy link to this view' ); ?></button>
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

		function toggle() {
			panel.hidden = ! panel.hidden;
			fab.style.visibility = panel.hidden ? '' : 'hidden';
		}
		fab.addEventListener( 'click', toggle );
		wrap.querySelector( '.untangling-gproto-min' ).addEventListener( 'click', toggle );

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

	$bar->add_node( array( 'id' => 'untangling-site-stats', 'parent' => 'site-name', 'title' => __( 'Stats' ), 'href' => $msd . '/stats' ) );
	$bar->add_node( array( 'id' => 'untangling-site-plan', 'parent' => 'site-name', 'title' => __( 'Plan' ) . '<span class="untangling-chip">' . esc_html( untangling_get_plan() ) . '</span>', 'href' => $msd . '/plans' ) );

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
 * Styles
 * ---------------------------------------------------------------------- */

add_action( 'admin_enqueue_scripts', function () {
	wp_add_inline_style( 'common', '
		.untangling-lede .button { margin-left: 12px; }
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
		   card cannot stretch its whole row into white space. */
		.plugin-install-php #the-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
		.plugin-install-php .plugin-card { float: none; width: auto; margin: 0; display: flex; flex-direction: column; }
		.plugin-install-php .plugin-card-top { flex: 1; }
		@media ( min-width: 1900px ) { .plugin-install-php #the-list { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
		@media ( max-width: 800px ) { .plugin-install-php #the-list { grid-template-columns: 1fr; } }
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
