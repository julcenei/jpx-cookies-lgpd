# JPX Cookies LGPD

Plugin WordPress de consentimento de cookies para a LGPD, feito para portais de notícias: Google Consent Mode v2, bloqueio de scripts de terceiros, registro de consentimentos e um banner leve que não pesa no Core Web Vitals.

Desenvolvido pela [Jotapix Agência Criativa](https://jotapix.com).

## Recursos

- **Consent Mode v2 sempre ativo.** As tags do Google começam negadas e são liberadas pela escolha do visitante. Os scripts do Google (GA4, AdSense, Ad Manager) nunca são bloqueados: quem recusa continua vendo anúncios não personalizados.
- **Base legal por categoria.** Consentimento (opt-in) ou legítimo interesse (opt-out), com aviso automático no banner. Útil para manter a medição de audiência de quem ignora o banner.
- **Bloqueio de scripts de terceiros** (Meta Pixel, TikTok, Clarity, Hotjar…) por padrões configuráveis no painel. A reescrita acontece no HTML antes do cache de página, então funciona com cache e Cloudflare.
- **Registro de consentimentos** como prova (LGPD, art. 8º, §2º): IP anonimizado, retenção automática, exportação CSV, exclusão por ID a pedido do titular e taxa de aceite dos últimos 30 dias.
- **Banner minimizado.** Ao clicar fora, rolar a página ou após alguns segundos, o aviso vira um atalho discreto até o fim da visita, sem registrar escolha. Nove posições para o atalho.
- **Validade separada** para quem aceitou (padrão 182 dias) e quem recusou (padrão 90 dias).
- **Sete modelos de mensagem** prontos, com prévia em cards no painel.
- **Botões com o mesmo destaque**, como orienta a ANPD.
- **Exportação e importação de configurações** entre sites, integração com a WP Consent API e filtros para desenvolvedores.

## Performance

| | Primeira visita | Quem já escolheu ou minimizou |
|---|---|---|
| Script inline no `<head>` | ≈0,7 KB gzip | igual |
| Configuração inline | ≈1,5 KB gzip | igual |
| Loader `jpxcc.min.js` (defer, em cache) | ≈3,6 KB gzip | em cache |
| Biblioteca do banner (JS + CSS) | ≈15 KB gzip, 2 requests | **não carrega** (só ao abrir as preferências) |
| CLS | 0 | 0 |

## Instalação

1. Gere o `.zip` com `npm run build:zip` (ou baixe uma versão pronta) e instale em **Plugins → Adicionar novo → Enviar plugin**.
2. Ative e vá em **Configurações → JPX Cookies LGPD**. Comece com o **Modo de teste** ligado.
3. Em **Google**, informe o ID do GA4 e ligue "Carregar o GA4", desativando a tag em outros plugins.
4. Coloque o shortcode `[jpx_cookies_preferencias]` no rodapé ou ative o botão flutuante.
5. Desative outros plugins de cookies, desligue o modo de teste e limpe o cache do site e da CDN.

> O plugin não substitui orientação jurídica. Ao usar legítimo interesse, atualize a Política de Privacidade (o WordPress mostra um texto sugerido em **Configurações → Privacidade**) e documente o teste de balanceamento.

## Estrutura

```
jpx-cookies-lgpd.php                  bootstrap e constantes
includes/
  class-jpxcc-settings.php            padrões, sanitização, modelos de mensagem
  class-jpxcc-frontend.php            Consent Mode no <head>, GA4/GTM, configuração do banner
  class-jpxcc-blocker.php             bloqueio de scripts no HTML final
  class-jpxcc-consent-log.php         tabela, endpoint REST, retenção, estatísticas, CSV
  class-jpxcc-admin.php               painel
assets/js/jpxcc.js                    loader (fonte); jpxcc.min.js é gerado
assets/vendor/cookieconsent/          CookieConsent v3 (Orest Bida, MIT)
tests/                                testes de navegador (fora do .zip)
bin/                                  scripts de build e do site de testes (fora do .zip)
```

## Desenvolvimento

Requisitos: PHP 7.4+ com `pdo_sqlite`, Node 18+ e Git.

```bash
npm install
npx playwright install chromium   # primeira vez
```

Depois de editar `assets/js/jpxcc.js`, gere o minificado:

```bash
npm run build:js
```

### Testes

Os testes sobem um WordPress local com SQLite (sem MySQL) e simulam visitantes reais no Chromium: banner, Consent Mode, bloqueio de scripts, registro, opt-out, minimização e painel.

```bash
npm run test:setup                                    # cria .test-site/ (uma vez)
php -S localhost:8099 -t .test-site                   # em outro terminal
npm test                                              # todas as suítes
node tests/run.mjs minimize                           # uma suíte
```

Variáveis opcionais: `JPXCC_BASE` (URL do site), `JPXCC_SITE` (pasta do WordPress), `JPXCC_ADMIN_USER`, `JPXCC_ADMIN_PASS` e `JPXCC_SHOTS` (pasta das capturas de tela, padrão `.shots/`).

### Gerar uma versão

1. Atualize a versão em `jpx-cookies-lgpd.php` (cabeçalho e `JPXCC_VERSION`), `readme.txt` (`Stable tag` e changelog) e `package.json`.
2. `npm run build:js && npm test`
3. Faça o commit, crie a tag (`git tag v1.3.0`) e rode `npm run build:zip`. O arquivo sai em `dist/`, sem testes nem arquivos de desenvolvimento.

## Para desenvolvedores

- `data-cc="show-preferencesModal"` em qualquer elemento abre as preferências.
- `<script type="text/plain" data-category="marketing" data-jpx-blocked="1">` bloqueia um script manualmente; `data-jpx-ignore` impede o bloqueio automático.
- Evento JS `jpxcc:consent` (`e.detail.categories`) e evento `jpxcc_consent` no `dataLayer` a cada escolha.
- Filtros PHP: `jpxcc_block_rules`, `jpxcc_never_block`, `jpxcc_client_config`, `jpxcc_is_active`, `jpxcc_client_ip`, `jpxcc_message_presets`. Ação: `jpxcc_consent_logged`.

## Licença

GPL-2.0-or-later. Inclui a [CookieConsent](https://github.com/orestbida/cookieconsent) v3, licença MIT.
