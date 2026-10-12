import { chromium } from 'playwright';
import { BASE, SHOTS, ADMIN_USER, ADMIN_PASS, set, setBasis, humanContext, reporter } from './_env.mjs';

const rep = reporter();
const ok = rep.ok;

async function newCtx(browser) {
	const ctx = await browser.newContext({ userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36' });
	await ctx.addInitScript(() => Object.defineProperty(Navigator.prototype, 'webdriver', { get: () => false }));
	const log = { lib: 0, posts: [] };
	await ctx.route(/googletagmanager|googlesyndication|facebook\.net|clarity\.ms/, (r) => r.fulfill({ status: 200, contentType: 'text/javascript', body: '' }));
	ctx.on('request', (req) => {
		if (req.url().includes('cookieconsent.umd.js')) log.lib++;
		if (req.url().includes('/jpx-cookies/v1/consent')) log.posts.push(req.postData());
	});
	return { ctx, log };
}

const consentCmds = (page) => page.evaluate(() => (window.dataLayer || [])
	.filter((e) => e && e[0] === 'consent').map((e) => ({ t: e[1], a: e[2].analytics_storage, m: e[2].ad_storage })));

const browser = await chromium.launch();

// 1) Visitante novo -> recusa
{
	const { ctx, log } = await newCtx(browser);
	const page = await ctx.newPage();
	const errors = [];
	page.on('pageerror', (e) => errors.push(e.message));
	await page.goto(BASE);
	await page.waitForSelector('#cc-main .cm', { state: 'visible', timeout: 5000 });
	ok('banner aparece para visitante novo', true);
	let cmds = await consentCmds(page);
	ok('consent default negado', cmds[0] && cmds[0].t === 'default' && cmds[0].a === 'denied' && cmds[0].m === 'denied', JSON.stringify(cmds[0]));
	ok('pixel bloqueado antes da escolha', !(await page.evaluate(() => window.__pixelRan)));
	ok('AdSense não bloqueado', await page.evaluate(() => !!document.querySelector('script[src*="adsbygoogle"]:not([type="text/plain"])')));
	const title = await page.textContent('#cc-main .cm__title');
	ok('texto padrão (Melhor experiência)', title.includes('navegação melhor'), title);
	const link = await page.getAttribute('#cc-main .cm__desc a', 'href');
	ok('link {politica} resolvido', /politica-de-privacidade/.test(link || ''), link);
	const sameWeight = await page.evaluate(() => {
		const b = [...document.querySelectorAll('#cc-main .cm__btn-group:first-child .cm__btn')];
		return b.length >= 2 && getComputedStyle(b[0]).backgroundColor === getComputedStyle(b[1]).backgroundColor;
	});
	ok('aceitar e recusar com mesmo destaque', sameWeight);

	await page.click('#cc-main .cm [data-role="necessary"]');
	await page.waitForTimeout(800);
	cmds = await consentCmds(page);
	const last = cmds[cmds.length - 1];
	ok('update após recusa continua negado', last.t === 'update' && last.a === 'denied', JSON.stringify(last));
	ok('registro enviado (reject_all)', log.posts.some((p) => p.includes('"reject_all"')), log.posts.join(' | '));
	ok('pixel continua bloqueado', !(await page.evaluate(() => window.__pixelRan)));
	const rc = (await ctx.cookies()).find((c) => c.name === 'jpxcc_consent');
	const rdays = Math.round((rc.expires - Date.now() / 1000) / 86400);
	ok('recusa expira em 90 dias', rdays >= 89 && rdays <= 90, rdays + ' dias');

	// Recarrega: não deve baixar a lib
	const libBefore = log.lib;
	await page.reload();
	await page.waitForTimeout(800);
	ok('visitante recorrente não baixa a biblioteca', log.lib === libBefore, `downloads: ${log.lib}`);
	ok('banner não reaparece', !(await page.$('#cc-main .cm')));
	cmds = await consentCmds(page);
	ok('head aplica escolha salva já no default', cmds.length === 1 && cmds[0].t === 'default' && cmds[0].a === 'denied', JSON.stringify(cmds));

	// Abre preferências pelo link do rodapé e libera tudo
	await page.click('a.jpxcc-prefs-link');
	await page.waitForSelector('#cc-main .pm', { state: 'visible', timeout: 5000 });
	ok('link de preferências abre a janela (lib sob demanda)', log.lib === libBefore + 1);
	const id = await page.textContent('#cc-main .jpxcc-id');
	ok('ID do consentimento exibido', /^[0-9a-f-]{36}$/.test(id), id);
	await page.click('#cc-main .pm [data-role="all"]');
	await page.waitForTimeout(1000);
	ok('pixel ativado após aceitar nas preferências', await page.evaluate(() => window.__pixelRan === true));
	ok('módulo de estatísticas ativado', await page.evaluate(() => window.__clarityModule === true));
	ok('registro de alteração (update)', log.posts.some((p) => p.includes('"update"')));
	cmds = await consentCmds(page);
	ok('Consent Mode liberado', cmds[cmds.length - 1].a === 'granted' && cmds[cmds.length - 1].m === 'granted');

	// Recorrente com tudo aceito: ativação sem a lib
	const lib2 = log.lib;
	await page.reload();
	await page.waitForTimeout(800);
	ok('recorrente (aceitou): scripts ativados sem a lib', log.lib === lib2 && await page.evaluate(() => window.__pixelRan === true && window.__clarityModule === true));

	// Revogação: recarrega a página
	await page.click('a.jpxcc-prefs-link');
	await page.waitForSelector('#cc-main .pm', { state: 'visible' });
	const nav = page.waitForNavigation({ timeout: 4000 }).then(() => true).catch(() => false);
	await page.click('#cc-main .pm [data-role="necessary"]');
	ok('revogar recarrega a página', await nav);
	await page.waitForTimeout(500);
	ok('após revogar, pixel não roda', !(await page.evaluate(() => window.__pixelRan)));
	ok('sem erros de JS', errors.length === 0, errors.join(' | '));
	await ctx.close();
}

// 2) Visitante novo aceita tudo no banner
{
	const { ctx, log } = await newCtx(browser);
	const page = await ctx.newPage();
	await page.goto(BASE);
	await page.waitForSelector('#cc-main .cm', { state: 'visible' });
	await page.click('#cc-main .cm [data-role="all"]');
	await page.waitForTimeout(800);
	ok('aceitar tudo ativa o pixel na hora', await page.evaluate(() => window.__pixelRan === true));
	ok('registro accept_all', log.posts.some((p) => p.includes('"accept_all"')));
	const cookie = (await ctx.cookies()).find((c) => c.name === 'jpxcc_consent');
	const adays = Math.round((cookie.expires - Date.now() / 1000) / 86400);
	ok('aceite expira em 182 dias', adays >= 181 && adays <= 182, adays + ' dias');
	await ctx.close();
}

// 3) Pré-visualização
{
	const { ctx } = await newCtx(browser);
	const page = await ctx.newPage();
	await page.goto(BASE);
	await page.waitForSelector('#cc-main .cm', { state: 'visible' });
	await page.click('#cc-main .cm [data-role="all"]');
	await page.goto(BASE + '?jpxcc_preview=1');
	await page.waitForSelector('#cc-main .cm', { state: 'visible', timeout: 5000 }).catch(() => {});
	ok('pré-visualização mostra o banner', await page.isVisible('#cc-main .cm'));
	await ctx.close();
}

await browser.close();
rep.done();
