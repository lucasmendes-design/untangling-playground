<?php
/**
 * Marketplace tab for Free (and Personal) Simple sites — before and after.
 *
 * Required by untangling-prototype.php. Two modes, switched from Prototype
 * controls (?untangling_mkt_tab=production|proposal):
 *
 *   production  faithful replica of the live wp-admin Marketplace tab on a
 *               free Simple site (Nov 2026): "Upgrade and purchase" cards,
 *               yearly price with "Plus a Personal plan", a details modal with
 *               no price, the "One more step" address dialog BEFORE checkout,
 *               a Calypso-style checkout with the yearly plugin in the cart,
 *               and the Marketplace thank-you page.
 *   proposal    one explainer block instead of a paywall on every card,
 *               "Purchase" with the monthly price first, an in-admin product
 *               page with a Monthly/Annually choice, the same checkout with
 *               the monthly plugin in the cart and a hosting line, and after
 *               paying the visitor lands back where they came from with the
 *               plugin active and the address-change notice shown THEN.
 *
 * Real USD prices from the public products API (2026-11-06). The catalog here
 * is the production catalog's shape (store slug, monthly + yearly variations);
 * the paid-site tab keeps the older tier-based catalog in the main plugin.
 */

/* -------------------------------------------------------------------------
 * Gates and switches
 * ---------------------------------------------------------------------- */

// The new tab serves Free (needs a plan) and Personal (the plan a Free site
// buys here), so the post-purchase state renders with the same cards.
function untangling_mktf_enabled() {
	return in_array( untangling_get_plan(), array( 'Free', 'Personal' ), true );
}

function untangling_mktf_needs_plan() {
	return 'Free' === untangling_get_plan();
}

function untangling_get_mkt_tab() {
	$allowed = array( 'production', 'proposal' );
	if ( defined( 'UNTANGLING_FORCE_MKT_TAB' ) ) {
		return in_array( UNTANGLING_FORCE_MKT_TAB, $allowed, true ) ? UNTANGLING_FORCE_MKT_TAB : 'proposal';
	}
	if ( ! untangling_is_locked_demo() && isset( $_GET['untangling_mkt_tab'] ) && in_array( $_GET['untangling_mkt_tab'], $allowed, true ) ) {
		update_option( 'untangling_mkt_tab', $_GET['untangling_mkt_tab'] );
	}
	$mode = get_option( 'untangling_mkt_tab', 'proposal' );
	return in_array( $mode, $allowed, true ) ? $mode : 'proposal';
}

function untangling_get_mkt_default_tab() {
	$allowed = array( 'marketplace', 'featured' );
	if ( defined( 'UNTANGLING_FORCE_MKT_DEFAULT_TAB' ) ) {
		return in_array( UNTANGLING_FORCE_MKT_DEFAULT_TAB, $allowed, true ) ? UNTANGLING_FORCE_MKT_DEFAULT_TAB : 'marketplace';
	}
	if ( ! untangling_is_locked_demo() && isset( $_GET['untangling_mkt_default_tab'] ) && in_array( $_GET['untangling_mkt_default_tab'], $allowed, true ) ) {
		update_option( 'untangling_mkt_default_tab', $_GET['untangling_mkt_default_tab'] );
	}
	$tab = get_option( 'untangling_mkt_default_tab', 'marketplace' );
	return in_array( $tab, $allowed, true ) ? $tab : 'marketplace';
}

// Persist the switches early so the panel's reload lands on the new value
// whatever page it happens on.
add_action( 'admin_init', function () {
	untangling_get_mkt_tab();
	untangling_get_mkt_default_tab();
}, 5 );

/* -------------------------------------------------------------------------
 * Catalog and prices
 * ---------------------------------------------------------------------- */

function untangling_mktf_icon( $letter, $bg ) {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128"><rect width="128" height="128" rx="16" fill="' . $bg . '"/><text x="64" y="82" font-family="-apple-system,Helvetica,Arial,sans-serif" font-size="56" font-weight="700" fill="#fff" text-anchor="middle">' . $letter . '</text></svg>';
	return 'data:image/svg+xml;utf8,' . rawurlencode( $svg );
}

function untangling_mktf_banner( $from, $to ) {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1544 500"><defs><linearGradient id="g" x1="0" x2="1" y1="0" y2="1"><stop offset="0" stop-color="' . $from . '"/><stop offset="1" stop-color="' . $to . '"/></linearGradient></defs><rect width="1544" height="500" fill="url(#g)"/></svg>';
	return 'data:image/svg+xml;utf8,' . rawurlencode( $svg );
}

// slug => product. Prices are the real USD store prices (rest/v1.1/products,
// 2026-11-06). `store` is the checkout product slug stem (store_yearly /
// store_monthly). `requires` = plugins checkout installs alongside.
function untangling_mktf_products() {
	static $products = null;
	if ( null !== $products ) {
		return $products;
	}
	$products = array(
		'wordpress-seo-premium' => array(
			'name'     => 'Yoast SEO Premium',
			'store'    => 'wordpress_seo_premium',
			'author'   => 'Team Yoast',
			'desc'     => 'Yoast SEO Premium gives you unlimited AI-powered optimization, real-time SEO guidance, and tools to strengthen internal linking and site structure…',
			'icon'     => 'https://ps.w.org/wordpress-seo/assets/icon-256x256.gif',
			'banner'   => 'https://ps.w.org/wordpress-seo/assets/banner-1544x500.png',
			'monthly'  => 10.00,
			'yearly'   => 118.80,
			'updated'  => '1 week ago',
			'version'  => '28.8',
			'requires' => 'Yoast SEO – Advanced SEO with real-time guidance and built-in AI',
			'tags'     => array( 'plugins', 'marketing', 'seo' ),
		),
		'js-composer'           => array(
			'name'    => 'WPBakery Page Builder',
			'store'   => 'js-composer',
			'author'  => 'WPBakery',
			'desc'    => 'One of the leading page builders for WordPress with drag-and-drop editing, design options, and live frontend and schematic backend editors.',
			'icon'    => untangling_mktf_icon( 'W', '#0f6fc6' ),
			'banner'  => untangling_mktf_banner( '#0f6fc6', '#083f73' ),
			'monthly' => 9.90,
			'yearly'  => 82.00,
			'updated' => '4 days ago',
			'version' => '8.4',
			'tags'    => array( 'plugins', 'design', 'page builder' ),
		),
		'gravityforms'          => array(
			'name'    => 'Gravity Forms',
			'store'   => 'gravityforms',
			'author'  => 'Gravity Forms',
			'desc'    => 'Build custom forms for any project requirements with Gravity Forms, a powerful WordPress form builder for businesses and web professionals.',
			'icon'    => untangling_mktf_icon( 'G', '#f15a24' ),
			'banner'  => untangling_mktf_banner( '#f15a24', '#9e2f0d' ),
			'monthly' => 12.00,
			'yearly'  => 120.00,
			'updated' => '2 weeks ago',
			'version' => '2.9.9',
			'tags'    => array( 'plugins', 'forms' ),
		),
		'astra-pro'             => array(
			'name'    => 'Astra Pro',
			'store'   => 'astra_pro',
			'author'  => 'Brainstorm Force',
			'desc'    => 'Create stunning websites with one of the most powerful and user-friendly WordPress themes.',
			'icon'    => untangling_mktf_icon( 'A', '#5a3fd6' ),
			'banner'  => untangling_mktf_banner( '#5a3fd6', '#2b1a7a' ),
			'monthly' => 3.99,
			'yearly'  => 47.00,
			'updated' => '3 days ago',
			'version' => '4.11',
			'tags'    => array( 'plugins', 'design' ),
		),
		'woocommerce-subscriptions' => array(
			'name'    => 'WooCommerce Subscriptions',
			'store'   => 'woocommerce_subscriptions',
			'author'  => 'WooCommerce',
			'desc'    => 'Let customers subscribe to your products or services and pay on a weekly, monthly, or annual basis.',
			'icon'    => untangling_mktf_icon( 'S', '#7f54b3' ),
			'banner'  => untangling_mktf_banner( '#7f54b3', '#3c1f63' ),
			'monthly' => 24.00,
			'yearly'  => 279.00,
			'updated' => '1 week ago',
			'version' => '7.6.0',
			'tags'    => array( 'plugins', 'store', 'payments' ),
		),
		'woocommerce-bookings'  => array(
			'name'    => 'WooCommerce Bookings',
			'store'   => 'woocommerce_bookings',
			'author'  => 'WooCommerce',
			'desc'    => 'Allow customers to book appointments, make reservations, or rent equipment without leaving your site.',
			'icon'    => untangling_mktf_icon( 'B', '#7f54b3' ),
			'banner'  => untangling_mktf_banner( '#7f54b3', '#3c1f63' ),
			'monthly' => 21.00,
			'yearly'  => 224.00,
			'updated' => '2 weeks ago',
			'version' => '2.1.14',
			'tags'    => array( 'plugins', 'store' ),
		),
		'events-calendar-pro'   => array(
			'name'    => 'Events Calendar Pro',
			'store'   => 'events_calendar_pro',
			'author'  => 'The Events Calendar',
			'desc'    => 'Add recurring events, advanced calendar views, and more to The Events Calendar.',
			'icon'    => 'https://ps.w.org/the-events-calendar/assets/icon-256x256.gif',
			'banner'  => 'https://ps.w.org/the-events-calendar/assets/banner-1544x500.png',
			'monthly' => 9.00,
			'yearly'  => 99.00,
			'updated' => '5 days ago',
			'version' => '7.5',
			'tags'    => array( 'plugins', 'events' ),
		),
		'sensei-pro'            => array(
			'name'    => 'Sensei Pro',
			'store'   => 'sensei_pro',
			'author'  => 'Automattic',
			'desc'    => 'Create and sell courses, quizzes, and interactive lessons on your WordPress site.',
			'icon'    => 'https://ps.w.org/sensei-lms/assets/icon-256x256.png',
			'banner'  => 'https://ps.w.org/sensei-lms/assets/banner-1544x500.png',
			'monthly' => 9.00,
			'yearly'  => 99.00,
			'updated' => '6 days ago',
			'version' => '4.25.1',
			'tags'    => array( 'plugins', 'education' ),
		),
		'mailpoet-business'     => array(
			'name'    => 'MailPoet Business',
			'store'   => 'mailpoet_business',
			'author'  => 'MailPoet',
			'desc'    => 'Grow your audience with newsletters, automations, and new-post notifications sent from WordPress.',
			'icon'    => 'https://ps.w.org/mailpoet/assets/icon-256x256.png',
			'banner'  => 'https://ps.w.org/mailpoet/assets/banner-1544x500.png',
			'monthly' => 140.00,
			'yearly'  => 312.00,
			'updated' => '3 days ago',
			'version' => '5.12.0',
			'tags'    => array( 'plugins', 'email', 'marketing' ),
		),
	);
	return $products;
}

function untangling_mktf_product( $slug ) {
	$products = untangling_mktf_products();
	return isset( $products[ $slug ] ) ? $products[ $slug ] + array( 'slug' => $slug ) : null;
}

// Long-form copy for the details modal / product page. Falls back to the
// main plugin's untangling_marketplace_plugin_details() where a slug matches.
function untangling_mktf_details( $slug ) {
	$own = array(
		'js-composer'         => array( array(
			'WPBakery Page Builder is a drag-and-drop editor for WordPress. Build pages with the live frontend editor or the schematic backend editor, with 50+ content elements and a template library.',
			'Design options, responsive controls, and a grid builder cover most layouts without code. Works with any theme.',
		), array( 'Frontend and backend editors', '50+ content elements', 'Template library', 'Responsive design options' ) ),
		'astra-pro'           => array( array(
			'Astra Pro extends the Astra theme with layout, header, footer, and typography controls, plus ready-to-import starter templates.',
			'Fine-tune spacing, colors, and page layouts from the Customizer, and keep the site fast: Astra is built for performance.',
		), array( 'Header and footer builder', 'Starter templates', 'Typography and spacing controls', 'WooCommerce layouts' ) ),
		'events-calendar-pro' => array( array(
			'Events Calendar Pro adds recurring events, additional views (week, map, photo), and venue and organizer pages to The Events Calendar.',
			'Shortcodes and blocks place calendars anywhere; filters let visitors find events by category, date, or location.',
		), array( 'Recurring events', 'Week, map, and photo views', 'Filter bar', 'Event series' ) ),
		'mailpoet-business'   => array( array(
			'MailPoet Business lets you create, send, and track newsletters and automatic new-post notifications from your WordPress dashboard, with sending included.',
			'Build emails with a drag-and-drop editor, welcome new subscribers automatically, and watch open and click stats roll in.',
		), array( 'Drag-and-drop email editor', 'New-post notifications', 'Welcome automations', 'Subscriber segmentation' ) ),
	);
	if ( isset( $own[ $slug ] ) ) {
		return $own[ $slug ];
	}
	$main = untangling_marketplace_plugin_details();
	if ( isset( $main[ $slug ] ) ) {
		return array( $main[ $slug ][2], $main[ $slug ][3] );
	}
	$p = untangling_mktf_product( $slug );
	return array( array( $p ? $p['desc'] : '' ), array() );
}

// esc_url() drops data: URIs (not an allowed protocol); the generated icons use them.
function untangling_mktf_src( $url ) {
	return 0 === strpos( $url, 'data:' ) ? esc_attr( $url ) : esc_url( $url );
}

function untangling_mktf_money( $n ) {
	return 'US$' . number_format_i18n( (float) $n, 2 );
}

// Checkout summary column: production drops the cents when there are none ("US$108 US$48").
function untangling_mktf_money_short( $n ) {
	$n = (float) $n;
	return 'US$' . ( abs( $n - round( $n ) ) < 0.005 ? number_format_i18n( $n, 0 ) : number_format_i18n( $n, 2 ) );
}

// Production rule (Marketplace_Catalog::yearly_saving): shown only when ≥ 5%.
function untangling_mktf_saving( $p ) {
	if ( empty( $p['monthly'] ) || empty( $p['yearly'] ) ) {
		return 0;
	}
	$pct = (int) round( ( 1 - $p['yearly'] / ( $p['monthly'] * 12 ) ) * 100 );
	return ( $pct >= 5 && $pct <= 50 ) ? $pct : 0;
}

// WordPress.com Personal, the plan a free site buys with its first plugin.
function untangling_mktf_plan_terms() {
	return array(
		'month' => array( 'label' => 'Month', 'price' => 9.00, 'full' => 9.00, 'per_month' => 9.00, 'save' => 0, 'billed' => 'Billed monthly' ),
		'year'  => array( 'label' => 'One year', 'price' => 48.00, 'full' => 108.00, 'per_month' => 4.00, 'save' => 55, 'billed' => 'Billed every year' ),
		'2y'    => array( 'label' => 'Two years', 'price' => 78.00, 'full' => 216.00, 'per_month' => 3.25, 'save' => 63, 'billed' => 'Billed every two years' ),
	);
}

/* -------------------------------------------------------------------------
 * Purchases (demo state)
 * ---------------------------------------------------------------------- */

function untangling_mktf_owned() {
	return (array) get_option( 'untangling_mkt_plugins_owned', array() );
}

function untangling_mktf_is_owned( $slug ) {
	$owned = untangling_mktf_owned();
	return isset( $owned[ $slug ] );
}

function untangling_mktf_renews_line( $slug ) {
	$owned = untangling_mktf_owned();
	$p     = untangling_mktf_product( $slug );
	if ( ! $p || ! isset( $owned[ $slug ] ) ) {
		return '';
	}
	// Post-purchase wording: "monthly" / "yearly" (point of sale says "annually").
	return 'yearly' === $owned[ $slug ]['term']
		? sprintf( __( 'Renews yearly at %s.' ), untangling_mktf_money( $p['yearly'] ) )
		: sprintf( __( 'Renews monthly at %s.' ), untangling_mktf_money( $p['monthly'] ) );
}

// ?untangling_purchase_plugin=slug&term=monthly|yearly&plan_term=month|year|2y
// Written by the checkout's Complete Checkout link. Buying on a Free site
// also buys Personal. In the proposal the visitor goes straight back to the
// page the purchase started from; production continues to the thank-you page.
add_action( 'admin_init', function () {
	if ( ! isset( $_GET['untangling_purchase_plugin'] ) ) {
		return;
	}
	$slug = sanitize_key( $_GET['untangling_purchase_plugin'] );
	if ( ! untangling_mktf_product( $slug ) ) {
		return;
	}
	$term      = ( isset( $_GET['term'] ) && 'yearly' === $_GET['term'] ) ? 'yearly' : 'monthly';
	$plan_term = ( isset( $_GET['plan_term'] ) && isset( untangling_mktf_plan_terms()[ $_GET['plan_term'] ] ) ) ? $_GET['plan_term'] : 'year';
	if ( untangling_mktf_needs_plan() ) {
		untangling_set_plan( 'Personal' );
	}
	$owned          = untangling_mktf_owned();
	$owned[ $slug ] = array( 'term' => $term, 'plan_term' => $plan_term, 'date' => current_time( 'mysql' ) );
	update_option( 'untangling_mkt_plugins_owned', $owned );
	$installed   = (array) get_option( 'untangling_mkt_installed', array() );
	$installed[] = $slug;
	update_option( 'untangling_mkt_installed', array_values( array_unique( $installed ) ) );

	$mode = isset( $_GET['mode'] ) && 'production' === $_GET['mode'] ? 'production' : 'proposal';
	if ( 'proposal' === $mode ) {
		$back = ! empty( $_GET['back'] ) ? wp_validate_redirect( wp_unslash( $_GET['back'] ), '' ) : '';
		if ( ! $back ) {
			$back = admin_url( 'plugin-install.php?tab=wpcom_marketplace' );
		}
		wp_safe_redirect( add_query_arg( 'untangling_purchased', $slug, $back ) );
		exit;
	}
}, 6 );

/* -------------------------------------------------------------------------
 * URLs
 * ---------------------------------------------------------------------- */

function untangling_mktf_tab_url() {
	return admin_url( 'plugin-install.php?tab=wpcom_marketplace' );
}

function untangling_mktf_details_url( $slug ) {
	return untangling_marketplace_url( 'plugins', array( 'ustep' => 'details', 'type' => 'plugin', 'slug' => $slug ) );
}

function untangling_mktf_checkout_url( $slug, $term, $mode, $back ) {
	return untangling_marketplace_url( 'plugins', array(
		'ustep' => 'checkout',
		'type'  => 'plugin',
		'slug'  => $slug,
		'term'  => 'yearly' === $term ? 'yearly' : 'monthly',
		'mode'  => $mode,
		'back'  => rawurlencode( $back ),
	) );
}

// Plan-only pricing step (the whole ladder from Free), returning to Add Plugins.
function untangling_mktf_plans_url( $back = '' ) {
	return untangling_marketplace_url( 'plugins', array( 'ustep' => 'pricing', 'back' => rawurlencode( $back ? $back : untangling_mktf_tab_url() ) ) );
}

function untangling_mktf_new_address() {
	return preg_replace( '/\.wordpress\.com$/', '.wpcomstaging.com', untangling_wpcom_domain() );
}

/* -------------------------------------------------------------------------
 * The tab
 * ---------------------------------------------------------------------- */

// Core plugin-card markup with production's Marketplace strip (label, price
// rows, "Additional plugins will be installed"). Both modes share the card
// shell; the CTA, the price rows and where More Details goes differ.
function untangling_mktf_card( $slug, $p, $mode ) {
	$needs_plan = untangling_mktf_needs_plan();
	$owned      = untangling_mktf_is_owned( $slug );
	$saving     = untangling_mktf_saving( $p );
	$details    = 'production' === $mode ? '#' : untangling_mktf_details_url( $slug );
	$tab_url    = untangling_mktf_tab_url();
	$checkout   = untangling_mktf_checkout_url( $slug, 'production' === $mode ? 'yearly' : 'monthly', $mode, $tab_url );

	echo '<div class="plugin-card plugin-card-' . esc_attr( $slug ) . ' wpcom-marketplace-card" data-slug="' . esc_attr( $slug ) . '" data-installed="' . ( $owned ? 'true' : 'false' ) . '" data-needs-plan="' . ( $needs_plan && ! $owned ? 'true' : 'false' ) . '">';
	echo '<div class="plugin-card-top">';
	echo '<div class="name column-name"><h3><a href="' . esc_url( $details ) . '" class="' . ( 'production' === $mode ? 'untangling-mktf-open-details' : '' ) . '">' . esc_html( $p['name'] ) . '<img class="plugin-icon" src="' . untangling_mktf_src( $p['icon'] ) . '" alt=""></a></h3></div>';
	echo '<div class="action-links"><ul class="plugin-action-buttons">';
	if ( $owned ) {
		echo '<li><button type="button" class="button button-disabled" disabled="disabled">' . esc_html__( 'Active' ) . '</button></li>';
	} elseif ( 'production' === $mode && $needs_plan ) {
		echo '<li><a class="button button-compact wpcom-marketplace-card__upgrade untangling-mktf-upgrade" href="' . esc_url( $checkout ) . '" aria-label="' . esc_attr( sprintf( __( 'Upgrade your plan and purchase %s' ), $p['name'] ) ) . '">' . esc_html__( 'Upgrade and purchase' ) . '</a></li>';
	} else {
		echo '<li><a class="button button-compact" href="' . esc_url( $checkout ) . '" aria-label="' . esc_attr( sprintf( __( 'Purchase and activate %s' ), $p['name'] ) ) . '">' . esc_html__( 'Purchase' ) . '</a></li>';
	}
	echo '<li><a href="' . esc_url( $details ) . '" class="' . ( 'production' === $mode ? 'untangling-mktf-open-details' : '' ) . '" aria-label="' . esc_attr( sprintf( __( 'More information about %s' ), $p['name'] ) ) . '">' . esc_html__( 'More Details' ) . '</a></li>';
	echo '</ul></div>';
	echo '<div class="desc column-description"><p><span class="wpcom-marketplace-label">' . esc_html__( 'WordPress.com Marketplace' ) . '</span>' . esc_html( $p['desc'] ) . '</p><p class="authors"><cite>' . esc_html( 'By ' . $p['author'] ) . '</cite></p></div>';
	if ( ! empty( $p['requires'] ) && ! $owned ) {
		echo '<div class="plugin-dependencies"><p class="plugin-dependencies-explainer-text">' . esc_html__( 'Additional plugins will be installed' ) . '</p><div class="plugin-dependency"><span class="plugin-dependency-name">' . esc_html( $p['requires'] ) . '</span> <a href="' . esc_url( $details ) . '" class="' . ( 'production' === $mode ? 'untangling-mktf-open-details' : '' ) . '">' . esc_html__( 'More Details' ) . '</a></div></div>';
	}
	echo '</div>';

	// Bottom strip: production drops the price once the plugin is installed.
	echo '<div class="plugin-card-bottom">';
	echo '<div class="untangling-mktf-strip-left">';
	echo '<div class="column-rating wpcom-marketplace-card__headline">';
	if ( ! $owned ) {
		if ( 'production' === $mode ) {
			echo '<span class="wpcom-marketplace-card__amount">' . esc_html( untangling_mktf_money( $p['yearly'] ) ) . '</span> <span class="wpcom-marketplace-card__per">/' . esc_html__( 'year' ) . '</span>';
			if ( $saving ) {
				echo ' <span class="wpcom-marketplace-card__saving">' . esc_html( sprintf( __( 'Save %d%%' ), $saving ) ) . '</span>';
			}
		} else {
			echo '<span class="wpcom-marketplace-card__amount">' . esc_html( untangling_mktf_money( $p['monthly'] ) ) . '</span> <span class="wpcom-marketplace-card__per">/' . esc_html__( 'month' ) . '</span>';
		}
	}
	echo '</div>';
	echo '<div class="column-downloaded wpcom-marketplace-card__alternative">';
	if ( ! $owned ) {
		if ( 'production' === $mode ) {
			echo '<span class="wpcom-marketplace-card__note">' . esc_html( $needs_plan ? sprintf( __( 'Plus a Personal plan, %s/year' ), untangling_mktf_money( 48 ) ) : sprintf( __( '%s/month if billed monthly' ), untangling_mktf_money( $p['monthly'] ) ) ) . '</span>';
		} else {
			$alt = sprintf( __( 'or %s/year' ), untangling_mktf_money( $p['yearly'] ) );
			echo '<span class="wpcom-marketplace-card__note">' . esc_html( $alt ) . ( $saving ? ' <span class="wpcom-marketplace-card__saving">' . esc_html( sprintf( __( 'Save %d%%' ), $saving ) ) . '</span>' : '' ) . '</span>';
		}
	}
	echo '</div>';
	echo '</div>';
	echo '<div class="untangling-mktf-strip-right">';
	echo '<div class="column-updated"><strong>' . esc_html__( 'Last Updated:' ) . '</strong> ' . esc_html( $p['updated'] ) . '</div>';
	echo '<div class="column-compatibility"><span class="compatibility-compatible"><strong>' . esc_html__( 'Compatible' ) . '</strong> ' . esc_html__( 'with your version of WordPress' ) . '</span></div>';
	echo '</div>';
	echo '</div>';

	// Production's details modal reads the card; the content travels in a template.
	if ( 'production' === $mode ) {
		list( $paragraphs ) = untangling_mktf_details( $slug );
		echo '<template class="untangling-mktf-details-tpl" data-name="' . esc_attr( $p['name'] ) . '" data-banner="' . esc_attr( $p['banner'] ) . '" data-version="' . esc_attr( $p['version'] ) . '" data-author="' . esc_attr( $p['author'] ) . '" data-updated="' . esc_attr( $p['updated'] ) . '">';
		echo '<h2>' . esc_html( $p['name'] ) . '</h2>';
		foreach ( $paragraphs as $para ) {
			echo '<p>' . esc_html( $para ) . '</p>';
		}
		echo '</template>';
	}
	echo '</div>';
}

function untangling_mktf_render_tab() {
	$mode     = untangling_get_mkt_tab();
	$products = untangling_mktf_products();
	untangling_mktf_styles();
	echo '<div class="untangling-mktf untangling-mktf--' . esc_attr( $mode ) . '" data-mode="' . esc_attr( $mode ) . '">';

	untangling_mktf_purchased_notice();
	if ( 'proposal' === $mode ) {
		untangling_mktf_explainer();
	}
	// The proposal drops production's intro line: the explainer block above says what matters.
	if ( 'production' === $mode ) {
		echo '<p class="wpcom-marketplace-intro">' . esc_html__( 'Premium plugins from WordPress.com and our partners. Each one comes with a subscription, and is installed, updated, and supported for you.' ) . '</p>';
	}
	// Production prints an item count here; the proposal drops it so the cards sit right under the notice.
	if ( 'production' === $mode ) {
		echo '<div class="untangling-mktf-count">' . esc_html( sprintf( _n( '%s item', '%s items', count( $products ) ), number_format_i18n( count( $products ) ) ) ) . '</div>';
	}
	echo '<div class="wp-list-table widefat plugin-install untangling-marketplace"><div id="the-list">';
	foreach ( $products as $slug => $p ) {
		untangling_mktf_card( $slug, $p, $mode );
	}
	echo '</div></div><div class="clear"></div>';
	echo '</div>';
	if ( 'production' === $mode ) {
		untangling_mktf_production_modals();
	}
	untangling_mktf_purchased_modal();
}

// The one place the rule is stated on a free site. It is the WordPress.com
// showcase hero the prototype used on Add Plugins before the tab (compact
// metrics of the wpcom themes banner: Recoleta heading, logo lockup, dark
// rounded panel, plugin-tile artwork), carrying the proposal's copy.
function untangling_mktf_explainer() {
	if ( ! untangling_mktf_needs_plan() ) {
		return;
	}
	$assets = 'https://cdn.jsdelivr.net/gh/Automattic/jetpack@f319779638296460446adc163ff042ef57789b15/projects/packages/jetpack-mu-wpcom/src/features/wpcom-plugins/images';
	?>
	<div class="untangling-mktf-hero" role="region" aria-label="<?php esc_attr_e( 'Plans and plugins' ); ?>" style="background-image: url(<?php echo esc_url( $assets . '/banner-background.webp' ); ?>);">
		<div class="untangling-mktf-hero__content">
			<img src="<?php echo esc_url( $assets . '/wpcom-logo.svg' ); ?>" alt="WordPress.com">
			<h3><?php esc_html_e( 'Plugins need a hosting plan' ); ?></h3>
			<p><?php echo esc_html( sprintf( __( 'Any paid plan, from %s/month billed annually, includes plugin installs and managed hosting. Add what you need. Nothing breaks.' ), 'US$4' ) ); ?></p>
			<a class="untangling-mktf-hero__cta" href="<?php echo esc_url( untangling_mktf_plans_url() ); ?>"><?php esc_html_e( 'See plans' ); ?></a>
		</div>
	</div>
	<?php
}

// Proposal only: the same block sits on top of the free-plugin tabs, where
// every Install button is an upgrade today.
foreach ( array( 'featured', 'popular', 'recommended', 'favorites' ) as $untangling_mktf_core_tab ) {
	add_action( 'install_plugins_' . $untangling_mktf_core_tab, function () {
		if ( untangling_mktf_enabled() && 'proposal' === untangling_get_mkt_tab() && untangling_mktf_needs_plan() ) {
			untangling_mktf_styles();
			echo '<div class="untangling-mktf untangling-mktf--proposal is-core-tab">';
			untangling_mktf_explainer();
			echo '</div>';
		}
	}, 9 );
}

/* -------------------------------------------------------------------------
 * Post-purchase landing (proposal): notice + address modal, once
 * ---------------------------------------------------------------------- */

function untangling_mktf_purchased_slug() {
	if ( empty( $_GET['untangling_purchased'] ) ) {
		return '';
	}
	$slug = sanitize_key( $_GET['untangling_purchased'] );
	return untangling_mktf_is_owned( $slug ) ? $slug : '';
}

function untangling_mktf_purchased_notice() {
	$slug = untangling_mktf_purchased_slug();
	if ( ! $slug ) {
		return;
	}
	$p = untangling_mktf_product( $slug );
	echo '<div class="untangling-mktf-success" role="status"><span class="untangling-mktf-check">✓</span><p>' . esc_html( sprintf( __( '%s is active.' ), $p['name'] ) . ' ' . untangling_mktf_renews_line( $slug ) ) . ' <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Manage plugins' ) . '</a></p></div>';
}

function untangling_mktf_purchased_modal() {
	$slug = untangling_mktf_purchased_slug();
	if ( ! $slug ) {
		return;
	}
	$old = untangling_wpcom_domain();
	$new = untangling_mktf_new_address();
	?>
	<div class="untangling-mktf-dialog-wrap is-open" id="untangling-mktf-moved" role="dialog" aria-modal="true" aria-labelledby="untangling-mktf-moved-title">
		<div class="untangling-mktf-dialog">
			<button type="button" class="untangling-mktf-dialog-close" aria-label="<?php esc_attr_e( 'Close' ); ?>">✕</button>
			<h2 id="untangling-mktf-moved-title"><?php esc_html_e( 'Your site now runs on managed hosting' ); ?></h2>
			<p><?php echo esc_html( sprintf( __( '%s is active, and your site moved to WordPress.com’s managed infrastructure. The hard parts are handled:' ), untangling_mktf_product( $slug )['name'] ) ); ?></p>
			<ul class="untangling-mktf-dialog-list">
				<li><?php esc_html_e( 'Faster, more reliable hosting, with backups, security, and updates taken care of.' ); ?></li>
				<li><?php esc_html_e( '24/7 expert support, included in your plan.' ); ?></li>
				<li><?php esc_html_e( 'Your address changed. Every link to the old one redirects on its own. Nothing to do on your side.' ); ?></li>
				<li><?php esc_html_e( 'A free domain for a year is included. Add it whenever you like.' ); ?></li>
			</ul>
			<div class="untangling-mktf-addr"><span><?php echo esc_html( $old ); ?></span><em class="is-old"><?php esc_html_e( 'redirects' ); ?></em></div>
			<div class="untangling-mktf-addr"><span><?php echo esc_html( $new ); ?></span><em class="is-new"><?php esc_html_e( 'current' ); ?></em></div>
			<div class="untangling-mktf-dialog-actions">
				<a class="untangling-mktf-btn is-secondary" href="<?php echo esc_url( untangling_domain_search_url() ); ?>"><?php esc_html_e( 'Add a domain' ); ?></a>
				<button type="button" class="untangling-mktf-btn is-primary" data-close><?php esc_html_e( 'Got it' ); ?></button>
			</div>
		</div>
	</div>
	<script>
	( function () {
		var wrap = document.getElementById( 'untangling-mktf-moved' );
		if ( ! wrap ) { return; }
		function close() {
			wrap.classList.remove( 'is-open' );
			var url = new URL( window.location.href );
			url.searchParams.delete( 'untangling_purchased' );
			window.history.replaceState( null, '', url.toString() );
		}
		wrap.querySelectorAll( '.untangling-mktf-dialog-close, [data-close]' ).forEach( function ( b ) { b.addEventListener( 'click', close ); } );
		wrap.addEventListener( 'click', function ( e ) { if ( e.target === wrap ) { close(); } } );
		document.addEventListener( 'keydown', function ( e ) { if ( 'Escape' === e.key && wrap.classList.contains( 'is-open' ) ) { close(); } } );
	} )();
	</script>
	<?php
}

/* -------------------------------------------------------------------------
 * Production replica: details modal + "One more step" dialog
 * ---------------------------------------------------------------------- */

function untangling_mktf_production_modals() {
	$old = untangling_wpcom_domain();
	$new = untangling_mktf_new_address();
	?>
	<div class="untangling-mktf-tb" id="untangling-mktf-tb" hidden>
		<div class="untangling-mktf-tb-window" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Plugin details' ); ?>">
			<button type="button" class="untangling-mktf-tb-close" aria-label="<?php esc_attr_e( 'Close' ); ?>"><span>✕</span></button>
			<div class="untangling-mktf-tb-banner"><h2></h2></div>
			<div class="untangling-mktf-tb-tabs"><a href="#" class="is-current"><?php esc_html_e( 'Description' ); ?></a><a href="#"><?php esc_html_e( 'Screenshots' ); ?></a></div>
			<div class="untangling-mktf-tb-body">
				<div class="untangling-mktf-tb-content"></div>
				<div class="untangling-mktf-tb-meta">
					<p><strong><?php esc_html_e( 'Version:' ); ?></strong> <span data-version></span></p>
					<p><strong><?php esc_html_e( 'Author:' ); ?></strong> <span data-author></span></p>
					<p><strong><?php esc_html_e( 'Last Updated:' ); ?></strong> <span data-updated></span></p>
					<p><a href="#"><?php esc_html_e( 'Plugin Homepage »' ); ?></a></p>
				</div>
			</div>
			<div class="untangling-mktf-tb-foot"></div>
		</div>
	</div>
	<div class="untangling-mktf-dialog-wrap" id="untangling-mktf-onemore" role="dialog" aria-modal="true" aria-labelledby="untangling-mktf-onemore-title">
		<div class="untangling-mktf-dialog is-onemore">
			<button type="button" class="untangling-mktf-dialog-close" aria-label="<?php esc_attr_e( 'Close' ); ?>">✕</button>
			<h2 id="untangling-mktf-onemore-title"><?php esc_html_e( 'One more step' ); ?></h2>
			<p><?php esc_html_e( 'To install plugins, we’ll need to move your site over to WordPress.com’s advanced managed cloud hosting.' ); ?></p>
			<p><strong><?php esc_html_e( 'Your site’s address will change' ); ?></strong> <?php esc_html_e( 'to the one below. Links to your old address will redirect automatically.' ); ?> <a href="#"><?php esc_html_e( 'Learn more' ); ?></a>.</p>
			<div class="untangling-mktf-addr"><span><?php echo esc_html( $old ); ?></span><em class="is-old"><?php esc_html_e( 'current' ); ?></em></div>
			<div class="untangling-mktf-addr"><span><?php echo esc_html( $new ); ?></span><em class="is-new"><?php esc_html_e( 'new' ); ?></em></div>
			<div class="untangling-mktf-dialog-actions is-split">
				<a href="#" class="untangling-mktf-help"><?php esc_html_e( 'Need help?' ); ?></a>
				<a class="untangling-mktf-btn is-primary" href="#" data-continue><?php esc_html_e( 'Continue' ); ?></a>
			</div>
		</div>
	</div>
	<script>
	( function () {
		var tb = document.getElementById( 'untangling-mktf-tb' );
		var onemore = document.getElementById( 'untangling-mktf-onemore' );
		function openDetails( card ) {
			var tpl = card.querySelector( '.untangling-mktf-details-tpl' );
			if ( ! tpl ) { return; }
			tb.querySelector( '.untangling-mktf-tb-banner' ).style.backgroundImage = 'url("' + tpl.dataset.banner + '")';
			tb.querySelector( '.untangling-mktf-tb-banner h2' ).textContent = tpl.dataset.name;
			tb.querySelector( '.untangling-mktf-tb-content' ).innerHTML = tpl.innerHTML;
			tb.querySelector( '[data-version]' ).textContent = tpl.dataset.version;
			tb.querySelector( '[data-author]' ).textContent = tpl.dataset.author;
			tb.querySelector( '[data-updated]' ).textContent = tpl.dataset.updated;
			tb.hidden = false;
			document.body.classList.add( 'modal-open' );
			tb.querySelector( '.untangling-mktf-tb-close' ).focus();
		}
		function closeAll() {
			tb.hidden = true;
			onemore.classList.remove( 'is-open' );
			document.body.classList.remove( 'modal-open' );
		}
		document.addEventListener( 'click', function ( e ) {
			var open = e.target.closest( '.untangling-mktf-open-details' );
			if ( open ) {
				e.preventDefault();
				openDetails( open.closest( '.plugin-card' ) );
				return;
			}
			var up = e.target.closest( '.untangling-mktf-upgrade' );
			if ( up ) {
				e.preventDefault();
				onemore.querySelector( '[data-continue]' ).href = up.href;
				onemore.classList.add( 'is-open' );
				document.body.classList.add( 'modal-open' );
				onemore.querySelector( '[data-continue]' ).focus();
				return;
			}
			if ( e.target.closest( '.untangling-mktf-tb-close' ) || e.target.closest( '.untangling-mktf-dialog-close' ) || e.target === tb || e.target === onemore ) {
				e.preventDefault();
				closeAll();
			}
		} );
		document.addEventListener( 'keydown', function ( e ) { if ( 'Escape' === e.key ) { closeAll(); } } );
	} )();
	</script>
	<?php
}

/* -------------------------------------------------------------------------
 * Product page (proposal): the in-admin wordpress.com/plugins/{slug} page
 * ---------------------------------------------------------------------- */

function untangling_mktf_details_step() {
	$slug = isset( $_GET['slug'] ) ? sanitize_key( $_GET['slug'] ) : '';
	$p    = untangling_mktf_product( $slug );
	if ( ! $p ) {
		echo '<div class="untangling-mkt-hero"><h1 class="untangling-mkt-brandfont">' . esc_html__( 'Plugin not found' ) . '</h1></div>';
		return;
	}
	list( $paragraphs, $highlights ) = untangling_mktf_details( $slug );
	$needs_plan = untangling_mktf_needs_plan();
	$owned      = untangling_mktf_is_owned( $slug );
	$saving     = untangling_mktf_saving( $p );
	$self       = untangling_mktf_details_url( $slug );
	$mode       = untangling_get_mkt_tab();
	untangling_mktf_styles();
	?>
	<div class="untangling-mktf untangling-mktf--<?php echo esc_attr( $mode ); ?> is-details">
	<?php untangling_mktf_purchased_notice(); ?>
	<nav class="untangling-mkt-crumbs">
		<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Plugins' ); ?></a>
		<span aria-hidden="true">›</span>
		<a href="<?php echo esc_url( untangling_mktf_tab_url() ); ?>"><?php esc_html_e( 'Add Plugins' ); ?></a>
		<span aria-hidden="true">›</span>
		<span class="is-current"><?php echo esc_html( $p['name'] ); ?></span>
	</nav>
	<div class="untangling-mkt-plugdetail">
		<div class="untangling-mkt-plugdetail-info">
			<div class="untangling-mkt-plugdetail-head">
				<img src="<?php echo untangling_mktf_src( $p['icon'] ); ?>" alt="" decoding="async">
				<div>
					<h1 class="untangling-mkt-brandfont"><?php echo esc_html( $p['name'] ); ?></h1>
					<p class="untangling-mkt-detail-by"><?php esc_html_e( 'By' ); ?> <a href="#"><?php echo esc_html( $p['author'] ); ?></a></p>
				</div>
			</div>
			<p class="untangling-mkt-plugdetail-tagline"><?php echo esc_html( rtrim( $p['desc'], '…' ) ); ?></p>
			<div class="untangling-mkt-plugdetail-tags">
				<?php foreach ( $p['tags'] as $tag ) : ?><span><?php echo esc_html( $tag ); ?></span><?php endforeach; ?>
			</div>
			<div class="untangling-mkt-plugdetail-meta">
				<div><span><?php esc_html_e( 'Version' ); ?></span><strong><?php echo esc_html( $p['version'] ); ?></strong></div>
				<div><span><?php esc_html_e( 'Last updated' ); ?></span><strong><?php echo esc_html( $p['updated'] ); ?></strong></div>
			</div>
			<div class="untangling-mkt-plugdetail-banner"><img src="<?php echo untangling_mktf_src( $p['banner'] ); ?>" alt="" decoding="async" onerror="this.parentNode.hidden = true;"></div>
			<h2><?php echo esc_html( $p['name'] ); ?></h2>
			<?php foreach ( $paragraphs as $para ) : ?>
				<p class="untangling-mkt-plugdetail-para"><?php echo esc_html( $para ); ?></p>
			<?php endforeach; ?>
			<?php if ( $highlights ) : ?>
				<h2><?php esc_html_e( 'Highlights' ); ?></h2>
				<div class="untangling-mkt-detail-feats">
					<?php foreach ( $highlights as $h ) : ?><span><?php echo esc_html( $h ); ?></span><?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $p['requires'] ) ) : ?>
				<h2><?php esc_html_e( 'Also installed' ); ?></h2>
				<p class="untangling-mkt-plugdetail-para"><?php echo esc_html( $p['requires'] ); ?></p>
			<?php endif; ?>
		</div>
		<aside class="untangling-mkt-plugdetail-buy untangling-mktf-buy" data-monthly="<?php echo esc_attr( $p['monthly'] ); ?>" data-yearly="<?php echo esc_attr( $p['yearly'] ); ?>">
			<?php if ( $owned ) : ?>
				<p class="untangling-mkt-plugdetail-price"><strong class="untangling-mkt-brandfont"><?php esc_html_e( 'Active' ); ?></strong></p>
				<p class="untangling-mktf-buy-sub"><?php echo esc_html( untangling_mktf_renews_line( $slug ) ); ?></p>
				<a class="untangling-mkt-button is-secondary is-block" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Manage plugins' ); ?></a>
			<?php else : ?>
				<p class="untangling-mkt-plugdetail-price">
					<strong class="untangling-mkt-brandfont" data-price><?php echo esc_html( untangling_mktf_money( $p['monthly'] ) ); ?></strong> <span data-period><?php esc_html_e( 'monthly' ); ?></span>
				</p>
				<div class="untangling-mkt-plugdetail-billing" role="radiogroup" aria-label="<?php esc_attr_e( 'Billing period' ); ?>">
					<label><input type="radio" name="untangling-mktf-term" value="monthly" checked> <?php esc_html_e( 'Monthly' ); ?></label>
					<label><input type="radio" name="untangling-mktf-term" value="yearly"> <?php esc_html_e( 'Annually' ); ?> <?php if ( $saving ) : ?><em><?php echo esc_html( sprintf( __( '(Save %d%%)' ), $saving ) ); ?></em><?php endif; ?></label>
				</div>
				<a class="untangling-mkt-button is-primary is-block" data-buy href="<?php echo esc_url( untangling_mktf_checkout_url( $slug, 'monthly', $mode, $self ) ); ?>"><?php esc_html_e( 'Purchase and activate' ); ?></a>
				<?php if ( $needs_plan ) : ?>
					<p class="untangling-mktf-buy-sub"><?php echo esc_html( sprintf( __( 'Needs a paid plan, from %s/month.' ), 'US$4' ) ); ?> <a href="<?php echo esc_url( untangling_mktf_plans_url( $self ) ); ?>"><?php esc_html_e( 'See plans' ); ?></a></p>
				<?php endif; ?>
			<?php endif; ?>
			<hr>
			<h3><?php esc_html_e( 'Included with your purchase' ); ?></h3>
			<ul>
				<li><?php esc_html_e( 'Plugin updates' ); ?></li>
				<li><?php esc_html_e( '14-day money-back guarantee' ); ?></li>
			</ul>
			<h3><?php esc_html_e( 'Included on all paid plans' ); ?></h3>
			<ul>
				<li><?php esc_html_e( 'Managed hosting' ); ?></li>
				<li><?php esc_html_e( '24/7 expert support' ); ?></li>
			</ul>
			<h3><?php esc_html_e( 'Support' ); ?></h3>
			<p><a href="#" data-open-help><?php esc_html_e( 'How to get help' ); ?></a></p>
			<p><a href="#"><?php esc_html_e( 'See privacy policy' ); ?> ↗</a></p>
		</aside>
	</div>
	<?php untangling_mktf_purchased_modal(); ?>
	</div>
	<script>
	( function () {
		var buy = document.querySelector( '.untangling-mktf-buy' );
		if ( ! buy ) { return; }
		var money = function ( n ) { return 'US$' + Number( n ).toFixed( 2 ).replace( /\B(?=(\d{3})+(?!\d))/g, ',' ); };
		var link = buy.querySelector( '[data-buy]' );
		buy.querySelectorAll( 'input[name="untangling-mktf-term"]' ).forEach( function ( r ) {
			r.addEventListener( 'change', function () {
				var yearly = 'yearly' === r.value;
				buy.querySelector( '[data-price]' ).textContent = money( yearly ? buy.dataset.yearly : buy.dataset.monthly );
				buy.querySelector( '[data-period]' ).textContent = yearly ? <?php echo wp_json_encode( __( 'annually' ) ); ?> : <?php echo wp_json_encode( __( 'monthly' ) ); ?>;
				if ( link ) {
					var url = new URL( link.href, location.href );
					url.searchParams.set( 'term', yearly ? 'yearly' : 'monthly' );
					link.href = url.toString();
				}
			} );
		} );
	} )();
	</script>
	<?php
}

/* -------------------------------------------------------------------------
 * Checkout (both modes): Calypso composite-checkout look
 * ---------------------------------------------------------------------- */

function untangling_mktf_checkout_step() {
	$slug = isset( $_GET['slug'] ) ? sanitize_key( $_GET['slug'] ) : '';
	$p    = untangling_mktf_product( $slug );
	if ( ! $p ) {
		echo '<div class="untangling-mkt-hero"><h1 class="untangling-mkt-brandfont">' . esc_html__( 'Item not found' ) . '</h1></div>';
		return;
	}
	$mode       = ( isset( $_GET['mode'] ) && 'production' === $_GET['mode'] ) ? 'production' : 'proposal';
	$term       = ( isset( $_GET['term'] ) && 'yearly' === $_GET['term'] ) ? 'yearly' : 'monthly';
	$needs_plan = untangling_mktf_needs_plan();
	$plan_terms = untangling_mktf_plan_terms();
	$plan_term  = 'year';
	$back       = ! empty( $_GET['back'] ) ? wp_validate_redirect( wp_unslash( $_GET['back'] ), '' ) : '';
	$user       = wp_get_current_user();
	$site       = untangling_wpcom_domain();
	$done       = untangling_marketplace_url( 'plugins', array(
		'ustep'                      => 'done',
		'type'                       => 'plugin',
		'slug'                       => $slug,
		'untangling_purchase_plugin' => $slug,
		'term'                       => $term,
		'plan_term'                  => $plan_term,
		'mode'                       => $mode,
		'back'                       => rawurlencode( $back ? $back : untangling_mktf_tab_url() ),
	) );
	$plugin_price = 'yearly' === $term ? $p['yearly'] : $p['monthly'];
	$plan_price   = $needs_plan ? $plan_terms[ $plan_term ]['price'] : 0;
	$plan_full    = $needs_plan ? $plan_terms[ $plan_term ]['full'] : 0;
	$subtotal     = $plan_price + $plugin_price;
	$discount     = $plan_full - $plan_price;
	untangling_mktf_styles();
	?>
	<div class="untangling-mktf untangling-mktf--<?php echo esc_attr( $mode ); ?> untangling-mktf-co" data-plan="<?php echo esc_attr( wp_json_encode( $plan_terms ) ); ?>" data-plugin="<?php echo esc_attr( wp_json_encode( array( 'monthly' => $p['monthly'], 'yearly' => $p['yearly'] ) ) ); ?>" data-needs-plan="<?php echo $needs_plan ? '1' : ''; ?>">
		<div class="untangling-mktf-co-main">
			<h1 class="untangling-mkt-brandfont"><?php esc_html_e( 'Checkout' ); ?></h1>
			<section class="untangling-mktf-co-step">
				<h2><span class="untangling-mktf-check">✓</span> <?php esc_html_e( 'Your order' ); ?></h2>
				<p class="untangling-mktf-co-meta"><?php echo esc_html( sprintf( __( 'Site: %s' ), $site ) ); ?><br><?php echo esc_html( sprintf( __( 'Account: %s' ), $user->user_email ) ); ?></p>
				<?php if ( $needs_plan ) : ?>
				<div class="untangling-mktf-item" data-item="plan">
					<div class="untangling-mktf-item-head">
						<div><strong><?php esc_html_e( 'WordPress.com Personal' ); ?></strong><small data-plan-billed><?php echo esc_html( $plan_terms[ $plan_term ]['billed'] ); ?></small></div>
						<div class="untangling-mktf-item-price"><strong data-plan-per><?php echo esc_html( untangling_mktf_money( $plan_terms[ $plan_term ]['per_month'] ) . '/month' ); ?></strong><small data-plan-strike><s><?php echo esc_html( untangling_mktf_money( 9 ) . ' /month' ); ?></s></small></div>
					</div>
					<div class="untangling-mktf-terms" role="radiogroup" aria-label="<?php esc_attr_e( 'Plan length' ); ?>">
						<?php foreach ( $plan_terms as $key => $t ) : ?>
							<label class="untangling-mktf-term<?php echo $key === $plan_term ? ' is-selected' : ''; ?>">
								<input type="radio" name="untangling-mktf-plan-term" value="<?php echo esc_attr( $key ); ?>"<?php checked( $key, $plan_term ); ?>>
								<span class="untangling-mktf-term-label"><?php echo esc_html( $t['label'] ); ?></span>
								<span class="untangling-mktf-term-price"><?php if ( $t['save'] ) : ?><em class="untangling-mktf-save"><?php echo esc_html( sprintf( __( 'Save %d%%' ), $t['save'] ) ); ?></em><?php endif; ?> <?php echo esc_html( untangling_mktf_money( $t['per_month'] ) ); ?> /mo</span>
							</label>
						<?php endforeach; ?>
					</div>
					<a href="#" class="untangling-mktf-remove" data-remove="plan"><?php esc_html_e( 'Remove from cart' ); ?></a>
				</div>
				<?php endif; ?>
				<div class="untangling-mktf-item" data-item="plugin">
					<div class="untangling-mktf-item-head">
						<div><strong><?php echo esc_html( $p['name'] ); ?></strong><small data-plugin-billed><?php echo 'yearly' === $term ? esc_html__( 'Billed annually' ) : esc_html__( 'Billed monthly' ); ?></small></div>
						<div class="untangling-mktf-item-price"><strong data-plugin-amount><?php echo esc_html( untangling_mktf_money( $plugin_price ) ); ?></strong></div>
					</div>
					<select class="untangling-mktf-select" data-plugin-term aria-label="<?php esc_attr_e( 'Plugin term' ); ?>">
						<option value="yearly"<?php selected( 'yearly', $term ); ?>><?php echo esc_html( sprintf( __( 'One year — %s' ), untangling_mktf_money( $p['yearly'] ) ) ); ?></option>
						<option value="monthly"<?php selected( 'monthly', $term ); ?>><?php echo esc_html( sprintf( __( 'One month — %s' ), untangling_mktf_money( $p['monthly'] ) ) ); ?></option>
					</select>
					<a href="#" class="untangling-mktf-remove" data-remove="plugin"><?php esc_html_e( 'Remove from cart' ); ?></a>
				</div>
				<?php if ( 'proposal' === $mode && $needs_plan ) : ?>
					<p class="untangling-mktf-co-hosting"><?php echo esc_html( sprintf( __( 'Includes managed hosting. Your site’s address becomes %s; links to the old address keep working.' ), untangling_mktf_new_address() ) ); ?></p>
				<?php endif; ?>
			</section>
			<section class="untangling-mktf-co-step is-collapsed">
				<h2><span class="untangling-mktf-check">✓</span> <?php esc_html_e( 'Billing information' ); ?> <a href="#" class="untangling-mktf-edit"><?php esc_html_e( 'Edit' ); ?></a></h2>
				<p class="untangling-mktf-co-meta"><?php esc_html_e( 'Billing address on file' ); ?></p>
			</section>
			<section class="untangling-mktf-co-step is-collapsed">
				<h2><span class="untangling-mktf-check">✓</span> <?php esc_html_e( 'Payment method' ); ?> <a href="#" class="untangling-mktf-edit"><?php esc_html_e( 'Edit' ); ?></a></h2>
				<p class="untangling-mktf-co-meta"><?php echo esc_html( $user->display_name ); ?><br><?php esc_html_e( 'Visa •••• 4242 · Expiry: 12/28' ); ?></p>
			</section>
			<p class="untangling-mktf-co-legal"><strong><?php esc_html_e( 'By checking out:' ); ?></strong> <?php esc_html_e( 'You agree to our Terms of Service and authorize your payment method to be charged on a recurring basis until you cancel, which you can do at any time.' ); ?> <?php esc_html_e( 'This is a prototype. No payment is processed.' ); ?></p>
		</div>
		<aside class="untangling-mktf-co-summary">
			<h2><?php esc_html_e( 'Summary' ); ?></h2>
			<?php if ( $needs_plan ) : ?>
			<div class="untangling-mktf-sum-row" data-sum="plan"><span><span class="untangling-mktf-check">✓</span><span><?php esc_html_e( 'WordPress.com Personal' ); ?></span></span><span><s data-sum-plan-full><?php echo esc_html( untangling_mktf_money_short( $plan_full ) ); ?></s> <strong data-sum-plan><?php echo esc_html( untangling_mktf_money_short( $plan_price ) ); ?></strong></span></div>
			<?php endif; ?>
			<div class="untangling-mktf-sum-row" data-sum="plugin"><span><span class="untangling-mktf-check">✓</span><span><?php echo esc_html( $p['name'] ); ?></span></span><strong data-sum-plugin><?php echo esc_html( untangling_mktf_money_short( $plugin_price ) ); ?></strong></div>
			<div class="untangling-mktf-sum-divider"></div>
			<div class="untangling-mktf-sum-row is-sub"><span><?php esc_html_e( 'Subtotal' ); ?></span><span><s data-sum-subtotal-full<?php echo $discount ? '' : ' hidden'; ?>><?php echo esc_html( untangling_mktf_money_short( $subtotal + $discount ) ); ?></s> <strong data-sum-subtotal><?php echo esc_html( untangling_mktf_money_short( $subtotal ) ); ?></strong></span></div>
			<div class="untangling-mktf-sum-row is-sub" data-sum-discount-row<?php echo $discount ? '' : ' hidden'; ?>><span><?php esc_html_e( 'Discount' ); ?></span><strong class="is-green" data-sum-discount><?php echo esc_html( untangling_mktf_money_short( $discount ) ); ?></strong></div>
			<div class="untangling-mktf-sum-divider"></div>
			<div class="untangling-mktf-sum-row is-total"><span><?php esc_html_e( 'Total' ); ?></span><strong data-sum-total><?php echo esc_html( untangling_mktf_money_short( $subtotal ) ); ?></strong></div>
			<a class="untangling-mktf-btn is-primary is-block" data-pay href="<?php echo esc_url( $done ); ?>"><?php esc_html_e( 'Complete Checkout' ); ?></a>
			<p class="untangling-mktf-sum-note">🔒 <?php esc_html_e( 'SSL secure payment · 256-bit encryption' ); ?></p>
			<p class="untangling-mktf-sum-note">↻ <?php esc_html_e( '14-day money back guarantee' ); ?></p>
			<p class="untangling-mktf-sum-fine"><?php esc_html_e( 'By purchasing, you accept the Terms of Service and Privacy Policy.' ); ?></p>
		</aside>
	</div>
	<script>
	( function () {
		var co = document.querySelector( '.untangling-mktf-co' );
		if ( ! co ) { return; }
		var PLAN = JSON.parse( co.dataset.plan ), PLUGIN = JSON.parse( co.dataset.plugin );
		var needsPlan = !! co.dataset.needsPlan;
		var state = { planTerm: 'year', pluginTerm: <?php echo wp_json_encode( $term ); ?>, plan: needsPlan, plugin: true };
		var money = function ( n ) { return 'US$' + Number( n ).toFixed( 2 ).replace( /\B(?=(\d{3})+(?!\d))/g, ',' ); };
		var short = function ( n ) { n = Number( n ); return 'US$' + ( Math.abs( n - Math.round( n ) ) < 0.005 ? String( Math.round( n ) ) : n.toFixed( 2 ) ).replace( /\B(?=(\d{3})+(?!\d))/g, ',' ); };
		var $ = function ( s ) { return co.querySelector( s ); };
		function render() {
			var planPrice = state.plan ? PLAN[ state.planTerm ].price : 0;
			var planFull = state.plan ? PLAN[ state.planTerm ].full : 0;
			var pluginPrice = state.plugin ? PLUGIN[ state.pluginTerm ] : 0;
			var subtotal = planPrice + pluginPrice, discount = planFull - planPrice;
			if ( needsPlan ) {
				$( '[data-plan-per]' ).textContent = money( PLAN[ state.planTerm ].per_month ) + '/month';
				$( '[data-plan-billed]' ).textContent = PLAN[ state.planTerm ].billed;
				$( '[data-plan-strike]' ).hidden = 'month' === state.planTerm;
				$( '[data-sum-plan]' ).textContent = short( planPrice );
				$( '[data-sum-plan-full]' ).textContent = short( planFull );
				$( '[data-sum-plan-full]' ).hidden = ! discount;
				co.querySelectorAll( '.untangling-mktf-term' ).forEach( function ( l ) { l.classList.toggle( 'is-selected', l.querySelector( 'input' ).value === state.planTerm ); } );
			}
			$( '[data-plugin-amount]' ).textContent = money( PLUGIN[ state.pluginTerm ] );
			$( '[data-plugin-billed]' ).textContent = 'yearly' === state.pluginTerm ? <?php echo wp_json_encode( __( 'Billed annually' ) ); ?> : <?php echo wp_json_encode( __( 'Billed monthly' ) ); ?>;
			$( '[data-sum-plugin]' ).textContent = short( PLUGIN[ state.pluginTerm ] );
			$( '[data-sum-subtotal]' ).textContent = short( subtotal );
			$( '[data-sum-subtotal-full]' ).textContent = short( subtotal + discount );
			$( '[data-sum-subtotal-full]' ).hidden = ! discount;
			$( '[data-sum-discount-row]' ).hidden = ! discount;
			$( '[data-sum-discount]' ).textContent = short( discount );
			$( '[data-sum-total]' ).textContent = short( subtotal );
			var pay = $( '[data-pay]' );
			var url = new URL( pay.href, location.href );
			url.searchParams.set( 'term', state.pluginTerm );
			url.searchParams.set( 'plan_term', state.planTerm );
			pay.href = url.toString();
			pay.classList.toggle( 'is-disabled', ! state.plugin && ! state.plan );
		}
		co.querySelectorAll( 'input[name="untangling-mktf-plan-term"]' ).forEach( function ( r ) { r.addEventListener( 'change', function () { state.planTerm = r.value; render(); } ); } );
		var sel = $( '[data-plugin-term]' );
		if ( sel ) { sel.addEventListener( 'change', function () { state.pluginTerm = sel.value; render(); } ); }
		co.querySelectorAll( '[data-remove]' ).forEach( function ( a ) {
			a.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var which = a.dataset.remove;
				state[ which ] = false;
				co.querySelector( '[data-item="' + which + '"]' ).hidden = true;
				var row = co.querySelector( '[data-sum="' + which + '"]' );
				if ( row ) { row.hidden = true; }
				if ( 'plugin' === which ) {
					var pay = $( '[data-pay]' ), url = new URL( pay.href, location.href );
					url.searchParams.delete( 'untangling_purchase_plugin' );
					url.searchParams.set( 'untangling_set_plan', 'Personal' );
					pay.href = url.toString();
				}
				render();
			} );
		} );
		render();
	} )();
	</script>
	<?php
}

/* -------------------------------------------------------------------------
 * Thank-you page (production replica of the Marketplace thank-you)
 * ---------------------------------------------------------------------- */

function untangling_mktf_done_step() {
	$slug = isset( $_GET['slug'] ) ? sanitize_key( $_GET['slug'] ) : '';
	$p    = untangling_mktf_product( $slug );
	if ( ! $p ) {
		echo '<div class="untangling-mkt-hero"><h1 class="untangling-mkt-brandfont">' . esc_html__( 'Item not found' ) . '</h1></div>';
		return;
	}
	untangling_mktf_styles();
	?>
	<div class="untangling-mktf untangling-mktf-ty">
		<h1 class="untangling-mkt-brandfont"><?php esc_html_e( 'Congrats on your investment!' ); ?></h1>
		<p class="untangling-mktf-ty-lede"><?php esc_html_e( 'We’re thrilled to see you invest in your online presence and can’t wait to see what the future holds for you.' ); ?></p>
		<div class="untangling-mktf-ty-card">
			<img src="<?php echo untangling_mktf_src( $p['icon'] ); ?>" alt="">
			<div>
				<strong><?php echo esc_html( $p['name'] ); ?></strong>
				<span class="untangling-mktf-ty-status" data-ty-status><?php esc_html_e( 'Installing…' ); ?></span>
			</div>
			<a class="untangling-mktf-btn is-secondary" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" data-ty-manage hidden><?php esc_html_e( 'Manage plugin' ); ?></a>
		</div>
		<div class="untangling-mktf-ty-steps">
			<div><strong><?php esc_html_e( 'Your site is moving to managed hosting' ); ?></strong><span><?php echo esc_html( sprintf( __( 'New address: %s' ), untangling_mktf_new_address() ) ); ?></span></div>
			<div><strong><?php esc_html_e( 'Plugin setup' ); ?></strong><span><?php esc_html_e( 'Installed and activated for you.' ); ?></span></div>
		</div>
		<div class="untangling-mktf-ty-actions">
			<a class="untangling-mktf-btn is-primary" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Go to plugins' ); ?></a>
			<a class="untangling-mktf-btn is-secondary" href="<?php echo esc_url( untangling_mktf_tab_url() ); ?>"><?php esc_html_e( 'Back to Add Plugins' ); ?></a>
		</div>
	</div>
	<script>
	setTimeout( function () {
		var s = document.querySelector( '[data-ty-status]' );
		if ( s ) { s.textContent = <?php echo wp_json_encode( __( 'Active' ) ); ?>; s.classList.add( 'is-active' ); }
		var m = document.querySelector( '[data-ty-manage]' );
		if ( m ) { m.hidden = false; }
	}, 1800 );
	</script>
	<?php
}

/* -------------------------------------------------------------------------
 * Styles (printed once per page). Card strip = production's CSS; the
 * explainer, dialogs and checkout use WPDS tokens declared locally.
 * ---------------------------------------------------------------------- */

function untangling_mktf_styles() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<style>
	.untangling-mktf, .untangling-mktf-tb, .untangling-mktf-dialog-wrap {
		--wpds-color-background-surface-neutral: #fff;
		--wpds-color-background-surface-neutral-weak: #f6f7f7;
		--wpds-color-background-surface-success-weak: #edfaef;
		--wpds-color-background-interactive-brand-strong: #3858e9;
		--wpds-color-background-interactive-brand-strong-active: #2145e6;
		--wpds-color-foreground-content-neutral: #1e1e1e;
		--wpds-color-foreground-content-neutral-weak: #757575;
		--wpds-color-foreground-content-success: #00450c;
		--wpds-color-foreground-interactive-brand: #3858e9;
		--wpds-color-stroke-surface-neutral: #dcdcde;
		--wpds-color-stroke-surface-neutral-weak: #f0f0f0;
		--wpds-color-stroke-interactive-brand: #3858e9;
		--wpds-border-radius-sm: 2px;
		--wpds-border-radius-lg: 8px;
		--wpds-dimension-padding-md: 12px;
		--wpds-dimension-padding-lg: 16px;
		--wpds-dimension-padding-xl: 24px;
		--wpds-dimension-gap-sm: 8px;
		--wpds-dimension-gap-md: 12px;
		--wpds-dimension-gap-lg: 16px;
		--wpds-typography-font-size-sm: 12px;
		--wpds-typography-font-size-md: 13px;
		--wpds-typography-font-size-lg: 15px;
		--wpds-typography-font-size-xl: 20px;
		--wpds-typography-font-weight-emphasis: 600;
	}
	/* --- production card strip (jetpack-mu-wpcom css/marketplace-tab.css) --- */
	.wpcom-marketplace-card__headline, .wpcom-marketplace-card__alternative { display: flex; flex-wrap: wrap; align-items: baseline; gap: 2px 8px; margin: 0; }
	.wpcom-marketplace-card__amount { font-size: 16px; line-height: 23px; font-weight: 600; font-variant-numeric: tabular-nums; letter-spacing: -0.01em; }
	.wpcom-marketplace-card__per { margin-inline-start: -6px; color: #646970; font-size: 13px; }
	.wpcom-marketplace-card__note { color: #646970; font-size: 12px; }
	.wpcom-marketplace-card__saving { align-self: center; border-radius: 3px; padding: 2px 6px; background: #d5eddb; color: #00450c; font-size: 11px; font-weight: 600; line-height: 1.45; white-space: nowrap; }
	.plugin-card .plugin-card-bottom .wpcom-marketplace-card__headline { min-block-size: 23px; line-height: inherit; max-inline-size: max(180px, 100% - 190px); }
	.plugin-card .plugin-card-bottom .wpcom-marketplace-card__alternative { min-block-size: 18px; }
	.plugin-card .plugin-card-bottom .wpcom-marketplace-card__headline + .column-updated { inline-size: min(190px, 100% - 180px); }
	@media (min-width: 1101px), (min-width: 481px) and (max-width: 781px) {
		.plugin-card .plugin-action-buttons .button.wpcom-marketplace-card__upgrade { display: inline-flex; align-items: center; justify-content: center; block-size: auto; padding-block: 4px; line-height: 1.4; text-align: center; white-space: normal; }
	}
	/* Core's own layout switch for these cards: the action column sits on the
	   right from 1101px (and 481–781px) by VIEWPORT width, as in production.
	   The prototype's container query (600px card width) is overridden by the
	   extra class in the selector. */
	@media (min-width: 1101px), (min-width: 481px) and (max-width: 781px) {
		.plugin-install-php .wrap .untangling-mktf .plugin-card .action-links { position: absolute; top: 20px; right: 20px; width: 120px; margin-left: 0; }
		.plugin-install-php .wrap .untangling-mktf .plugin-card .plugin-action-buttons { float: right; margin: 0 0 1em; text-align: right; }
		.plugin-install-php .wrap .untangling-mktf .plugin-card .plugin-action-buttons li { display: block; margin-bottom: 10px; }
		.plugin-install-php .wrap .untangling-mktf .plugin-card .plugin-action-buttons li .button { margin-right: 0; }
		.plugin-install-php .wrap .untangling-mktf .plugin-card .name,
		.plugin-install-php .wrap .untangling-mktf .plugin-card .desc > p { margin-right: 128px; }
		.plugin-install-php .wrap .untangling-mktf .plugin-card .desc { margin-right: 0; }
	}
	@media (min-width: 782px) and (max-width: 1100px), (max-width: 480px) {
		.plugin-install-php .wrap .untangling-mktf .plugin-card .action-links { position: static; margin-left: 148px; width: auto; }
		.plugin-install-php .wrap .untangling-mktf .plugin-card .plugin-action-buttons { float: none; margin: 1em 0 0; text-align: left; }
		.plugin-install-php .wrap .untangling-mktf .plugin-card .plugin-action-buttons li { display: inline-block; vertical-align: middle; margin-bottom: 10px; }
		.plugin-install-php .wrap .untangling-mktf .plugin-card .plugin-action-buttons li .button { margin-right: 20px; }
		.plugin-install-php .wrap .untangling-mktf .plugin-card .name,
		.plugin-install-php .wrap .untangling-mktf .plugin-card .desc,
		.plugin-install-php .wrap .untangling-mktf .plugin-card .desc > p { margin-right: 0; }
	}
	/* Strip as two flex columns, each line on one row, whatever the card width:
	   left = price + alternative, right = updated + compatibility. Core's
	   floated 180px columns are neutralised; the right column truncates
	   before anything wraps. */
	.plugin-install-php .wrap .untangling-mktf .plugin-card .plugin-card-bottom { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 8px 12px; }
	.plugin-install-php .wrap .untangling-mktf .plugin-card .plugin-card-bottom::after { display: none; }
	.untangling-mktf-strip-left, .untangling-mktf-strip-right { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
	/* The right column (Last Updated, Compatible) never shrinks or truncates;
	   the left column gives way, and only its alternative line can ellipsize. */
	/* Neither column shrinks: when both do not fit on one row (cards under
	   ~500px), the right column wraps under the left, right-aligned. Every
	   line stays whole at every width. */
	.untangling-mktf-strip-left { flex: 0 0 auto; }
	.untangling-mktf-strip-right { flex: 0 0 auto; margin-left: auto; align-items: flex-end; text-align: right; }
	.plugin-install-php .wrap .untangling-mktf .plugin-card .plugin-card-bottom > div > div { float: none; clear: none; width: auto; inline-size: auto; min-block-size: 0; }
	.plugin-install-php .wrap .untangling-mktf .plugin-card .plugin-card-bottom .untangling-mktf-strip-left > div { max-inline-size: none; }
	.plugin-install-php .wrap .untangling-mktf .plugin-card .plugin-card-bottom .untangling-mktf-strip-right > div { max-inline-size: none; }
	.untangling-mktf .wpcom-marketplace-card__headline, .untangling-mktf .wpcom-marketplace-card__alternative { flex-wrap: nowrap; white-space: nowrap; min-block-size: 20px; }
	.untangling-mktf .wpcom-marketplace-card__alternative { display: block; }
	.untangling-mktf .wpcom-marketplace-card__note { white-space: nowrap; }
	.untangling-mktf .wpcom-marketplace-card__note .wpcom-marketplace-card__saving { margin-left: 6px; }
	.untangling-mktf-strip-right .column-updated, .untangling-mktf-strip-right .column-compatibility { white-space: nowrap; }
	.wpcom-marketplace-label { display: block; inline-size: fit-content; margin-block-end: 6px; border-radius: 3px; padding: 2px 6px; background: #f0f6fc; color: #135e96; font-size: 11px; font-weight: 600; line-height: 1.45; }
	.wpcom-marketplace-intro { max-inline-size: 60em; margin: 1em 0; color: #50575e; }
	/* Production caps the intro at 60em, which wraps it onto two lines; the proposal lets it run. */
	.untangling-mktf--proposal .wpcom-marketplace-intro { max-inline-size: none; }
	.untangling-mktf-count { text-align: right; color: #50575e; font-size: 13px; margin: 0 0 12px; }
	.untangling-mktf--proposal .untangling-marketplace { margin-top: 20px; }
	.untangling-mktf .plugin-card .plugin-dependencies { margin: 16px 20px 20px; border-left: 4px solid #3858e9; padding: 6px 0 6px 12px; }
	.untangling-mktf .plugin-card .plugin-dependencies-explainer-text { margin: 0 0 6px; font-weight: 600; }
	.untangling-mktf .plugin-card .plugin-dependency { display: flex; justify-content: space-between; gap: 16px; font-size: 13px; }
	.untangling-mktf .plugin-card .plugin-dependency a { white-space: nowrap; }
	.untangling-mktf .plugin-card .column-description p { margin-top: 0; }
	.plugin-install-php .untangling-mktf .plugin-card .column-description p:first-of-type { -webkit-line-clamp: 4; }
	.untangling-mktf-success { display: flex; align-items: center; gap: 10px; margin: 16px 0 0; padding: 12px 16px; background: var(--wpds-color-background-surface-success-weak); border: 1px solid #b8e6bf; border-radius: var(--wpds-border-radius-lg); font-size: var(--wpds-typography-font-size-md); color: var(--wpds-color-foreground-content-neutral); }
	.untangling-mktf-success p { margin: 0; }
	.untangling-mktf.is-details .untangling-mktf-success { margin: 0 0 24px; }
	.untangling-mktf-success + .untangling-mktf-hero { margin-top: 16px; }
	/* --- proposal: explainer block --- */
	/* --- proposal: WordPress.com showcase hero (compact wpcom-themes-banner metrics) --- */
	@font-face { font-display: swap; font-family: Recoleta; font-weight: 400; src: url(https://s1.wp.com/i/fonts/recoleta/400.woff2) format("woff2"), url(https://s1.wp.com/i/fonts/recoleta/400.woff) format("woff"); }
	.untangling-mktf-hero { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; background-color: #242424; padding: 24px 32px; border-radius: 10px; margin: 20px 0 0; background-repeat: no-repeat; background-position: bottom right 48px; background-size: 340px; }
	.untangling-mktf-hero__content { width: 540px; max-width: 100%; }
	.untangling-mktf-hero__content img { height: 16px; width: auto; display: block; }
	#wpcontent .untangling-mktf-hero h3, #wpcontent .untangling-mktf-hero p { font-weight: 400; letter-spacing: -0.32px; margin: 8px 0; text-wrap: pretty; }
	.untangling-mktf-hero h3 { font-family: Recoleta, serif; font-size: 24px; line-height: 32px; color: #fff; }
	.untangling-mktf-hero p { font-size: 14px; line-height: 20px; color: #a7aaad; }
	.untangling-mktf-hero__cta, .untangling-mktf-hero__cta:visited { display: inline-flex; align-items: center; box-sizing: border-box; height: 40px; padding: 0 16px; background-color: var(--wpds-color-background-interactive-brand-strong); color: #fff; border-radius: var(--wpds-border-radius-sm); font-size: var(--wpds-typography-font-size-md); font-weight: var(--wpds-typography-font-weight-emphasis); line-height: 20px; text-decoration: none; margin-top: 12px; }
	.untangling-mktf-hero__cta:hover, .untangling-mktf-hero__cta:focus { background-color: #fff; color: #1d2327; }
	@media ( max-width: 1120px ) { .untangling-mktf-hero { background-position: bottom right 5px; background-size: 280px; } }
	@media ( max-width: 850px ) { .untangling-mktf-hero { background-image: none !important; } }
	.is-core-tab .untangling-mktf-hero { margin: 16px 0 12px; }
	/* --- product page extras --- */
	.untangling-mktf-buy-sub { font-size: 13px; color: var(--mkt-gray-60, #50575e); margin: 12px 0 0 !important; }
	.untangling-mktf.is-details .untangling-mkt-plugdetail-meta { display: flex; gap: 32px; margin: 16px 0 24px; }
	.untangling-mktf.is-details .untangling-mkt-plugdetail-meta div { display: flex; flex-direction: column; gap: 2px; font-size: 13px; }
	.untangling-mktf.is-details .untangling-mkt-plugdetail-meta span { color: #646970; }
	/* --- generic dialog (One more step, post-purchase address) --- */
	.untangling-mktf-dialog-wrap { position: fixed; inset: 0; z-index: 100100; background: rgba(0,0,0,.35); display: none; align-items: center; justify-content: center; padding: 16px; }
	.untangling-mktf-dialog-wrap.is-open { display: flex; }
	.untangling-mktf-dialog { position: relative; width: 500px; max-width: 100%; background: var(--wpds-color-background-surface-neutral); border-radius: var(--wpds-border-radius-lg); padding: var(--wpds-dimension-padding-xl); box-shadow: 0 10px 40px rgba(0,0,0,.2); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: var(--wpds-color-foreground-content-neutral); }
	.untangling-mktf-dialog h2 { margin: 0 0 12px; font-size: var(--wpds-typography-font-size-xl); font-weight: 500; }
	.untangling-mktf-dialog p { margin: 0 0 12px; font-size: var(--wpds-typography-font-size-md); line-height: 1.5; color: var(--wpds-color-foreground-content-neutral); }
	.untangling-mktf-dialog a { color: var(--wpds-color-foreground-interactive-brand); }
	.untangling-mktf-dialog-list { list-style: none; margin: 0 0 16px; padding: 0; }
	.untangling-mktf-dialog-list li { position: relative; padding-left: 24px; margin: 0 0 8px; font-size: var(--wpds-typography-font-size-md); line-height: 1.5; color: var(--wpds-color-foreground-content-neutral); }
	.untangling-mktf-dialog-list li::before { content: "✓"; position: absolute; left: 2px; top: 0; color: #00a32a; font-weight: 600; }
	.untangling-mktf-dialog-close { position: absolute; top: 14px; right: 14px; border: 0; background: none; font-size: 16px; cursor: pointer; color: #1e1e1e; padding: 6px; line-height: 1; }
	.untangling-mktf-addr { display: flex; justify-content: space-between; align-items: center; gap: 12px; background: var(--wpds-color-background-surface-neutral-weak); border-radius: 4px; padding: 12px 14px; margin: 8px 0; font-size: var(--wpds-typography-font-size-md); }
	.untangling-mktf-addr em { font-style: normal; font-size: var(--wpds-typography-font-size-sm); border-radius: 3px; padding: 2px 8px; background: #dcdcde; color: #2c3338; }
	.untangling-mktf-addr em.is-new { background: #b8e6bf; color: #00450c; }
	.untangling-mktf-dialog-actions { display: flex; justify-content: flex-end; gap: var(--wpds-dimension-gap-sm); margin-top: 20px; align-items: center; }
	.untangling-mktf-dialog-actions.is-split { justify-content: space-between; }
	.untangling-mktf-help { font-size: 13px; }
	.untangling-mktf-btn { display: inline-flex; align-items: center; justify-content: center; border-radius: var(--wpds-border-radius-sm); font-size: 14px; font-weight: 500; line-height: 1; padding: 12px 20px; text-decoration: none; cursor: pointer; border: 0; font-family: inherit; }
	.untangling-mktf-btn.is-primary { background: var(--wpds-color-background-interactive-brand-strong); color: #fff; }
	.untangling-mktf-btn.is-primary:hover { background: var(--wpds-color-background-interactive-brand-strong-active); color: #fff; }
	.untangling-mktf-btn.is-secondary { background: #fff; color: var(--wpds-color-foreground-interactive-brand); box-shadow: inset 0 0 0 1px var(--wpds-color-stroke-interactive-brand); }
	.untangling-mktf-btn.is-block { display: flex; width: 100%; box-sizing: border-box; }
	.untangling-mktf-btn.is-disabled { opacity: .5; pointer-events: none; }
	/* --- production details modal (thickbox look) --- */
	.untangling-mktf-tb { position: fixed; inset: 0; z-index: 100050; background: rgba(0,0,0,.7); display: flex; align-items: flex-start; justify-content: center; padding: 30px 16px; overflow: auto; }
	.untangling-mktf-tb[hidden] { display: none; }
	.untangling-mktf-tb-window { position: relative; width: 752px; max-width: 100%; background: #fff; display: flex; flex-direction: column; max-height: calc(100vh - 60px); }
	.untangling-mktf-tb-close { position: absolute; top: 0; right: -30px; width: 30px; height: 30px; border: 0; background: #fff; cursor: pointer; font-size: 14px; }
	.untangling-mktf-tb-banner { height: 240px; background-size: cover; background-position: center; display: flex; align-items: flex-end; padding: 16px; flex-shrink: 0; }
	.untangling-mktf-tb-banner h2 { margin: 0; background: rgba(30,30,30,.9); color: #fff; font-size: 24px; padding: 8px 14px; border-radius: 4px; }
	.untangling-mktf-tb-tabs { border-bottom: 1px solid #dcdcde; padding: 0 16px; display: flex; gap: 4px; flex-shrink: 0; }
	.untangling-mktf-tb-tabs a { padding: 12px 10px; text-decoration: none; color: #2271b1; font-size: 13px; border: 1px solid transparent; border-bottom: 0; }
	.untangling-mktf-tb-tabs a.is-current { background: #fff; color: #1e1e1e; border-color: #dcdcde; margin-bottom: -1px; }
	.untangling-mktf-tb-body { display: grid; grid-template-columns: minmax(0,1fr) 230px; overflow: auto; }
	.untangling-mktf-tb-content { padding: 24px 24px 24px 24px; font-size: 14px; line-height: 1.6; color: #1e1e1e; }
	.untangling-mktf-tb-content h2 { font-size: 16px; margin: 0 0 12px; }
	.untangling-mktf-tb-meta { background: #f6f7f7; padding: 20px 16px; font-size: 13px; }
	.untangling-mktf-tb-meta p { margin: 0 0 8px; }
	.untangling-mktf-tb-foot { height: 60px; border-top: 1px solid #dcdcde; background: #f6f7f7; flex-shrink: 0; }
	/* --- checkout (Calypso composite checkout look) --- */
	.untangling-mktf-co { display: grid; grid-template-columns: minmax(0, 1fr) 370px; gap: 48px; align-items: start; max-width: 1040px; margin: 0 auto; padding: 24px 0 64px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #1e1e1e; }
	@media ( max-width: 900px ) { .untangling-mktf-co { grid-template-columns: 1fr; } }
	.untangling-mktf-co h1 { font-size: 40px; margin: 0 0 24px; }
	.untangling-mktf-check { display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; border-radius: 50%; background: #00a32a; color: #fff; font-size: 12px; margin-right: 8px; }
	.untangling-mktf-co-step { margin-bottom: 28px; }
	.untangling-mktf-co-step h2 { display: flex; align-items: center; font-size: 18px; font-weight: 600; margin: 0 0 6px; }
	.untangling-mktf-co-step h2 .untangling-mktf-edit { margin-left: auto; font-size: 13px; font-weight: 400; }
	.untangling-mktf-co-meta { margin: 0 0 20px 28px; font-size: 13px; color: #50575e; line-height: 1.6; }
	.untangling-mktf-item { margin: 0 0 24px 28px; }
	.untangling-mktf-item[hidden] { display: none; }
	.untangling-mktf-item-head { display: flex; justify-content: space-between; gap: 16px; margin-bottom: 10px; }
	.untangling-mktf-item-head strong { display: block; font-size: 16px; }
	.untangling-mktf-item-head small { display: block; color: #50575e; font-size: 12px; margin-top: 2px; }
	.untangling-mktf-item-price { text-align: right; }
	.untangling-mktf-item-price strong { font-size: 16px; }
	.untangling-mktf-item-price small s { color: #757575; }
	.untangling-mktf-item-price small[hidden] { display: none; }
	.untangling-mktf-terms { display: flex; flex-direction: column; gap: 8px; }
	.untangling-mktf-term { display: flex; align-items: center; gap: 10px; border: 1px solid #dcdcde; border-radius: 4px; padding: 12px 14px; cursor: pointer; font-size: 14px; }
	.untangling-mktf-term.is-selected { border-color: #3858e9; box-shadow: inset 0 0 0 1px #3858e9; }
	.untangling-mktf-term input { margin: 0; }
	.untangling-mktf-term-price { margin-left: auto; font-size: 14px; }
	.untangling-mktf-save { font-style: normal; font-size: 11px; font-weight: 600; background: #d5eddb; color: #00450c; border-radius: 3px; padding: 2px 6px; margin-right: 8px; }
	.untangling-mktf-select { display: block; margin-top: 2px; width: 100%; max-width: 100%; box-sizing: border-box; font-size: 14px; padding: 10px 12px; border: 1px solid #dcdcde; border-radius: 4px; }
	.untangling-mktf-remove { display: inline-block; margin-top: 12px; font-size: 13px; color: #3858e9; }
	.untangling-mktf-item .untangling-mktf-remove { display: block; width: fit-content; }
	.untangling-mktf-co-hosting { margin: 16px 0 0 28px; font-size: 13px; color: #50575e; background: #f6f7f7; border-radius: 4px; padding: 10px 12px; }
	.untangling-mktf-co-legal { margin: 32px 0 0 28px; font-size: 12px; color: #50575e; line-height: 1.6; }
	.untangling-mktf-co-summary { border: 1px solid #dcdcde; border-radius: 4px; padding: 24px; background: #fff; position: sticky; top: 88px; }
	.untangling-mktf-co-summary h2 { margin: 0 0 16px; font-size: 18px; font-weight: 600; }
	.untangling-mktf-sum-row { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; font-size: 13px; margin: 0 0 10px; }
	.untangling-mktf-sum-row > :first-child { flex: 1; min-width: 0; display: flex; align-items: flex-start; gap: 8px; }
	.untangling-mktf-sum-row .untangling-mktf-check { flex-shrink: 0; margin-right: 0; }
	.untangling-mktf-sum-row > :last-child { white-space: nowrap; text-align: right; flex-shrink: 0; }
	.untangling-mktf-sum-row[hidden] { display: none; }
	.untangling-mktf-sum-row s { color: #757575; margin-right: 4px; }
	.untangling-mktf-sum-row.is-sub { font-size: 13px; color: #50575e; margin-bottom: 6px; }
	.untangling-mktf-sum-row.is-sub strong { color: #1e1e1e; }
	.untangling-mktf-sum-row.is-total { font-size: 18px; margin: 14px 0 16px; }
	.untangling-mktf-sum-row .is-green { color: #00450c; }
	.untangling-mktf-sum-divider { border-top: 1px solid #dcdcde; margin: 14px 0; }
	.untangling-mktf-sum-note { font-size: 12px; color: #50575e; margin: 12px 0 0; }
	.untangling-mktf-sum-fine { font-size: 11px; color: #757575; margin: 14px 0 0; border-top: 1px solid #dcdcde; padding-top: 12px; line-height: 1.5; }
	/* --- thank-you --- */
	.untangling-mktf-ty { max-width: 640px; margin: 48px auto; text-align: center; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #1e1e1e; }
	.untangling-mktf-ty h1 { font-size: 36px; margin: 0 0 12px; }
	.untangling-mktf-ty-lede { font-size: 15px; color: #50575e; margin: 0 0 32px; }
	.untangling-mktf-ty-card { display: flex; align-items: center; gap: 16px; text-align: left; border: 1px solid #dcdcde; border-radius: 8px; padding: 16px 20px; margin: 0 0 20px; }
	.untangling-mktf-ty-card img { width: 48px; height: 48px; border-radius: 8px; }
	.untangling-mktf-ty-card > div { flex: 1; }
	.untangling-mktf-ty-card strong { display: block; font-size: 15px; }
	.untangling-mktf-ty-status { font-size: 13px; color: #50575e; }
	.untangling-mktf-ty-status.is-active { color: #00450c; font-weight: 600; }
	.untangling-mktf-ty-steps { display: grid; gap: 12px; text-align: left; margin: 0 0 32px; }
	.untangling-mktf-ty-steps div { display: flex; flex-direction: column; gap: 2px; font-size: 13px; color: #50575e; }
	.untangling-mktf-ty-steps strong { color: #1e1e1e; font-size: 14px; }
	.untangling-mktf-ty-actions { display: flex; gap: 12px; justify-content: center; }
	</style>
	<?php
}
