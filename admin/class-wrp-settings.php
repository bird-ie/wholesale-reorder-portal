<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRP_Settings {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function add_menu_page() {
		add_submenu_page(
			'options-general.php',
			'Wholesale Reorder Portal Settings',
			'Wholesale Portal',
			'manage_options',
			'wrp-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		register_setting( 'wrp_settings', 'wrp_portal_page_id', array( 'default' => 0 ) );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to access this page.' );
		}

		$selected_page_id = get_option( 'wrp_portal_page_id', 0 );
		?>
		<div class="wrap">
			<h1>Wholesale Reorder Portal Settings</h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'wrp_settings' ); ?>
				<?php do_settings_sections( 'wrp_settings' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="wrp_portal_page_id">Portal Page</label></th>
						<td>
							<?php
							wp_dropdown_pages( array(
								'name'             => 'wrp_portal_page_id',
								'id'               => 'wrp_portal_page_id',
								'selected'         => $selected_page_id,
								'show_option_none' => '— Select Page —',
								'option_none_value' => '',
							) );
							?>
							<p class="description">Select the page where the Wholesale Portal will be displayed.</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr>

			<h2>Manual Shortcode (Optional)</h2>
			<p>If you prefer to use the portal on a different page or build a custom layout, you can manually place this shortcode:</p>
			<code style="display: block; background: #f1f1f1; padding: 10px; border-radius: 4px; font-size: 14px;">[wrp_reorder_portal]</code>
			<p class="description">The portal is only accessible to users with the <strong>Wholesale Buyer</strong> role.</p>
		</div>
		<?php
	}
}