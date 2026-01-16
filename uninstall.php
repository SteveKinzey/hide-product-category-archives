<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

$terms = get_terms( [
	'taxonomy'   => 'product_cat',
	'hide_empty' => false,
	'fields'     => 'ids',
] );

if ( ! is_wp_error( $terms ) ) {
	foreach ( $terms as $term_id ) {
		delete_term_meta( (int) $term_id, '_hpc_hide_product_cat_archive' );
	}
}