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

require_once WRP_PLUGIN_DIR . 'includes/class-wrp-roles.php';

function wrp_run_plugin() {
	WRP_Roles::init();
}
add_action( 'plugins_loaded', 'wrp_run_plugin' );