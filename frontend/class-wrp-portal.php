<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRP_Portal {

	public static function init() {
		add_shortcode( 'wrp_reorder_portal', array( __CLASS__, 'render_portal' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_wrp_upload_csv', array( __CLASS__, 'ajax_upload_csv' ) );
		add_action( 'wp_ajax_wrp_add_to_cart', array( __CLASS__, 'ajax_add_to_cart' ) );
		add_action( 'wp_ajax_wrp_save_template', array( __CLASS__, 'ajax_save_template' ) );
		add_action( 'wp_ajax_wrp_delete_template', array( __CLASS__, 'ajax_delete_template' ) );
		add_action( 'wp_ajax_wrp_reorder_template', array( __CLASS__, 'ajax_reorder_template' ) );
		
		// Custom login handlers
		add_action( 'wp_ajax_nopriv_wrp_custom_login', array( __CLASS__, 'ajax_custom_login' ) );
	}

	public static function enqueue_scripts() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'wrp_reorder_portal' ) ) {
			wp_enqueue_style(
				'wrp-frontend-css',
				WRP_PLUGIN_URL . 'assets/frontend.css',
				array(),
				WRP_VERSION
			);

			wp_enqueue_script(
				'wrp-portal-js',
				WRP_PLUGIN_URL . 'assets/portal.js',
				array( 'jquery' ),
				WRP_VERSION,
				true
			);

			wp_localize_script(
				'wrp-portal-js',
				'wrpAjax',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'wrp_portal_nonce' ),
				)
			);
		}
	}

	public static function render_portal() {
		// If not logged in, show a custom login form
		if ( ! is_user_logged_in() ) {
			ob_start();
			?>
			<div class="wrp-portal">
				<div class="wrp-login-box">
					<h2>Wholesale Portal Login</h2>
					<p>Please log in to access your wholesale reorder portal.</p>
					
					<form id="wrp-login-form">
						<div class="wrp-form-group">
							<label for="wrp_username">Username or Email</label>
							<input type="text" id="wrp_username" name="username" required />
						</div>
						<div class="wrp-form-group">
							<label for="wrp_password">Password</label>
							<input type="password" id="wrp_password" name="password" required />
						</div>
						<button type="submit" class="wrp-btn-primary" style="width: 100%;">Log In</button>
						<div id="wrp-login-message" style="margin-top: 10px; font-weight: bold;"></div>
					</form>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		// If logged in but not a wholesale buyer, show access denied
		if ( ! current_user_can( 'wrp_access_reorder_portal' ) ) {
			$message = '<div class="wrp-notice wrp-notice-error" style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';
			$message .= '<strong>Access Denied.</strong><br>';
			$message .= 'Your account does not have Wholesale Buyer permissions. Please contact the store administrator to upgrade your account.';
			$message .= '</div>';
			return $message;
		}

		$user_id = get_current_user_id();
		$templates = WRP_CSV_Handler::get_templates( $user_id );

		ob_start();
		?>
		<div class="wrp-portal">
			<h1>Wholesale Reorder Portal</h1>
			<p>Upload a CSV file with your order. The file must have two columns: <strong>SKU</strong> and <strong>Quantity</strong>.</p>

			<div class="wrp-upload-box">
				<input type="file" id="wrp-csv-file" accept=".csv" />
				<button type="button" id="wrp-upload-btn" class="wrp-btn-primary">Upload CSV</button>
				<div id="wrp-upload-message"></div>
			</div>

			<div id="wrp-results-container" style="display:none;">
				<h3>Validation Results</h3>
				<table id="wrp-results-table">
					<thead>
						<tr>
							<th>Row</th>
							<th>SKU</th>
							<th>Quantity</th>
							<th>Status</th>
							<th>Message</th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
				<div style="margin-top: 15px;">
					<button type="button" id="wrp-add-to-cart-btn" class="wrp-btn-success">Add All to Cart</button>
					<button type="button" id="wrp-save-template-btn" class="wrp-btn-secondary" style="margin-left: 10px;">Save as Template</button>
				</div>
				<div id="wrp-cart-message" style="margin-top: 10px; font-weight: bold;"></div>
			</div>

			<!-- Template Save Modal -->
			<div id="wrp-save-template-modal" class="wrp-modal" style="display:none;">
				<div class="wrp-modal-content">
					<h3>Save as Template</h3>
					<p>Give your template a name (e.g., "Weekly Order - Restaurant").</p>
					<input type="text" id="wrp-template-name" class="wrp-input" placeholder="Template name" />
					<div style="margin-top: 15px;">
						<button type="button" id="wrp-confirm-save-template" class="wrp-btn-primary">Save Template</button>
						<button type="button" id="wrp-cancel-save-template" class="wrp-btn-secondary">Cancel</button>
					</div>
					<div id="wrp-template-save-message" style="margin-top: 10px;"></div>
				</div>
			</div>

			<!-- Saved Templates -->
			<?php if ( ! empty( $templates ) ) : ?>
				<div class="wrp-templates-section">
					<h2>Saved Order Templates</h2>
					<div class="wrp-templates-grid">
						<?php foreach ( $templates as $template ) : ?>
							<div class="wrp-template-card">
								<div>
									<strong><?php echo esc_html( $template['name'] ); ?></strong>
									<p style="color: #6b7280; font-size: 12px;"><?php echo esc_html( $template['created_at'] ); ?> | <?php echo count( $template['items'] ); ?> items</p>
								</div>
								<div style="display: flex; gap: 10px;">
									<button type="button" class="wrp-btn-primary wrp-reorder-template" data-template-id="<?php echo esc_attr( $template['id'] ); ?>">Reorder</button>
									<button type="button" class="wrp-btn-danger wrp-delete-template" data-template-id="<?php echo esc_attr( $template['id'] ); ?>">Delete</button>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function ajax_custom_login() {
		check_ajax_referer( 'wrp_portal_nonce', 'nonce' );

		$username = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
		$password = isset( $_POST['password'] ) ? $_POST['password'] : '';

		if ( empty( $username ) || empty( $password ) ) {
			wp_send_json_error( array( 'message' => 'Please enter your username and password.' ) );
		}

		$creds = array(
			'user_login'    => $username,
			'user_password' => $password,
			'remember'      => true,
		);

		$user = wp_signon( $creds, false );

		if ( is_wp_error( $user ) ) {
			wp_send_json_error( array( 'message' => 'Invalid username or password.' ) );
		}

		wp_send_json_success( array( 'message' => 'Login successful. Redirecting...' ) );
	}

	public static function ajax_upload_csv() {
		check_ajax_referer( 'wrp_portal_nonce', 'nonce' );

		if ( ! current_user_can( 'wrp_upload_csv' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		if ( empty( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => 'No file uploaded.' ) );
		}

		$file_name = sanitize_file_name( $_FILES['csv_file']['name'] );
		$file_ext  = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

		if ( 'csv' !== $file_ext ) {
			wp_send_json_error( array( 'message' => 'Invalid file type. Please upload a .csv file.' ) );
		}

		$results = WRP_CSV_Handler::parse_csv( $_FILES['csv_file']['tmp_name'] );

		if ( isset( $results['error'] ) ) {
			wp_send_json_error( array( 'message' => $results['error'] ) );
		}

		$html = '';
		$valid_count = 0;
		$invalid_count = 0;

		foreach ( $results as $row ) {
			$status_class = ( 'valid' === $row['status'] ) ? 'wrp-status-valid' : 'wrp-status-invalid';
			$status_text  = ( 'valid' === $row['status'] ) ? 'Valid' : 'Invalid';

			if ( 'valid' === $row['status'] ) {
				$valid_count++;
			} else {
				$invalid_count++;
			}

			$html .= '<tr>';
			$html .= '<td>' . esc_html( $row['row'] ) . '</td>';
			$html .= '<td>' . esc_html( $row['sku'] ) . '</td>';
			$html .= '<td>' . esc_html( $row['quantity'] ) . '</td>';
			$html .= '<td class="' . $status_class . '">' . $status_text . '</td>';
			$html .= '<td>' . esc_html( $row['message'] ) . '</td>';
			$html .= '</tr>';
		}

		$transient_key = 'wrp_csv_results_' . get_current_user_id();
		set_transient( $transient_key, $results, HOUR_IN_SECONDS );

		wp_send_json_success( array(
			'html'          => $html,
			'valid_count'   => $valid_count,
			'invalid_count' => $invalid_count,
			'has_valid'     => $valid_count > 0,
		) );
	}

	public static function ajax_add_to_cart() {
		check_ajax_referer( 'wrp_portal_nonce', 'nonce' );

		if ( ! current_user_can( 'wrp_upload_csv' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$user_id = get_current_user_id();
		$transient_key = 'wrp_csv_results_' . $user_id;
		$results = get_transient( $transient_key );

		if ( ! $results || ! is_array( $results ) ) {
			wp_send_json_error( array( 'message' => 'No data to process. Please upload the CSV again.' ) );
		}

		$valid_rows = array();

		foreach ( $results as $row ) {
			if ( 'valid' === $row['status'] ) {
				$product_id = wc_get_product_id_by_sku( $row['sku'] );
				if ( $product_id ) {
					$product = wc_get_product( $product_id );
					if ( $product ) {
						$valid_rows[] = array(
							'status'   => 'valid',
							'sku'      => $row['sku'],
							'quantity' => absint( $row['quantity'] ),
							'product'  => $product,
						);
					}
				}
			}
		}

		$result = WRP_CSV_Handler::add_to_cart( $valid_rows );

		if ( $result['added'] > 0 ) {
			wp_send_json_success( array(
				'message'  => $result['added'] . ' item(s) added to cart.',
				'cart_url' => wc_get_cart_url(),
			) );
		} else {
			wp_send_json_error( array( 'message' => 'No items could be added to cart.' ) );
		}
	}

	public static function ajax_save_template() {
		check_ajax_referer( 'wrp_portal_nonce', 'nonce' );

		if ( ! current_user_can( 'wrp_save_templates' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$user_id = get_current_user_id();
		$name    = isset( $_POST['template_name'] ) ? sanitize_text_field( wp_unslash( $_POST['template_name'] ) ) : '';

		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => 'Please enter a template name.' ) );
		}

		$transient_key = 'wrp_csv_results_' . $user_id;
		$results = get_transient( $transient_key );

		if ( ! $results || ! is_array( $results ) ) {
			wp_send_json_error( array( 'message' => 'No data to save. Please upload the CSV again.' ) );
		}

		$saved = WRP_CSV_Handler::save_template( $user_id, $name, $results );

		if ( $saved ) {
			wp_send_json_success( array( 'message' => 'Template saved successfully!' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Could not save template. You may have reached the 10-template limit.' ) );
		}
	}

	public static function ajax_delete_template() {
		check_ajax_referer( 'wrp_portal_nonce', 'nonce' );

		if ( ! current_user_can( 'wrp_save_templates' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$user_id     = get_current_user_id();
		$template_id = isset( $_POST['template_id'] ) ? sanitize_text_field( wp_unslash( $_POST['template_id'] ) ) : '';

		if ( empty( $template_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid template.' ) );
		}

		$deleted = WRP_CSV_Handler::delete_template( $user_id, $template_id );

		if ( $deleted ) {
			wp_send_json_success( array( 'message' => 'Template deleted.' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Could not delete template.' ) );
		}
	}

	public static function ajax_reorder_template() {
		check_ajax_referer( 'wrp_portal_nonce', 'nonce' );

		if ( ! current_user_can( 'wrp_access_reorder_portal' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$user_id     = get_current_user_id();
		$template_id = isset( $_POST['template_id'] ) ? sanitize_text_field( wp_unslash( $_POST['template_id'] ) ) : '';

		if ( empty( $template_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid template.' ) );
		}

		$templates = WRP_CSV_Handler::get_templates( $user_id );

		if ( ! isset( $templates[ $template_id ] ) ) {
			wp_send_json_error( array( 'message' => 'Template not found.' ) );
		}

		$template = $templates[ $template_id ];
		$valid_rows = array();

		foreach ( $template['items'] as $item ) {
			$product_id = wc_get_product_id_by_sku( $item['sku'] );
			if ( $product_id ) {
				$product = wc_get_product( $product_id );
				if ( $product && $product->is_in_stock() ) {
					$valid_rows[] = array(
						'status'   => 'valid',
						'sku'      => $item['sku'],
						'quantity' => $item['quantity'],
						'product'  => $product,
					);
				}
			}
		}

		if ( empty( $valid_rows ) ) {
			wp_send_json_error( array( 'message' => 'No items from this template are available.' ) );
		}

		$result = WRP_CSV_Handler::add_to_cart( $valid_rows );

		if ( $result['added'] > 0 ) {
			wp_send_json_success( array(
				'message'  => $result['added'] . ' item(s) added to cart.',
				'cart_url' => wc_get_cart_url(),
			) );
		} else {
			wp_send_json_error( array( 'message' => 'Could not add items to cart.' ) );
		}
	}
}