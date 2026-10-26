<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRP_CSV_Handler {

	/**
	 * Parse a CSV file and return validated rows.
	 *
	 * @param string $file_path Path to the uploaded CSV file.
	 * @return array Array of validated rows with status.
	 */
	public static function parse_csv( $file_path ) {
		$results = array();

		if ( ! file_exists( $file_path ) ) {
			return $results;
		}

		$handle = fopen( $file_path, 'r' );

		if ( ! $handle ) {
			return $results;
		}

		$header = fgetcsv( $handle );

		// Validate header
		if ( ! $header || strtolower( trim( $header[0] ) ) !== 'sku' || strtolower( trim( $header[1] ) ) !== 'quantity' ) {
			fclose( $handle );
			return array(
				'error' => 'Invalid CSV header. Please use: SKU, Quantity',
			);
		}

		$row_number = 1;

		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			$row_number++;

			if ( count( $data ) < 2 ) {
				continue;
			}

			$sku      = sanitize_text_field( trim( $data[0] ) );
			$quantity = absint( trim( $data[1] ) );

			$row = array(
				'row'      => $row_number,
				'sku'      => $sku,
				'quantity' => $quantity,
				'status'   => 'valid',
				'message'  => '',
				'product'  => null,
			);

			// Validate SKU exists
			$product_id = wc_get_product_id_by_sku( $sku );

			if ( ! $product_id ) {
				$row['status']  = 'invalid';
				$row['message'] = 'SKU not found.';
				$results[] = $row;
				continue;
			}

			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				$row['status']  = 'invalid';
				$row['message'] = 'Product could not be loaded.';
				$results[] = $row;
				continue;
			}

			// Validate quantity
			if ( $quantity < 1 ) {
				$row['status']  = 'invalid';
				$row['message'] = 'Quantity must be at least 1.';
				$results[] = $row;
				continue;
			}

			// Check stock
			if ( ! $product->is_in_stock() ) {
				$row['status']  = 'invalid';
				$row['message'] = 'Product is out of stock.';
				$results[] = $row;
				continue;
			}

			$row['product'] = $product;
			$results[] = $row;
		}

		fclose( $handle );

		return $results;
	}

	/**
	 * Add validated items to the WooCommerce cart.
	 *
	 * @param array $validated_rows Array of validated rows from parse_csv().
	 * @return array Result with success count and errors.
	 */
	public static function add_to_cart( $validated_rows ) {
		if ( ! WC()->cart ) {
			wc_load_cart();
		}

		$added   = 0;
		$errors  = array();

		foreach ( $validated_rows as $row ) {
			if ( 'valid' !== $row['status'] || ! $row['product'] ) {
				continue;
			}

			$product_id = $row['product']->get_id();
			$quantity   = $row['quantity'];

			$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity );

			if ( $cart_item_key ) {
				$added++;
			} else {
				$errors[] = 'Could not add SKU ' . esc_html( $row['sku'] ) . ' to cart.';
			}
		}

		return array(
			'added'  => $added,
			'errors' => $errors,
		);
	}

	/**
	 * Save a template to the user's meta.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $name    The template name.
	 * @param array  $rows    The validated rows.
	 * @return bool True on success, false on failure.
	 */
	public static function save_template( $user_id, $name, $rows ) {
		$templates = self::get_templates( $user_id );

		// Limit to 10 templates per user
		if ( count( $templates ) >= 10 ) {
			return false;
		}

		$template_id = uniqid( 'wrp_tpl_' );

		$templates[ $template_id ] = array(
			'id'         => $template_id,
			'name'       => sanitize_text_field( $name ),
			'created_at' => current_time( 'mysql' ),
			'items'      => array(),
		);

		foreach ( $rows as $row ) {
			if ( 'valid' === $row['status'] ) {
				$templates[ $template_id ]['items'][] = array(
					'sku'      => sanitize_text_field( $row['sku'] ),
					'quantity' => absint( $row['quantity'] ),
				);
			}
		}

		update_user_meta( $user_id, '_wrp_saved_templates', $templates );

		return true;
	}

	/**
	 * Get all templates for a user.
	 *
	 * @param int $user_id The user ID.
	 * @return array Array of templates.
	 */
	public static function get_templates( $user_id ) {
		$templates = get_user_meta( $user_id, '_wrp_saved_templates', true );

		if ( ! is_array( $templates ) ) {
			$templates = array();
		}

		return $templates;
	}

	/**
	 * Delete a template.
	 *
	 * @param int    $user_id     The user ID.
	 * @param string $template_id The template ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_template( $user_id, $template_id ) {
		$templates = self::get_templates( $user_id );

		if ( isset( $templates[ $template_id ] ) ) {
			unset( $templates[ $template_id ] );
			update_user_meta( $user_id, '_wrp_saved_templates', $templates );
			return true;
		}

		return false;
	}
}