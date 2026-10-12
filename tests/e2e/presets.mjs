import { chromium } from 'playwright';
import { BASE, SHOTS, ADMIN_USER, ADMIN_PASS, reporter } from './_env.mjs';

const rep = reporter();
const ok = rep.ok;
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const p = await ctx.newPage();
const errors = [];
p.on('pageerror', (e) => errors.push(e.message));

await p.goto(BASE + 'wp-login.php');
await p.fill('#user_login', ADMIN_USER);
await p.fill('#user_pass', ADMIN_PASS);
await p.click('#wp-submit');
await p.waitForLoadState('networkidle');

await p.goto(BASE + 'wp-admin/options-general.php?page=jpx-cookies-lgpd#textos');
await p.waitForTimeout(400);
ok('sete modelos de mensagem', (await p.locator('.jpxcc-preset').count()) === 7);
ok('modelo padrão marcado (Melhor experiência)', (await p.locator('.jpxcc-preset.is-current .jpxcc-preset__head strong').textContent()) === 'Melhor experiência');
await p.screenshot({ path: SHOTS + '/admin-presets.png' });

await p.locator('.jpxcc-preset', { hasText: 'Portal de notícias' }).locator('.jpxcc-use-preset').click();
await p.waitForTimeout(400);
ok('modelo preenche os campos', (await p.inputValue('#jpxcc-texts-cm_title')) === 'Apoie o jornalismo da sua região' && (await p.inputValue('#jpxcc-texts-accept_all')) === 'Aceitar e apoiar');

await p.click('#submit');
await p.waitForLoadState('networkidle');
ok('modelo salvo', (await p.inputValue('#jpxcc-texts-cm_title')) === 'Apoie o jornalismo da sua região');

await p.goto(BASE + 'wp-admin/options-general.php?page=jpx-cookies-lgpd#geral');
await p.waitForTimeout(300);
ok('campos de validade e posição do atalho', await p.isVisible('#jpxcc-cookie_days_reject') && await p.isVisible('#jpxcc-shortcut_position'));
ok('sem erros de JS', errors.length === 0, errors.join(' | '));

await browser.close();
rep.done();
