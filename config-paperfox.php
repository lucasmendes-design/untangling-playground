<?php
/**
 * Plugin Name: Untangling Site Config (Paper Fox Prints)
 * Description: Demo identity for the shared untangling prototype plugin — combined MSD + WP Admin demo, WordPress Playground edition.
 */
define( 'UNTANGLING_PLAN', 'Business' );
define( 'UNTANGLING_SITE_SLUG', 'paperfoxprints.com' );
define( 'UNTANGLING_PRIMARY_DOMAIN', 'paperfoxprints.com' );
define( 'UNTANGLING_DOMAIN_UPSELL', 'paperfox.art' );
// MSD links point at the calypso.live preview of the prototype branch. The
// redirector needs the branch/env args on every link; the shared plugin
// appends UNTANGLING_MSD_QUERY at click time.
define( 'UNTANGLING_MSD_URL', 'https://calypso.live' );
define( 'UNTANGLING_MSD_QUERY', 'branch=prototype/untangling-ia&env=dashboard&persona=developer' );
