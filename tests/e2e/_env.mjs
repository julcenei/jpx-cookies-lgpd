// Configuração compartilhada dos testes de navegador.
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const here = path.dirname(fileURLToPath(import.meta.url));

export const BASE = (process.env.JPXCC_BASE || 'http://localhost:8099').replace(/\/$/, '') + '/';
export const SITE = process.env.JPXCC_SITE || path.resolve(here, '../../.test-site');
export const ADMIN_USER = process.env.JPXCC_ADMIN_USER || 'admin';
export const ADMIN_PASS = process.env.JPXCC_ADMIN_PASS || 'admin123';
export const SHOTS = process.env.JPXCC_SHOTS || path.resolve(here, '../../.shots');
mkdirSync(SHOTS, { recursive: true });

const helper = (name, args) => execFileSync('php', [path.resolve(here, '../helpers', name), SITE, ...args]).toString().trim();

/** Altera configurações: set('minimize_delay=2', 'categories.analytics.basis=legit') */
export const set = (...kv) => helper('setopt.php', kv);

/** Restaura textos/layout padrão e define a base legal de Estatísticas. */
export const setBasis = (basis) => helper('setbasis.php', [basis]);

/** Contexto de navegador "humano" (a CookieConsent esconde o banner de robôs). */
export async function humanContext(browser, options = {}) {
	const ctx = await browser.newContext(options);
	await ctx.addInitScript(() => Object.defineProperty(Navigator.prototype, 'webdriver', { get: () => false }));
	// Scripts de terceiros são simulados: o teste não depende de rede externa.
	await ctx.route(/googletagmanager|googlesyndication|facebook\.net|clarity\.ms/, (r) => r.fulfill({ status: 200, contentType: 'text/javascript', body: '' }));
	return ctx;
}

export function reporter() {
	const results = [];
	return {
		ok: (n, c, e = '') => results.push(`${c ? 'PASS' : 'FAIL'}  ${n}${e ? '  — ' + e : ''}`),
		done() {
			const passed = results.filter((r) => r.startsWith('PASS')).length;
			console.log(results.join('\n'));
			console.log(`\n${passed}/${results.length} ok`);
			if (passed !== results.length) process.exitCode = 1;
		},
	};
}
