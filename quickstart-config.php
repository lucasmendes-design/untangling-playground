<?php
/**
 * Plugin Name: Untangling Site Config (Quick start demo)
 * Description: Demo identity for the shared untangling prototype plugin — My Site / Just created scenario, WordPress Playground edition.
 */
define( 'UNTANGLING_STANDALONE', true );
// One fixed scenario: the My Site drawer (sidebar item below Dashboard) on a
// just-created Simple site, so visitors land on the Next steps launchpad.
// No Prototype controls, no URL toggles.
define( 'UNTANGLING_LOCKED_DEMO', true );
define( 'UNTANGLING_FORCE_VARIANT', 'drawer' );
define( 'UNTANGLING_FORCE_MS_STATE', 'new' );
define( 'UNTANGLING_FORCE_SITE_TYPE', 'simple' );
define( 'UNTANGLING_FORCE_MARKETPLACE', 'tabs' );
define( 'UNTANGLING_FORCE_PLAN_FILTER', 'dropdown' );
define( 'UNTANGLING_PLAN', 'Free' );
define( 'UNTANGLING_SITE_SLUG', 'aperture-diaries.wordpress.com' );
define( 'UNTANGLING_PRIMARY_DOMAIN', 'aperture-diaries.wordpress.com' );
define( 'UNTANGLING_DOMAIN_UPSELL', 'aperture-diaries.com' );
