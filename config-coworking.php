<?php
/**
 * Plugin Name: Untangling Site Config (Core Coworking)
 * Description: Demo identity for the shared untangling prototype plugin — combined MSD + WP Admin demo, WordPress Playground edition.
 */
define( 'UNTANGLING_PLAN', 'Business' );
define( 'UNTANGLING_SITE_SLUG', 'corecoworking.com' );
define( 'UNTANGLING_PRIMARY_DOMAIN', 'corecoworking.com' );
define( 'UNTANGLING_DOMAIN_UPSELL', 'corecoworking.space' );
// MSD links point at the calypso.live preview of the prototype branch. The
// redirector needs the branch/env args on every link; the shared plugin
// appends UNTANGLING_MSD_QUERY at click time.
define( 'UNTANGLING_MSD_URL', 'https://calypso.live' );
define( 'UNTANGLING_MSD_QUERY', 'branch=prototype/untangling-ia&env=dashboard&persona=developer' );
