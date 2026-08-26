<?php
/**
 * Plugin Name: Untangling Content Seeder
 * Description: Seeds demo content from mu-plugins/untangling-seed.json. Trigger: /?untangling_seed=run (localhost only, idempotent by version; add &force=1 to re-run). Symlinked as zz-untangling-seeder.php so it loads after the config.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	if ( ! isset( $_GET['untangling_seed'] ) || 'run' !== $_GET['untangling_seed'] ) {
		return;
	}
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	if ( 'localhost' !== $host && '127.0.0.1' !== $host ) {
		return;
	}

	$result = untangling_seed_run( isset( $_GET['force'] ) );
	wp_send_json( $result );
}, 20 );

function untangling_seed_run( $force ) {
	$json_file = __DIR__ . '/untangling-seed.json';
	// __DIR__ resolves through the symlink to the repo; read from the site's own mu-plugins dir.
	$local = WPMU_PLUGIN_DIR . '/untangling-seed.json';
	if ( file_exists( $local ) ) {
		$json_file = $local;
	}
	if ( ! file_exists( $json_file ) ) {
		return array( 'ok' => false, 'error' => 'untangling-seed.json not found' );
	}
	$cfg = json_decode( file_get_contents( $json_file ), true );
	if ( ! is_array( $cfg ) ) {
		return array( 'ok' => false, 'error' => 'invalid JSON' );
	}

	$version = isset( $cfg['version'] ) ? (string) $cfg['version'] : '1';
	$done    = get_option( 'untangling_seed_version' );
	$log     = array();

	if ( $done === $version && ! $force ) {
		return array( 'ok' => true, 'skipped' => 'already seeded v' . $version, 'woo_ready' => class_exists( 'WC_Product_Simple' ) );
	}

	if ( ! empty( $cfg['tagline'] ) ) {
		update_option( 'blogdescription', $cfg['tagline'] );
		$log[] = 'tagline';
	}

	// Default content out of the way.
	$hello = get_post( 1 );
	if ( $hello && 'Hello world!' === $hello->post_title ) {
		wp_delete_post( 1, true );
		$log[] = 'deleted hello-world';
	}

	// Register the pre-placed seed images as attachments.
	$media = untangling_seed_media( $log );

	if ( ! empty( $media['site-icon'] ) ) {
		update_option( 'site_icon', $media['site-icon'] );
		$log[] = 'site icon';
	}

	if ( ! empty( $cfg['posts'] ) ) {
		foreach ( $cfg['posts'] as $post ) {
			untangling_seed_post( $post, $media, $log );
		}
	}

	if ( ! empty( $cfg['pages'] ) ) {
		foreach ( $cfg['pages'] as $page ) {
			untangling_seed_page( $page, $media, $log );
		}
	}

	$woo_ready = class_exists( 'WC_Product_Simple' );
	if ( ! empty( $cfg['woocommerce'] ) && ! $woo_ready ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		if ( file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) {
			$activated = activate_plugin( 'woocommerce/woocommerce.php' );
			$log[] = is_wp_error( $activated ) ? 'woo activation error: ' . $activated->get_error_message() : 'woocommerce activated (products need a second run)';
		} else {
			$log[] = 'woocommerce plugin missing';
		}
	}

	$products_pending = false;
	if ( ! empty( $cfg['products'] ) ) {
		if ( class_exists( 'WC_Product_Simple' ) ) {
			foreach ( $cfg['products'] as $product ) {
				untangling_seed_product( $product, $media, $log );
			}
		} else {
			$products_pending = true;
		}
	}

	// Only stamp the version once everything, products included, is in.
	if ( ! $products_pending ) {
		update_option( 'untangling_seed_version', $version );
	}

	return array( 'ok' => true, 'log' => $log, 'products_pending' => $products_pending );
}

function untangling_seed_media( &$log ) {
	$uploads = wp_upload_dir();
	$dir     = trailingslashit( $uploads['basedir'] ) . 'untangling-seed';
	$map     = array();
	if ( ! is_dir( $dir ) ) {
		$log[] = 'no seed images dir';
		return $map;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	foreach ( glob( $dir . '/*.png' ) as $file ) {
		$name     = basename( $file, '.png' );
		$existing = get_posts( array(
			'post_type'   => 'attachment',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => '_untangling_seed_name',
			'meta_value'  => $name,
		) );
		if ( $existing ) {
			$map[ $name ] = $existing[0];
			continue;
		}
		$rel = 'untangling-seed/' . basename( $file );
		$id  = wp_insert_attachment( array(
			'post_title'     => $name,
			'post_mime_type' => 'image/png',
			'post_status'    => 'inherit',
			'guid'           => trailingslashit( $uploads['baseurl'] ) . $rel,
		), $file );
		if ( is_wp_error( $id ) || ! $id ) {
			$log[] = 'attachment failed: ' . $name;
			continue;
		}
		update_post_meta( $id, '_wp_attached_file', $rel );
		update_post_meta( $id, '_untangling_seed_name', $name );
		$meta = wp_generate_attachment_metadata( $id, $file );
		if ( empty( $meta ) || empty( $meta['width'] ) ) {
			$size = getimagesize( $file );
			$meta = array(
				'width'  => $size ? $size[0] : 1200,
				'height' => $size ? $size[1] : 800,
				'file'   => $rel,
				'sizes'  => array(),
			);
		}
		wp_update_attachment_metadata( $id, $meta );
		$map[ $name ] = $id;
	}
	$log[] = 'media: ' . count( $map );
	return $map;
}

function untangling_seed_exists( $title, $type ) {
	$q = new WP_Query( array(
		'post_type'      => $type,
		'title'          => $title,
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	) );
	return $q->posts ? $q->posts[0] : 0;
}

function untangling_seed_blocks( $paragraphs ) {
	$out = '';
	foreach ( (array) $paragraphs as $p ) {
		$out .= "<!-- wp:paragraph -->\n<p>" . $p . "</p>\n<!-- /wp:paragraph -->\n\n";
	}
	return $out;
}

function untangling_seed_post( $post, $media, &$log ) {
	if ( untangling_seed_exists( $post['title'], 'post' ) ) {
		return;
	}
	$cat_id = 0;
	if ( ! empty( $post['category'] ) ) {
		$term   = term_exists( $post['category'], 'category' );
		$term   = $term ? $term : wp_insert_term( $post['category'], 'category' );
		$cat_id = is_array( $term ) ? (int) $term['term_id'] : 0;
	}
	$days = isset( $post['days_ago'] ) ? (int) $post['days_ago'] : 0;
	$id   = wp_insert_post( array(
		'post_title'    => $post['title'],
		'post_content'  => untangling_seed_blocks( $post['content'] ),
		'post_status'   => 'publish',
		'post_date'     => gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ),
		'post_category' => $cat_id ? array( $cat_id ) : array(),
	) );
	if ( ! $id || is_wp_error( $id ) ) {
		$log[] = 'post failed: ' . $post['title'];
		return;
	}
	if ( ! empty( $post['image'] ) && ! empty( $media[ $post['image'] ] ) ) {
		set_post_thumbnail( $id, $media[ $post['image'] ] );
	}
	if ( ! empty( $post['comments'] ) ) {
		foreach ( $post['comments'] as $comment ) {
			wp_insert_comment( array(
				'comment_post_ID'      => $id,
				'comment_author'       => $comment[0],
				'comment_author_email' => sanitize_title( $comment[0] ) . '@example.com',
				'comment_content'      => $comment[1],
				'comment_approved'     => 1,
				'comment_date'         => gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS + HOUR_IN_SECONDS ),
			) );
		}
	}
	$log[] = 'post: ' . $post['title'];
}

function untangling_seed_page( $page, $media, &$log ) {
	$id = untangling_seed_exists( $page['title'], 'page' );
	if ( ! $id ) {
		$id = wp_insert_post( array(
			'post_title'   => $page['title'],
			'post_content' => untangling_seed_blocks( $page['content'] ),
			'post_status'  => 'publish',
			'post_type'    => 'page',
		) );
		if ( ! $id || is_wp_error( $id ) ) {
			$log[] = 'page failed: ' . $page['title'];
			return;
		}
		$log[] = 'page: ' . $page['title'];
	}
	if ( ! empty( $page['front'] ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $id );
	}
}

function untangling_seed_product( $product, $media, &$log ) {
	if ( untangling_seed_exists( $product['name'], 'product' ) ) {
		return;
	}
	$p = new WC_Product_Simple();
	$p->set_name( $product['name'] );
	$p->set_regular_price( (string) $product['price'] );
	$p->set_description( $product['description'] );
	$p->set_short_description( $product['description'] );
	$p->set_status( 'publish' );
	if ( ! empty( $product['virtual'] ) ) {
		$p->set_virtual( true );
	}
	if ( ! empty( $product['image'] ) && ! empty( $media[ $product['image'] ] ) ) {
		$p->set_image_id( $media[ $product['image'] ] );
	}
	if ( ! empty( $product['stock'] ) ) {
		$p->set_manage_stock( true );
		$p->set_stock_quantity( (int) $product['stock'] );
	}
	$p->save();
	$log[] = 'product: ' . $product['name'];
}
