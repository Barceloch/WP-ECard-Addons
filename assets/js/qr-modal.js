/**
 * WP ECard Addons shared QR modal behaviour.
 *
 * Delegated listeners keep the script valid for any number of cards on the
 * page and for markup injected after load. No dependencies.
 */
(function () {
	'use strict';

	function dialogFrom(opener) {
		var id = opener.getAttribute('aria-controls');

		if (id) {
			var byId = document.getElementById(id);

			if (byId) {
				return byId;
			}
		}

		var wrapper = opener.parentNode;

		if (wrapper) {
			var local = wrapper.querySelector('[data-my-ecard-qr-dialog]');

			if (local) {
				return local;
			}
		}

		/*
		 * The action bar renders the opener outside the modal wrapper, so the
		 * shared dialog is the fallback target.
		 */
		return document.querySelector('[data-my-ecard-qr-dialog]');
	}

	/*
	 * Restore focus to the control that opened the dialog. The native dialog
	 * usually does this automatically; this keeps it explicit across browsers.
	 */
	document.addEventListener('close', function (event) {
		var dialog = event.target;

		if (dialog && dialog.hasAttribute && dialog.hasAttribute('data-my-ecard-qr-dialog') && dialog.myEcardOpener) {
			dialog.myEcardOpener.focus();
		}
	}, true);

	document.addEventListener('click', function (event) {
		var target = event.target;

		if (!(target instanceof Element)) {
			return;
		}

		var opener = target.closest('[data-my-ecard-qr-open]');

		if (opener) {
			event.preventDefault();

			var dialog = dialogFrom(opener);

			if (dialog && typeof dialog.showModal === 'function' && !dialog.open) {
				dialog.myEcardOpener = opener;
				dialog.showModal();
			}

			return;
		}

		var closer = target.closest('[data-my-ecard-qr-close]');

		if (closer) {
			var parent = closer.closest('dialog');

			if (parent && typeof parent.close === 'function') {
				parent.close();
			}
		}
	});

	document.addEventListener('click', function (event) {
		var target = event.target;

		if (!(target instanceof Element) || !target.hasAttribute('data-my-ecard-qr-dialog')) {
			return;
		}

		var rect = target.getBoundingClientRect();
		var inside =
			event.clientX >= rect.left &&
			event.clientX <= rect.right &&
			event.clientY >= rect.top &&
			event.clientY <= rect.bottom;

		if (!inside && typeof target.close === 'function') {
			target.close();
		}
	});
})();
