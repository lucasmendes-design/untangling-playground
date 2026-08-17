<?php
/**
 * Plugin Name: Untangling Site Config (Quick start demo)
 * Description: Demo identity for the shared untangling prototype plugin — My Site / Just created scenario, WordPress Playground edition.
 */
define( 'UNTANGLING_STANDALONE', true );
// Not a locked demo: the Prototype controls (bottom-right fab) are visible and
// every toggle works, so visitors can compare the variants themselves. The
// starting scenario — My Site drawer, just-created Simple site, Marketplace
// tabs — comes from the blueprint's seeded options and the Free plan below,
// not from UNTANGLING_FORCE_* constants (those would pin the toggles).
define( 'UNTANGLING_PLAN', 'Free' );
define( 'UNTANGLING_SITE_SLUG', 'aperture-diaries.wordpress.com' );
define( 'UNTANGLING_PRIMARY_DOMAIN', 'aperture-diaries.wordpress.com' );
define( 'UNTANGLING_DOMAIN_UPSELL', 'aperture-diaries.com' );
