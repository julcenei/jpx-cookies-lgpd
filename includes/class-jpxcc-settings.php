<?php
/**
 * Configurações: valores padrão, leitura e sanitização.
 *
 * @package JPX_Cookies_LGPD
 */

defined( 'ABSPATH' ) || exit;

class JPXCC_Settings {

	/** Categorias opcionais (a "necessary" é sempre ativa). */
	const OPTIONAL_CATEGORIES = array( 'functional', 'analytics', 'marketing' );

	/** @var array|null */
	private static $cache = null;

	/**
	 * Valores padrão.
	 *
	 * @return array
	 */
	public static function defaults() {
		$presets = self::presets();
		$preset  = isset( $presets['experiencia'] ) ? $presets['experiencia'] : reset( $presets );

		return array(
			// Geral.
			'enabled'                  => 1,
			'test_mode'                => 0,
			'revision'                 => 1,
			'cookie_days'              => 182,
			'cookie_days_reject'       => 90,
			'policy_page_id'           => (int) get_option( 'wp_page_for_privacy_policy', 0 ),
			'show_mode'                => 'load',
			'floating_button'          => 0,
			'disable_page_interaction' => 0,
			'shortcut_position'        => 'right-middle',

			// Minimizar o banner sem decisão.
			'minimize_enabled'         => 1,
			'minimize_outside'         => 1,
			'minimize_scroll'          => 1,
			'minimize_delay'           => 10,

			// Aparência.
			'layout'                   => 'box',
			'position_v'               => 'bottom',
			'position_h'               => 'right',
			'pm_layout'                => 'box',
			'theme'                    => 'light',
			'flip_buttons'             => 0,
			'font_family'              => '',
			'color_bg'                 => '#ffffff',
			'color_text'               => '#2c2f31',
			'color_primary'            => '#1f6feb',
			'color_primary_text'       => '#ffffff',
			'color_secondary'          => '#eaeff2',
			'color_secondary_text'     => '#2c2f31',
			'color_link'               => '#1f6feb',
			'radius'                   => 8,

			// Textos.
			'texts'                    => array(
				'cm_title'       => $preset['cm_title'],
				'cm_description' => $preset['cm_description'],
				'accept_all'     => $preset['accept_all'],
				'reject_all'     => $preset['reject_all'],
				'show_prefs'     => $preset['show_prefs'],
				'policy_label'   => __( 'Política de Privacidade', 'jpx-cookies-lgpd' ),
				'pm_title'       => __( 'Preferências de cookies', 'jpx-cookies-lgpd' ),
				'save_prefs'     => __( 'Salvar preferências', 'jpx-cookies-lgpd' ),
				'close_label'    => __( 'Fechar', 'jpx-cookies-lgpd' ),
				'pm_intro_title' => __( 'Como usamos cookies', 'jpx-cookies-lgpd' ),
				'pm_intro_desc'  => __( 'Cookies são pequenos arquivos salvos no seu navegador. Os essenciais garantem o funcionamento do site; os demais só são usados com a sua autorização, que pode ser alterada a qualquer momento.', 'jpx-cookies-lgpd' ),
				'pm_more_title'  => __( 'Mais informações', 'jpx-cookies-lgpd' ),
				'pm_more_desc'   => __( 'Para exercer seus direitos previstos na LGPD, entre em contato pelo canal indicado na nossa {politica}. Identificador do seu consentimento: {id}', 'jpx-cookies-lgpd' ),
				'floating_label' => __( 'Preferências de cookies', 'jpx-cookies-lgpd' ),
				'minimized_label' => __( 'Cookies', 'jpx-cookies-lgpd' ),
				'minimized_aria' => __( 'Abrir o aviso de cookies', 'jpx-cookies-lgpd' ),
				'optout_notice'  => __( 'A medição de {categorias} já está ativa com base no nosso legítimo interesse; você pode desativá-la a qualquer momento recusando ou em Personalizar.', 'jpx-cookies-lgpd' ),
			),

			// Categorias.
			'categories'               => array(
				'necessary'  => array(
					'enabled'     => 1,
					'title'       => __( 'Essenciais', 'jpx-cookies-lgpd' ),
					'description' => __( 'Necessários para o funcionamento do site, como segurança e o registro da sua escolha de cookies. Não podem ser desativados.', 'jpx-cookies-lgpd' ),
					'patterns'    => '',
					'basis'       => 'consent',
				),
				'functional' => array(
					'enabled'     => 0,
					'title'       => __( 'Funcionais', 'jpx-cookies-lgpd' ),
					'description' => __( 'Lembram preferências como idioma, player e conteúdos personalizados.', 'jpx-cookies-lgpd' ),
					'patterns'    => '',
					'basis'       => 'consent',
				),
				'analytics'  => array(
					'enabled'     => 1,
					'title'       => __( 'Estatísticas', 'jpx-cookies-lgpd' ),
					'description' => __( 'Nos ajudam a entender quantas pessoas visitam o site e quais conteúdos são mais lidos (ex.: Google Analytics).', 'jpx-cookies-lgpd' ),
					'patterns'    => "clarity.ms\nhotjar.com\nmc.yandex.ru\ncdn.mxpnl.com",
					'basis'       => 'consent',
				),
				'marketing'  => array(
					'enabled'     => 1,
					'title'       => __( 'Publicidade e marketing', 'jpx-cookies-lgpd' ),
					'description' => __( 'Permitem exibir anúncios mais relevantes e medir campanhas (ex.: Google AdSense, Meta Pixel). Sem esta autorização, os anúncios continuam aparecendo, mas não são personalizados.', 'jpx-cookies-lgpd' ),
					'patterns'    => "connect.facebook.net\nfbq(\nanalytics.tiktok.com\nttq.load\nsnap.licdn.com\nstatic.ads-twitter.com\nsc-static.net\ns.pinimg.com/ct",
					'basis'       => 'consent',
				),
			),

			// Google.
			'load_gtag'                => 0,
			'ga_ids'                   => '',
			'gtm_id'                   => '',
			'ads_data_redaction'       => 1,
			'url_passthrough'          => 0,
			'wait_for_update'          => 500,

			// Bloqueio.
			'enable_blocker'           => 1,
			'block_exclusions'         => '',

			// Registro.
			'log_enabled'              => 1,
			'log_ip'                   => 'anon',
			'log_ua'                   => 1,
			'retention_days'           => 730,

			// Desinstalação.
			'delete_on_uninstall'      => 0,
		);
	}

	/**
	 * Retorna todas as configurações mescladas com os padrões.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved = get_option( JPXCC_OPTION, array() );
			$saved = is_array( $saved ) ? $saved : array();
			self::$cache = self::merge( self::defaults(), $saved );
		}
		return self::$cache;
	}

	/**
	 * Retorna uma configuração.
	 *
	 * @param string $key     Chave.
	 * @param mixed  $default Valor se ausente.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/** Limpa o cache em memória (após salvar). */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Mescla recursivamente, mantendo apenas chaves conhecidas.
	 *
	 * @param array $defaults Padrões.
	 * @param array $saved    Salvos.
	 * @return array
	 */
	private static function merge( array $defaults, array $saved ) {
		$out = $defaults;
		foreach ( $defaults as $key => $value ) {
			if ( ! array_key_exists( $key, $saved ) ) {
				continue;
			}
			if ( is_array( $value ) && is_array( $saved[ $key ] ) ) {
				$out[ $key ] = self::merge( $value, $saved[ $key ] );
			} elseif ( ! is_array( $value ) ) {
				$out[ $key ] = $saved[ $key ];
			}
		}
		return $out;
	}

	/**
	 * Opções válidas dos campos de seleção.
	 *
	 * @return array
	 */
	public static function choices() {
		return array(
			'show_mode'  => array(
				'load'        => __( 'Assim que a página carregar', 'jpx-cookies-lgpd' ),
				'interaction' => __( 'Na primeira interação (rolagem, toque ou tecla) — melhor para o LCP', 'jpx-cookies-lgpd' ),
			),
			'layout'     => array(
				'box'          => __( 'Caixa', 'jpx-cookies-lgpd' ),
				'box wide'     => __( 'Caixa larga', 'jpx-cookies-lgpd' ),
				'box inline'   => __( 'Caixa com botões em linha', 'jpx-cookies-lgpd' ),
				'cloud'        => __( 'Nuvem (centralizada, larga)', 'jpx-cookies-lgpd' ),
				'cloud inline' => __( 'Nuvem com botões em linha', 'jpx-cookies-lgpd' ),
				'bar'          => __( 'Barra', 'jpx-cookies-lgpd' ),
				'bar inline'   => __( 'Barra com botões em linha', 'jpx-cookies-lgpd' ),
			),
			'position_v' => array(
				'bottom' => __( 'Inferior', 'jpx-cookies-lgpd' ),
				'middle' => __( 'Meio', 'jpx-cookies-lgpd' ),
				'top'    => __( 'Superior', 'jpx-cookies-lgpd' ),
			),
			'position_h' => array(
				'right'  => __( 'Direita', 'jpx-cookies-lgpd' ),
				'center' => __( 'Centro', 'jpx-cookies-lgpd' ),
				'left'   => __( 'Esquerda', 'jpx-cookies-lgpd' ),
			),
			'pm_layout'  => array(
				'box'      => __( 'Caixa (modal central)', 'jpx-cookies-lgpd' ),
				'bar'      => __( 'Barra lateral', 'jpx-cookies-lgpd' ),
				'bar wide' => __( 'Barra lateral larga', 'jpx-cookies-lgpd' ),
			),
			'shortcut_position' => array(
				'right-middle'  => __( 'Lateral direita, no centro', 'jpx-cookies-lgpd' ),
				'left-middle'   => __( 'Lateral esquerda, no centro', 'jpx-cookies-lgpd' ),
				'bottom-left'   => __( 'Canto inferior esquerdo', 'jpx-cookies-lgpd' ),
				'bottom-right'  => __( 'Canto inferior direito', 'jpx-cookies-lgpd' ),
				'bottom-center' => __( 'Inferior, no centro', 'jpx-cookies-lgpd' ),
				'right-low'     => __( 'Lateral direita, acima do rodapé (deixa livre o canto)', 'jpx-cookies-lgpd' ),
				'left-low'      => __( 'Lateral esquerda, acima do rodapé (deixa livre o canto)', 'jpx-cookies-lgpd' ),
				'top-right'     => __( 'Canto superior direito', 'jpx-cookies-lgpd' ),
				'top-left'      => __( 'Canto superior esquerdo', 'jpx-cookies-lgpd' ),
			),
			'theme'      => array(
				'light' => __( 'Claro', 'jpx-cookies-lgpd' ),
				'dark'  => __( 'Escuro', 'jpx-cookies-lgpd' ),
				'auto'  => __( 'Automático (segue o sistema do visitante)', 'jpx-cookies-lgpd' ),
			),
			'basis'      => array(
				'consent' => __( 'Consentimento (opt-in): desligada até o visitante aceitar', 'jpx-cookies-lgpd' ),
				'legit'   => __( 'Legítimo interesse (opt-out): ligada até o visitante recusar', 'jpx-cookies-lgpd' ),
			),
			'log_ip'     => array(
				'anon' => __( 'Anonimizado (último bloco zerado)', 'jpx-cookies-lgpd' ),
				'none' => __( 'Não registrar', 'jpx-cookies-lgpd' ),
			),
		);
	}

	/**
	 * Sanitiza o array vindo do formulário (ou de uma importação).
	 *
	 * @param mixed $input Entrada bruta.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$choices  = self::choices();
		$out      = array();

		$bools = array( 'enabled', 'test_mode', 'floating_button', 'disable_page_interaction', 'flip_buttons', 'load_gtag', 'ads_data_redaction', 'url_passthrough', 'enable_blocker', 'log_enabled', 'log_ua', 'delete_on_uninstall', 'minimize_enabled', 'minimize_outside', 'minimize_scroll' );
		foreach ( $bools as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$ints = array(
			'revision'        => array( 1, 9999 ),
			'cookie_days'     => array( 1, 395 ),
			'cookie_days_reject' => array( 1, 395 ),
			'policy_page_id'  => array( 0, PHP_INT_MAX ),
			'radius'          => array( 0, 40 ),
			'wait_for_update' => array( 0, 5000 ),
			'retention_days'  => array( 30, 3650 ),
			'minimize_delay'  => array( 0, 120 ),
		);
		foreach ( $ints as $key => $range ) {
			$val         = isset( $input[ $key ] ) ? (int) $input[ $key ] : (int) $defaults[ $key ];
			$out[ $key ] = max( $range[0], min( $range[1], $val ) );
		}

		foreach ( $choices as $key => $options ) {
			if ( ! array_key_exists( $key, $defaults ) ) {
				continue; // Ex.: "basis" é por categoria, tratado abaixo.
			}
			$val         = isset( $input[ $key ] ) ? (string) $input[ $key ] : '';
			$out[ $key ] = array_key_exists( $val, $options ) ? $val : $defaults[ $key ];
		}

		$colors = array( 'color_bg', 'color_text', 'color_primary', 'color_primary_text', 'color_secondary', 'color_secondary_text', 'color_link' );
		foreach ( $colors as $key ) {
			$val         = isset( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : '';
			$out[ $key ] = $val ? $val : $defaults[ $key ];
		}

		$out['font_family'] = isset( $input['font_family'] ) ? preg_replace( '/[^a-zA-Z0-9 ,\'"\-_]/', '', $input['font_family'] ) : '';

		// IDs do Google.
		$ga_ids = array();
		if ( ! empty( $input['ga_ids'] ) ) {
			foreach ( preg_split( '/[\s,]+/', strtoupper( (string) $input['ga_ids'] ) ) as $id ) {
				if ( preg_match( '/^(G|AW|DC)-[A-Z0-9]+$/', $id ) ) {
					$ga_ids[] = $id;
				}
			}
		}
		$out['ga_ids'] = implode( ', ', array_unique( $ga_ids ) );

		$gtm           = isset( $input['gtm_id'] ) ? strtoupper( trim( (string) $input['gtm_id'] ) ) : '';
		$out['gtm_id'] = preg_match( '/^GTM-[A-Z0-9]+$/', $gtm ) ? $gtm : '';

		$out['block_exclusions'] = self::sanitize_patterns( isset( $input['block_exclusions'] ) ? $input['block_exclusions'] : '' );

		// Textos.
		$rich_texts   = array( 'cm_description', 'pm_intro_desc', 'pm_more_desc', 'optout_notice' );
		$out['texts'] = array();
		foreach ( $defaults['texts'] as $key => $default ) {
			$val = isset( $input['texts'][ $key ] ) ? (string) $input['texts'][ $key ] : '';
			$val = in_array( $key, $rich_texts, true ) ? wp_kses( $val, self::allowed_html() ) : sanitize_text_field( $val );
			$out['texts'][ $key ] = '' === trim( $val ) ? $default : $val;
		}

		// Categorias.
		$out['categories'] = array();
		foreach ( $defaults['categories'] as $slug => $cat_default ) {
			$in    = isset( $input['categories'][ $slug ] ) && is_array( $input['categories'][ $slug ] ) ? $input['categories'][ $slug ] : array();
			$title = isset( $in['title'] ) ? sanitize_text_field( $in['title'] ) : '';
			$desc  = isset( $in['description'] ) ? wp_kses( (string) $in['description'], self::allowed_html() ) : '';

			$out['categories'][ $slug ] = array(
				'enabled'     => 'necessary' === $slug ? 1 : ( empty( $in['enabled'] ) ? 0 : 1 ),
				'title'       => '' === $title ? $cat_default['title'] : $title,
				'description' => '' === trim( $desc ) ? $cat_default['description'] : $desc,
				'patterns'    => 'necessary' === $slug ? '' : self::sanitize_patterns( isset( $in['patterns'] ) ? $in['patterns'] : '' ),
				'basis'       => ( 'necessary' !== $slug && isset( $in['basis'] ) && 'legit' === $in['basis'] ) ? 'legit' : 'consent',
			);
		}

		self::flush();
		return $out;
	}

	/**
	 * Uma regra por linha, sem tags, sem duplicadas.
	 *
	 * @param string $raw Texto bruto.
	 * @return string
	 */
	public static function sanitize_patterns( $raw ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
		$clean = array();
		foreach ( $lines as $line ) {
			$line = trim( wp_strip_all_tags( $line ) );
			if ( '' !== $line && strlen( $line ) >= 3 ) {
				$clean[] = substr( $line, 0, 200 );
			}
		}
		return implode( "\n", array_unique( $clean ) );
	}

	/**
	 * Converte o texto de padrões em array.
	 *
	 * @param string $raw Padrões, um por linha.
	 * @return string[]
	 */
	public static function patterns_to_array( $raw ) {
		$raw = trim( (string) $raw );
		return '' === $raw ? array() : array_values( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) );
	}

	/**
	 * HTML permitido nas descrições.
	 *
	 * @return array
	 */
	public static function allowed_html() {
		return array(
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'i'      => array(),
			'br'     => array(),
		);
	}

	/**
	 * Modelos de mensagem do banner. Todos são verdadeiros por padrão: não
	 * prometem o que o site não pode garantir (consentimento precisa ser informado).
	 *
	 * @return array
	 */
	public static function presets() {
		$presets = array(
			'profissional' => array(
				'label'          => __( 'Profissional', 'jpx-cookies-lgpd' ),
				'hint'           => __( 'Neutro e completo. Bom padrão para qualquer site.', 'jpx-cookies-lgpd' ),
				'cm_title'       => __( 'Sua privacidade importa', 'jpx-cookies-lgpd' ),
				'cm_description' => __( 'Usamos cookies para medir a audiência, aprimorar sua experiência e exibir publicidade. Você pode aceitar todos, recusar os não essenciais ou escolher por categoria. Saiba mais em nossa {politica}.', 'jpx-cookies-lgpd' ),
				'accept_all'     => __( 'Aceitar todos', 'jpx-cookies-lgpd' ),
				'reject_all'     => __( 'Recusar não essenciais', 'jpx-cookies-lgpd' ),
				'show_prefs'     => __( 'Personalizar', 'jpx-cookies-lgpd' ),
			),
			'experiencia'  => array(
				'label'          => __( 'Melhor experiência', 'jpx-cookies-lgpd' ),
				'hint'           => __( 'Foca no benefício da navegação e na segurança dos dados.', 'jpx-cookies-lgpd' ),
				'cm_title'       => __( 'Para uma navegação melhor', 'jpx-cookies-lgpd' ),
				'cm_description' => __( 'Aceite os cookies para ter uma navegação mais fluida e conteúdos mais relevantes para você. Seus dados são tratados com segurança e conforme a LGPD, e você pode mudar sua escolha quando quiser. {politica}', 'jpx-cookies-lgpd' ),
				'accept_all'     => __( 'Aceitar cookies', 'jpx-cookies-lgpd' ),
				'reject_all'     => __( 'Recusar', 'jpx-cookies-lgpd' ),
				'show_prefs'     => __( 'Personalizar', 'jpx-cookies-lgpd' ),
			),
			'persuasiva'   => array(
				'label'          => __( 'Persuasiva', 'jpx-cookies-lgpd' ),
				'hint'           => __( 'Explica o que o visitante ganha e por que o site precisa.', 'jpx-cookies-lgpd' ),
				'cm_title'       => __( 'Ajude a manter o conteúdo gratuito', 'jpx-cookies-lgpd' ),
				'cm_description' => __( 'Com os cookies, entendemos o que você mais gosta de ler, melhoramos o site e mantemos o conteúdo gratuito com publicidade. Leva um segundo, seus dados são tratados conforme a LGPD e você pode mudar de ideia a qualquer momento. Saiba mais na {politica}.', 'jpx-cookies-lgpd' ),
				'accept_all'     => __( 'Aceitar e continuar', 'jpx-cookies-lgpd' ),
				'reject_all'     => __( 'Recusar', 'jpx-cookies-lgpd' ),
				'show_prefs'     => __( 'Escolher', 'jpx-cookies-lgpd' ),
			),
			'noticias'     => array(
				'label'          => __( 'Portal de notícias', 'jpx-cookies-lgpd' ),
				'hint'           => __( 'Apela ao apoio ao jornalismo local.', 'jpx-cookies-lgpd' ),
				'cm_title'       => __( 'Apoie o jornalismo da sua região', 'jpx-cookies-lgpd' ),
				'cm_description' => __( 'Os cookies nos mostram quais notícias são mais lidas e ajudam a manter o portal gratuito com publicidade. Você decide o que autoriza e pode mudar a escolha a qualquer momento. {politica}', 'jpx-cookies-lgpd' ),
				'accept_all'     => __( 'Aceitar e apoiar', 'jpx-cookies-lgpd' ),
				'reject_all'     => __( 'Recusar', 'jpx-cookies-lgpd' ),
				'show_prefs'     => __( 'Personalizar', 'jpx-cookies-lgpd' ),
			),
			'simples'      => array(
				'label'          => __( 'Simples', 'jpx-cookies-lgpd' ),
				'hint'           => __( 'Curtíssimo, ideal para o celular.', 'jpx-cookies-lgpd' ),
				'cm_title'       => __( 'Cookies', 'jpx-cookies-lgpd' ),
				'cm_description' => __( 'Este site usa cookies para funcionar melhor. Você escolhe o que autoriza. {politica}', 'jpx-cookies-lgpd' ),
				'accept_all'     => __( 'Aceitar', 'jpx-cookies-lgpd' ),
				'reject_all'     => __( 'Recusar', 'jpx-cookies-lgpd' ),
				'show_prefs'     => __( 'Opções', 'jpx-cookies-lgpd' ),
			),
			'amigavel'     => array(
				'label'          => __( 'Amigável', 'jpx-cookies-lgpd' ),
				'hint'           => __( 'Descontraído, para rádios e marcas jovens.', 'jpx-cookies-lgpd' ),
				'cm_title'       => __( 'Aceita um cookie? 🍪', 'jpx-cookies-lgpd' ),
				'cm_description' => __( 'A gente usa cookies para o site funcionar direitinho e mostrar o que combina com você. Fica tranquilo: seus dados são tratados conforme a LGPD e dá para mudar a escolha quando quiser. {politica}', 'jpx-cookies-lgpd' ),
				'accept_all'     => __( 'Pode aceitar', 'jpx-cookies-lgpd' ),
				'reject_all'     => __( 'Não, obrigado', 'jpx-cookies-lgpd' ),
				'show_prefs'     => __( 'Ver opções', 'jpx-cookies-lgpd' ),
			),
			'formal'       => array(
				'label'          => __( 'Formal', 'jpx-cookies-lgpd' ),
				'hint'           => __( 'Linguagem jurídica, para órgãos públicos e institucionais.', 'jpx-cookies-lgpd' ),
				'cm_title'       => __( 'Aviso de cookies e privacidade', 'jpx-cookies-lgpd' ),
				'cm_description' => __( 'Em conformidade com a Lei Geral de Proteção de Dados (Lei nº 13.709/2018), utilizamos cookies essenciais ao funcionamento do site e, mediante sua autorização, cookies de estatística e de publicidade. Você pode aceitar, recusar ou configurar suas preferências e revogar a autorização a qualquer momento. Consulte nossa {politica}.', 'jpx-cookies-lgpd' ),
				'accept_all'     => __( 'Aceitar todos', 'jpx-cookies-lgpd' ),
				'reject_all'     => __( 'Rejeitar', 'jpx-cookies-lgpd' ),
				'show_prefs'     => __( 'Configurar', 'jpx-cookies-lgpd' ),
			),
		);
		return (array) apply_filters( 'jpxcc_message_presets', $presets );
	}

	/**
	 * Categorias opcionais ativas configuradas como opt-out (legítimo interesse).
	 *
	 * @return string[]
	 */
	public static function optout_categories() {
		$cats = self::get( 'categories' );
		$out  = array();
		foreach ( self::active_optional_categories() as $slug ) {
			if ( isset( $cats[ $slug ]['basis'] ) && 'legit' === $cats[ $slug ]['basis'] ) {
				$out[] = $slug;
			}
		}
		return $out;
	}

	/**
	 * Categorias opcionais ativas.
	 *
	 * @return string[]
	 */
	public static function active_optional_categories() {
		$cats = self::get( 'categories' );
		$out  = array();
		foreach ( self::OPTIONAL_CATEGORIES as $slug ) {
			if ( ! empty( $cats[ $slug ]['enabled'] ) ) {
				$out[] = $slug;
			}
		}
		return $out;
	}
}
