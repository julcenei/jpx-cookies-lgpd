import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { BASE, SITE, ADMIN_USER, ADMIN_PASS, SHOTS, humanContext, reporter } from './_env.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const log = (...args) => execFileSync('php', [path.resolve(here, '../helpers/log.php'), SITE, ...args.map(String)]).toString().trim();
const rep = reporter();
const ok = rep.ok;

const browser = await chromium.launch();

// 1) Registro feito pelo navegador grava o navegador resumido
log('clear');
{
	const ctx = await humanContext(browser, {
		userAgent: 'Mozilla/5.0 (Linux; Android 14; SM-A546E) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36',
	});
	const p = await ctx.newPage();
	await p.goto(BASE);
	await p.waitForSelector('#cc-main .cm', { state: 'visible' });
	await p.click('#cc-main .cm [data-role="all"]');
	await p.waitForTimeout(800);
	await ctx.close();
}

// 2) Volume para paginação e busca
log('seed', 120, 0);
log('seed', 3, 0, 'abcd12');
const total = Number(log('count'));
ok('registros semeados', total === 124, String(total));

const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
await page.goto(BASE + 'wp-login.php');
await page.fill('#user_login', ADMIN_USER);
await page.fill('#user_pass', ADMIN_PASS);
await page.click('#wp-submit');
await page.waitForLoadState('networkidle');

const url = BASE + 'wp-admin/options-general.php?page=jpx-cookies-lgpd&view=registros';
const ids = () => page.$$eval('.jpxcc-log tbody tr td:first-child + td code', (els) => els.map((e) => e.textContent.trim()));
const hasOlder = () => page.isVisible('.jpxcc-pager a:has-text("Mais antigos")');
const hasNewer = () => page.isVisible('.jpxcc-pager a:has-text("Mais recentes")');

await page.goto(url);
const p1 = await ids();
ok('primeira página com 50 registros', p1.length === 50, String(p1.length));
ok('primeira página: só "Mais antigos"', (await hasOlder()) && !(await hasNewer()));
const navUA = (await page.textContent('.jpxcc-log tbody tr:last-child td:nth-child(7)')) || '';
const infoText = (await page.textContent('.jpxcc-tableinfo')).trim();
const infoRows = Number((infoText.match(/cerca de\s+([\d.]+)\s+registros/) || [0, '0'])[1].replace(/\./g, ''));
ok('tamanho da tabela exibido', infoRows >= total && infoRows < total * 2 && !/0\.0 B|0 B/.test(infoText), infoText);
await page.screenshot({ path: `${SHOTS}/log-page1.png`, fullPage: true });

let seen = [...p1];
let pages = 1;
while (await hasOlder()) {
	await page.click('.jpxcc-pager a:has-text("Mais antigos")');
	await page.waitForLoadState('networkidle');
	seen = seen.concat(await ids());
	pages++;
	if (pages > 10) break;
}
ok('paginação percorre todos os registros sem repetir', seen.length === total && new Set(seen).size === total, `${seen.length} de ${total} em ${pages} páginas`);
ok('última página: sem "Mais antigos"', !(await hasOlder()) && (await hasNewer()));

await page.click('.jpxcc-pager a:has-text("Mais recentes")');
await page.waitForLoadState('networkidle');
await page.click('.jpxcc-pager a:has-text("Mais recentes")');
await page.waitForLoadState('networkidle');
const back = await ids();
ok('voltar com "Mais recentes" chega à primeira página', back[0] === p1[0] && !(await hasNewer()));

// Busca pelo início do ID
await page.fill('input[name="s"]', 'ABCD12');
await page.click('input[value="Buscar"]');
await page.waitForLoadState('networkidle');
const found = await ids();
ok('busca pelo início do ID (sem diferenciar maiúsculas)', found.length === 3 && found.every((i) => i.startsWith('abcd12')), found.join(', '));
ok('busca curta não pagina', !(await hasOlder()) && !(await hasNewer()));

// Navegador resumido gravado pelo endpoint
await page.goto(url);
const lastRowUA = await page.$$eval('.jpxcc-log tbody tr', (rows) => rows.map((r) => r.children[6].textContent.trim()));
ok('navegador gravado de forma resumida', lastRowUA.includes('Chrome 141 / Android'), navUA);

// 3) Limpeza em lotes
log('seed', 12000, 800);
const oldBefore = Number(log('count', 730));
const t0 = Date.now();
const deleted = Number(log('purge'));
const purgeMs = Date.now() - t0;
ok('limpeza apaga os vencidos em lotes', oldBefore === 12000 && deleted === 12000 && Number(log('count', 730)) === 0, `${deleted} apagados em ${purgeMs} ms`);
ok('limpeza mantém os registros dentro do prazo', Number(log('count')) === total);

// 4) Painel com volume maior
log('seed', 50000, 1);
const t1 = Date.now();
await page.goto(url);
const loadMs = Date.now() - t1;
await page.fill('input[name="s"]', 'abcd12');
const t2 = Date.now();
await page.click('input[value="Buscar"]');
await page.waitForLoadState('networkidle');
const searchMs = Date.now() - t2;
ok('aba Registros com 50 mil linhas abre rápido', loadMs < 3000, `${loadMs} ms`);
ok('busca com 50 mil linhas responde rápido', searchMs < 3000 && (await ids()).length === 3, `${searchMs} ms`);

ok('sem erros de JS', errors.length === 0, errors.join(' | '));
log('clear');
await browser.close();
rep.done();
