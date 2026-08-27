<?php
/**
 * Plugin Name: Untangling Site Config (Quick start demo)
 * Description: Demo identity for the shared untangling prototype plugin — all-in Dashboard / Business scenario, WordPress Playground edition.
 */
define( 'UNTANGLING_STANDALONE', true );
// Not a locked demo: the Prototype controls (bottom-right fab) are visible and
// every toggle works, so visitors can compare the variants themselves. The
// starting scenario — all-in Dashboard widgets, Business plan on Atomic,
// Marketplace split, omnibar upsell — comes from the blueprint's seeded
// options and the plan below, not from UNTANGLING_FORCE_* constants (those
// would pin the toggles).
define( 'UNTANGLING_PLAN', 'Business' );
define( 'UNTANGLING_SITE_SLUG', 'aperture-diaries.wordpress.com' );
define( 'UNTANGLING_PRIMARY_DOMAIN', 'aperture-diaries.com' );
define( 'UNTANGLING_DOMAIN_UPSELL', 'aperture-diaries.com' );
