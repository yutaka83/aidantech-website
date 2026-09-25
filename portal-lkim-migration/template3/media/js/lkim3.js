/**
 * Portal Rasmi LKIM — LKIM-3 front-end behaviour.
 *
 * Dependency-free and small. The layout, mega menu (on pointer devices) and all
 * links work without JS; this adds the search popover, the mobile drawer, touch
 * support for the mega menu, the sticky menu, the agency carousel, back-to-top
 * and the visitor count.
 *
 * The accessibility controls live in a11y.js, which loads alongside this.
 */
(function () {
	'use strict';

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

	/*
	 * ── Search popover ──────────────────────────────────────────────────────
	 *
	 * Search only. Text size and contrast moved to the accessibility panel
	 * (a11y.js), and this must not reach it: the header's accessibility button
	 * points at the panel through aria-controls, so while this still matched
	 * [data-lkim-a11y] its outside-click handler was setting hidden on the
	 * panel the moment anything else was clicked.
	 */

	function initPopovers() {
		var pairs = [];

		document.querySelectorAll('[data-lkim-search]').forEach(function (btn) {
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

	/* ── Sticky menu ─────────────────────────────────────────────────────── */

	function initStickyMenu() {
		if (!document.body.classList.contains('has-sticky-menu')) return;

		var nav = document.querySelector('.hero-nav-row');
		if (!nav || !nav.parentNode) return;

		// A 1px marker left in the flow where the nav started (the stylesheet
		// cancels that pixel with a negative margin). Measuring the marker
		// rather than the nav keeps the threshold stable: the nav leaves the
		// flow when it sticks, the marker never does.
		var sentinel = document.createElement('div');
		sentinel.className = 'lk3-sticky-sentinel';
		nav.parentNode.insertBefore(sentinel, nav);

		var threshold = 0;

		function measure() {
			threshold = sentinel.getBoundingClientRect().top + window.scrollY;
		}

		function update() {
			document.body.classList.toggle('lk3-menu-stuck', window.scrollY > threshold);
		}

		measure();
		update();

		window.addEventListener('scroll', update, { passive: true });
		window.addEventListener(
			'resize',
			function () {
				measure();
				update();
			},
			{ passive: true }
		);
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
		initPopovers();
		initNav();
		initStickyHeader();
		initStickyMenu();
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
