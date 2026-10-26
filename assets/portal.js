jQuery(document).ready(function($) {
	var csvData = [];

	// Upload CSV
	$('#wrp-upload-btn').on('click', function() {
		var fileInput = $('#wrp-csv-file')[0];
		var file = fileInput.files[0];
		var msg = $('#wrp-upload-message');

		if (!file) {
			msg.css('color', 'red').text('Please select a CSV file.');
			return;
		}

		var formData = new FormData();
		formData.append('action', 'wrp_upload_csv');
		formData.append('nonce', wrpAjax.nonce);
		formData.append('csv_file', file);

		msg.css('color', 'blue').text('Uploading and validating...');

		$.ajax({
			url: wrpAjax.ajaxUrl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {
				if (response.success) {
					$('#wrp-results-table tbody').html(response.data.html);
					$('#wrp-results-container').show();

					if (response.data.has_valid) {
						$('#wrp-add-to-cart-btn').show();
					} else {
						$('#wrp-add-to-cart-btn').hide();
					}

					msg.css('color', 'green').text('Validation complete. Found ' + response.data.valid_count + ' valid and ' + response.data.invalid_count + ' invalid items.');
				} else {
					msg.css('color', 'red').text(response.data.message);
				}
			},
			error: function() {
				msg.css('color', 'red').text('Upload failed. Please try again.');
			}
		});
	});

	// Add to Cart
	$('#wrp-add-to-cart-btn').on('click', function() {
		var btn = $(this);
		var msg = $('#wrp-cart-message');

		// We need to re-parse the file or pass the validated data
		// For MVP, we will re-upload the file
		var fileInput = $('#wrp-csv-file')[0];
		var file = fileInput.files[0];

		if (!file) {
			msg.css('color', 'red').text('Please upload the CSV file again.');
			return;
		}

		var formData = new FormData();
		formData.append('action', 'wrp_add_to_cart');
		formData.append('nonce', wrpAjax.nonce);
		formData.append('csv_file', file);

		btn.text('Adding...').prop('disabled', true);
		msg.text('');

		$.ajax({
			url: wrpAjax.ajaxUrl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {
				btn.text('Add All to Cart').prop('disabled', false);

				if (response.success) {
					msg.css('color', 'green').html(response.data.message + ' <a href="' + response.data.cart_url + '">Go to Cart</a>');
				} else {
					msg.css('color', 'red').text(response.data.message);
				}
			},
			error: function() {
				btn.text('Add All to Cart').prop('disabled', false);
				msg.css('color', 'red').text('Something went wrong.');
			}
		});
	});
});