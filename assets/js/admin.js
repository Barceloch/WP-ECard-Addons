/**
 * WP ECard Addons admin behaviour.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var root = document.querySelector('.my-ecard-settings');

		if (!root) {
			return;
		}

		var qrToggle = root.querySelector('#my-ecard-enable_qr');
		var qrPanel = root.querySelector('[data-my-ecard-qr-panel]');

		function syncQrPanel() {
			if (!qrPanel) {
				return;
			}

			qrPanel.hidden = !(qrToggle && qrToggle.checked);
		}

		if (qrToggle) {
			qrToggle.addEventListener('change', syncQrPanel);
			syncQrPanel();
		}

		var input = root.querySelector('#my-ecard-cover_image');
		var preview = root.querySelector('[data-my-ecard-cover-preview]');
		var previewImage = preview ? preview.querySelector('img') : null;
		var choose = root.querySelector('[data-my-ecard-cover-choose]');
		var clear = root.querySelector('[data-my-ecard-cover-clear]');
		var frame = null;

		function setCover(id, url) {
			if (!input) {
				return;
			}

			input.value = String(id || 0);

			if (!preview || !previewImage) {
				return;
			}

			if (url) {
				previewImage.src = url;
				preview.hidden = false;
			} else {
				previewImage.removeAttribute('src');
				preview.hidden = true;
			}
		}

		if (choose && window.wp && window.wp.media) {
			choose.addEventListener('click', function (event) {
				event.preventDefault();

				if (frame) {
					frame.open();
					return;
				}

				frame = window.wp.media({
					title: choose.getAttribute('data-title') || '',
					button: { text: choose.getAttribute('data-button') || '' },
					library: { type: 'image' },
					multiple: false
				});

				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					var sizes = attachment.sizes || {};
					var url = sizes.medium ? sizes.medium.url : attachment.url;

					setCover(attachment.id, url);
				});

				frame.open();
			});
		}

		if (clear) {
			clear.addEventListener('click', function (event) {
				event.preventDefault();
				setCover(0, '');
			});
		}
	});
})();
