/**
 * WP ECard Addons shared media viewer behaviour.
 *
 * One script drives the gallery and the videos dialogs rendered by
 * templates/parts/media-viewers.php. Media is built from data attributes only
 * when a dialog opens, so no iframe or video element exists before that.
 *
 * No dependencies, no innerHTML, and no assumptions about theme markup.
 */
(function () {
	'use strict';

	var VIEWER_SELECTOR = '[data-my-ecard-media-viewer]';
	var GALLERY_SELECTOR = '[data-my-ecard-media-viewer="gallery"]';
	var VIDEOS_SELECTOR = '[data-my-ecard-media-viewer="videos"]';
	var ITEM_SELECTOR = '[data-my-ecard-media-item]';
	var ElementCtor = typeof window !== 'undefined' && window.Element ? window.Element : null;
	var SWIPE_THRESHOLD = 40;

	/**
	 * Resolve the closest matching element, tolerating odd event targets.
	 *
	 * @param {*} target Event target.
	 * @param {string} selector CSS selector.
	 * @return {Element|null}
	 */
	function closest(target, selector) {
		if (!target) {
			return null;
		}

		if (ElementCtor && !(target instanceof ElementCtor)) {
			return null;
		}

		if (typeof target.closest !== 'function') {
			return null;
		}

		return target.closest(selector);
	}

	/**
	 * Collect the media items of one dialog.
	 *
	 * @param {Element} dialog Viewer dialog.
	 * @return {NodeList|Array}
	 */
	function getItems(dialog) {
		if (!dialog || typeof dialog.querySelectorAll !== 'function') {
			return [];
		}

		return dialog.querySelectorAll(ITEM_SELECTOR);
	}

	/**
	 * Locate the dialog an opener controls.
	 *
	 * @param {Element} opener Action bar button.
	 * @param {string}  type   "gallery" or "videos".
	 * @return {Element|null}
	 */
	function findDialog(opener, type) {
		var id = opener && typeof opener.getAttribute === 'function' ? opener.getAttribute('aria-controls') : '';

		if (id) {
			var byId = document.getElementById(id);

			if (byId && typeof byId.getAttribute === 'function' && byId.getAttribute('data-my-ecard-media-viewer') === type) {
				return byId;
			}
		}

		return document.querySelector(type === 'gallery' ? GALLERY_SELECTOR : VIDEOS_SELECTOR);
	}

	/**
	 * Set text without parsing markup.
	 *
	 * @param {Element|null} element Target element.
	 * @param {string}       text    Text content.
	 * @return {void}
	 */
	function setText(element, text) {
		if (element) {
			element.textContent = text;
		}
	}

	/**
	 * Pause and remove any video, then clear the stage.
	 *
	 * @param {Element|null} stage Video stage.
	 * @return {void}
	 */
	function clearStage(stage) {
		if (!stage) {
			return;
		}

		var videos = stage.querySelectorAll('video');

		for (var i = 0; i < videos.length; i++) {
			try {
				if (typeof videos[i].pause === 'function') {
					videos[i].pause();
				}
			} catch (error) {
				/* A video that refuses to pause must not block removal. */
			}
		}

		while (stage.firstChild) {
			stage.removeChild(stage.firstChild);
		}
	}

	/**
	 * Close a dialog and tear down its transient media.
	 *
	 * The close event does the same work for Escape and any other native close
	 * path. Doing it here as well keeps the close button responsive even in
	 * browsers that defer the close event.
	 *
	 * @param {Element} dialog Viewer dialog.
	 * @return {void}
	 */
	function closeViewer(dialog) {
		if (!dialog) {
			return;
		}

		if (typeof dialog.close === 'function' && dialog.open) {
			dialog.close();
		}

		clearStage(dialog.querySelector('[data-my-ecard-media-video]'));

		if (dialog.myEcardOpener && typeof dialog.myEcardOpener.focus === 'function') {
			dialog.myEcardOpener.focus();
		}
	}

	/**
	 * Build the video stage content for one item.
	 *
	 * @param {Element} dialog Viewer dialog.
	 * @param {Element} item   Active video item.
	 * @return {void}
	 */
	function renderVideo(dialog, item) {
		var stage = dialog.querySelector('[data-my-ecard-media-video]');
		var title = typeof item.getAttribute === 'function' ? item.getAttribute('data-title') || '' : '';
		var embed = typeof item.getAttribute === 'function' ? item.getAttribute('data-embed') || '' : '';
		var direct = typeof item.getAttribute === 'function' ? item.getAttribute('data-direct') || '' : '';
		var url = typeof item.getAttribute === 'function' ? item.getAttribute('data-url') || '' : '';

		embed = embed.trim();
		direct = direct.trim();
		url = url.trim();

		clearStage(stage);

		if (stage) {
			if (embed) {
				var frame = document.createElement('iframe');

				frame.setAttribute('src', embed);
				frame.setAttribute('title', title || 'Video');
				frame.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture');
				frame.setAttribute('allowfullscreen', '');
				frame.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
				stage.appendChild(frame);
			} else if (direct) {
				var video = document.createElement('video');

				video.setAttribute('controls', '');
				video.setAttribute('playsinline', '');
				video.setAttribute('preload', 'metadata');
				video.setAttribute('src', direct);
				video.setAttribute('aria-label', title);
				stage.appendChild(video);
			} else if (url) {
				var link = document.createElement('a');
				var label = typeof item.getAttribute === 'function' ? item.getAttribute('data-open-label') || '' : '';

				link.setAttribute('href', url);
				link.setAttribute('target', '_blank');
				link.setAttribute('rel', 'noopener noreferrer');
				link.textContent = label || 'Open video';
				stage.appendChild(link);
			}
		}

		setText(dialog.querySelector('[data-my-ecard-media-caption]'), title);
	}

	/**
	 * Build the gallery stage content for one item.
	 *
	 * @param {Element} dialog Viewer dialog.
	 * @param {Element} item   Active gallery item.
	 * @return {void}
	 */
	function renderGallery(dialog, item) {
		var image = dialog.querySelector('[data-my-ecard-media-image]');
		var alt = typeof item.getAttribute === 'function' ? item.getAttribute('data-alt') || '' : '';
		var title = typeof item.getAttribute === 'function' ? item.getAttribute('data-title') || '' : '';

		if (image) {
			image.setAttribute('src', typeof item.getAttribute === 'function' ? item.getAttribute('data-src') || '' : '');
			image.setAttribute('alt', alt || title);
		}

		setText(dialog.querySelector('[data-my-ecard-media-caption]'), title || alt);
	}

	/**
	 * Render one index of a dialog.
	 *
	 * @param {Element} dialog    Viewer dialog.
	 * @param {number}  nextIndex Requested index.
	 * @return {number} Clamped index.
	 */
	function render(dialog, nextIndex) {
		var items = getItems(dialog);
		var total = items.length;

		if (!total) {
			return 0;
		}

		var index = typeof nextIndex === 'number' && isFinite(nextIndex) ? Math.floor(nextIndex) : 0;

		if (index < 0) {
			index = 0;
		}

		if (index >= total) {
			index = total - 1;
		}

		dialog.myEcardIndex = index;

		for (var i = 0; i < total; i++) {
			var item = items[i];

			if (!item) {
				continue;
			}

			if (i === index) {
				if (typeof item.setAttribute === 'function') {
					item.setAttribute('aria-current', 'true');
				}

				if (item.classList && typeof item.classList.add === 'function') {
					item.classList.add('is-active');
				}
			} else {
				if (typeof item.removeAttribute === 'function') {
					item.removeAttribute('aria-current');
				}

				if (item.classList && typeof item.classList.remove === 'function') {
					item.classList.remove('is-active');
				}
			}
		}

		var prev = dialog.querySelector('[data-my-ecard-media-prev]');
		var next = dialog.querySelector('[data-my-ecard-media-next]');

		if (prev) {
			prev.disabled = total < 2;
		}

		if (next) {
			next.disabled = total < 2;
		}

		setText(dialog.querySelector('[data-my-ecard-media-counter]'), (index + 1) + ' / ' + total);

		var type = typeof dialog.getAttribute === 'function' ? dialog.getAttribute('data-my-ecard-media-viewer') : '';

		if (type === 'gallery') {
			renderGallery(dialog, items[index]);
		} else if (type === 'videos') {
			renderVideo(dialog, items[index]);
		}

		return index;
	}

	/**
	 * Navigate by a relative step with wraparound.
	 *
	 * @param {Element} dialog Viewer dialog.
	 * @param {number}  delta  Step, usually 1 or -1.
	 * @return {void}
	 */
	function step(dialog, delta) {
		var items = getItems(dialog);
		var total = items.length;

		if (total < 2) {
			return;
		}

		var current = typeof dialog.myEcardIndex === 'number' ? dialog.myEcardIndex : 0;
		var next = (current + delta) % total;

		if (next < 0) {
			next += total;
		}

		render(dialog, next);
	}

	/**
	 * Initialize one dialog exactly once.
	 *
	 * @param {Element} dialog Viewer dialog.
	 * @return {void}
	 */
	function initDialog(dialog) {
		if (!dialog || !dialog.dataset) {
			return;
		}

		if (!getItems(dialog).length) {
			return;
		}

		if (dialog.dataset.myEcardReady === '1') {
			return;
		}

		dialog.dataset.myEcardReady = '1';

		if (typeof dialog.myEcardIndex !== 'number') {
			dialog.myEcardIndex = 0;
		}

		var items = getItems(dialog);
		var touchStartX = null;
		var touchStartY = null;

		dialog.addEventListener('click', function (event) {
			var target = event.target;
			var closer = closest(target, '[data-my-ecard-media-close]');

			if (closer && dialog.contains(closer)) {
				closeViewer(dialog);
				return;
			}

			var prev = closest(target, '[data-my-ecard-media-prev]');

			if (prev && dialog.contains(prev)) {
				if (!prev.disabled) {
					step(dialog, -1);
				}

				return;
			}

			var next = closest(target, '[data-my-ecard-media-next]');

			if (next && dialog.contains(next)) {
				if (!next.disabled) {
					step(dialog, 1);
				}

				return;
			}

			var item = closest(target, ITEM_SELECTOR);

			if (item && dialog.contains(item) && !item.disabled) {
				var index = Array.prototype.indexOf.call(items, item);

				if (index >= 0) {
					render(dialog, index);
				}

				return;
			}

			/*
			 * Backdrop clicks land on the dialog itself. Native dialogs also fire
			 * clicks inside their padding, so the coordinates decide.
			 */
			if (target === dialog && typeof dialog.getBoundingClientRect === 'function') {
				var rect = dialog.getBoundingClientRect();
				var inside =
					event.clientX >= rect.left &&
					event.clientX <= rect.right &&
					event.clientY >= rect.top &&
					event.clientY <= rect.bottom;

				if (!inside) {
					closeViewer(dialog);
				}
			}
		});

		dialog.addEventListener('keydown', function (event) {
			var key = event.key;

			if (key === 'ArrowLeft') {
				event.preventDefault();
				step(dialog, -1);
			} else if (key === 'ArrowRight') {
				event.preventDefault();
				step(dialog, 1);
			} else if (key === 'Home') {
				event.preventDefault();
				render(dialog, 0);
			} else if (key === 'End') {
				event.preventDefault();
				render(dialog, getItems(dialog).length - 1);
			}
		});

		dialog.addEventListener('close', function () {
			clearStage(dialog.querySelector('[data-my-ecard-media-video]'));

			if (dialog.myEcardOpener && typeof dialog.myEcardOpener.focus === 'function') {
				dialog.myEcardOpener.focus();
			}
		});

		var stage = dialog.querySelector('[data-my-ecard-media-stage]');

		if (!stage || typeof stage.addEventListener !== 'function') {
			return;
		}

		stage.addEventListener('touchstart', function (event) {
			var touch = event.touches && event.touches.length ? event.touches[0] : null;

			if (!touch || closest(event.target, 'button')) {
				touchStartX = null;
				touchStartY = null;
				return;
			}

			touchStartX = touch.clientX;
			touchStartY = touch.clientY;
		}, { passive: true });

		stage.addEventListener('touchend', function (event) {
			if (typeof touchStartX !== 'number' || typeof touchStartY !== 'number') {
				return;
			}

			var touch = event.changedTouches && event.changedTouches.length ? event.changedTouches[0] : null;
			var startX = touchStartX;
			var startY = touchStartY;

			touchStartX = null;
			touchStartY = null;

			if (!touch) {
				return;
			}

			var deltaX = touch.clientX - startX;
			var deltaY = touch.clientY - startY;

			if (Math.abs(deltaX) <= SWIPE_THRESHOLD || Math.abs(deltaX) <= Math.abs(deltaY)) {
				return;
			}

			step(dialog, deltaX < 0 ? 1 : -1);
		}, { passive: true });
	}

	/**
	 * Open a viewer from its action bar button.
	 *
	 * @param {Element} opener Action bar button.
	 * @param {string}  type   "gallery" or "videos".
	 * @return {void}
	 */
	function openViewer(opener, type) {
		var dialog = findDialog(opener, type);

		if (!dialog) {
			return;
		}

		initDialog(dialog);
		dialog.myEcardOpener = opener;
		dialog.myEcardIndex = 0;
		render(dialog, 0);

		if (typeof dialog.showModal === 'function' && !dialog.open) {
			dialog.showModal();
		}

		var closeButton = dialog.querySelector('[data-my-ecard-media-close]');

		if (closeButton && typeof closeButton.focus === 'function') {
			closeButton.focus();
		}
	}

	document.addEventListener('click', function (event) {
		var galleryOpener = closest(event.target, '[data-my-ecard-gallery-open]');

		if (galleryOpener) {
			event.preventDefault();
			openViewer(galleryOpener, 'gallery');
			return;
		}

		var videosOpener = closest(event.target, '[data-my-ecard-videos-open]');

		if (videosOpener) {
			event.preventDefault();
			openViewer(videosOpener, 'videos');
		}
	});

	/**
	 * Wire every viewer present at load time.
	 *
	 * @return {void}
	 */
	function initAll() {
		var dialogs = document.querySelectorAll(VIEWER_SELECTOR);

		for (var i = 0; i < dialogs.length; i++) {
			initDialog(dialogs[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}
})();
