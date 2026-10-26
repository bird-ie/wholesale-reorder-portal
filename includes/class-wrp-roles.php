<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRP_Roles {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_roles' ) );
	}

	public static function register_roles() {
		$wholesale_caps = array(
			'read'                          => true,
			'wrp_access_reorder_portal'     => true,
			'wrp_upload_csv'                => true,
			'wrp_save_templates'            => true,
		);

		$wholesale_role = get_role( 'wrp_wholesale_buyer' );
		if ( ! $wholesale_role ) {
			add_role( 'wrp_wholesale_buyer', 'Wholesale Buyer', $wholesale_caps );
		}

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( array_keys( $wholesale_caps ) as $cap ) {
				$admin->add_cap( $cap );
			}
		}
	}
}