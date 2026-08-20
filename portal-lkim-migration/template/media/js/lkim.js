/**
 * Portal Rasmi LKIM — front-end behaviour.
 *
 * Deliberately dependency-free and small: the menu, dropdowns and layout all
 * work without JS, so this only adds the accessibility controls, the mobile
 * nav toggle, touch support for dropdowns, back-to-top, and the visitor count.
 */
(function () {
	'use strict';

	var STORE_SCALE = 'lkim.textScale';
	var STORE_CONTRAST = 'lkim.contrast';
	var SCALES = [0.875, 1, 1.125, 1.25];

	function store(key, value) {
		try {
			if (value === undefined) return window.localStorage.getItem(key);
			window.localStorage.setItem(key, value);
		} catch (e) {
			/* private mode — controls still work, they just do not persist */
		}
		return null;
	}

	/* ── Text size ───────────────────────────────────────────────────────── */

	function applyScale(scale) {
		document.documentElement.style.setProperty('--lkim-text-scale', scale);
		store(STORE_SCALE, String(scale));
	}

	function currentScale() {
		var saved = parseFloat(store(STORE_SCALE));
		return SCALES.indexOf(saved) === -1 ? 1 : saved;
	}

	function initTextSize() {
		var buttons = document.querySelectorAll('[data-lkim-text]');
		if (!buttons.length) return;

		applyScale(currentScale());

		buttons.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var index = SCALES.indexOf(currentScale());
				var mode = btn.getAttribute('data-lkim-text');

				if (mode === 'reset') {
					index = SCALES.indexOf(1);
				} else if (mode === 'increase') {
					index = Math.min(index + 1, SCALES.length - 1);
				} else {
					index = Math.max(index - 1, 0);
				}

				applyScale(SCALES[index]);
			});
		});
	}

	/* ── High contrast ───────────────────────────────────────────────────── */

	function initContrast() {
		var btn = document.querySelector('[data-lkim-contrast]');
		var on = store(STORE_CONTRAST) === 'on';

		function apply(state) {
			document.documentElement.setAttribute('data-lkim-contrast', state ? 'on' : 'off');
			if (btn) btn.setAttribute('aria-pressed', state ? 'true' : 'false');
			store(STORE_CONTRAST, state ? 'on' : 'off');
		}

		apply(on);

		if (btn) {
			btn.addEventListener('click', function () {
				apply(document.documentElement.getAttribute('data-lkim-contrast') !== 'on');
			});
		}
	}

	/* ── Mobile navigation ───────────────────────────────────────────────── */

	function initNav() {
		var toggle = document.querySelector('[data-lkim-navtoggle]');
		var nav = document.getElementById('lkim-mainnav');
		if (!toggle || !nav) return;

		toggle.addEventListener('click', function () {
			var open = nav.classList.toggle('is-open');
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		});

		// On touch devices a first tap on a parent should open the submenu
		// rather than follow the link.
		nav.querySelectorAll('li.parent > a').forEach(function (link) {
			link.addEventListener('click', function (event) {
				var isTouch = window.matchMedia('(hover: none)').matches;
				var li = link.parentNode;

				if (isTouch && !li.classList.contains('is-open')) {
					event.preventDefault();
					nav.querySelectorAll('li.is-open').forEach(function (open) {
						if (!open.contains(li)) open.classList.remove('is-open');
					});
					li.classList.add('is-open');
				}
			});
		});

		document.addEventListener('click', function (event) {
			if (!nav.contains(event.target)) {
				nav.querySelectorAll('li.is-open').forEach(function (li) {
					li.classList.remove('is-open');
				});
			}
		});

		nav.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				nav.querySelectorAll('li.is-open').forEach(function (li) {
					li.classList.remove('is-open');
				});
			}
		});
	}

	/* ── Back to top ─────────────────────────────────────────────────────── */

	function initBackToTop() {
		var link = document.querySelector('.lkim-backtotop');
		if (!link) return;

		function update() {
			link.classList.toggle('is-visible', window.scrollY > 400);
		}

		update();
		window.addEventListener('scroll', update, { passive: true });
	}

	/* ── Wide tables ─────────────────────────────────────────────────────── */

	function initTables() {
		document.querySelectorAll('.lkim-content table').forEach(function (table) {
			if (table.parentNode.classList.contains('lkim-table-scroll')) return;
			var wrap = document.createElement('div');
			wrap.className = 'lkim-table-scroll';
			table.parentNode.insertBefore(wrap, table);
			wrap.appendChild(table);
		});
	}

	/* ── Visitor counter ─────────────────────────────────────────────────── */

	function initCounter() {
		var target = document.querySelector('[data-lkim-counter]');
		if (!target) return;

		fetch(document.body.getAttribute('data-lkim-base') || '/index.php?option=com_ajax&plugin=lkimcounter&format=json', {
			headers: { Accept: 'application/json' }
		})
			.then(function (res) {
				return res.ok ? res.json() : Promise.reject(res.status);
			})
			.then(function (payload) {
				var value = payload && payload.data && payload.data[0];
				if (value !== undefined && value !== null) {
					target.textContent = Number(value).toLocaleString('ms-MY');
				}
			})
			.catch(function () {
				// No counter plugin installed yet — leave the em dash in place
				// rather than showing a broken number.
			});
	}

	function init() {
		initTextSize();
		initContrast();
		initNav();
		initBackToTop();
		initTables();
		initCounter();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
