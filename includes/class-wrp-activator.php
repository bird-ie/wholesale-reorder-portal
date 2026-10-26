<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRP_Activator {

	public static function activate() {
		self::create_portal_page();
	}

	private static function create_portal_page() {
		$existing_page_id = get_option( 'wrp_portal_page_id', 0 );

		// Check if a page is already assigned
		if ( $existing_page_id && get_post( $existing_page_id ) ) {
			return;
		}

		// Check if a page with the slug exists
		$existing_page = get_page_by_path( 'wholesale-portal' );

		if ( $existing_page ) {
			update_option( 'wrp_portal_page_id', $existing_page->ID );
			return;
		}

		// Create a new page
		$page_id = wp_insert_post( array(
			'post_title'   => 'Wholesale Portal',
			'post_content' => '[wrp_reorder_portal]',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_name'    => 'wholesale-portal',
		) );

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( 'wrp_portal_page_id', $page_id );
		}
	}
}