<?php
/**
 * Plugin Name:       Wholesale Reorder Portal
 * Plugin URI:        https://github.com/bird-ie/wholesale-reorder-portal
 * Description:       A B2B portal for wholesale customers to bulk-reorder products via CSV and save order templates.
 * Version:           1.0.0
 * Author:            Sohaib Hassan
 * Author URI:        https://github.com/bird-ie
 * License:           GPL-2.0+
 * Text Domain:       wholesale-reorder-portal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WRP_VERSION', '1.0.0' );
define( 'WRP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WRP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

function wrp_check_woocommerce() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error is-dismissible"><p><strong>Wholesale Reorder Portal</strong> requires WooCommerce to be installed and active.</p></div>';
		});
		return false;
	}
	return true;
}

require_once WRP_PLUGIN_DIR . 'includes/class-wrp-activator.php';
require_once WRP_PLUGIN_DIR . 'includes/class-wrp-roles.php';
require_once WRP_PLUGIN_DIR . 'includes/class-wrp-csv-handler.php';
require_once WRP_PLUGIN_DIR . 'frontend/class-wrp-portal.php';
require_once WRP_PLUGIN_DIR . 'admin/class-wrp-settings.php';

register_activation_hook( __FILE__, array( 'WRP_Activator', 'activate' ) );

function wrp_run_plugin() {
	if ( ! wrp_check_woocommerce() ) {
		return;
	}
	WRP_Roles::init();
	WRP_Portal::init();
	WRP_Settings::init();
}
add_action( 'plugins_loaded', 'wrp_run_plugin' );

// Add Settings link on Plugins page
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function( $links ) {
	$settings_link = '<a href="' . admin_url( 'options-general.php?page=wrp-settings' ) . '">Settings</a>';
	array_unshift( $links, $settings_link );
	return $links;
});