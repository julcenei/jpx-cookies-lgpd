import { chromium } from 'playwright';
import { BASE, SHOTS, ADMIN_USER, ADMIN_PASS, set, setBasis, humanContext, reporter, effectiveConsent } from './_env.mjs';

const rep = reporter();
const ok = rep.ok;

const browser = await chromium.launch();
async function ctxNew() {
	const ctx = await browser.newContext();
	await ctx.addInitScript(() => Object.defineProperty(Navigator.prototype, 'webdriver', { get: () => false }));
	await ctx.route(/googletagmanager|googlesyndication|facebook\.net|clarity\.ms/, (r) => r.fulfill({ status: 200, body: '' }));
	const log = { posts: [] };
	ctx.on('request', (r) => { if (r.url().includes('/jpx-cookies/v1/consent')) log.posts.push(r.postData()); });
	return { ctx, log };
}
const consent = (p) => p.evaluate(() => (window.dataLayer || []).filter((e) => e && e[0] === 'consent').map((e) => ({ t: e[1], a: e[2].analytics_storage, m: e[2].ad_storage })));

// Visitante que recusou ANTES de ligar o opt-out
const old = await ctxNew();
const op = await old.ctx.newPage();
await op.goto(BASE);
await op.waitForSelector('#cc-main .cm', { state: 'visible' });
await op.click('#cc-main .cm [data-role="necessary"]');
await op.waitForTimeout(500);

setBasis('legit');

// 1) Visitante novo, opt-out em Estatísticas
{
	const { ctx, log } = await ctxNew();
	const p = await ctx.newPage();
	const errors = [];
	p.on('pageerror', (e) => errors.push(e.message));
	await p.goto(BASE);
	await p.waitForSelector('#cc-main .cm', { state: 'visible' });
	const c = await consent(p);
	const eff0 = await effectiveConsent(p);
	ok('legítimo interesse: estatísticas liberadas, publicidade negada', c[0].t === 'default' && c[0].a === 'denied' && eff0.analytics === 'granted' && eff0.ads === 'denied', JSON.stringify(c));
	ok('script de estatísticas roda antes de qualquer clique', await p.evaluate(() => window.__clarityModule === true));
	ok('pixel (publicidade) continua bloqueado', !(await p.evaluate(() => window.__pixelRan)));
	const desc = await p.textContent('#cc-main .cm__desc');
	ok('banner avisa do legítimo interesse', /legítimo interesse/.test(desc) && /estatísticas/.test(desc), desc.slice(-140));
	ok('dataLayer recebe evento implícito', await p.evaluate(() => window.dataLayer.some((e) => e.event === 'jpxcc_consent' && e.jpxcc_implicit)));

	await p.reload();
	await p.waitForSelector('#cc-main .cm', { state: 'visible' });
	ok('quem ignora segue contado na página seguinte', (await effectiveConsent(p)).analytics === 'granted');
	ok('ignorar não gera registro de consentimento', log.posts.length === 0, String(log.posts.length));

	await p.click('#cc-main [data-role="show"]');
	await p.waitForSelector('#cc-main .pm', { state: 'visible' });
	ok('preferências mostram estatísticas ligadas', await p.isChecked('#cc-main .pm input[value="analytics"]'));
	ok('preferências mostram publicidade desligada', !(await p.isChecked('#cc-main .pm input[value="marketing"]')));
	await p.click('#cc-main .pm .pm__close-btn');

	await p.evaluate(() => { window.__marker = 1; });
	await p.click('#cc-main .cm [data-role="necessary"]');
	await p.waitForTimeout(500);
	const ck = (await ctx.cookies()).find((x) => x.name === 'jpxcc_consent');
	const v = JSON.parse(decodeURIComponent(ck.value));
	console.log('timestamps', v.consentTimestamp, v.lastConsentTimestamp);
	await p.waitForTimeout(1200);
	ok('recusar recarrega (scripts de estatísticas já tinham rodado)', await p.evaluate(() => window.__marker === undefined));
	const c2 = await consent(p);
	ok('após recusar: estatísticas negadas', c2[c2.length - 1].a === 'denied', JSON.stringify(c2));
	ok('após recusar: script de estatísticas não roda', !(await p.evaluate(() => window.__clarityModule)));
	ok('após recusar: banner some', !(await p.isVisible('#cc-main .cm')));
	ok('recusa registrada como reject_all', log.posts.length === 1 && log.posts[0].includes('"reject_all"'), log.posts.join('|'));
	ok('sem erros de JS', errors.length === 0, errors.join('|'));
	await ctx.close();
}

// 2) Visitante novo aceita tudo: sem reload, pixel liberado
{
	const { ctx, log } = await ctxNew();
	const p = await ctx.newPage();
	await p.goto(BASE);
	await p.waitForSelector('#cc-main .cm', { state: 'visible' });
	await p.evaluate(() => { window.__marker = 1; });
	await p.click('#cc-main .cm [data-role="all"]');
	await p.waitForTimeout(1200);
	ok('aceitar tudo não recarrega', await p.evaluate(() => window.__marker === 1));
	await p.waitForTimeout(500);
	ok('aceitar tudo libera o pixel', await p.evaluate(() => window.__pixelRan === true));
	ok('aceite registrado', log.posts.some((x) => x.includes('"accept_all"')));
	await ctx.close();
}

// 3) Quem recusou antes da mudança continua recusado
await op.reload();
await op.waitForTimeout(600);
const oc = await consent(op);
const effOld = await effectiveConsent(op);
ok('recusa anterior é respeitada após ligar o opt-out', effOld.analytics === 'denied' && effOld.ads === 'denied' && !(await op.isVisible('#cc-main .cm')), JSON.stringify(oc));
await old.ctx.close();

setBasis('consent');
await browser.close();
rep.done();
