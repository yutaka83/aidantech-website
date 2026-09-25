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
	var RATES = [0.6, 0.8, 1, 1.2, 1.5];

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
		targets: false,

		// Click-to-read. Persisted, because it only ever reacts to a click —
		// unlike reading the page, which must never resume by itself on load.
		speech: false,
		rate: 2
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
		attr('data-a11y-speech', state.speech ? 'on' : '');

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

	/* ── Reading aloud ───────────────────────────────────────────────────── */

	/*
	 * Browser speech synthesis, so nothing is sent anywhere and there is no
	 * service to pay for or keep running. Two ways in: read the page (or the
	 * selection, if there is one) in one go, or switch on click-to-read and
	 * hear whatever you click.
	 *
	 * Click-to-read does not swallow the click. A widget that stops links
	 * working is a widget people turn off, and speech is cancelled on unload
	 * anyway, so following a link mid-sentence does the sensible thing.
	 */
	var speaking = false;
	var voices = [];
	var lastSelection = '';

	function speechSupported() {
		return 'speechSynthesis' in window && typeof window.SpeechSynthesisUtterance === 'function';
	}

	function loadVoices() {
		if (!speechSupported()) return;
		voices = window.speechSynthesis.getVoices() || [];
	}

	/** The closest voice to the page's own language, or the browser's default. */
	function pickVoice(lang) {
		if (!voices.length) loadVoices();
		if (!voices.length) return null;

		var want = (lang || '').toLowerCase();
		var base = want.split('-')[0];

		return voices.find(function (v) { return v.lang.toLowerCase() === want; })
			|| voices.find(function (v) { return v.lang.toLowerCase().replace('_', '-') === want; })
			|| voices.find(function (v) { return v.lang.toLowerCase().indexOf(base) === 0; })
			|| null;
	}

	function stopSpeaking() {
		if (!speechSupported()) return;

		window.speechSynthesis.cancel();
		speaking = false;

		document.querySelectorAll('.a11y-speaking').forEach(function (el) {
			el.classList.remove('a11y-speaking');
		});

		sync();
	}

	function speak(text, element) {
		if (!speechSupported()) return;

		stopSpeaking();

		text = (text || '').replace(/\s+/g, ' ').trim();
		if (text === '') return;

		var lang = document.documentElement.lang || 'en';
		var utterance = new window.SpeechSynthesisUtterance(text);
		var voice = pickVoice(lang);

		utterance.lang = lang;
		utterance.rate = RATES[state.rate] || 1;
		if (voice) utterance.voice = voice;

		if (element) element.classList.add('a11y-speaking');

		utterance.onend = utterance.onerror = function () {
			speaking = false;
			if (element) element.classList.remove('a11y-speaking');
			sync();
		};

		speaking = true;
		window.speechSynthesis.speak(utterance);
		sync();
	}

	/** What a visitor means by "the page": their selection, else the main content. */
	function pageText() {
		var selection = window.getSelection ? String(window.getSelection()) : '';

		if (selection.trim() === '') selection = lastSelection;

		if (selection.trim() !== '') return selection;

		var extract = function (node) {
			if (!node) return '';

			var clone = node.cloneNode(true);

			clone.querySelectorAll('script, style, noscript, .a11y-panel, .a11y-launcher, nav, [aria-hidden="true"]')
				.forEach(function (el) { el.remove(); });

			return (clone.textContent || '').replace(/\s+/g, ' ').trim();
		};

		/*
		 * The main landmark first, but only if the page actually put anything
		 * in it. A <main> can be an empty shell — this portal's own home page
		 * lays its bands out beside it rather than inside — and reading nothing
		 * aloud looks identical to the button being broken.
		 */
		var main = extract(document.querySelector('main, [role="main"], #main-content'));

		return main.length > 120 ? main : extract(document.body);
	}

	function readPage() {
		if (speaking) {
			stopSpeaking();
			return;
		}

		speak(pageText(), null);
	}

	function initSpeech() {
		if (!speechSupported()) {
			// The stylesheet hides the whole group off the back of this.
			document.documentElement.setAttribute('data-a11y-speechless', 'on');
			return;
		}

		loadVoices();
		window.speechSynthesis.addEventListener('voiceschanged', loadVoices);

		document.addEventListener('selectionchange', function () {
			var text = String(window.getSelection() || '');
			var anchor = window.getSelection() ? window.getSelection().anchorNode : null;
			var inPanel = anchor && anchor.parentElement && anchor.parentElement.closest('.a11y-panel');

			if (text.trim() !== '' && !inPanel) lastSelection = text;
		});

		// Leaving the page mid-sentence should not carry the voice with it.
		window.addEventListener('beforeunload', function () { window.speechSynthesis.cancel(); });

		document.addEventListener('click', function (event) {
			if (!state.speech) return;
			if (event.target.closest('.a11y-panel, .a11y-launcher')) return;

			var block = event.target.closest('p, li, h1, h2, h3, h4, h5, h6, td, th, dd, dt, figcaption, blockquote, a, button, label, summary');
			if (!block) return;

			var text = block.innerText || block.textContent;
			if (!text || text.trim() === '') return;

			speak(text, block);
		});
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

		out = panel.querySelector('[data-a11y-out="rate"]');
		if (out) out.textContent = (RATES[state.rate] || 1).toFixed(1) + 'x';

		// Reading the page is an action, not a setting: its tile shows pressed
		// only while the voice is actually going.
		var readBtn = panel.querySelector('[data-a11y-speak]');
		if (readBtn) readBtn.setAttribute('aria-pressed', speaking ? 'true' : 'false');
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
		initSpeech();
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
			var el = event.target.closest('[data-a11y-profile], [data-a11y-toggle], [data-a11y-filter-btn], [data-a11y-step], [data-a11y-reset], [data-a11y-speak]');
			if (!el) return;

			if (el.hasAttribute('data-a11y-speak')) {
				readPage();
				return;
			}

			if (el.hasAttribute('data-a11y-reset')) {
				activeProfile = '';
				state = Object.assign({}, DEFAULTS);
				stopSpeaking();
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
				var ladder = parts[0] === 'line' ? LINES : (parts[0] === 'rate' ? RATES : LETTERS);
				step(parts[0], ladder, parts[1] === 'up' ? 1 : -1);
				return;
			}

			var key = el.getAttribute('data-a11y-toggle');

			if (key === 'images') {
				setTool('images', !state.images);
			} else if (key === 'font') {
				setTool('font', state.font === 'readable' ? '' : 'readable');
			} else if (key === 'align') {
				setTool('align', state.align === 'left' ? '' : 'left');
			} else if (key === 'speech') {
				// Leaving click-to-read should take the current sentence with it.
				if (state.speech) stopSpeaking();
				setTool('speech', !state.speech);
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
