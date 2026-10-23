<?php
/**
 * Plugin Name: Untangling Site Config (Quick start demo)
 * Description: Demo identity for the shared untangling prototype plugin — all-in Dashboard / Personal scenario, WordPress Playground edition.
 */
define( 'UNTANGLING_STANDALONE', true );
// Not a locked demo: the Prototype controls (bottom-right fab) are visible and
// every toggle works, so visitors can compare the variants themselves. The
// starting scenario — all-in Dashboard widgets, Personal plan (Simple, with
// the custom domain), Marketplace tabs with the Included plan filter, no
// upsell — comes from the blueprint's seeded options and the plan below, not
// from UNTANGLING_FORCE_* constants (those would pin the toggles). Flip the
// Plan segment in the Prototype controls to see the other plans.
// blueprint-themes.json shares this file: it seeds the plan override to
// Business so the partner theme subscription state is on screen, and lands
// on the Marketplace tab.
define( 'UNTANGLING_PLAN', 'Personal' );
define( 'UNTANGLING_SITE_SLUG', 'aperture-diaries.wordpress.com' );
define( 'UNTANGLING_PRIMARY_DOMAIN', 'aperture-diaries.com' );
define( 'UNTANGLING_DOMAIN_UPSELL', 'aperture-diaries.com' );
