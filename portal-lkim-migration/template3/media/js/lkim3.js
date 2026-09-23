/**
 * Portal Rasmi LKIM — LKIM-3 front-end behaviour.
 *
 * Dependency-free and small. The layout, mega menu (on pointer devices) and all
 * links work without JS; this adds the accessibility controls, the header
 * popovers, the mobile drawer, touch support for the mega menu, the agency
 * carousel, back-to-top and the visitor count.
 */
(function () {
	'use strict';

	var STORE_SCALE = 'lkim.textScale';
	var STORE_CONTRAST = 'lkim.contrast';
	var SCALES = [0.875, 1, 1.125, 1.25];
	var MOBILE = '(max-width: 900px)';

	function store(key, value) {
		try {
			if (value === undefined) return window.localStorage.getItem(key);
			window.localStorage.setItem(key, value);
		} catch (e) {
			/* private mode — controls still work, they just do not persist */
		}
		return null;
	}

	function isMobile() {
		return window.matchMedia(MOBILE).matches;
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

	/* ── Header popovers (accessibility tools, search) ───────────────────── */

	function initPopovers() {
		var pairs = [];

		document.querySelectorAll('[data-lkim-a11y], [data-lkim-search]').forEach(function (btn) {
			var panel = document.getElementById(btn.getAttribute('aria-controls'));
			if (panel) pairs.push({ btn: btn, panel: panel });
		});

		if (!pairs.length) return;

		function close(pair) {
			pair.panel.hidden = true;
			pair.btn.setAttribute('aria-expanded', 'false');
		}

		function closeAll(except) {
			pairs.forEach(function (pair) {
				if (pair !== except) close(pair);
			});
		}

		pairs.forEach(function (pair) {
			pair.btn.addEventListener('click', function (event) {
				event.stopPropagation();
				var open = pair.panel.hidden;
				closeAll(pair);
				pair.panel.hidden = !open;
				pair.btn.setAttribute('aria-expanded', open ? 'true' : 'false');

				if (open) {
					var field = pair.panel.querySelector('input, button, a');
					if (field) field.focus();
				}
			});

			pair.panel.addEventListener('click', function (event) {
				event.stopPropagation();
			});
		});

		document.addEventListener('click', function () {
			closeAll();
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') closeAll();
		});
	}

	/* ── Navigation: mobile drawer + mega menus ──────────────────────────── */

	function initNav() {
		var nav = document.getElementById('mainNav');
		var toggle = document.querySelector('[data-lkim-navtoggle]');
		var backdrop = document.querySelector('[data-lkim-backdrop]');

		if (!nav) return;

		function closeNav() {
			nav.classList.remove('is-open');
			if (toggle) toggle.setAttribute('aria-expanded', 'false');
			if (backdrop) backdrop.hidden = true;
			nav.querySelectorAll('li.is-open').forEach(function (li) {
				li.classList.remove('is-open');
				var btn = li.querySelector('.mega-toggle');
				if (btn) btn.setAttribute('aria-expanded', 'false');
			});
		}

		if (toggle) {
			toggle.addEventListener('click', function () {
				var open = nav.classList.toggle('is-open');
				toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
				if (backdrop) backdrop.hidden = !open;
			});
		}

		if (backdrop) backdrop.addEventListener('click', closeNav);

		// The chevron always toggles its panel; the parent link stays a link so
		// keyboard and pointer users can still reach the section landing page.
		nav.querySelectorAll('.mega-toggle').forEach(function (btn) {
			btn.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();

				var li = btn.closest('li');
				var open = li.classList.contains('is-open');

				nav.querySelectorAll('li.is-open').forEach(function (other) {
					if (other !== li) {
						other.classList.remove('is-open');
						var otherBtn = other.querySelector('.mega-toggle');
						if (otherBtn) otherBtn.setAttribute('aria-expanded', 'false');
					}
				});

				li.classList.toggle('is-open', !open);
				btn.setAttribute('aria-expanded', open ? 'false' : 'true');
			});
		});

		// On touch devices the first tap on a parent link opens its panel.
		nav.querySelectorAll('li.has-mega > .nav-top > a').forEach(function (link) {
			link.addEventListener('click', function (event) {
				var li = link.closest('li');

				if ((isMobile() || window.matchMedia('(hover: none)').matches) && !li.classList.contains('is-open')) {
					event.preventDefault();
					var btn = li.querySelector('.mega-toggle');
					if (btn) btn.click();
				}
			});
		});

		// A real destination closes the drawer behind it.
		nav.querySelectorAll('.mega a').forEach(function (link) {
			link.addEventListener('click', function () {
				if (isMobile()) closeNav();
			});
		});

		document.addEventListener('click', function (event) {
			if (!nav.contains(event.target) && (!toggle || !toggle.contains(event.target))) {
				nav.querySelectorAll('li.is-open').forEach(function (li) {
					li.classList.remove('is-open');
					var btn = li.querySelector('.mega-toggle');
					if (btn) btn.setAttribute('aria-expanded', 'false');
				});
			}
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') closeNav();
		});
	}

	/* ── Sticky header ───────────────────────────────────────────────────── */

	function initStickyHeader() {
		var header = document.querySelector('.site-header.is-sticky');
		if (!header) return;

		function update() {
			header.classList.toggle('is-solid', window.scrollY > 80);
		}

		update();
		window.addEventListener('scroll', update, { passive: true });
	}

	/* ── Agency carousel ─────────────────────────────────────────────────── */

	function initAgencies() {
		var track = document.querySelector('[data-lkim-agency-track]');
		if (!track) return;

		var buttons = document.querySelectorAll('[data-lkim-agency]');
		// The markup repeats the logo set three times; parking on the middle
		// copy lets the strip scroll either way before it needs rewinding.
		var setWidth = track.scrollWidth / 3;

		if (setWidth > 0) track.scrollLeft = setWidth;

		buttons.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var step = btn.getAttribute('data-lkim-agency') === 'prev' ? -320 : 320;
				track.scrollBy({ left: step, behavior: 'smooth' });
			});
		});

		var timer;
		track.addEventListener(
			'scroll',
			function () {
				clearTimeout(timer);
				timer = setTimeout(function () {
					if (setWidth <= 0) return;
					if (track.scrollLeft < setWidth * 0.15) {
						track.scrollLeft += setWidth;
					} else if (track.scrollLeft > setWidth * 1.85) {
						track.scrollLeft -= setWidth;
					}
				}, 80);
			},
			{ passive: true }
		);
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
		initPopovers();
		initNav();
		initStickyHeader();
		initAgencies();
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
