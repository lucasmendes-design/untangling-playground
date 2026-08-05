<?php
/**
 * Plugin Name: Untangling Site Config (playground demo)
 * Description: Demo identity for the shared untangling prototype plugin, WordPress Playground edition.
 */
define( 'UNTANGLING_STANDALONE', true );
// One fixed scenario: Marketplace tabs + Included plan filter, no Prototype
// controls, no URL toggles.
define( 'UNTANGLING_LOCKED_DEMO', true );
define( 'UNTANGLING_FORCE_MARKETPLACE', 'tabs' );
define( 'UNTANGLING_FORCE_PLAN_FILTER', 'included' );
define( 'UNTANGLING_PLAN', 'Free' );
define( 'UNTANGLING_SITE_SLUG', 'aperture-diaries.wordpress.com' );
define( 'UNTANGLING_PRIMARY_DOMAIN', 'aperture-diaries.wordpress.com' );
define( 'UNTANGLING_DOMAIN_UPSELL', 'aperture-diaries.com' );
