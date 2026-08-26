<?php
/**
 * Plugin Name: Untangling Site Config (Cast Iron Supply Co)
 * Description: Demo identity for the shared untangling prototype plugin — combined MSD + WP Admin demo, WordPress Playground edition.
 */
define( 'UNTANGLING_PLAN', 'Commerce' );
define( 'UNTANGLING_SITE_SLUG', 'castironsupply.com' );
define( 'UNTANGLING_PRIMARY_DOMAIN', 'castironsupply.com' );
define( 'UNTANGLING_DOMAIN_UPSELL', 'castiron.shop' );
// MSD links point at the calypso.live preview of the prototype branch. The
// redirector needs the branch/env args on every link; the shared plugin
// appends UNTANGLING_MSD_QUERY at click time.
define( 'UNTANGLING_MSD_URL', 'https://calypso.live' );
define( 'UNTANGLING_MSD_QUERY', 'branch=prototype/untangling-ia&env=dashboard&persona=developer' );
