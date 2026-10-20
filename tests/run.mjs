// Roda todas as suítes em sequência, restaurando as configurações entre elas.
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { BASE, SITE, set, setBasis } from './e2e/_env.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
// O registro (log) roda por último: ele limpa a tabela ao terminar.
const suites = process.argv.slice(2).length ? process.argv.slice(2) : ['opt-in', 'opt-out', 'minimize', 'conflict', 'presets', 'admin', 'log'];

function reset() {
	spawnSync('php', [path.join(here, 'helpers', 'log.php'), SITE, 'reset-limits']);
	setBasis('consent');
	set('shortcut_position=right-middle', 'minimize_delay=10', 'floating_button=0', 'show_mode=load', 'test_mode=0', 'revision=1');
}

try {
	const res = await fetch(BASE);
	if (!res.ok) throw new Error(String(res.status));
} catch (e) {
	console.error(`Site de testes indisponível em ${BASE}. Rode bin/setup-test-site.sh e inicie o servidor (veja o README).`);
	process.exit(1);
}

let failed = 0;

if (!process.argv.slice(2).length) {
	console.log('\n━━ unit: resumo de navegador ━━');
	const u = spawnSync('php', [path.join(here, 'unit', 'ua-summary.php'), SITE], { stdio: 'inherit' });
	if (u.status !== 0) failed++;
}
for (const name of suites) {
	reset();
	console.log(`\n━━ ${name} ━━`);
	const r = spawnSync(process.execPath, [path.join(here, 'e2e', `${name}.mjs`)], { stdio: 'inherit' });
	if (r.status !== 0) failed++;
}
reset();
console.log(failed ? `\n${failed} suíte(s) com falha.` : '\nTodas as suítes passaram.');
process.exit(failed ? 1 : 0);
