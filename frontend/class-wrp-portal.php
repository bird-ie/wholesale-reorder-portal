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
		if ( ! is_user_logged_in() || ! current_user_can( 'wrp_access_reorder_portal' ) ) {
			return '<p>You do not have permission to access this portal. Please log in as a Wholesale Buyer.</p>';
		}

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
				<button type="button" id="wrp-add-to-cart-btn" class="wrp-btn-success" style="display:none;">Add All to Cart</button>
				<div id="wrp-cart-message"></div>
			</div>
		</div>
		<?php
		return ob_get_clean();
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

		// Build HTML for results table
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

		// Re-parse the CSV from session or re-upload
		// For MVP simplicity, we'll re-upload the file via a hidden field
		if ( empty( $_POST['csv_data'] ) ) {
			wp_send_json_error( array( 'message' => 'No data to process.' ) );
		}

		$csv_data = json_decode( stripslashes( $_POST['csv_data'] ), true );

		if ( ! is_array( $csv_data ) ) {
			wp_send_json_error( array( 'message' => 'Invalid data.' ) );
		}

		$valid_rows = array();

		foreach ( $csv_data as $row ) {
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
				'message' => $result['added'] . ' item(s) added to cart.',
				'cart_url' => wc_get_cart_url(),
			) );
		} else {
			wp_send_json_error( array( 'message' => 'No items could be added to cart.' ) );
		}
	}
}