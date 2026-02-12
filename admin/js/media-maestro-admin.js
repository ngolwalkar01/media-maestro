(function ($) {
	'use strict';

	$(document).ready(function () {
		// AI Media Studio - Meta Box Logic
		if (typeof mm_data === 'undefined') {
			return;
		}

		const $container = $('#mm-ai-tools-container');
		const $status = $('#mm-job-status');
		const attachmentId = mm_data.attachment_id;
		const apiUrl = mm_data.api_url; // e.g. /wp-json/mm/v1/jobs
		const nonce = mm_data.nonce; // wp_rest nonce

		$('#mm-btn-remove-bg').on('click', function (e) {
			e.preventDefault();
			startJob('remove_background');
		});

		$('#mm-btn-style-transfer').on('click', function (e) {
			e.preventDefault();
			// detailed UI for prompt would be here, for now just simple trigger
			startJob('style_transfer', { prompt: 'Oil painting' });
		});

		function startJob(operation, params = {}) {
			$status.html('Starting job...');

			$.ajax({
				url: apiUrl,
				method: 'POST',
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', nonce);
				},
				data: {
					attachment_id: attachmentId,
					operation: operation,
					params: params
				}
			}).done(function (response) {
				if (response.id) {
					$status.html('Job started (ID: ' + response.id + '). Processing...');
					pollJob(response.id);
				} else {
					$status.html('Error starting job.');
				}
			}).fail(function (jqXHR) {
				$status.html('Error: ' + jqXHR.statusText);
			});
		}

		function pollJob(jobId) {
			setTimeout(function () {
				$.ajax({
					url: apiUrl + '/' + jobId,
					method: 'GET',
					beforeSend: function (xhr) {
						xhr.setRequestHeader('X-WP-Nonce', nonce);
					}
				}).done(function (response) {
					if (response.status === 'completed') {
						$status.html('Job Completed! <a href="#" onclick="window.location.reload();">Reload to see result</a>');
						if (response.result && response.result.length > 0) {
							// Ideally fetch the new attachment URL and show it
						}
					} else if (response.status === 'failed') {
						$status.html('Job Failed: ' + (response.error || 'Unknown error'));
					} else {
						// Still processing
						$status.html('Status: ' + response.status + '...');
						pollJob(jobId);
					}
				}).fail(function () {
					$status.html('Error polling job.');
				});
			}, 2000); // Poll every 2s
		}

	});

})(jQuery);
