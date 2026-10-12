import { chromium } from 'playwright';
import { BASE, SHOTS, ADMIN_USER, ADMIN_PASS, set, setBasis, humanContext, reporter } from './_env.mjs';

const rep = reporter();
const ok = rep.ok;

const browser = await chromium.launch();
async function ctxNew(viewport = { width: 1280, height: 800 }) {
	const ctx = await browser.newContext({ viewport });
	await ctx.addInitScript(() => Object.defineProperty(Navigator.prototype, 'webdriver', { get: () => false }));
	await ctx.route(/googletagmanager|googlesyndication|facebook\.net|clarity\.ms/, (r) => r.fulfill({ status: 200, body: '' }));
	const log = { posts: [], lib: 0 };
	ctx.on('request', (r) => {
		if (r.url().includes('/jpx-cookies/v1/consent')) log.posts.push(r.postData());
		if (r.url().includes('cookieconsent.umd.js')) log.lib++;
	});
	return { ctx, log };
}
const consent = (p) => p.evaluate(() => (window.dataLayer || []).filter((e) => e && e[0] === 'consent').map((e) => e[2].analytics_storage));
const errors = [];

// 1) Clicar fora -> minimiza na lateral direita, centro
{
	set('shortcut_position=right-middle', 'minimize_delay=10', 'floating_button=0', 'categories.analytics.basis=consent');
	const { ctx, log } = await ctxNew();
	const p = await ctx.newPage();
	p.on('pageerror', (e) => errors.push(e.message));
	await p.goto(BASE);
	await p.waitForSelector('#cc-main .cm', { state: 'visible' });
	await p.waitForTimeout(600);
	await p.mouse.click(400, 300);
	await p.waitForTimeout(500);
	ok('clicar fora minimiza', !(await p.isVisible('#cc-main .cm')) && await p.isVisible('#jpxcc-pill'));
	const box = await p.locator('#jpxcc-pill').boundingBox();
	ok('pílula colada na lateral direita, no centro', Math.round(box.x + box.width) === 1280 && Math.abs(box.y + box.height / 2 - 400) < 3, JSON.stringify(box));
	await p.screenshot({ path: `${SHOTS}/min-right-middle.png` });
	ok('minimizar não grava registro', log.posts.length === 0);
	ok('minimizar não libera estatísticas (opt-in)', (await consent(p)).every((v) => v === 'denied'));

	const libBefore = log.lib;
	await p.click('#other-link');
	await p.waitForLoadState('load');
	await p.waitForTimeout(700);
	ok('próxima página já abre minimizada', await p.isVisible('#jpxcc-pill') && !(await p.isVisible('#cc-main .cm')));
	ok('página minimizada não baixa a biblioteca', log.lib === libBefore, `${libBefore} -> ${log.lib}`);

	await p.click('#jpxcc-pill');
	await p.waitForSelector('#cc-main .cm', { state: 'visible', timeout: 4000 });
	ok('clicar na pílula reabre o banner', !(await p.isVisible('#jpxcc-pill')));
	await p.click('#cc-main .cm [data-role="all"]');
	await p.waitForTimeout(600);
	ok('aceitar depois de reabrir registra accept_all', log.posts.some((x) => x.includes('"accept_all"')));
	ok('depois da escolha a pílula some', !(await p.isVisible('#jpxcc-pill')));
	await p.reload();
	await p.waitForTimeout(600);
	ok('nova página após escolher: sem banner e sem pílula', !(await p.isVisible('#jpxcc-pill')) && !(await p.isVisible('#cc-main .cm')));
	await ctx.close();
}

// 2) Rolar a página minimiza
{
	const { ctx } = await ctxNew();
	const p = await ctx.newPage();
	await p.goto(BASE);
	await p.waitForSelector('#cc-main .cm', { state: 'visible' });
	await p.waitForTimeout(1200);
	await p.mouse.wheel(0, 200);
	await p.waitForTimeout(400);
	ok('rolagem pequena não minimiza', await p.isVisible('#cc-main .cm'));
	await p.mouse.wheel(0, 500);
	await p.waitForTimeout(500);
	ok('rolar meia tela minimiza', await p.isVisible('#jpxcc-pill') && !(await p.isVisible('#cc-main .cm')));
	await ctx.close();
}

// 3) Tempo: minimiza sozinho
{
	set('minimize_delay=2');
	const { ctx } = await ctxNew();
	const p = await ctx.newPage();
	await p.goto(BASE);
	await p.waitForSelector('#cc-main .cm', { state: 'visible' });
	await p.waitForTimeout(1000);
	ok('antes do tempo continua aberto', await p.isVisible('#cc-main .cm'));
	await p.waitForTimeout(1800);
	ok('depois do tempo minimiza', await p.isVisible('#jpxcc-pill'));
	await ctx.close();
}

// 4) Abrir preferências não minimiza (nem pelo tempo)
{
	const { ctx } = await ctxNew();
	const p = await ctx.newPage();
	await p.goto(BASE);
	await p.waitForSelector('#cc-main .cm', { state: 'visible' });
	await p.click('#cc-main [data-role="show"]');
	await p.waitForSelector('#cc-main .pm', { state: 'visible' });
	await p.waitForTimeout(2800);
	ok('preferências abertas não minimizam', await p.isVisible('#cc-main .pm') && !(await p.isVisible('#jpxcc-pill')));
	await ctx.close();
}

// 5) Posições e botão flutuante no mesmo lugar
for (const pos of ['bottom-left', 'left-middle', 'bottom-center', 'right-low']) {
	set(`shortcut_position=${pos}`, 'minimize_delay=10', 'floating_button=1');
	const { ctx } = await ctxNew({ width: 390, height: 844 });
	const p = await ctx.newPage();
	await p.goto(BASE);
	await p.waitForSelector('#cc-main .cm', { state: 'visible' });
	await p.waitForTimeout(600);
	await p.mouse.click(200, 120);
	await p.waitForTimeout(400);
	const b = await p.locator('#jpxcc-pill').boundingBox();
	await p.screenshot({ path: `${SHOTS}/min-${pos}.png` });
	await p.click('#jpxcc-pill');
	await p.waitForSelector('#cc-main .cm', { state: 'visible' });
	await p.click('#cc-main .cm [data-role="necessary"]');
	await p.waitForTimeout(500);
	const f = await p.locator('#jpxcc-fab').boundingBox();
	const near = f && Math.abs((f.x + f.width / 2) - (b.x + b.width / 2)) < 40 && Math.abs((f.y + f.height / 2) - (b.y + b.height / 2)) < 60;
	ok(`posição ${pos}: pílula e botão flutuante no mesmo lugar`, near, `pílula ${Math.round(b.x)},${Math.round(b.y)} / botão ${f ? Math.round(f.x) + ',' + Math.round(f.y) : 'n/a'}`);
	await ctx.close();
}

// 6) Opt-out + minimizado: continua contando
{
	set('shortcut_position=right-middle', 'minimize_delay=10', 'floating_button=0', 'categories.analytics.basis=legit');
	const { ctx, log } = await ctxNew();
	const p = await ctx.newPage();
	await p.goto(BASE);
	await p.waitForSelector('#cc-main .cm', { state: 'visible' });
	await p.waitForTimeout(600);
	await p.evaluate(() => document.body.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true })));
	await p.waitForTimeout(400);
	await p.reload();
	await p.waitForTimeout(600);
	ok('opt-out minimizado: estatísticas seguem liberadas', (await consent(p))[0] === 'granted' && await p.isVisible('#jpxcc-pill'));
	ok('opt-out minimizado: nada registrado', log.posts.length === 0);
	await ctx.close();
}

set('shortcut_position=right-middle', 'minimize_delay=10', 'floating_button=0', 'categories.analytics.basis=consent');
ok('sem erros de JS', errors.length === 0, errors.join('|'));
await browser.close();
rep.done();
