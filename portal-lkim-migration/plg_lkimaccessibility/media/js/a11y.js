/**
 * Portal Rasmi LKIM — accessibility panel.
 *
 * One state object drives everything. It is written to <html> as attributes and
 * CSS variables, and persisted so a visitor's adjustments survive navigation.
 * The disability profiles are nothing more than named sets of the same
 * adjustments, which is why choosing one and then changing a single tool works
 * the way you would expect.
 *
 * Markup comes from the template (a11y.php); this file only reads and writes.
 */
(function () {
	'use strict';

	var STORE = 'lkim.a11y';
	var SCALES = [0.875, 1, 1.125, 1.25, 1.5, 1.75];
	var LINES = ['normal', '1.8', '2.2'];
	var LETTERS = ['normal', '0.08em', '0.15em'];
	var WORDS = ['normal', '0.16em', '0.3em'];

	var DEFAULTS = {
		scale: 1,
		line: 0,
		letter: 0,
		font: '',
		align: '',
		filter: '',
		contrast: false,
		links: false,
		headings: false,
		cursor: false,
		motion: false,
		images: true,
		read: false,
		guide: false,
		mask: false,
		media: false,
		targets: false
	};

	// A profile is a partial state. Anything it does not name is left alone.
	var PROFILES = {
		blind:      { scale: 1.25, links: true, headings: true, motion: true, contrast: true, font: 'readable' },
		lowvision:  { scale: 1.5, contrast: true, links: true, cursor: true },
		colorblind: { filter: 'mono', links: true, headings: true },
		dyslexia:   { font: 'readable', line: 2, letter: 1, align: 'left', guide: true },
		adhd:       { read: true, mask: true, motion: true },
		epilepsy:   { motion: true, filter: 'lowsat' },
		motor:      { cursor: true, targets: true },
		deaf:       { media: true },
		elderly:    { scale: 1.25, contrast: true, cursor: true, links: true }
	};

	var state = Object.assign({}, DEFAULTS);
	var activeProfile = '';
	var panel;
	var launcher;
	var backdrop;
	var guideEl;
	var maskTop;
	var maskBottom;

	function store(value) {
		try {
			if (value === undefined) return window.localStorage.getItem(STORE);
			window.localStorage.setItem(STORE, value);
		} catch (e) {
			/* private mode — the controls still work, they just do not persist */
		}
		return null;
	}

	function load() {
		try {
			var saved = JSON.parse(store() || '{}');
			state = Object.assign({}, DEFAULTS, saved.state || {});
			activeProfile = saved.profile || '';
		} catch (e) {
			state = Object.assign({}, DEFAULTS);
			activeProfile = '';
		}
	}

	function save() {
		store(JSON.stringify({ state: state, profile: activeProfile }));
	}

	/* ── Applying state ──────────────────────────────────────────────────── */

	function attr(name, value) {
		var el = document.documentElement;
		if (value) el.setAttribute(name, value);
		else el.removeAttribute(name);
	}

	function apply() {
		var el = document.documentElement;

		el.style.setProperty('--lkim-text-scale', state.scale);
		el.style.setProperty('--a11y-line', LINES[state.line]);
		el.style.setProperty('--a11y-letter', LETTERS[state.letter]);
		el.style.setProperty('--a11y-word', WORDS[state.letter]);

		attr('data-a11y-line', state.line ? 'on' : '');
		attr('data-a11y-letter', state.letter ? 'on' : '');
		attr('data-a11y-font', state.font);
		attr('data-a11y-align', state.align);
		attr('data-a11y-filter', state.filter);
		attr('data-a11y-links', state.links ? 'on' : '');
		attr('data-a11y-headings', state.headings ? 'on' : '');
		attr('data-a11y-cursor', state.cursor ? 'on' : '');
		attr('data-a11y-motion', state.motion ? 'off' : '');
		attr('data-a11y-images', state.images ? '' : 'off');
		attr('data-a11y-read', state.read ? 'on' : '');
		attr('data-a11y-media', state.media ? 'on' : '');
		attr('data-a11y-targets', state.targets ? 'on' : '');

		// The high-contrast palette predates this panel and stays where it was.
		el.setAttribute('data-lkim-contrast', state.contrast ? 'on' : 'off');

		overlay();
		mediaNotices();
		sync();
		save();
	}

	/* ── Pointer-following overlays ──────────────────────────────────────── */

	function overlay() {
		var wanted = state.guide || state.mask;

		if (wanted && !guideEl) {
			guideEl = document.createElement('div');
			guideEl.className = 'a11y-guide';
			maskTop = document.createElement('div');
			maskTop.className = 'a11y-mask-top';
			maskBottom = document.createElement('div');
			maskBottom.className = 'a11y-mask-bottom';
			document.body.appendChild(guideEl);
			document.body.appendChild(maskTop);
			document.body.appendChild(maskBottom);
			document.addEventListener('pointermove', track, { passive: true });
		}

		if (guideEl) {
			guideEl.hidden = !state.guide;
			maskTop.hidden = !state.mask;
			maskBottom.hidden = !state.mask;
		}

		if (!wanted && guideEl) {
			document.removeEventListener('pointermove', track);
			[guideEl, maskTop, maskBottom].forEach(function (el) {
				el.parentNode.removeChild(el);
			});
			guideEl = maskTop = maskBottom = null;
		}
	}

	function track(event) {
		document.documentElement.style.setProperty('--a11y-y', event.clientY + 'px');
	}

	/* ── Hearing ─────────────────────────────────────────────────────────── */

	/**
	 * The page cannot caption a video it did not author, so the honest thing is
	 * to flag anything carrying sound and say where to ask for a transcript.
	 */
	function mediaNotices() {
		var notes = document.querySelectorAll('.a11y-media-note');
		var i;

		if (!state.media) {
			for (i = 0; i < notes.length; i++) notes[i].parentNode.removeChild(notes[i]);
			return;
		}

		if (notes.length) return;

		var media = document.querySelectorAll('video, audio, iframe[src*="youtube"], iframe[src*="vimeo"], iframe[src*="facebook"]');

		for (i = 0; i < media.length; i++) {
			var note = document.createElement('p');
			note.className = 'a11y-media-note';
			note.textContent = panel ? panel.getAttribute('data-media-note') || '' : '';
			if (note.textContent) media[i].insertAdjacentElement('afterend', note);
		}
	}

	/* ── Panel wiring ────────────────────────────────────────────────────── */

	function setProfile(name) {
		if (activeProfile === name) {
			// Choosing the active profile again clears it and its adjustments.
			activeProfile = '';
			state = Object.assign({}, DEFAULTS);
		} else {
			activeProfile = name;
			state = Object.assign({}, DEFAULTS, PROFILES[name] || {});
		}

		apply();
	}

	function setTool(tool, value) {
		if (!(tool in state)) return;
		state[tool] = value;
		// A hand-made change means the visitor is no longer on a named profile.
		activeProfile = '';
		apply();
	}

	function sync() {
		if (!panel) return;

		panel.querySelectorAll('[data-a11y-profile]').forEach(function (btn) {
			btn.setAttribute('aria-pressed', btn.getAttribute('data-a11y-profile') === activeProfile ? 'true' : 'false');
		});

		panel.querySelectorAll('[data-a11y-toggle]').forEach(function (btn) {
			var key = btn.getAttribute('data-a11y-toggle');
			var on = key === 'images' ? !state.images : !!state[key];
			btn.setAttribute('aria-pressed', on ? 'true' : 'false');
			var label = btn.querySelector('.a11y-state');
			if (label) label.textContent = on ? btn.getAttribute('data-on') : btn.getAttribute('data-off');
		});

		panel.querySelectorAll('[data-a11y-filter-btn]').forEach(function (btn) {
			btn.setAttribute('aria-pressed', btn.getAttribute('data-a11y-filter-btn') === state.filter ? 'true' : 'false');
		});

		var out = panel.querySelector('[data-a11y-out="scale"]');
		if (out) out.textContent = Math.round(state.scale * 100) + '%';

		out = panel.querySelector('[data-a11y-out="line"]');
		if (out) out.textContent = LINES[state.line] === 'normal' ? '100%' : LINES[state.line];

		out = panel.querySelector('[data-a11y-out="letter"]');
		if (out) out.textContent = LETTERS[state.letter] === 'normal' ? '0' : LETTERS[state.letter];
	}

	function step(key, list, delta) {
		if (key === 'scale') {
			var i = SCALES.indexOf(state.scale);
			i = i === -1 ? SCALES.indexOf(1) : i;
			state.scale = SCALES[Math.min(Math.max(i + delta, 0), SCALES.length - 1)];
		} else {
			state[key] = Math.min(Math.max(state[key] + delta, 0), list.length - 1);
		}

		activeProfile = '';
		apply();
	}

	function open() {
		backdrop.hidden = false;
		panel.classList.add('is-open');
		if (launcher) launcher.setAttribute('aria-expanded', 'true');
		document.querySelectorAll('[data-lkim-a11y]').forEach(function (b) {
			b.setAttribute('aria-expanded', 'true');
		});
		var first = panel.querySelector('button, a');
		if (first) first.focus();
	}

	function close() {
		panel.classList.remove('is-open');
		backdrop.hidden = true;
		if (launcher) launcher.setAttribute('aria-expanded', 'false');
		document.querySelectorAll('[data-lkim-a11y]').forEach(function (b) {
			b.setAttribute('aria-expanded', 'false');
		});
		if (launcher) launcher.focus();
	}

	function init() {
		panel = document.getElementById('a11y-panel');
		if (!panel) return;

		launcher = document.querySelector('.a11y-launcher');
		backdrop = document.querySelector('.a11y-backdrop');

		load();
		apply();

		if (launcher) launcher.addEventListener('click', open);
		document.querySelectorAll('[data-lkim-a11y]').forEach(function (btn) {
			btn.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				open();
			});
		});

		panel.querySelector('.a11y-close').addEventListener('click', close);
		backdrop.addEventListener('click', close);

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && !panel.hidden) close();
		});

		panel.addEventListener('click', function (event) {
			var el = event.target.closest('[data-a11y-profile], [data-a11y-toggle], [data-a11y-filter-btn], [data-a11y-step], [data-a11y-reset]');
			if (!el) return;

			if (el.hasAttribute('data-a11y-reset')) {
				activeProfile = '';
				state = Object.assign({}, DEFAULTS);
				apply();
				return;
			}

			if (el.hasAttribute('data-a11y-profile')) {
				setProfile(el.getAttribute('data-a11y-profile'));
				return;
			}

			if (el.hasAttribute('data-a11y-filter-btn')) {
				var want = el.getAttribute('data-a11y-filter-btn');
				setTool('filter', state.filter === want ? '' : want);
				return;
			}

			if (el.hasAttribute('data-a11y-step')) {
				var parts = el.getAttribute('data-a11y-step').split(':');
				step(parts[0], parts[0] === 'line' ? LINES : LETTERS, parts[1] === 'up' ? 1 : -1);
				return;
			}

			var key = el.getAttribute('data-a11y-toggle');

			if (key === 'images') {
				setTool('images', !state.images);
			} else if (key === 'font') {
				setTool('font', state.font === 'readable' ? '' : 'readable');
			} else if (key === 'align') {
				setTool('align', state.align === 'left' ? '' : 'left');
			} else {
				setTool(key, !state[key]);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
