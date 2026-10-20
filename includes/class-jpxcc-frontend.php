<?php
/**
 * Frontend: Consent Mode v2 no <head>, carregamento do Google, banner e shortcode.
 *
 * @package JPX_Cookies_LGPD
 */

defined( 'ABSPATH' ) || exit;

class JPXCC_Frontend {

	const COOKIE_NAME = 'jpxcc_consent';

	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'print_head' ), -1000 );
		add_action( 'wp_body_open', array( __CLASS__, 'print_gtm_noscript' ), 0 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_shortcode( 'jpx_cookies_preferencias', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * O plugin deve atuar nesta requisição?
	 * No modo de teste, apenas administradores logados veem o banner.
	 *
	 * @return bool
	 */
	public static function is_active() {
		if ( is_admin() || ! JPXCC_Settings::get( 'enabled' ) ) {
			return false;
		}
		if ( JPXCC_Settings::get( 'test_mode' ) && ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		return (bool) apply_filters( 'jpxcc_is_active', true );
	}

	/** Categorias cujo consentimento é gerenciado (necessary + opcionais ativas). */
	private static function managed_categories() {
		return array_merge( array( 'necessary' ), JPXCC_Settings::active_optional_categories() );
	}

	/**
	 * Primeiro script da página: define o Consent Mode como "negado" e aplica
	 * a escolha salva do visitante (ou o legítimo interesse) antes de qualquer
	 * tag do Google. A escolha vai como "update", e não como "default": no Consent
	 * Mode o update sempre prevalece, então outro código que declare um "default"
	 * depois (tema, snippet antigo, outro plugin) não consegue anulá-la.
	 */
	public static function print_head() {
		if ( ! self::is_active() ) {
			return;
		}
		$s          = JPXCC_Settings::all();
		$functional = in_array( 'functional', JPXCC_Settings::active_optional_categories(), true );

		$head_cfg = array(
			'n' => self::COOKIE_NAME,
			'r' => (int) $s['revision'],
			'f' => $functional ? 1 : 0,
			'w' => (int) $s['wait_for_update'],
			'a' => $s['ads_data_redaction'] ? 1 : 0,
			'u' => $s['url_passthrough'] ? 1 : 0,
			'o' => JPXCC_Settings::optout_categories(),
		);

		$js = 'window.JPXCC_HEAD=' . wp_json_encode( $head_cfg ) . ';' . self::head_js();

		if ( $s['load_gtag'] && $s['ga_ids'] ) {
			$ids = array_filter( array_map( 'trim', explode( ',', $s['ga_ids'] ) ) );
			$js .= 'gtag("js",new Date());';
			foreach ( $ids as $id ) {
				$js .= 'gtag("config",' . wp_json_encode( $id ) . ');';
			}
		}

		if ( $s['gtm_id'] ) {
			$js .= '(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({"gtm.start":new Date().getTime(),event:"gtm.js"});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!="dataLayer"?"&l="+l:"";j.async=true;j.src="https://www.googletagmanager.com/gtm.js?id="+i+dl;f.parentNode.insertBefore(j,f);})(window,document,"script","dataLayer",' . wp_json_encode( $s['gtm_id'] ) . ');';
		}

		wp_print_inline_script_tag( $js, array( 'id' => 'jpxcc-head' ) );

		if ( $s['load_gtag'] && $s['ga_ids'] ) {
			$first = trim( explode( ',', $s['ga_ids'] )[0] );
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- precisa vir logo após o Consent Mode.
			echo '<script async id="jpxcc-gtag" src="' . esc_url( 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $first ) ) . '"></script>' . "\n";
		}
	}

	/**
	 * JS do <head> (≈0,6 KB minificado). Mantido legível; o servidor envia como está.
	 *
	 * @return string
	 */
	private static function head_js() {
		return <<<'JS'
(function(w,d){var H=w.JPXCC_HEAD,dl=w.dataLayer=w.dataLayer||[],o=H.o||[];
if(typeof w.gtag!=="function"){w.gtag=function(){dl.push(arguments);};}
function m(c){var g=function(k){return c.indexOf(k)>-1?"granted":"denied";},f=H.f?g("functional"):"granted";
return{analytics_storage:g("analytics"),ad_storage:g("marketing"),ad_user_data:g("marketing"),ad_personalization:g("marketing"),functionality_storage:f,personalization_storage:f,security_storage:"granted"};}
w.JPXCC_MAP=m;var s={valid:false,cats:[]};
try{var k=d.cookie.match(new RegExp("(?:^|;\\s*)"+H.n+"=([^;]*)"));
if(k){var v=JSON.parse(decodeURIComponent(k[1]));if(v&&v.consentId&&v.consentTimestamp&&Array.isArray(v.categories)&&+v.revision===H.r){s={valid:true,cats:v.categories,id:v.consentId};}}}catch(e){}
if(!s.valid&&o.length){s.cats=o.slice();s.implicit=true;}
var x=m([]);if(H.w&&!s.valid&&!s.implicit){x.wait_for_update=H.w;}w.gtag("consent","default",x);
if(s.valid||s.implicit){w.gtag("consent","update",m(s.cats));}
if(H.a){w.gtag("set","ads_data_redaction",true);}if(H.u){w.gtag("set","url_passthrough",true);}
if(s.valid||s.implicit){dl.push({event:"jpxcc_consent",jpxcc_categories:s.cats.join(","),jpxcc_implicit:!!s.implicit});}
w.JPXCC_STATE=s;})(window,document);
JS;
	}

	/** Noscript do GTM (opcional). */
	public static function print_gtm_noscript() {
		$gtm = JPXCC_Settings::get( 'gtm_id' );
		if ( ! $gtm || ! self::is_active() ) {
			return;
		}
		echo '<noscript><iframe src="' . esc_url( 'https://www.googletagmanager.com/ns.html?id=' . rawurlencode( $gtm ) ) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
	}

	/** Carrega o loader (≈1,5 KB gzip, em cache no navegador) e a configuração. */
	public static function enqueue() {
		if ( ! self::is_active() ) {
			return;
		}
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		$file   = file_exists( JPXCC_DIR . "assets/js/jpxcc{$suffix}.js" ) ? "jpxcc{$suffix}.js" : 'jpxcc.js';

		wp_register_script( 'jpxcc', JPXCC_URL . 'assets/js/' . $file, array(), JPXCC_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
		wp_add_inline_script( 'jpxcc', 'window.JPXCC_CFG=' . wp_json_encode( self::client_config() ) . ';', 'before' );
		wp_enqueue_script( 'jpxcc' );
	}

	/**
	 * Configuração enviada ao navegador.
	 *
	 * @return array
	 */
	public static function client_config() {
		$s = JPXCC_Settings::all();

		$config = array(
			'js'        => JPXCC_URL . 'assets/vendor/cookieconsent/cookieconsent.umd.js?ver=' . JPXCC_VENDOR_VERSION,
			'css'       => JPXCC_URL . 'assets/vendor/cookieconsent/cookieconsent.css?ver=' . JPXCC_VENDOR_VERSION,
			'rest'      => $s['log_enabled'] ? esc_url_raw( rest_url( JPXCC_Consent_Log::REST_NS . '/consent' ) ) : '',
			'showMode'  => $s['show_mode'],
			'theme'     => $s['theme'],
			'fab'       => $s['floating_button'] ? 1 : 0,
			'fabLabel'  => $s['texts']['floating_label'],
			'optOut'    => JPXCC_Settings::optout_categories(),
			'days'      => array( (int) $s['cookie_days'], (int) $s['cookie_days_reject'] ),
			'pos'       => $s['shortcut_position'],
			'min'       => ( $s['minimize_enabled'] && ! $s['disable_page_interaction'] ) ? array(
				'outside' => $s['minimize_outside'] ? 1 : 0,
				'scroll'  => $s['minimize_scroll'] ? 1 : 0,
				'delay'   => (int) $s['minimize_delay'],
				'label'   => $s['texts']['minimized_label'],
				'aria'    => $s['texts']['minimized_aria'],
			) : null,
			'accent'    => array( $s['color_primary'], $s['color_primary_text'] ),
			'idPending' => __( 'será gerado ao salvar sua escolha', 'jpx-cookies-lgpd' ),
			'styles'    => self::css_vars( $s ),
			'cc'        => self::cookieconsent_config( $s ),
		);

		/**
		 * Permite ajustar a configuração enviada ao navegador.
		 *
		 * @param array $config Configuração.
		 */
		return apply_filters( 'jpxcc_client_config', $config );
	}

	/**
	 * Configuração no formato da CookieConsent v3.
	 *
	 * @param array $s Configurações.
	 * @return array
	 */
	private static function cookieconsent_config( array $s ) {
		$t       = $s['texts'];
		$cats    = $s['categories'];
		$managed = self::managed_categories();
		$optout  = JPXCC_Settings::optout_categories();

		// Aviso de legítimo interesse no banner (transparência exigida para o opt-out).
		$description = self::placeholders( $t['cm_description'], $s );
		if ( $optout ) {
			$names = array();
			foreach ( $optout as $slug ) {
				$names[] = function_exists( 'mb_strtolower' ) ? mb_strtolower( $cats[ $slug ]['title'] ) : strtolower( $cats[ $slug ]['title'] );
			}
			$notice       = str_replace( '{categorias}', esc_html( implode( ' e ', $names ) ), $t['optout_notice'] );
			$description .= ' ' . self::placeholders( $notice, $s );
		}

		$is_bar   = 0 === strpos( $s['layout'], 'bar' );
		$position = $is_bar ? ( 'middle' === $s['position_v'] ? 'bottom' : $s['position_v'] ) : $s['position_v'] . ' ' . $s['position_h'];

		$categories = array();
		$sections   = array(
			array(
				'title'       => $t['pm_intro_title'],
				'description' => self::placeholders( $t['pm_intro_desc'], $s ),
			),
		);
		foreach ( $managed as $slug ) {
			$categories[ $slug ] = 'necessary' === $slug ? array(
				'enabled'  => true,
				'readOnly' => true,
			) : array( 'enabled' => in_array( $slug, $optout, true ) );

			$sections[] = array(
				'title'          => $cats[ $slug ]['title'],
				'description'    => self::placeholders( $cats[ $slug ]['description'], $s ),
				'linkedCategory' => $slug,
			);
		}
		$sections[] = array(
			'title'       => $t['pm_more_title'],
			'description' => self::placeholders( $t['pm_more_desc'], $s ),
		);

		$lang = substr( get_locale(), 0, 2 );

		return array(
			'mode'                   => $optout ? 'opt-out' : 'opt-in',
			'revision'               => (int) $s['revision'],
			'autoShow'               => true,
			'hideFromBots'           => true,
			'manageScriptTags'       => true,
			'autoClearCookies'       => true,
			'disablePageInteraction' => (bool) $s['disable_page_interaction'],
			'cookie'                 => array(
				'name'             => self::COOKIE_NAME,
				'expiresAfterDays' => (int) $s['cookie_days'],
				'sameSite'         => 'Lax',
			),
			'guiOptions'             => array(
				'consentModal'     => array(
					'layout'             => $s['layout'],
					'position'           => $position,
					'equalWeightButtons' => true, // Recusar tão visível quanto aceitar (orientação da ANPD).
					'flipButtons'        => (bool) $s['flip_buttons'],
				),
				'preferencesModal' => array(
					'layout'             => $s['pm_layout'],
					'position'           => 'right',
					'equalWeightButtons' => true,
					'flipButtons'        => (bool) $s['flip_buttons'],
				),
			),
			'categories'             => $categories,
			'language'               => array(
				'default'      => $lang,
				'translations' => array(
					$lang => array(
						'consentModal'     => array(
							'title'              => $t['cm_title'],
							'description'        => $description,
							'acceptAllBtn'       => $t['accept_all'],
							'acceptNecessaryBtn' => $t['reject_all'],
							'showPreferencesBtn' => $t['show_prefs'],
						),
						'preferencesModal' => array(
							'title'              => $t['pm_title'],
							'acceptAllBtn'       => $t['accept_all'],
							'acceptNecessaryBtn' => $t['reject_all'],
							'savePreferencesBtn' => $t['save_prefs'],
							'closeIconLabel'     => $t['close_label'],
							'sections'           => $sections,
						),
					),
				),
			),
		);
	}

	/** URL da política de privacidade (se publicada). */
	public static function policy_url() {
		$id = (int) JPXCC_Settings::get( 'policy_page_id' );
		if ( $id && 'publish' === get_post_status( $id ) ) {
			return get_permalink( $id );
		}
		return '';
	}

	/**
	 * Substitui {politica} e {id}.
	 *
	 * @param string $text Texto.
	 * @param array  $s    Configurações.
	 * @return string
	 */
	private static function placeholders( $text, array $s ) {
		$url   = self::policy_url();
		$label = esc_html( $s['texts']['policy_label'] );
		$link  = $url ? '<a href="' . esc_url( $url ) . '">' . $label . '</a>' : $label;
		return strtr(
			(string) $text,
			array(
				'{politica}' => $link,
				'{id}'       => '<code class="jpxcc-id">—</code>',
			)
		);
	}

	/**
	 * Variáveis CSS da CookieConsent a partir das cores escolhidas.
	 *
	 * @param array $s Configurações.
	 * @return string
	 */
	private static function css_vars( array $s ) {
		$p      = $s['color_primary'];
		$pt     = $s['color_primary_text'];
		$radius = (int) $s['radius'];
		$font   = $s['font_family'] ? '--cc-font-family:' . $s['font_family'] . ';' : '--cc-font-family:inherit;';

		$buttons = "--cc-btn-primary-bg:{$p};--cc-btn-primary-border-color:{$p};--cc-btn-primary-color:{$pt};"
			. "--cc-btn-primary-hover-bg:{$p};--cc-btn-primary-hover-border-color:{$p};--cc-btn-primary-hover-color:{$pt};"
			. "--cc-toggle-on-bg:{$p};--cc-btn-border-radius:{$radius}px;--cc-modal-border-radius:" . ( $radius + 4 ) . 'px;' . $font;

		$light = "--cc-bg:{$s['color_bg']};--cc-primary-color:{$s['color_text']};--cc-secondary-color:{$s['color_text']};"
			. "--cc-btn-secondary-bg:{$s['color_secondary']};--cc-btn-secondary-border-color:{$s['color_secondary']};--cc-btn-secondary-color:{$s['color_secondary_text']};"
			. "--cc-btn-secondary-hover-bg:{$s['color_secondary']};--cc-btn-secondary-hover-border-color:{$p};--cc-btn-secondary-hover-color:{$s['color_secondary_text']};"
			. "--cc-link-color:{$s['color_link']};";

		$css = '#cc-main{' . $buttons . ( 'dark' !== $s['theme'] ? $light : '' ) . '}'
			. '.cc--darkmode #cc-main{' . $buttons . '}'
			. '#cc-main .btn:hover{filter:brightness(.94)}'
			. '#cc-main .jpxcc-id{font-size:.85em;word-break:break-all}';

		return $css;
	}

	/**
	 * [jpx_cookies_preferencias texto="Preferências de cookies"]
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'texto' => __( 'Preferências de cookies', 'jpx-cookies-lgpd' ),
				'class' => '',
			),
			$atts,
			'jpx_cookies_preferencias'
		);
		return '<a href="#" role="button" data-cc="show-preferencesModal" class="jpxcc-prefs-link ' . esc_attr( $atts['class'] ) . '">' . esc_html( $atts['texto'] ) . '</a>';
	}
}
