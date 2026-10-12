=== JPX Cookies LGPD ===
Contributors: jotapix
Tags: lgpd, cookies, consentimento, consent mode, google analytics
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Banner de cookies para a LGPD com Google Consent Mode v2, bloqueio de scripts de terceiros e registro de consentimentos.

== Description ==

* Google Consent Mode v2 sempre ativo: tudo começa negado e é liberado pela escolha do visitante. Quem recusa continua gerando sinais sem cookies para a modelagem do GA4, e o AdSense segue exibindo anúncios não personalizados.
* Bloqueio de scripts de terceiros (Meta Pixel, TikTok, Clarity, Hotjar…) por padrões configuráveis no painel, compatível com cache de página e Cloudflare.
* Registro de consentimentos (prova exigida pela LGPD, art. 8º, §2º) com IP anonimizado, retenção automática, exportação CSV e exclusão por ID a pedido do titular.
* Taxa de aceite dos últimos 30 dias no painel.
* Leve: o visitante que já escolheu não baixa a biblioteca do banner.
* Botões "Aceitar" e "Recusar" com o mesmo destaque, como orienta a ANPD.
* Base legal por categoria: consentimento (opt-in) ou legítimo interesse (opt-out), com aviso automático no banner.
* Validade separada para quem aceitou e para quem recusou.
* Banner minimizado: ao clicar fora, rolar a página ou após alguns segundos, o aviso vira um atalho discreto até o fim da visita, sem registrar escolha.
* Nove posições para o atalho e o botão flutuante, para não cobrir WhatsApp, "voltar ao topo" ou player.
* Sete modelos de mensagem prontos (profissional, melhor experiência, persuasiva, portal de notícias, simples, amigável e formal).
* Exportação e importação de configurações entre sites.
* Integração com a WP Consent API (Site Kit e outros).

Usa a biblioteca CookieConsent v3 de Orest Bida (licença MIT), incluída localmente.

== Installation ==

1. Envie a pasta `jpx-cookies-lgpd` para `/wp-content/plugins/` ou instale o .zip pelo painel.
2. Ative o plugin.
3. Vá em Configurações → JPX Cookies LGPD. Comece com o "Modo de teste" ligado.
4. Em "Google", informe o ID do GA4 e ligue "Carregar o GA4" (e desative a tag em outros plugins).
5. Coloque o shortcode `[jpx_cookies_preferencias]` no rodapé ou ative o botão flutuante.
6. Desative outros plugins de cookies, desligue o modo de teste e limpe o cache.

== Frequently Asked Questions ==

= O AdSense para de funcionar para quem recusa? =

Não. Os scripts do Google nunca são bloqueados; o Consent Mode informa a recusa e o Google passa a servir anúncios não personalizados.

= Os acessos de quem recusa aparecem no GA4? =

Não como usuários comuns. Eles geram sinais sem cookies que o GA4 usa na modelagem comportamental quando o volume mínimo exigido pelo Google é atingido. Ative a identidade de relatórios "Combinada" no GA4.

== Changelog ==

= 1.2.0 =
* Minimizar sem decisão: clique fora, rolagem de meia tela e tempo configurável (padrão 10 s). Fica minimizado até o fim da visita; nada é registrado e nenhuma categoria muda de estado.
* Páginas com o banner minimizado não baixam a biblioteca do banner.
* Posição configurável do atalho (laterais, cantos, inferior centro), usada também pelo botão flutuante.

= 1.1.0 =
* Base legal por categoria (opt-in ou opt-out por legítimo interesse). Visitantes que já escolheram mantêm a escolha.
* Validade separada para recusas (padrão 90 dias), dando nova chance de aceite.
* Modelos de mensagem em cards na aba Textos; padrão "Melhor experiência".
* O Consent Mode já nasce com a escolha salva do visitante, sem atualização posterior.

= 1.0.0 =
* Versão inicial.
