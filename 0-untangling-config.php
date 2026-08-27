<?php
/**
 * Plugin Name: Untangling Site Config (playground demo)
 * Description: Demo identity for the shared untangling prototype plugin, WordPress Playground edition.
 */
define( 'UNTANGLING_STANDALONE', true );
// One fixed scenario: Marketplace tabs + Included plan filter, no Prototype
// controls, no URL toggles. The variant is pinned too — the plugin's default
// is the all-in Dashboard now, and this locked walkthrough was written
// against the My Site drawer.
define( 'UNTANGLING_LOCKED_DEMO', true );
define( 'UNTANGLING_FORCE_VARIANT', 'drawer' );
define( 'UNTANGLING_FORCE_MARKETPLACE', 'tabs' );
define( 'UNTANGLING_FORCE_PLAN_FILTER', 'included' );
define( 'UNTANGLING_PLAN', 'Free' );
define( 'UNTANGLING_SITE_SLUG', 'aperture-diaries.wordpress.com' );
define( 'UNTANGLING_PRIMARY_DOMAIN', 'aperture-diaries.wordpress.com' );
define( 'UNTANGLING_DOMAIN_UPSELL', 'aperture-diaries.com' );
