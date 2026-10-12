<?php
/**
 * Painel administrativo.
 *
 * @package JPX_Cookies_LGPD
 */

defined( 'ABSPATH' ) || exit;

class JPXCC_Admin {

	const SLUG = 'jpx-cookies-lgpd';

	/** Plugins de consentimento que conflitam com este. */
	const CONFLICTS = array(
		'complianz-gdpr/complianz-gpdr.php'                 => 'Complianz',
		'complianz-gdpr-premium/complianz-gpdr-premium.php' => 'Complianz Premium',
		'cookie-law-info/cookie-law-info.php'               => 'CookieYes',
		'cookiebot/cookiebot.php'                           => 'Cookiebot',
		'gdpr-cookie-compliance/moove-gdpr.php'             => 'GDPR Cookie Compliance',
		'real-cookie-banner/index.php'                      => 'Real Cookie Banner',
		'cookie-notice/cookie-notice.php'                   => 'Cookie Notice',
	);

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_init', array( __CLASS__, 'privacy_policy_content' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'update_option_' . JPXCC_OPTION, array( __CLASS__, 'purge_page_cache' ) );
		add_action( 'add_option_' . JPXCC_OPTION, array( __CLASS__, 'purge_page_cache' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( JPXCC_FILE ), array( __CLASS__, 'action_links' ) );

		foreach ( array( 'export_csv', 'delete_consent', 'export_settings', 'import_settings', 'reset_settings', 'bump_revision' ) as $action ) {
			add_action( 'admin_post_jpxcc_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}
	}

	public static function menu() {
		add_options_page(
			__( 'JPX Cookies LGPD', 'jpx-cookies-lgpd' ),
			__( 'JPX Cookies LGPD', 'jpx-cookies-lgpd' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	public static function register() {
		register_setting(
			'jpxcc',
			JPXCC_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'JPXCC_Settings', 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Configurações', 'jpx-cookies-lgpd' ) . '</a>' );
		return $links;
	}

	public static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'options-general.php' ) );
	}

	public static function assets( $hook ) {
		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'jpxcc-admin', JPXCC_URL . 'assets/css/admin.css', array(), JPXCC_VERSION );
		wp_enqueue_script( 'jpxcc-admin', JPXCC_URL . 'assets/js/admin.js', array( 'wp-color-picker' ), JPXCC_VERSION, true );
	}

	/** Tenta limpar caches de página conhecidos após salvar. */
	public static function purge_page_cache() {
		JPXCC_Settings::flush();
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}
		do_action( 'litespeed_purge_all' );
		do_action( 'breeze_clear_all_cache' );
		do_action( 'jpxcc_purge_cache' );
	}

	/** Texto sugerido para a Política de Privacidade (Configurações → Privacidade). */
	public static function privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content = '<p>' . __( 'Este site usa cookies essenciais para funcionar e, somente com a sua autorização, cookies de estatísticas e de publicidade. Você pode alterar ou revogar sua escolha a qualquer momento pelo link "Preferências de cookies".', 'jpx-cookies-lgpd' ) . '</p>'
			. '<p>' . __( 'Para comprovar o consentimento (LGPD, art. 8º), registramos um identificador aleatório da sua escolha, as categorias autorizadas, a data, a página e, conforme configurado, o IP anonimizado e o navegador utilizado. Esses registros são mantidos pelo prazo definido pelo controlador e depois excluídos automaticamente.', 'jpx-cookies-lgpd' ) . '</p>'
			. '<p>' . __( 'Sem autorização para estatísticas e publicidade, o Google Analytics e os anúncios do Google funcionam em modo de consentimento negado: não gravam cookies de identificação e os anúncios exibidos não são personalizados.', 'jpx-cookies-lgpd' ) . '</p>';
		if ( JPXCC_Settings::optout_categories() ) {
			$content .= '<p>' . __( 'Os cookies de medição de audiência são usados com base no legítimo interesse do controlador (LGPD, art. 7º, IX), para conhecer o alcance do conteúdo publicado. Eles ficam ativos por padrão e você pode desativá-los a qualquer momento, recusando no aviso de cookies ou pelo link "Preferências de cookies". [Descreva aqui o teste de balanceamento realizado.]', 'jpx-cookies-lgpd' ) . '</p>';
		}
		wp_add_privacy_policy_content( __( 'JPX Cookies LGPD', 'jpx-cookies-lgpd' ), wp_kses_post( $content ) );
	}

	/* ------------------------------------------------------------------
	 * Avisos
	 * ---------------------------------------------------------------- */

	public static function notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( self::CONFLICTS as $file => $name ) {
			if ( is_plugin_active( $file ) ) {
				printf(
					'<div class="notice notice-warning"><p><strong>JPX Cookies LGPD:</strong> %s</p></div>',
					/* translators: %s: plugin name */
					esc_html( sprintf( __( 'o plugin %s está ativo. Dois gerenciadores de consentimento ao mesmo tempo bloqueiam scripts em dobro e confundem o Consent Mode. Desative-o depois de configurar este.', 'jpx-cookies-lgpd' ), $name ) )
				);
			}
		}

		$screen = get_current_screen();
		if ( ! $screen || 'settings_page_' . self::SLUG !== $screen->id ) {
			return;
		}

		if ( JPXCC_Settings::get( 'load_gtag' ) && is_plugin_active( 'google-site-kit/google-site-kit.php' ) ) {
			echo '<div class="notice notice-warning"><p><strong>JPX Cookies LGPD:</strong> ' . esc_html__( 'o Site Kit está ativo e este plugin também está carregando o GA4. Se o Site Kit inserir a mesma tag, os acessos serão contados em dobro: desative a inserção da tag no Site Kit ou o carregamento por aqui.', 'jpx-cookies-lgpd' ) . '</p></div>';
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- apenas exibição de mensagens.
		$msg   = isset( $_GET['jpxcc_msg'] ) ? sanitize_key( $_GET['jpxcc_msg'] ) : '';
		$count = isset( $_GET['jpxcc_n'] ) ? absint( $_GET['jpxcc_n'] ) : 0;
		// phpcs:enable

		$messages = array(
			'imported' => __( 'Configurações importadas.', 'jpx-cookies-lgpd' ),
			'invalid'  => __( 'Arquivo de configurações inválido.', 'jpx-cookies-lgpd' ),
			'reset'    => __( 'Configurações restauradas para o padrão.', 'jpx-cookies-lgpd' ),
			'bumped'   => __( 'Revisão incrementada: todos os visitantes verão o banner novamente.', 'jpx-cookies-lgpd' ),
			/* translators: %d: number of rows */
			'deleted'  => sprintf( _n( '%d registro excluído.', '%d registros excluídos.', $count, 'jpx-cookies-lgpd' ), $count ),
			'badid'    => __( 'ID de consentimento inválido.', 'jpx-cookies-lgpd' ),
		);
		if ( isset( $messages[ $msg ] ) ) {
			$type = in_array( $msg, array( 'invalid', 'badid' ), true ) ? 'error' : 'success';
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $type ), esc_html( $messages[ $msg ] ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['settings-updated'] ) ) {
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Se o site usa cache de página ou Cloudflare, limpe o cache para os visitantes receberem a nova configuração. (Caches do WP Rocket, LiteSpeed, W3 Total Cache, WP Super Cache, SiteGround e Breeze foram limpos automaticamente.)', 'jpx-cookies-lgpd' ) . '</p></div>';
		}
	}

	/**
	 * Texto de ajuda da base legal, por categoria.
	 *
	 * @param string $slug Categoria.
	 * @return string
	 */
	private static function basis_help( $slug ) {
		if ( 'marketing' === $slug ) {
			return __( '<strong>Atenção:</strong> publicidade personalizada com base em legítimo interesse dificilmente se sustenta na LGPD. O AdSense já roda para todos com anúncios não personalizados, então isto não aumenta a receita de quem não aceita.', 'jpx-cookies-lgpd' );
		}
		if ( 'analytics' === $slug ) {
			return __( 'Em legítimo interesse, o GA4 conta o visitante desde a primeira página e só para se ele recusar. Ferramentas de gravação de sessão (Clarity, Hotjar) listadas abaixo também passam a rodar por padrão; se não quiser isso, remova-as dos padrões ou mova-as para Publicidade. Exige Política de Privacidade atualizada e teste de balanceamento documentado.', 'jpx-cookies-lgpd' );
		}
		return __( 'Em legítimo interesse, a categoria fica ligada até o visitante recusar.', 'jpx-cookies-lgpd' );
	}

	/* ------------------------------------------------------------------
	 * Campos
	 * ---------------------------------------------------------------- */

	private static function name( $path ) {
		$parts = (array) $path;
		return JPXCC_OPTION . '[' . implode( '][', array_map( 'esc_attr', $parts ) ) . ']';
	}

	private static function value( $path ) {
		$v = JPXCC_Settings::all();
		foreach ( (array) $path as $p ) {
			$v = isset( $v[ $p ] ) ? $v[ $p ] : '';
		}
		return $v;
	}

	private static function id( $path ) {
		return 'jpxcc-' . implode( '-', (array) $path );
	}

	private static function row_start( $path, $label ) {
		echo '<tr><th scope="row"><label for="' . esc_attr( self::id( $path ) ) . '">' . esc_html( $label ) . '</label></th><td>';
	}

	private static function row_end( $desc = '' ) {
		if ( $desc ) {
			echo '<p class="description">' . wp_kses( $desc, array( 'code' => array(), 'strong' => array(), 'br' => array(), 'a' => array( 'href' => true, 'target' => true ) ) ) . '</p>';
		}
		echo '</td></tr>';
	}

	private static function checkbox( $path, $label, $text, $desc = '' ) {
		self::row_start( $path, $label );
		printf(
			'<input type="hidden" name="%1$s" value="0"><label><input type="checkbox" id="%2$s" name="%1$s" value="1" %3$s> %4$s</label>',
			esc_attr( self::name( $path ) ),
			esc_attr( self::id( $path ) ),
			checked( (int) self::value( $path ), 1, false ),
			esc_html( $text )
		);
		self::row_end( $desc );
	}

	private static function number( $path, $label, $min, $max, $suffix = '', $desc = '' ) {
		self::row_start( $path, $label );
		printf(
			'<input type="number" class="small-text" id="%s" name="%s" value="%d" min="%d" max="%d"> %s',
			esc_attr( self::id( $path ) ),
			esc_attr( self::name( $path ) ),
			(int) self::value( $path ),
			(int) $min,
			(int) $max,
			esc_html( $suffix )
		);
		self::row_end( $desc );
	}

	private static function select( $path, $label, $desc = '' ) {
		$choices = JPXCC_Settings::choices();
		$key     = is_array( $path ) ? end( $path ) : $path;
		self::row_start( $path, $label );
		echo '<select id="' . esc_attr( self::id( $path ) ) . '" name="' . esc_attr( self::name( $path ) ) . '">';
		foreach ( $choices[ $key ] as $val => $text ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $val ), selected( self::value( $path ), $val, false ), esc_html( $text ) );
		}
		echo '</select>';
		self::row_end( $desc );
	}

	private static function text( $path, $label, $desc = '', $class = 'regular-text', $placeholder = '' ) {
		self::row_start( $path, $label );
		printf(
			'<input type="text" class="%s" id="%s" name="%s" value="%s" placeholder="%s">',
			esc_attr( $class ),
			esc_attr( self::id( $path ) ),
			esc_attr( self::name( $path ) ),
			esc_attr( self::value( $path ) ),
			esc_attr( $placeholder )
		);
		self::row_end( $desc );
	}

	private static function textarea( $path, $label, $desc = '', $rows = 3, $class = 'large-text' ) {
		self::row_start( $path, $label );
		printf(
			'<textarea class="%s" rows="%d" id="%s" name="%s">%s</textarea>',
			esc_attr( $class ),
			(int) $rows,
			esc_attr( self::id( $path ) ),
			esc_attr( self::name( $path ) ),
			esc_textarea( self::value( $path ) )
		);
		self::row_end( $desc );
	}

	private static function color( $path, $label ) {
		self::row_start( $path, $label );
		printf(
			'<input type="text" class="jpxcc-color" id="%s" name="%s" value="%s" data-default-color="%s">',
			esc_attr( self::id( $path ) ),
			esc_attr( self::name( $path ) ),
			esc_attr( self::value( $path ) ),
			esc_attr( JPXCC_Settings::defaults()[ $path ] )
		);
		self::row_end();
	}

	/* ------------------------------------------------------------------
	 * Página
	 * ---------------------------------------------------------------- */

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : 'settings';

		$tabs = array(
			'geral'      => __( 'Geral', 'jpx-cookies-lgpd' ),
			'aparencia'  => __( 'Aparência', 'jpx-cookies-lgpd' ),
			'textos'     => __( 'Textos', 'jpx-cookies-lgpd' ),
			'categorias' => __( 'Categorias e bloqueio', 'jpx-cookies-lgpd' ),
			'google'     => __( 'Google', 'jpx-cookies-lgpd' ),
		);
		?>
		<div class="wrap jpxcc-wrap">
			<h1><?php esc_html_e( 'JPX Cookies LGPD', 'jpx-cookies-lgpd' ); ?> <span class="jpxcc-version">v<?php echo esc_html( JPXCC_VERSION ); ?></span></h1>

			<nav class="nav-tab-wrapper jpxcc-tabs">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( self::url() . '#' . $slug ); ?>" class="nav-tab <?php echo 'settings' === $view ? 'jpxcc-js-tab' : ''; ?>" data-tab="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
				<a href="<?php echo esc_url( self::url( array( 'view' => 'registros' ) ) ); ?>" class="nav-tab <?php echo 'registros' === $view ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Registros', 'jpx-cookies-lgpd' ); ?></a>
				<a href="<?php echo esc_url( self::url( array( 'view' => 'ferramentas' ) ) ); ?>" class="nav-tab <?php echo 'ferramentas' === $view ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Ferramentas', 'jpx-cookies-lgpd' ); ?></a>
			</nav>

			<?php
			if ( 'registros' === $view ) {
				self::render_logs();
			} elseif ( 'ferramentas' === $view ) {
				self::render_tools();
			} else {
				self::render_settings();
			}
			?>
		</div>
		<?php
	}

	private static function render_settings() {
		$preview = add_query_arg( 'jpxcc_preview', '1', home_url( '/' ) );
		?>
		<form method="post" action="options.php" class="jpxcc-form">
			<?php settings_fields( 'jpxcc' ); ?>

			<section class="jpxcc-panel" data-panel="geral">
				<?php self::render_status(); ?>
				<table class="form-table" role="presentation">
					<?php
					self::checkbox( 'enabled', __( 'Ativar', 'jpx-cookies-lgpd' ), __( 'Exibir o banner e aplicar o Consent Mode no site', 'jpx-cookies-lgpd' ) );
					self::checkbox( 'test_mode', __( 'Modo de teste', 'jpx-cookies-lgpd' ), __( 'Somente administradores logados veem o banner e o bloqueio', 'jpx-cookies-lgpd' ), __( 'Use para configurar com o site no ar sem afetar os visitantes. Desligue para valer para todos.', 'jpx-cookies-lgpd' ) );

					self::row_start( 'policy_page_id', __( 'Política de Privacidade', 'jpx-cookies-lgpd' ) );
					wp_dropdown_pages(
						array(
							'name'              => esc_attr( self::name( 'policy_page_id' ) ),
							'id'                => esc_attr( self::id( 'policy_page_id' ) ),
							'selected'          => (int) self::value( 'policy_page_id' ),
							'show_option_none'  => esc_html__( '— Selecione —', 'jpx-cookies-lgpd' ),
							'option_none_value' => '0',
						)
					);
					self::row_end( __( 'Usada no marcador <code>{politica}</code> dos textos.', 'jpx-cookies-lgpd' ) );

					self::select( 'show_mode', __( 'Quando exibir o banner', 'jpx-cookies-lgpd' ), __( 'Exibir na primeira interação evita que o banner seja medido como LCP no celular. Nada não essencial roda antes da escolha em nenhum dos modos.', 'jpx-cookies-lgpd' ) );
					self::checkbox( 'floating_button', __( 'Botão flutuante', 'jpx-cookies-lgpd' ), __( 'Mostrar botão para reabrir as preferências depois da escolha', 'jpx-cookies-lgpd' ), __( 'Alternativa ao link no rodapé com o shortcode <code>[jpx_cookies_preferencias]</code>. Um dos dois é necessário para o visitante poder revogar o consentimento.', 'jpx-cookies-lgpd' ) );
					self::select( 'shortcut_position', __( 'Posição do atalho', 'jpx-cookies-lgpd' ), __( 'Onde ficam o banner minimizado e o botão flutuante. Escolha um lugar que não cubra WhatsApp, "voltar ao topo" ou player do tema.', 'jpx-cookies-lgpd' ) );
					self::checkbox( 'disable_page_interaction', __( 'Bloquear a página', 'jpx-cookies-lgpd' ), __( 'Escurecer a página e impedir rolagem até a escolha', 'jpx-cookies-lgpd' ), __( 'Não recomendado para portais: aumenta a rejeição. Desativa a minimização.', 'jpx-cookies-lgpd' ) );
					self::number( 'cookie_days', __( 'Validade de quem aceitou tudo', 'jpx-cookies-lgpd' ), 1, 395, __( 'dias', 'jpx-cookies-lgpd' ), __( 'Depois desse prazo o banner aparece de novo.', 'jpx-cookies-lgpd' ) );
					self::number( 'cookie_days_reject', __( 'Validade de quem recusou', 'jpx-cookies-lgpd' ), 1, 395, __( 'dias', 'jpx-cookies-lgpd' ), __( 'Vale para quem recusou ou personalizou deixando alguma categoria desligada. Um prazo menor dá nova chance de aceitar; evite menos de 30 dias para não insistir demais com o visitante.', 'jpx-cookies-lgpd' ) );
					self::number( 'revision', __( 'Revisão do consentimento', 'jpx-cookies-lgpd' ), 1, 9999, '', __( 'Ao aumentar este número, todos os visitantes precisam escolher novamente. Faça isso quando incluir novas finalidades ou ferramentas.', 'jpx-cookies-lgpd' ) );
					?>
				</table>

				<h2><?php esc_html_e( 'Minimizar sem decisão', 'jpx-cookies-lgpd' ); ?></h2>
				<p class="description"><?php esc_html_e( 'O banner encolhe para um atalho discreto até o fim da visita. Minimizar não é uma escolha: categorias em consentimento continuam desligadas, as de legítimo interesse continuam ligadas, e nada é gravado no registro.', 'jpx-cookies-lgpd' ); ?></p>
				<table class="form-table" role="presentation">
					<?php
					self::checkbox( 'minimize_enabled', __( 'Minimizar', 'jpx-cookies-lgpd' ), __( 'Encolher o banner quando o visitante seguir navegando sem escolher', 'jpx-cookies-lgpd' ) );
					self::checkbox( 'minimize_outside', __( 'Ao clicar fora', 'jpx-cookies-lgpd' ), __( 'Clique ou toque fora do banner', 'jpx-cookies-lgpd' ) );
					self::checkbox( 'minimize_scroll', __( 'Ao rolar a página', 'jpx-cookies-lgpd' ), __( 'Depois de rolar cerca de meia tela', 'jpx-cookies-lgpd' ) );
					self::number( 'minimize_delay', __( 'Após um tempo', 'jpx-cookies-lgpd' ), 0, 120, __( 'segundos (0 = desligado)', 'jpx-cookies-lgpd' ) );
					?>
				</table>

				<h2><?php esc_html_e( 'Registro de consentimentos', 'jpx-cookies-lgpd' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::checkbox( 'log_enabled', __( 'Registrar', 'jpx-cookies-lgpd' ), __( 'Guardar a prova de cada escolha (recomendado pela LGPD, art. 8º, §2º)', 'jpx-cookies-lgpd' ) );
					self::select( 'log_ip', __( 'IP do visitante', 'jpx-cookies-lgpd' ) );
					self::checkbox( 'log_ua', __( 'Navegador', 'jpx-cookies-lgpd' ), __( 'Registrar navegador e sistema, de forma resumida (ex.: "Chrome 141 / Android")', 'jpx-cookies-lgpd' ) );
					self::number( 'retention_days', __( 'Manter registros por', 'jpx-cookies-lgpd' ), 30, 3650, __( 'dias', 'jpx-cookies-lgpd' ), __( 'Registros mais antigos são excluídos automaticamente todos os dias. Defina o prazo com o responsável jurídico.', 'jpx-cookies-lgpd' ) );
					?>
				</table>

				<h2><?php esc_html_e( 'Desinstalação', 'jpx-cookies-lgpd' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php self::checkbox( 'delete_on_uninstall', __( 'Apagar dados', 'jpx-cookies-lgpd' ), __( 'Excluir configurações e registros ao desinstalar o plugin', 'jpx-cookies-lgpd' ) ); ?>
				</table>
			</section>

			<section class="jpxcc-panel" data-panel="aparencia">
				<p><a class="button" href="<?php echo esc_url( $preview ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Pré-visualizar no site', 'jpx-cookies-lgpd' ); ?></a>
				<span class="description"><?php esc_html_e( 'Salve antes. A pré-visualização mostra o banner sem apagar a sua escolha atual.', 'jpx-cookies-lgpd' ); ?></span></p>
				<table class="form-table" role="presentation">
					<?php
					self::select( 'layout', __( 'Formato do banner', 'jpx-cookies-lgpd' ) );
					self::select( 'position_v', __( 'Posição vertical', 'jpx-cookies-lgpd' ) );
					self::select( 'position_h', __( 'Posição horizontal', 'jpx-cookies-lgpd' ), __( 'Ignorada nos formatos de barra.', 'jpx-cookies-lgpd' ) );
					self::select( 'pm_layout', __( 'Janela de preferências', 'jpx-cookies-lgpd' ) );
					self::select( 'theme', __( 'Tema', 'jpx-cookies-lgpd' ) );
					self::checkbox( 'flip_buttons', __( 'Inverter botões', 'jpx-cookies-lgpd' ), __( 'Trocar a ordem de "Aceitar" e "Recusar"', 'jpx-cookies-lgpd' ), __( 'Os dois botões têm sempre o mesmo destaque, como orienta a ANPD.', 'jpx-cookies-lgpd' ) );
					self::color( 'color_primary', __( 'Cor dos botões', 'jpx-cookies-lgpd' ) );
					self::color( 'color_primary_text', __( 'Texto dos botões', 'jpx-cookies-lgpd' ) );
					self::color( 'color_secondary', __( 'Cor do botão secundário', 'jpx-cookies-lgpd' ) );
					self::color( 'color_secondary_text', __( 'Texto do botão secundário', 'jpx-cookies-lgpd' ) );
					self::color( 'color_bg', __( 'Fundo', 'jpx-cookies-lgpd' ) );
					self::color( 'color_text', __( 'Texto', 'jpx-cookies-lgpd' ) );
					self::color( 'color_link', __( 'Links', 'jpx-cookies-lgpd' ) );
					self::number( 'radius', __( 'Arredondamento', 'jpx-cookies-lgpd' ), 0, 40, 'px' );
					self::text( 'font_family', __( 'Fonte', 'jpx-cookies-lgpd' ), __( 'Deixe vazio para herdar a fonte do tema.', 'jpx-cookies-lgpd' ), 'regular-text', 'Inter, sans-serif' );
					?>
				</table>
				<p class="description"><?php esc_html_e( 'No tema escuro, fundo, texto e links seguem a paleta escura da biblioteca; as cores dos botões continuam valendo.', 'jpx-cookies-lgpd' ); ?></p>
			</section>

			<section class="jpxcc-panel" data-panel="textos">
				<p class="description"><?php echo wp_kses( __( 'Marcadores: <code>{politica}</code> vira o link da Política de Privacidade; <code>{id}</code> mostra o identificador do consentimento do visitante (útil para pedidos de acesso ou exclusão). Nas descrições são permitidos links, negrito e itálico.', 'jpx-cookies-lgpd' ), array( 'code' => array() ) ); ?></p>
				<h2><?php esc_html_e( 'Modelos de mensagem', 'jpx-cookies-lgpd' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Escolha um ponto de partida: os campos do banner abaixo são preenchidos e você pode ajustar antes de salvar. Os botões de aceitar e recusar continuam com o mesmo destaque em todos os modelos.', 'jpx-cookies-lgpd' ); ?></p>
				<div class="jpxcc-presets">
					<?php
					$current_title = self::value( array( 'texts', 'cm_title' ) );
					foreach ( JPXCC_Settings::presets() as $key => $preset ) :
						$fields  = array_intersect_key( $preset, array_flip( array( 'cm_title', 'cm_description', 'accept_all', 'reject_all', 'show_prefs' ) ) );
						$preview = str_replace( '{politica}', '<u>' . esc_html( self::value( array( 'texts', 'policy_label' ) ) ) . '</u>', esc_html( $preset['cm_description'] ) );
						?>
						<div class="jpxcc-preset <?php echo $current_title === $preset['cm_title'] ? 'is-current' : ''; ?>">
							<div class="jpxcc-preset__head">
								<strong><?php echo esc_html( $preset['label'] ); ?></strong>
								<span><?php echo esc_html( $preset['hint'] ); ?></span>
							</div>
							<div class="jpxcc-preset__card">
								<p class="jpxcc-preset__title"><?php echo esc_html( $preset['cm_title'] ); ?></p>
								<p class="jpxcc-preset__text"><?php echo wp_kses( $preview, array( 'u' => array() ) ); ?></p>
								<div class="jpxcc-preset__btns">
									<span class="is-primary"><?php echo esc_html( $preset['accept_all'] ); ?></span>
									<span class="is-primary"><?php echo esc_html( $preset['reject_all'] ); ?></span>
									<span><?php echo esc_html( $preset['show_prefs'] ); ?></span>
								</div>
							</div>
							<button type="button" class="button jpxcc-use-preset" data-preset="<?php echo esc_attr( wp_json_encode( $fields ) ); ?>"><?php esc_html_e( 'Usar este modelo', 'jpx-cookies-lgpd' ); ?></button>
						</div>
					<?php endforeach; ?>
				</div>
				<p class="jpxcc-preset-applied" hidden><?php esc_html_e( 'Modelo aplicado aos campos abaixo. Revise e clique em "Salvar alterações".', 'jpx-cookies-lgpd' ); ?></p>
				<h2><?php esc_html_e( 'Banner', 'jpx-cookies-lgpd' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::text( array( 'texts', 'cm_title' ), __( 'Título', 'jpx-cookies-lgpd' ) );
					self::textarea( array( 'texts', 'cm_description' ), __( 'Texto', 'jpx-cookies-lgpd' ), '', 4 );
					self::text( array( 'texts', 'accept_all' ), __( 'Botão aceitar', 'jpx-cookies-lgpd' ) );
					self::text( array( 'texts', 'reject_all' ), __( 'Botão recusar', 'jpx-cookies-lgpd' ) );
					self::text( array( 'texts', 'show_prefs' ), __( 'Botão preferências', 'jpx-cookies-lgpd' ) );
					self::text( array( 'texts', 'policy_label' ), __( 'Texto do link da política', 'jpx-cookies-lgpd' ) );
					?>
				</table>
				<h2><?php esc_html_e( 'Janela de preferências', 'jpx-cookies-lgpd' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::text( array( 'texts', 'pm_title' ), __( 'Título', 'jpx-cookies-lgpd' ) );
					self::text( array( 'texts', 'save_prefs' ), __( 'Botão salvar', 'jpx-cookies-lgpd' ) );
					self::text( array( 'texts', 'close_label' ), __( 'Rótulo do fechar', 'jpx-cookies-lgpd' ) );
					self::text( array( 'texts', 'pm_intro_title' ), __( 'Título da introdução', 'jpx-cookies-lgpd' ) );
					self::textarea( array( 'texts', 'pm_intro_desc' ), __( 'Introdução', 'jpx-cookies-lgpd' ) );
					self::text( array( 'texts', 'pm_more_title' ), __( 'Título do rodapé', 'jpx-cookies-lgpd' ) );
					self::textarea( array( 'texts', 'pm_more_desc' ), __( 'Rodapé', 'jpx-cookies-lgpd' ) );
					self::text( array( 'texts', 'floating_label' ), __( 'Rótulo do botão flutuante', 'jpx-cookies-lgpd' ) );
					self::text( array( 'texts', 'minimized_label' ), __( 'Texto do banner minimizado', 'jpx-cookies-lgpd' ), __( 'Curto: aparece no atalho discreto.', 'jpx-cookies-lgpd' ) );
					self::text( array( 'texts', 'minimized_aria' ), __( 'Descrição do atalho (leitores de tela)', 'jpx-cookies-lgpd' ) );
					self::textarea( array( 'texts', 'optout_notice' ), __( 'Aviso de legítimo interesse', 'jpx-cookies-lgpd' ), __( 'Acrescentado ao texto do banner quando alguma categoria usa legítimo interesse. <code>{categorias}</code> vira o nome delas.', 'jpx-cookies-lgpd' ) );
					?>
				</table>
			</section>

			<section class="jpxcc-panel" data-panel="categorias">
				<p class="description"><?php echo wp_kses( __( 'Os padrões de bloqueio são trechos de texto, um por linha. Toda tag <code>&lt;script&gt;</code> cujo endereço ou conteúdo contenha um deles só roda depois que o visitante autorizar a categoria. <strong>Não inclua Google Analytics, AdSense, Ad Manager, AdRotate nem o JPX Ads/AdPulse</strong>: o Google é controlado pelo Consent Mode e os banners próprios não usam cookies de terceiros.', 'jpx-cookies-lgpd' ), array( 'code' => array(), 'strong' => array() ) ); ?></p>
				<?php
				$labels = array(
					'necessary'  => __( 'Essenciais (sempre ativos)', 'jpx-cookies-lgpd' ),
					'functional' => __( 'Funcionais', 'jpx-cookies-lgpd' ),
					'analytics'  => __( 'Estatísticas', 'jpx-cookies-lgpd' ),
					'marketing'  => __( 'Publicidade e marketing', 'jpx-cookies-lgpd' ),
				);
				foreach ( $labels as $slug => $heading ) :
					?>
					<h2><?php echo esc_html( $heading ); ?></h2>
					<table class="form-table" role="presentation">
						<?php
						if ( 'necessary' !== $slug ) {
							self::checkbox( array( 'categories', $slug, 'enabled' ), __( 'Exibir', 'jpx-cookies-lgpd' ), __( 'Oferecer esta categoria ao visitante', 'jpx-cookies-lgpd' ) );
							self::select( array( 'categories', $slug, 'basis' ), __( 'Base legal', 'jpx-cookies-lgpd' ), self::basis_help( $slug ) );
						}
						self::text( array( 'categories', $slug, 'title' ), __( 'Nome', 'jpx-cookies-lgpd' ) );
						self::textarea( array( 'categories', $slug, 'description' ), __( 'Descrição', 'jpx-cookies-lgpd' ) );
						if ( 'necessary' !== $slug ) {
							self::textarea( array( 'categories', $slug, 'patterns' ), __( 'Padrões de bloqueio', 'jpx-cookies-lgpd' ), '', 5, 'large-text code' );
						}
						?>
					</table>
				<?php endforeach; ?>

				<h2><?php esc_html_e( 'Bloqueio', 'jpx-cookies-lgpd' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::checkbox( 'enable_blocker', __( 'Bloquear scripts', 'jpx-cookies-lgpd' ), __( 'Aplicar os padrões acima no HTML das páginas', 'jpx-cookies-lgpd' ) );
					self::textarea( 'block_exclusions', __( 'Nunca bloquear', 'jpx-cookies-lgpd' ), __( 'Padrões que nunca são bloqueados, um por linha. Os scripts do Google já são protegidos automaticamente. Você também pode marcar uma tag com o atributo <code>data-jpx-ignore</code>.', 'jpx-cookies-lgpd' ), 3, 'large-text code' );
					?>
				</table>
			</section>

			<section class="jpxcc-panel" data-panel="google">
				<p class="description"><?php esc_html_e( 'O Consent Mode v2 é sempre ativado: o Google começa com tudo negado e é liberado conforme a escolha do visitante. Quem recusa continua gerando sinais sem cookies, usados pelo GA4 na modelagem, e o AdSense segue exibindo anúncios não personalizados.', 'jpx-cookies-lgpd' ); ?></p>
				<table class="form-table" role="presentation">
					<?php
					self::checkbox( 'load_gtag', __( 'Carregar o GA4', 'jpx-cookies-lgpd' ), __( 'Inserir a tag do Google (gtag.js) por este plugin', 'jpx-cookies-lgpd' ), __( 'Desative se a tag já vem do tema, do Site Kit ou de outro plugin. Nesse caso o Consent Mode continua valendo, desde que a tag seja inserida depois do <code>wp_head</code>.', 'jpx-cookies-lgpd' ) );
					self::text( 'ga_ids', __( 'IDs da tag do Google', 'jpx-cookies-lgpd' ), __( 'Um ou mais IDs separados por vírgula: <code>G-…</code> (GA4), <code>AW-…</code> (Google Ads).', 'jpx-cookies-lgpd' ), 'regular-text code', 'G-XXXXXXXXXX' );
					self::text( 'gtm_id', __( 'Google Tag Manager', 'jpx-cookies-lgpd' ), __( 'Opcional. Se usar o GTM, configure as tags lá e deixe o campo de IDs acima vazio. O evento <code>jpxcc_consent</code> é enviado ao dataLayer a cada escolha.', 'jpx-cookies-lgpd' ), 'regular-text code', 'GTM-XXXXXXX' );
					self::checkbox( 'ads_data_redaction', __( 'Ocultar dados de anúncios', 'jpx-cookies-lgpd' ), __( 'ads_data_redaction quando a publicidade for recusada', 'jpx-cookies-lgpd' ) );
					self::checkbox( 'url_passthrough', __( 'Passar parâmetros na URL', 'jpx-cookies-lgpd' ), __( 'url_passthrough (melhora a atribuição de campanhas sem cookies)', 'jpx-cookies-lgpd' ) );
					self::number( 'wait_for_update', __( 'Aguardar escolha', 'jpx-cookies-lgpd' ), 0, 5000, 'ms', __( 'Tempo que as tags do Google esperam por uma atualização de consentimento antes de disparar.', 'jpx-cookies-lgpd' ) );
					?>
				</table>
			</section>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/** Quadro de situação no topo da aba Geral. */
	private static function render_status() {
		$s      = JPXCC_Settings::all();
		$items  = array();
		$policy = JPXCC_Frontend::policy_url();

		if ( ! $s['enabled'] ) {
			$items[] = array( 'warn', __( 'Plugin desativado: o banner não aparece.', 'jpx-cookies-lgpd' ) );
		} elseif ( $s['test_mode'] ) {
			$items[] = array( 'warn', __( 'Modo de teste ligado: só administradores veem o banner.', 'jpx-cookies-lgpd' ) );
		} else {
			$items[] = array( 'ok', __( 'Banner ativo para todos os visitantes.', 'jpx-cookies-lgpd' ) );
		}
		$items[] = $policy ? array( 'ok', __( 'Política de Privacidade vinculada.', 'jpx-cookies-lgpd' ) ) : array( 'warn', __( 'Nenhuma Política de Privacidade publicada foi selecionada.', 'jpx-cookies-lgpd' ) );
		if ( $s['load_gtag'] && ! $s['ga_ids'] ) {
			$items[] = array( 'warn', __( '"Carregar o GA4" está ligado, mas nenhum ID foi informado.', 'jpx-cookies-lgpd' ) );
		} elseif ( $s['load_gtag'] ) {
			$items[] = array( 'ok', __( 'GA4 carregado pelo plugin: ', 'jpx-cookies-lgpd' ) . $s['ga_ids'] );
		}
		$optout = JPXCC_Settings::optout_categories();
		if ( $optout ) {
			$names = array();
			foreach ( $optout as $slug ) {
				$names[] = $s['categories'][ $slug ]['title'];
			}
			$items[] = array( 'warn', sprintf( /* translators: %s: categories */ __( 'Legítimo interesse (opt-out) ativo em: %s. Essas categorias rodam até o visitante recusar. Mantenha a Política de Privacidade atualizada e o teste de balanceamento documentado.', 'jpx-cookies-lgpd' ), implode( ', ', $names ) ) );
		}
		if ( ! $s['floating_button'] ) {
			$items[] = array( 'info', __( 'Lembre de colocar o shortcode [jpx_cookies_preferencias] no rodapé, ou ative o botão flutuante.', 'jpx-cookies-lgpd' ) );
		}

		echo '<ul class="jpxcc-status">';
		foreach ( $items as $item ) {
			printf( '<li class="is-%s">%s</li>', esc_attr( $item[0] ), esc_html( $item[1] ) );
		}
		echo '</ul>';
	}

	private static function render_logs() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? JPXCC_Consent_Log::normalize_search( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) : '';
		$before = isset( $_GET['before'] ) ? absint( $_GET['before'] ) : 0;
		$after  = isset( $_GET['after'] ) ? absint( $_GET['after'] ) : 0;
		// phpcs:enable
		$data  = JPXCC_Consent_Log::query(
			array(
				'per_page' => 50,
				'search'   => $search,
				'before'   => $before,
				'after'    => $after,
			)
		);
		$stats = JPXCC_Consent_Log::stats( 30 );
		$info  = JPXCC_Consent_Log::table_info();

		$cat_defs = JPXCC_Settings::get( 'categories' );

		$labels = array(
			'accept_all' => __( 'Aceitou todos', 'jpx-cookies-lgpd' ),
			'reject_all' => __( 'Recusou não essenciais', 'jpx-cookies-lgpd' ),
			'custom'     => __( 'Personalizou', 'jpx-cookies-lgpd' ),
			'update'     => __( 'Alterou a escolha', 'jpx-cookies-lgpd' ),
		);
		?>
		<h2><?php esc_html_e( 'Últimos 30 dias', 'jpx-cookies-lgpd' ); ?></h2>
		<div class="jpxcc-cards">
			<div class="jpxcc-card"><span><?php esc_html_e( 'Escolhas', 'jpx-cookies-lgpd' ); ?></span><strong><?php echo esc_html( number_format_i18n( $stats['first'] ) ); ?></strong></div>
			<div class="jpxcc-card"><span><?php esc_html_e( 'Aceitaram tudo', 'jpx-cookies-lgpd' ); ?></span><strong><?php echo esc_html( number_format_i18n( $stats['rate_all'], 1 ) ); ?>%</strong></div>
			<div class="jpxcc-card"><span><?php esc_html_e( 'Liberaram estatísticas', 'jpx-cookies-lgpd' ); ?></span><strong><?php echo esc_html( number_format_i18n( $stats['rate_stats'], 1 ) ); ?>%</strong></div>
			<div class="jpxcc-card"><span><?php esc_html_e( 'Recusaram', 'jpx-cookies-lgpd' ); ?></span><strong><?php echo esc_html( number_format_i18n( $stats['reject_all'] ) ); ?></strong></div>
			<div class="jpxcc-card"><span><?php esc_html_e( 'Personalizaram', 'jpx-cookies-lgpd' ); ?></span><strong><?php echo esc_html( number_format_i18n( $stats['custom'] ) ); ?></strong></div>
		</div>
		<p class="description"><?php esc_html_e( 'Só ações reais do visitante são registradas. Quem ignora o banner não aparece aqui, nem mesmo nas categorias em legítimo interesse: ignorar não é consentimento. Compare "Escolhas" com os usuários do GA4 para estimar quantos ignoram o banner.', 'jpx-cookies-lgpd' ); ?></p>

		<div class="jpxcc-logbar">
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
				<input type="hidden" name="view" value="registros">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Início do ID de consentimento', 'jpx-cookies-lgpd' ); ?>" class="regular-text" maxlength="36" pattern="[0-9a-fA-F\-]*">
				<?php submit_button( __( 'Buscar', 'jpx-cookies-lgpd' ), 'secondary', '', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'jpxcc_export_csv' ); ?>
				<input type="hidden" name="action" value="jpxcc_export_csv">
				<input type="hidden" name="s" value="<?php echo esc_attr( $search ); ?>">
				<?php submit_button( __( 'Exportar CSV', 'jpx-cookies-lgpd' ), 'secondary', '', false ); ?>
			</form>
		</div>

		<table class="widefat striped jpxcc-log">
			<thead><tr>
				<th><?php esc_html_e( 'Data', 'jpx-cookies-lgpd' ); ?></th>
				<th><?php esc_html_e( 'ID do consentimento', 'jpx-cookies-lgpd' ); ?></th>
				<th><?php esc_html_e( 'Ação', 'jpx-cookies-lgpd' ); ?></th>
				<th><?php esc_html_e( 'Categorias', 'jpx-cookies-lgpd' ); ?></th>
				<th><?php esc_html_e( 'Rev.', 'jpx-cookies-lgpd' ); ?></th>
				<th><?php esc_html_e( 'IP', 'jpx-cookies-lgpd' ); ?></th>
				<th><?php esc_html_e( 'Navegador', 'jpx-cookies-lgpd' ); ?></th>
				<th><?php esc_html_e( 'Página', 'jpx-cookies-lgpd' ); ?></th>
			</tr></thead>
			<tbody>
			<?php if ( ! $data['rows'] ) : ?>
				<tr><td colspan="8"><?php esc_html_e( 'Nenhum registro encontrado.', 'jpx-cookies-lgpd' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $data['rows'] as $r ) : ?>
				<tr>
					<td><?php echo esc_html( get_date_from_gmt( $r['created_at'], 'd/m/Y H:i:s' ) ); ?></td>
					<td><code><?php echo esc_html( $r['consent_id'] ); ?></code></td>
					<td><?php echo esc_html( isset( $labels[ $r['action'] ] ) ? $labels[ $r['action'] ] : $r['action'] ); ?></td>
					<td>
					<?php
					$cat_titles = array();
					foreach ( explode( ',', $r['categories'] ) as $slug ) {
						$cat_titles[] = isset( $cat_defs[ $slug ]['title'] ) ? $cat_defs[ $slug ]['title'] : $slug;
					}
					echo esc_html( implode( ', ', $cat_titles ) );
					?>
					</td>
					<td><?php echo esc_html( $r['revision'] ); ?></td>
					<td><?php echo esc_html( $r['ip'] ); ?></td>
					<td><?php echo esc_html( strlen( $r['user_agent'] ) > 60 ? JPXCC_Consent_Log::summarize_ua( $r['user_agent'] ) : $r['user_agent'] ); ?></td>
					<td><?php echo esc_html( $r['page'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		if ( $data['rows'] && ( $data['has_newer'] || $data['has_older'] ) ) {
			$base = self::url(
				array_filter(
					array(
						'view' => 'registros',
						's'    => $search,
					)
				)
			);
			$first_id = (int) $data['rows'][0]['id'];
			$last_id  = (int) end( $data['rows'] )['id'];
			echo '<div class="tablenav jpxcc-pager">';
			if ( $data['has_newer'] ) {
				echo '<a class="button" href="' . esc_url( add_query_arg( 'after', $first_id, $base ) ) . '">&larr; ' . esc_html__( 'Mais recentes', 'jpx-cookies-lgpd' ) . '</a> ';
			}
			if ( $data['has_older'] ) {
				echo '<a class="button" href="' . esc_url( add_query_arg( 'before', $last_id, $base ) ) . '">' . esc_html__( 'Mais antigos', 'jpx-cookies-lgpd' ) . ' &rarr;</a>';
			}
			echo '</div>';
		}
		?>

		<p class="description jpxcc-tableinfo">
			<?php
			$size = null !== $info['bytes'] ? ' · ' . size_format( $info['bytes'], 1 ) : '';
			echo esc_html(
				sprintf(
					/* translators: 1: rows, 2: size, 3: retention days */
					__( 'Tabela: cerca de %1$s registros%2$s. Registros com mais de %3$s dias são apagados automaticamente, em lotes, todos os dias.', 'jpx-cookies-lgpd' ),
					number_format_i18n( $info['rows'] ),
					$size,
					number_format_i18n( (int) JPXCC_Settings::get( 'retention_days' ) )
				)
			);
			?>
		</p>

		<h2><?php esc_html_e( 'Pedido de exclusão do titular', 'jpx-cookies-lgpd' ); ?></h2>
		<p class="description"><?php esc_html_e( 'O visitante encontra o próprio ID na janela de preferências. Informe-o aqui para excluir todos os registros ligados a ele (LGPD, art. 18, VI).', 'jpx-cookies-lgpd' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Excluir definitivamente os registros deste ID?', 'jpx-cookies-lgpd' ) ); ?>');">
			<?php wp_nonce_field( 'jpxcc_delete_consent' ); ?>
			<input type="hidden" name="action" value="jpxcc_delete_consent">
			<input type="text" name="consent_id" class="regular-text code" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" required>
			<?php submit_button( __( 'Excluir registros', 'jpx-cookies-lgpd' ), 'delete', '', false ); ?>
		</form>
		<?php
	}

	private static function render_tools() {
		$revision = (int) JPXCC_Settings::get( 'revision' );
		?>
		<h2><?php esc_html_e( 'Pedir o consentimento de novo', 'jpx-cookies-lgpd' ); ?></h2>
		<p><?php echo esc_html( sprintf( /* translators: %d: revision */ __( 'Revisão atual: %d. Incrementar faz todos os visitantes verem o banner novamente. Use ao adicionar novas ferramentas ou finalidades.', 'jpx-cookies-lgpd' ), $revision ) ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'jpxcc_bump_revision' ); ?>
			<input type="hidden" name="action" value="jpxcc_bump_revision">
			<?php submit_button( __( 'Incrementar revisão', 'jpx-cookies-lgpd' ), 'secondary', '', false ); ?>
		</form>

		<h2><?php esc_html_e( 'Exportar configurações', 'jpx-cookies-lgpd' ); ?></h2>
		<p><?php esc_html_e( 'Baixe um arquivo JSON para reaproveitar esta configuração em outro site. Os registros de consentimento não são incluídos.', 'jpx-cookies-lgpd' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'jpxcc_export_settings' ); ?>
			<input type="hidden" name="action" value="jpxcc_export_settings">
			<?php submit_button( __( 'Baixar JSON', 'jpx-cookies-lgpd' ), 'secondary', '', false ); ?>
		</form>

		<h2><?php esc_html_e( 'Importar configurações', 'jpx-cookies-lgpd' ); ?></h2>
		<p><?php esc_html_e( 'A página da Política de Privacidade é mantida a deste site.', 'jpx-cookies-lgpd' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<?php wp_nonce_field( 'jpxcc_import_settings' ); ?>
			<input type="hidden" name="action" value="jpxcc_import_settings">
			<input type="file" name="jpxcc_file" accept=".json,application/json" required>
			<?php submit_button( __( 'Importar', 'jpx-cookies-lgpd' ), 'secondary', '', false ); ?>
		</form>

		<h2><?php esc_html_e( 'Restaurar padrões', 'jpx-cookies-lgpd' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Restaurar todas as configurações para o padrão?', 'jpx-cookies-lgpd' ) ); ?>');">
			<?php wp_nonce_field( 'jpxcc_reset_settings' ); ?>
			<input type="hidden" name="action" value="jpxcc_reset_settings">
			<?php submit_button( __( 'Restaurar padrões', 'jpx-cookies-lgpd' ), 'delete', '', false ); ?>
		</form>

		<h2><?php esc_html_e( 'Para desenvolvedores', 'jpx-cookies-lgpd' ); ?></h2>
		<ul class="jpxcc-dev">
			<li><code>[jpx_cookies_preferencias texto="…"]</code> — <?php esc_html_e( 'link para reabrir as preferências', 'jpx-cookies-lgpd' ); ?></li>
			<li><code>data-cc="show-preferencesModal"</code> — <?php esc_html_e( 'qualquer elemento com este atributo abre as preferências', 'jpx-cookies-lgpd' ); ?></li>
			<li><code>&lt;script type="text/plain" data-category="marketing" data-jpx-blocked="1"&gt;</code> — <?php esc_html_e( 'bloqueio manual de um script', 'jpx-cookies-lgpd' ); ?></li>
			<li><code>document.addEventListener('jpxcc:consent', e =&gt; e.detail.categories)</code> — <?php esc_html_e( 'evento JS a cada escolha', 'jpx-cookies-lgpd' ); ?></li>
			<li><code>dataLayer: { event: 'jpxcc_consent', jpxcc_categories }</code> — <?php esc_html_e( 'evento para o GTM', 'jpx-cookies-lgpd' ); ?></li>
			<li><?php esc_html_e( 'Filtros PHP:', 'jpx-cookies-lgpd' ); ?> <code>jpxcc_block_rules</code>, <code>jpxcc_never_block</code>, <code>jpxcc_client_config</code>, <code>jpxcc_is_active</code>, <code>jpxcc_client_ip</code>. <?php esc_html_e( 'Ação:', 'jpx-cookies-lgpd' ); ?> <code>jpxcc_consent_logged</code>.</li>
		</ul>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Ações
	 * ---------------------------------------------------------------- */

	private static function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'jpx-cookies-lgpd' ), 403 );
		}
		check_admin_referer( $action );
	}

	private static function back( $view, $msg, $extra = array() ) {
		wp_safe_redirect( self::url( array_merge( array( 'view' => $view, 'jpxcc_msg' => $msg ), $extra ) ) );
		exit;
	}

	public static function handle_export_csv() {
		self::guard( 'jpxcc_export_csv' );
		$search = isset( $_POST['s'] ) ? JPXCC_Consent_Log::normalize_search( sanitize_text_field( wp_unslash( $_POST['s'] ) ) ) : '';
		JPXCC_Consent_Log::export_csv( $search );
	}

	public static function handle_delete_consent() {
		self::guard( 'jpxcc_delete_consent' );
		$id = isset( $_POST['consent_id'] ) ? strtolower( trim( sanitize_text_field( wp_unslash( $_POST['consent_id'] ) ) ) ) : '';
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id ) ) {
			self::back( 'registros', 'badid' );
		}
		$n = JPXCC_Consent_Log::delete_by_consent_id( $id );
		self::back( 'registros', 'deleted', array( 'jpxcc_n' => $n ) );
	}

	public static function handle_export_settings() {
		self::guard( 'jpxcc_export_settings' );
		$data = array(
			'plugin'   => 'jpx-cookies-lgpd',
			'version'  => JPXCC_VERSION,
			'exported' => gmdate( 'c' ),
			'settings' => JPXCC_Settings::all(),
		);
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=jpx-cookies-lgpd-' . sanitize_title( wp_parse_url( home_url(), PHP_URL_HOST ) ) . '.json' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}

	public static function handle_import_settings() {
		self::guard( 'jpxcc_import_settings' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- arquivo validado abaixo.
		$file = isset( $_FILES['jpxcc_file']['tmp_name'] ) ? $_FILES['jpxcc_file']['tmp_name'] : '';
		if ( ! $file || ! is_uploaded_file( $file ) || filesize( $file ) > 512 * 1024 ) {
			self::back( 'ferramentas', 'invalid' );
		}
		$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! is_array( $data ) || ( $data['plugin'] ?? '' ) !== 'jpx-cookies-lgpd' || ! is_array( $data['settings'] ?? null ) ) {
			self::back( 'ferramentas', 'invalid' );
		}
		$settings                   = $data['settings'];
		$settings['policy_page_id'] = (int) JPXCC_Settings::get( 'policy_page_id' );
		update_option( JPXCC_OPTION, $settings ); // Passa pelo sanitize registrado.
		self::back( 'ferramentas', 'imported' );
	}

	public static function handle_reset_settings() {
		self::guard( 'jpxcc_reset_settings' );
		delete_option( JPXCC_OPTION );
		JPXCC_Settings::flush();
		self::purge_page_cache();
		self::back( 'ferramentas', 'reset' );
	}

	public static function handle_bump_revision() {
		self::guard( 'jpxcc_bump_revision' );
		$settings             = JPXCC_Settings::all();
		$settings['revision'] = (int) $settings['revision'] + 1;
		update_option( JPXCC_OPTION, $settings );
		self::back( 'ferramentas', 'bumped' );
	}
}
