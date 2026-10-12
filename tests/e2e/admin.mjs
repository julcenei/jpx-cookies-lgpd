import { chromium } from 'playwright';
import { BASE, SHOTS, ADMIN_USER, ADMIN_PASS, set, setBasis, humanContext, reporter } from './_env.mjs';
const rep = reporter();
const ok = rep.ok;

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
await ctx.addInitScript(() => Object.defineProperty(Navigator.prototype, 'webdriver', { get: () => false }));
await ctx.route(/googletagmanager|googlesyndication|facebook\.net|clarity\.ms/, (r) => r.fulfill({ status: 200, body: '' }));
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));

await page.goto(BASE + 'wp-login.php');
await page.fill('#user_login', ADMIN_USER);
await page.fill('#user_pass', ADMIN_PASS);
await page.click('#wp-submit');
await page.waitForLoadState('networkidle');

const url = BASE + 'wp-admin/options-general.php?page=jpx-cookies-lgpd';
await page.goto(url);
ok('painel abre', await page.isVisible('h1:has-text("JPX Cookies LGPD")'));
await page.screenshot({ path: `${SHOTS}/admin-geral.png`, fullPage: true });

for (const tab of ['aparencia', 'textos', 'categorias', 'google']) {
	await page.click(`.jpxcc-js-tab[data-tab="${tab}"]`);
	ok(`aba ${tab}`, await page.isVisible(`[data-panel="${tab}"]`) && !(await page.isVisible('[data-panel="geral"]')));
	await page.screenshot({ path: `${SHOTS}/admin-${tab}.png`, fullPage: true });
}

// Salvar: muda texto e cor e verifica persistência
await page.click('.jpxcc-js-tab[data-tab="textos"]');
await page.fill('#jpxcc-texts-cm_title', 'Cookies no Portal');
await page.click('.jpxcc-js-tab[data-tab="aparencia"]');
await page.selectOption('#jpxcc-layout', 'bar inline');
await page.click('#submit');
await page.waitForLoadState('networkidle');
ok('salvar volta na mesma aba', page.url().endsWith('#aparencia'), page.url());
ok('layout salvo', (await page.inputValue('#jpxcc-layout')) === 'bar inline');
ok('texto salvo', (await page.inputValue('#jpxcc-texts-cm_title')) === 'Cookies no Portal');
ok('categorias mantidas ao salvar', await page.isChecked('#jpxcc-categories-marketing-enabled'));
ok('ID GA mantido', (await page.inputValue('#jpxcc-ga_ids')) === 'G-TESTE12345');

// Registros
await page.goto(url + '&view=registros');
ok('registros listados', (await page.$$('.jpxcc-log tbody tr')).length >= 5);
await page.screenshot({ path: `${SHOTS}/admin-registros.png`, fullPage: true });

// Exportar CSV
const [dl] = await Promise.all([page.waitForEvent('download'), page.click('input[value="Exportar CSV"]')]);
const csvPath = `${SHOTS}/consent.csv`;
await dl.saveAs(csvPath);
ok('CSV exportado', true);

// Exclusão por ID
page.on('dialog', (d) => d.accept());
const targetId = (await page.textContent('.jpxcc-log tbody tr:first-child code')).trim();
const rowsWithId = await page.locator('.jpxcc-log tbody code', { hasText: targetId }).count();
await page.fill('input[name="consent_id"]', targetId);
await page.click('input[value="Excluir registros"]');
await page.waitForLoadState('networkidle');
const notice = (await page.textContent('.notice-success')) || '';
ok('exclusão por ID', notice.includes(`${rowsWithId} registro`) && (await page.locator('.jpxcc-log tbody code', { hasText: targetId }).count()) === 0, notice.trim());

// Ferramentas: exportar e importar JSON
await page.goto(url + '&view=ferramentas');
const [dj] = await Promise.all([page.waitForEvent('download'), page.click('input[value="Baixar JSON"]')]);
const jsonPath = `${SHOTS}/settings.json`;
await dj.saveAs(jsonPath);
await page.click('input[value="Incrementar revisão"]');
await page.waitForLoadState('networkidle');
ok('revisão incrementada', await page.isVisible('text=Revisão atual: 2'));
await page.setInputFiles('input[name="jpxcc_file"]', jsonPath);
await page.click('input[value="Importar"]');
await page.waitForLoadState('networkidle');
ok('importação', await page.isVisible('.notice-success:has-text("importadas")') && await page.isVisible('text=Revisão atual: 1'));
await page.screenshot({ path: `${SHOTS}/admin-ferramentas.png`, fullPage: true });
ok('sem erros JS no painel', errors.length === 0, errors.join('|'));

await browser.close();

// Banner: desktop e celular, visitante novo
const b2 = await chromium.launch();
for (const [name, vp] of [['desktop', { width: 1280, height: 800 }], ['mobile', { width: 390, height: 844 }]]) {
	const c = await b2.newContext({ viewport: vp, deviceScaleFactor: 2 });
	await c.addInitScript(() => Object.defineProperty(Navigator.prototype, 'webdriver', { get: () => false }));
	await c.route(/googletagmanager|googlesyndication|facebook\.net|clarity\.ms/, (r) => r.fulfill({ status: 200, body: '' }));
	const p = await c.newPage();
	await p.goto(BASE);
	await p.waitForSelector('#cc-main .cm', { state: 'visible' });
	await p.waitForTimeout(600);
	await p.screenshot({ path: `${SHOTS}/banner-${name}.png` });
	await p.click('#cc-main [data-role="show"]');
	await p.waitForSelector('#cc-main .pm', { state: 'visible' });
	await p.waitForTimeout(600);
	await p.screenshot({ path: `${SHOTS}/prefs-${name}.png` });
	await c.close();
}
await b2.close();
rep.done();
