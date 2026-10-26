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
}