/*!
 * JPX Cookies LGPD — loader
 * Carrega a CookieConsent sob demanda: só para quem ainda não escolheu
 * ou quando o visitante abre as preferências. Quem já escolheu não baixa
 * a biblioteca; os scripts autorizados são ativados por este arquivo.
 */
(function (w, d) {
	'use strict';

	var C = w.JPXCC_CFG;
	if (!C || w.JPXCC_LOADED) {
		return;
	}
	w.JPXCC_LOADED = true;

	var S = w.JPXCC_STATE || { valid: false, cats: [] };
	var map = w.JPXCC_MAP;
	var libState = 0; // 0 = não carregada, 1 = carregando, 2 = pronta
	var libQueue = [];
	var started = false;
	var startPromise = null;
	// Pré-visualização pelo painel (?jpxcc_preview=1): mostra o banner sem alterar a escolha salva.
	var preview = /[?&]jpxcc_preview=1(&|$)/.test(w.location.search);

	/* ---------------------------------------------------------------
	 * Consent Mode + integrações
	 * ------------------------------------------------------------- */

	// fromHead = true quando o <head> já aplicou o Consent Mode (visitante recorrente).
	function applyConsent(cats, fromHead) {
		if (!fromHead) {
			if (typeof w.gtag === 'function' && map) {
				w.gtag('consent', 'update', map(cats));
			}
			(w.dataLayer = w.dataLayer || []).push({ event: 'jpxcc_consent', jpxcc_categories: cats.join(',') });
		}

		// WP Consent API (Site Kit e outros plugins).
		if (typeof w.wp_set_consent === 'function') {
			var has = function (c) { return cats.indexOf(c) > -1 ? 'allow' : 'deny'; };
			w.wp_set_consent('functional', 'allow');
			w.wp_set_consent('preferences', has('functional'));
			w.wp_set_consent('statistics', has('analytics'));
			w.wp_set_consent('statistics-anonymous', has('analytics'));
			w.wp_set_consent('marketing', has('marketing'));
		}

		try {
			d.dispatchEvent(new CustomEvent('jpxcc:consent', { detail: { categories: cats.slice() } }));
		} catch (e) { /* navegadores muito antigos */ }
	}

	function log(action) {
		if (!C.rest || !w.CookieConsent) {
			return;
		}
		try {
			var ck = w.CookieConsent.getCookie();
			if (!ck || !ck.consentId) {
				return;
			}
			var body = JSON.stringify({
				consent_id: ck.consentId,
				action: action,
				categories: ck.categories || [],
				revision: ck.revision || 0,
				page: w.location.pathname
			});
			if (w.fetch) {
				w.fetch(C.rest, {
					method: 'POST',
					keepalive: true,
					credentials: 'omit',
					headers: { 'Content-Type': 'application/json' },
					body: body
				}).catch(function () {});
			}
		} catch (e) { /* o registro nunca pode quebrar a página */ }
	}

	/* ---------------------------------------------------------------
	 * Ativação de scripts bloqueados (visitante que já escolheu)
	 * ------------------------------------------------------------- */

	function activateScripts(cats) {
		var list = [].slice.call(d.querySelectorAll('script[data-category][data-jpx-blocked]')).filter(function (s) {
			return cats.indexOf(s.getAttribute('data-category')) > -1;
		});

		(function next(i) {
			if (i >= list.length) {
				return;
			}
			var old = list[i];
			var el = d.createElement('script');
			var skip = { type: 1, 'data-category': 1, 'data-jpx-blocked': 1, 'data-type': 1 };
			var attrs = old.attributes;
			var hasSrc = old.hasAttribute('src');

			for (var a = 0; a < attrs.length; a++) {
				if (!skip[attrs[a].name]) {
					el.setAttribute(attrs[a].name, attrs[a].value);
				}
			}
			el.type = old.getAttribute('data-type') || 'text/javascript';
			el.setAttribute('data-jpx-activated', old.getAttribute('data-category'));

			if (hasSrc) {
				// Mantém a ordem: o próximo só roda depois deste carregar.
				el.onload = el.onerror = function () { next(i + 1); };
				old.parentNode.replaceChild(el, old);
			} else {
				el.text = old.text;
				old.parentNode.replaceChild(el, old);
				next(i + 1);
			}
		})(0);
	}

	/* ---------------------------------------------------------------
	 * CookieConsent sob demanda
	 * ------------------------------------------------------------- */

	function loadLib(cb) {
		if (libState === 2) {
			return cb();
		}
		libQueue.push(cb);
		if (libState === 1) {
			return;
		}
		libState = 1;

		var pending = 2;
		var done = function () {
			if (--pending === 0) {
				libState = 2;
				libQueue.splice(0).forEach(function (f) { f(); });
			}
		};

		var style = d.createElement('style');
		style.id = 'jpxcc-vars';
		style.textContent = C.styles || '';

		var link = d.createElement('link');
		link.rel = 'stylesheet';
		link.href = C.css;
		link.onload = link.onerror = done;
		d.head.appendChild(link);
		d.head.appendChild(style);

		var s = d.createElement('script');
		s.src = C.js;
		s.async = true;
		s.onload = done;
		s.onerror = function () { libState = 0; };
		d.head.appendChild(s);
	}

	function applyTheme() {
		var root = d.documentElement;
		if (C.theme === 'dark') {
			root.classList.add('cc--darkmode');
		} else if (C.theme === 'auto' && w.matchMedia) {
			var mq = w.matchMedia('(prefers-color-scheme: dark)');
			root.classList.toggle('cc--darkmode', mq.matches);
		}
	}

	function fillConsentId() {
		var id = w.CookieConsent && w.CookieConsent.getCookie('consentId');
		[].forEach.call(d.querySelectorAll('#cc-main .jpxcc-id'), function (el) {
			el.textContent = id || C.idPending || '—';
		});
	}

	function autoClear() {
		return {
			analytics: { cookies: [{ name: /^_ga/ }, { name: '_gid' }, { name: /^_clck/ }, { name: /^_clsk/ }, { name: /^_hj/ }] },
			marketing: { cookies: [{ name: '_fbp' }, { name: '_fbc' }, { name: /^_gcl/ }, { name: '_ttp' }, { name: /^_pin/ }] }
		};
	}

	// noShow = true: carrega sem exibir o banner sozinho (usado ao reabrir pela pílula).
	function start(noShow) {
		if (startPromise) {
			return startPromise;
		}
		startPromise = new Promise(function (resolve) {
			loadLib(function () {
				var CC = w.CookieConsent;
				var cfg = C.cc;
				var clear = autoClear();

				Object.keys(cfg.categories).forEach(function (k) {
					if (clear[k]) {
						cfg.categories[k].autoClear = clear[k];
					}
				});

				// Validade da escolha: quem aceitou tudo x quem recusou alguma categoria.
				if (C.days) {
					cfg.cookie.expiresAfterDays = function (acceptType) {
						return acceptType === 'all' ? C.days[0] : C.days[1];
					};
				}

				cfg.onFirstConsent = function (p) {
					var type = CC.getUserPreferences().acceptType;
					var cats = p.cookie.categories;
					applyConsent(cats);
					log(type === 'all' ? 'accept_all' : (type === 'necessary' ? 'reject_all' : 'custom'));
					disarm();
					removePill();
					sessSet(false);
					fab();
					// Opt-out: se recusou uma categoria cujos scripts já rodaram por padrão, recarrega para pará-los.
					var ranAndRefused = [].some.call(d.querySelectorAll('script[data-jpx-activated]'), function (el) {
						return cats.indexOf(el.getAttribute('data-jpx-activated')) === -1;
					});
					if (ranAndRefused) {
						setTimeout(function () { w.location.reload(); }, 300);
					}
				};

				cfg.onChange = function (p) {
					// No modo opt-out a primeira escolha também dispara onChange;
					// ela já foi tratada (e registrada) em onFirstConsent.
					if (p.cookie.consentTimestamp === p.cookie.lastConsentTimestamp) {
						return;
					}
					applyConsent(p.cookie.categories);
					log('update');
					// Se alguma categoria foi revogada, recarrega para parar os scripts que já rodaram.
					var revoked = p.changedCategories.some(function (c) {
						return p.cookie.categories.indexOf(c) === -1;
					});
					if (revoked) {
						setTimeout(function () { w.location.reload(); }, 300);
					}
				};

				cfg.onModalShow = function (p) {
					if (p.modalName === 'preferencesModal') {
						fillConsentId();
						disarm();
					} else if (!CC.validConsent()) {
						removePill();
						armMinimize();
					}
				};

				cfg.onModalHide = function (p) {
					if (p.modalName === 'consentModal') {
						disarm();
					}
				};

				if (noShow) {
					cfg.autoShow = false;
				}

				applyTheme();
				started = true;
				CC.run(cfg).then(resolve, resolve);
			});
		});
		return startPromise;
	}

	/* ---------------------------------------------------------------
	 * Atalhos discretos: banner minimizado (pílula) e botão flutuante
	 * ------------------------------------------------------------- */

	var COOKIE_SVG = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="#c98a4b" d="M21.6 11.2a3 3 0 0 1-3.4-3.4 3 3 0 0 1-3.6-3.6A10 10 0 1 0 21.6 11.2z"/>'
		+ '<circle cx="8" cy="9" r="1.4" fill="#5b3a1a"/><circle cx="14" cy="15" r="1.6" fill="#5b3a1a"/><circle cx="8.5" cy="15.5" r="1.1" fill="#5b3a1a"/></svg>';
	var POS = C.pos || 'right-middle';
	var SIDE = /-(middle|low)$/.test(POS) ? (POS.indexOf('right') === 0 ? 'right' : 'left') : '';

	function shortcutCss() {
		if (d.getElementById('jpxcc-sc-css')) {
			return;
		}
		var a = (C.accent && C.accent[0]) || '#1f6feb';
		var t = (C.accent && C.accent[1]) || '#fff';
		var css = '.jpxcc-sc{position:fixed;z-index:2147483000;display:flex;align-items:center;justify-content:center;gap:6px;border:0;margin:0;cursor:pointer;'
			+ 'box-shadow:0 2px 12px rgba(0,0,0,.18);font-family:inherit;line-height:1;-webkit-tap-highlight-color:transparent}'
			+ '.jpxcc-sc svg{width:20px;height:20px;flex:none}'
			+ '.jpxcc-sc:focus-visible{outline:2px solid ' + a + ';outline-offset:2px}'
			+ '.jpxcc-pill{background:' + a + ';color:' + t + ';padding:9px 13px;border-radius:999px;font-size:13px;font-weight:600}'
			+ '.jpxcc-pill:hover{filter:brightness(.95)}'
			+ '#jpxcc-fab{background:#fff;width:44px;height:44px;border-radius:50%;padding:0}#jpxcc-fab svg{width:24px;height:24px}'
			+ '.jpxcc-sc--bottom-left{left:16px;bottom:16px}.jpxcc-sc--bottom-right{right:16px;bottom:16px}'
			+ '.jpxcc-sc--bottom-center{left:50%;bottom:16px;transform:translateX(-50%)}'
			+ '.jpxcc-sc--top-left{left:16px;top:16px}.jpxcc-sc--top-right{right:16px;top:16px}'
			+ '.jpxcc-sc--right-middle{right:0;top:50%;transform:translateY(-50%)}.jpxcc-sc--left-middle{left:0;top:50%;transform:translateY(-50%)}'
			+ '.jpxcc-sc--right-low{right:0;bottom:96px}.jpxcc-sc--left-low{left:0;bottom:96px}'
			// Nas laterais vira uma aba colada na borda.
			+ '.jpxcc-sc--side-right{border-radius:10px 0 0 10px}.jpxcc-sc--side-left{border-radius:0 10px 10px 0}'
			+ '.jpxcc-pill.jpxcc-sc--side-right,.jpxcc-pill.jpxcc-sc--side-left{flex-direction:column;padding:12px 8px}'
			+ '.jpxcc-pill.jpxcc-sc--side-right span,.jpxcc-pill.jpxcc-sc--side-left span{writing-mode:vertical-rl;transform:rotate(180deg)}'
			+ '#jpxcc-fab.jpxcc-sc--side-right,#jpxcc-fab.jpxcc-sc--side-left{width:40px;height:44px}'
			+ '@media print{.jpxcc-sc{display:none}}';
		var st = d.createElement('style');
		st.id = 'jpxcc-sc-css';
		st.textContent = css;
		d.head.appendChild(st);
	}

	function shortcutClass(extra) {
		return 'jpxcc-sc jpxcc-sc--' + POS + (SIDE ? ' jpxcc-sc--side-' + SIDE : '') + (extra ? ' ' + extra : '');
	}

	function fab() {
		if (!C.fab || d.getElementById('jpxcc-fab')) {
			return;
		}
		shortcutCss();
		var b = d.createElement('button');
		b.id = 'jpxcc-fab';
		b.type = 'button';
		b.className = shortcutClass();
		b.setAttribute('data-cc', 'show-preferencesModal');
		b.setAttribute('aria-label', C.fabLabel);
		b.title = C.fabLabel;
		b.innerHTML = COOKIE_SVG;
		d.body.appendChild(b);
	}

	/* ---------------------------------------------------------------
	 * Minimizar o banner sem decisão (até o fim da visita)
	 * Minimizar NÃO é uma escolha: nada é gravado e as categorias
	 * continuam no estado padrão (opt-in desligado, opt-out ligado).
	 * ------------------------------------------------------------- */

	var MIN_KEY = 'jpxcc_min';
	var detach = null;

	function sessGet() {
		try { return w.sessionStorage.getItem(MIN_KEY) === '1'; } catch (e) { return false; }
	}

	function sessSet(on) {
		try {
			if (on) {
				w.sessionStorage.setItem(MIN_KEY, '1');
			} else {
				w.sessionStorage.removeItem(MIN_KEY);
			}
		} catch (e) { /* modo privado ou bloqueado */ }
	}

	function pill() {
		if (!C.min || d.getElementById('jpxcc-pill')) {
			return;
		}
		shortcutCss();
		var b = d.createElement('button');
		b.id = 'jpxcc-pill';
		b.type = 'button';
		b.className = shortcutClass('jpxcc-pill');
		b.setAttribute('aria-label', C.min.aria || C.min.label);
		b.setAttribute('aria-haspopup', 'dialog');
		b.innerHTML = COOKIE_SVG;
		var label = d.createElement('span');
		label.textContent = C.min.label;
		b.appendChild(label);
		b.addEventListener('click', function (e) {
			e.preventDefault();
			reopen();
		});
		d.body.appendChild(b);
	}

	function removePill() {
		var p = d.getElementById('jpxcc-pill');
		if (p) {
			p.parentNode.removeChild(p);
		}
	}

	function reopen() {
		removePill();
		start(true).then(function () {
			if (!w.CookieConsent.validConsent()) {
				w.CookieConsent.show(true);
			}
		});
	}

	function disarm() {
		if (detach) {
			detach();
		}
	}

	function minimize() {
		disarm();
		if (started && w.CookieConsent) {
			w.CookieConsent.hide();
		}
		sessSet(true);
		pill();
	}

	function armMinimize() {
		if (!C.min || detach || preview) {
			return;
		}
		var offs = [];
		var shownAt = Date.now();
		var y0 = w.pageYOffset;
		var on = function (target, ev, fn, opt) {
			target.addEventListener(ev, fn, opt);
			offs.push(function () { target.removeEventListener(ev, fn, opt); });
		};

		if (C.min.outside) {
			on(d, 'pointerdown', function (e) {
				var t = e.target;
				if (Date.now() - shownAt < 400 || !t || !t.closest) {
					return;
				}
				if (t.closest('#cc-main') || t.closest('.jpxcc-sc') || t.closest('[data-cc]')) {
					return;
				}
				minimize();
			}, true);
		}

		if (C.min.scroll) {
			on(w, 'scroll', function () {
				// A rolagem que fez o banner aparecer (modo "primeira interação") não conta.
				if (Date.now() - shownAt < 1000) {
					y0 = w.pageYOffset;
					return;
				}
				if (Math.abs(w.pageYOffset - y0) > w.innerHeight * 0.5) {
					minimize();
				}
			}, { passive: true });
		}

		if (C.min.delay > 0) {
			var timer = setTimeout(minimize, C.min.delay * 1000);
			offs.push(function () { clearTimeout(timer); });
		}

		detach = function () {
			offs.forEach(function (f) { f(); });
			offs = [];
			detach = null;
		};
	}

	/* ---------------------------------------------------------------
	 * Inicialização
	 * ------------------------------------------------------------- */

	// Links "Preferências de cookies" (shortcode, rodapé, botão flutuante).
	d.addEventListener('click', function (e) {
		var t = e.target && e.target.closest ? e.target.closest('[data-cc="show-preferencesModal"]') : null;
		if (!t) {
			return;
		}
		e.preventDefault();
		if (started && w.CookieConsent) {
			w.CookieConsent.showPreferences();
			return;
		}
		start().then(function () {
			w.CookieConsent.showPreferences();
		});
	});


	if (S.valid) {
		// Escolha já feita: nada de biblioteca, só ativa o que foi autorizado.
		applyConsent(S.cats, true);
		activateScripts(S.cats);
		fab();
		if (preview) {
			start().then(function () { w.CookieConsent.show(true); });
		}
		return;
	}

	// Opt-out (legítimo interesse): categorias liberadas por padrão rodam já, antes do banner.
	if (S.implicit && S.cats.length) {
		applyConsent(S.cats, true);
		activateScripts(S.cats);
	}

	// Já minimizado nesta visita: só a pílula, sem baixar a biblioteca.
	if (C.min && !preview && sessGet()) {
		pill();
		return;
	}

	if (C.showMode === 'interaction' && !preview) {
		var events = ['pointerdown', 'keydown', 'scroll', 'touchstart'];
		var once = function () {
			events.forEach(function (ev) { w.removeEventListener(ev, once, true); });
			start();
		};
		events.forEach(function (ev) { w.addEventListener(ev, once, { capture: true, passive: true }); });
	} else {
		start();
	}
})(window, document);
