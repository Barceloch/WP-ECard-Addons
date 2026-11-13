/**
 * WP ECard Addons shared action bar behaviour.
 *
 * Delegated listeners keep the script valid for any number of bars on the page
 * and for markup injected after load. QR behaviour lives in qr-modal.js.
 */
(function () {
	'use strict';

	var STATUS_TIMEOUT = 3000;
	var HEIGHT_PROPERTY = '--my-ecard-action-bar-height';

	function barFor(element) {
		return element ? element.closest('[data-my-ecard-action-bar]') : null;
	}

	function statusFor(element) {
		var bar = barFor(element);

		return bar ? bar.querySelector('[data-my-ecard-share-status]') : null;
	}

	function messageFrom(button, name, fallback) {
		var value = button.getAttribute('data-my-ecard-share-' + name);

		return value && value.trim() ? value : fallback;
	}

	function showStatus(status, message) {
		if (!status) {
			return;
		}

		if (status.myEcardShareTimer) {
			window.clearTimeout(status.myEcardShareTimer);
		}

		status.textContent = message;

		status.myEcardShareTimer = window.setTimeout(function () {
			status.textContent = '';
			status.myEcardShareTimer = 0;
		}, STATUS_TIMEOUT);
	}

	function copyWithClipboard(text) {
		return new Promise(function (resolve, reject) {
			if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
				reject(new Error('Clipboard API unavailable'));
				return;
			}

			try {
				navigator.clipboard.writeText(text).then(resolve, reject);
			} catch (error) {
				reject(error);
			}
		});
	}

	function copyWithTextarea(text) {
		var area = document.createElement('textarea');
		var copied = false;

		area.value = text;
		area.setAttribute('readonly', 'readonly');
		area.setAttribute('aria-hidden', 'true');
		area.style.position = 'fixed';
		area.style.top = '0';
		area.style.left = '0';
		area.style.opacity = '0';
		area.style.pointerEvents = 'none';

		document.body.appendChild(area);

		try {
			area.select();
			area.setSelectionRange(0, area.value.length);
			copied = document.execCommand('copy');
		} catch (error) {
			copied = false;
		}

		document.body.removeChild(area);

		return copied;
	}

	function share(button) {
		var url = button.getAttribute('data-my-ecard-share-url') || window.location.href;
		var status = statusFor(button);
		var sharedMessage = messageFrom(button, 'shared', 'Link shared.');
		var copiedMessage = messageFrom(button, 'copied', 'Link copied to the clipboard.');
		var failedMessage = messageFrom(button, 'failed', 'The link could not be copied.');

		function reportCopy() {
			copyWithClipboard(url).then(function () {
				handleCopied(true);
			}, function () {
				handleCopied(copyWithTextarea(url));
			});
		}

		function handleCopied(copied) {
			showStatus(status, copied ? copiedMessage : failedMessage);
		}

		if (typeof navigator.share === 'function') {
			try {
				var result = navigator.share({ url: url });

				if (result && typeof result.then === 'function') {
					result.then(function () {
						showStatus(status, sharedMessage);
					}, function (error) {
						if (error && error.name === 'AbortError') {
							return;
						}

						reportCopy();
					});
				} else {
					showStatus(status, sharedMessage);
				}
			} catch (error) {
				reportCopy();
			}

			return;
		}

		reportCopy();
	}

	function updateBarHeight() {
		var root = document.documentElement;
		var bar = document.querySelector('[data-my-ecard-action-bar]');

		if (!root || !root.style || !bar) {
			return;
		}

		var height = bar.offsetHeight;

		if (height > 0) {
			root.style.setProperty(HEIGHT_PROPERTY, height + 'px');
		}
	}

	document.addEventListener('click', function (event) {
		var target = event.target;

		if (!(target instanceof Element)) {
			return;
		}

		var trigger = target.closest('[data-my-ecard-share]');

		if (trigger) {
			event.preventDefault();
			share(trigger);
		}
	});

	window.addEventListener('load', updateBarHeight);
	window.addEventListener('resize', updateBarHeight);

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', updateBarHeight);
	} else {
		updateBarHeight();
	}
})();
