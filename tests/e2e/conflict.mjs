// Conflito com outro código de Consent Mode na página (tema, snippet antigo, outro plugin).
// Usa o gtag.js real do Google para medir o consentimento efetivo e o parâmetro gcs
// de cada page_view. Os hits ao GA4 são interceptados: nada é enviado ao Google.
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { BASE, SITE, set, setBasis, reporter } from './_env.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const option = (...a) => execFileSync('php', [path.resolve(here, '../helpers/option.php'), SITE, ...a]).toString().trim();
const rep = reporter();
const ok = rep.ok;

// Baixa o gtag.js real uma vez (curl respeita HTTPS_PROXY) e o serve ao navegador.
function downloadGtag() {
	try {
		return execFileSync('curl', ['-sSf', '-m', '20', 'https://www.googletagmanager.com/gtag/js?id=G-TESTE12345'], { maxBuffer: 5 * 1024 * 1024 }).toString();
	} catch (e) {
		return '';
	}
}
const GTAG = downloadGtag();
if (!GTAG.includes('google_tag_data')) {
	console.log('SKIP  não foi possível baixar o gtag.js do Google; suíte de conflito ignorada.');
	process.exit(0);
}
const browser = await chromium.launch();

async function ctxNew() {
	const ctx = await browser.newContext();
	await ctx.addInitScript(() => Object.defineProperty(Navigator.prototype, 'webdriver', { get: () => false }));
	await ctx.route(/googlesyndication|facebook\.net|clarity\.ms/, (r) => r.fulfill({ status: 200, contentType: 'text/javascript', body: '' }));
	await ctx.route(/googletagmanager\.com\/gtag\/js/, (r) => r.fulfill({ status: 200, contentType: 'text/javascript', body: GTAG }));
	const views = [];
	await ctx.route(/doubleclick\.net|google\.com\/(ccm|pagead|measurement)/, (r) => r.fulfill({ status: 204, body: '' }));
	await ctx.route(/google-analytics\.com\/(g\/)?collect|analytics\.google\.com\/g\/collect/, (r) => {
		const q = new URL(r.request().url()).searchParams;
		const body = r.request().postData() || '';
		const events = [q.get('en'), ...body.split('\n').map((l) => new URLSearchParams(l).get('en'))].filter(Boolean);
		if (events.includes('page_view')) views.push({ gcs: q.get('gcs'), page: new URL(q.get('dl') || BASE).pathname });
		return r.fulfill({ status: 204, body: '' });
	});
	return { ctx, views };
}

const effective = (p) => p.evaluate(() => {
	const e = window.google_tag_data && window.google_tag_data.ics && window.google_tag_data.ics.entries;
	if (!e) return null;
	const v = (k) => (e[k] ? (e[k].update !== undefined ? e[k].update : e[k].default) : undefined);
	return { analytics: v('analytics_storage'), ads: v('ad_storage') };
});

async function waitViews(views, n) {
	for (let i = 0; i < 40 && views.length < n; i++) await new Promise((r) => setTimeout(r, 250));
}


option('jpxcc_test_conflict', '1');

// 1) Legítimo interesse em Estatísticas + snippet conflitante: quem ignora continua contado.
setBasis('legit');
{
	const { ctx, views } = await ctxNew();
	const p = await ctx.newPage();
	await p.goto(BASE);
	await p.waitForSelector('#cc-main .cm', { state: 'visible' });
	await waitViews(views, 1);
	const eff = await effective(p);
	ok('conflito + opt-out: estatísticas efetivamente liberadas', eff && eff.analytics === true && eff.ads === false, JSON.stringify(eff));
	ok('conflito + opt-out: page_view sai com gcs=G101', views[0] && views[0].gcs === 'G101', JSON.stringify(views));
	await p.goto(BASE + 'politica-de-privacidade/');
	await waitViews(views, 2);
	ok('conflito + opt-out: segunda página também contada', views[1] && views[1].gcs === 'G101', JSON.stringify(views[1]));
	await ctx.close();
}

// 2) Opt-in + snippet conflitante: quem aceita continua liberado nas páginas seguintes.
setBasis('consent');
{
	const { ctx, views } = await ctxNew();
	const p = await ctx.newPage();
	await p.goto(BASE);
	await p.waitForSelector('#cc-main .cm', { state: 'visible' });
	await waitViews(views, 1);
	ok('conflito + opt-in: antes da escolha, tudo negado (G100)', views[0] && views[0].gcs === 'G100', JSON.stringify(views[0]));
	await p.click('#cc-main .cm [data-role="all"]');
	await p.waitForTimeout(800);
	await p.goto(BASE + 'politica-de-privacidade/');
	await waitViews(views, 2);
	const eff = await effective(p);
	ok('conflito + opt-in: quem aceitou segue liberado na página seguinte', eff && eff.analytics === true && eff.ads === true, JSON.stringify(eff));
	ok('conflito + opt-in: page_view da página seguinte com gcs=G111', views[1] && views[1].gcs === 'G111', JSON.stringify(views[1]));
	await ctx.close();
}

// 3) Opt-in + snippet conflitante: quem recusou segue negado.
{
	const { ctx, views } = await ctxNew();
	const p = await ctx.newPage();
	await p.goto(BASE);
	await p.waitForSelector('#cc-main .cm', { state: 'visible' });
	await p.click('#cc-main .cm [data-role="necessary"]');
	await p.waitForTimeout(800);
	await p.goto(BASE + 'politica-de-privacidade/');
	await waitViews(views, 2);
	ok('conflito + opt-in: quem recusou segue negado (G100)', views.length >= 2 && views[views.length - 1].gcs === 'G100', JSON.stringify(views));
	await ctx.close();
}

// 4) Aviso no console sobre o conflito
{
	const { ctx } = await ctxNew();
	const p = await ctx.newPage();
	const warns = [];
	p.on('console', (m) => { if (m.type() === 'warning') warns.push(m.text()); });
	await p.goto(BASE);
	await p.waitForTimeout(1500);
	ok('console avisa sobre Consent Mode duplicado', warns.some((w) => w.includes('declarações de consent "default"')), warns.join(' | '));
	ok('console avisa sobre url_passthrough', warns.some((w) => w.includes('url_passthrough')));
	await ctx.close();
}

option('jpxcc_test_conflict');
set('show_mode=load');
await browser.close();
rep.done();
