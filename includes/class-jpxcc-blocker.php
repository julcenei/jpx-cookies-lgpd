<?php
/**
 * Bloqueio de scripts de terceiros até o consentimento.
 *
 * Reescreve, no HTML final, as tags <script> que casam com os padrões de cada
 * categoria para type="text/plain" data-category="…". Elas só são executadas
 * no navegador depois que o visitante autoriza a categoria. Como a reescrita
 * acontece antes do cache de página gravar o HTML, funciona com cache e CDN.
 *
 * @package JPX_Cookies_LGPD
 */

defined( 'ABSPATH' ) || exit;

class JPXCC_Blocker {

	/** Ordem de verificação: a categoria mais restritiva vence. */
	const ORDER = array( 'marketing', 'analytics', 'functional' );

	/** Tipos de script executáveis (os demais, como JSON-LD, são ignorados). */
	const JS_TYPES = array( '', 'text/javascript', 'application/javascript', 'module', 'text/ecmascript', 'application/ecmascript' );

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'start' ), PHP_INT_MAX );
	}

	/** Inicia o buffer apenas em páginas públicas comuns. */
	public static function start() {
		if ( ! JPXCC_Frontend::is_active() || ! JPXCC_Settings::get( 'enable_blocker' ) ) {
			return;
		}
		if ( is_feed() || is_robots() || is_trackback() || is_embed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_customize_preview() ) {
			return;
		}
		if ( ! self::rules() ) {
			return;
		}
		ob_start( array( __CLASS__, 'process' ) );
	}

	/**
	 * Regras ativas: categoria => padrões.
	 *
	 * @return array
	 */
	public static function rules() {
		$cats  = JPXCC_Settings::get( 'categories' );
		$rules = array();
		foreach ( self::ORDER as $slug ) {
			if ( empty( $cats[ $slug ]['enabled'] ) ) {
				continue;
			}
			$patterns = JPXCC_Settings::patterns_to_array( $cats[ $slug ]['patterns'] );
			if ( $patterns ) {
				$rules[ $slug ] = $patterns;
			}
		}
		/**
		 * Permite adicionar ou alterar regras de bloqueio via código.
		 *
		 * @param array $rules categoria => string[] padrões.
		 */
		return (array) apply_filters( 'jpxcc_block_rules', $rules );
	}

	/**
	 * Padrões que nunca são bloqueados. Os scripts do Google são controlados
	 * pelo Consent Mode e precisam rodar sempre (inclusive o AdSense).
	 *
	 * @return string[]
	 */
	public static function exclusions() {
		$builtin = array(
			'jpxcc',
			'googletagmanager.com/gtag',
			'googletagmanager.com/gtm',
			'gtag(',
			'adsbygoogle',
			'pagead2.googlesyndication.com',
			'googletagservices.com',
			'securepubads.g.doubleclick.net',
			'fundingchoicesmessages.google.com',
		);
		$user = JPXCC_Settings::patterns_to_array( JPXCC_Settings::get( 'block_exclusions' ) );
		return (array) apply_filters( 'jpxcc_never_block', array_merge( $builtin, $user ) );
	}

	/**
	 * Callback do buffer.
	 *
	 * @param string $html HTML da página.
	 * @return string
	 */
	public static function process( $html ) {
		if ( ! is_string( $html ) || '' === $html || false === stripos( $html, '<script' ) ) {
			return $html;
		}
		// Só processa documentos HTML (não JSON, XML etc.).
		if ( false === stripos( substr( $html, 0, 1000 ), '<html' ) && false === stripos( substr( $html, 0, 1000 ), '<!doctype' ) ) {
			return $html;
		}

		$rules      = self::rules();
		$exclusions = self::exclusions();
		if ( ! $rules ) {
			return $html;
		}

		$result = preg_replace_callback(
			'#<script\b([^>]*)>(.*?)</script>#is',
			static function ( $m ) use ( $rules, $exclusions ) {
				return self::rewrite_tag( $m[0], $m[1], $m[2], $rules, $exclusions );
			},
			$html
		);

		return null === $result ? $html : $result;
	}

	/**
	 * Reescreve uma tag, se necessário.
	 *
	 * @param string $full       Tag completa.
	 * @param string $attrs      Atributos.
	 * @param string $content    Conteúdo inline.
	 * @param array  $rules      Regras.
	 * @param array  $exclusions Exclusões.
	 * @return string
	 */
	public static function rewrite_tag( $full, $attrs, $content, array $rules, array $exclusions ) {
		if ( false !== stripos( $attrs, 'data-category' ) || false !== stripos( $attrs, 'data-jpx-ignore' ) ) {
			return $full;
		}

		$type = '';
		if ( preg_match( '#\btype\s*=\s*(["\']?)([^"\'\s>]*)\1#i', $attrs, $tm ) ) {
			$type = strtolower( trim( $tm[2] ) );
		}
		if ( ! in_array( $type, self::JS_TYPES, true ) ) {
			return $full;
		}

		$haystack = $attrs . ' ' . $content;

		foreach ( $exclusions as $ex ) {
			if ( '' !== $ex && false !== stripos( $haystack, $ex ) ) {
				return $full;
			}
		}

		foreach ( $rules as $category => $patterns ) {
			foreach ( $patterns as $pattern ) {
				if ( '' !== $pattern && false !== stripos( $haystack, $pattern ) ) {
					$new_attrs = preg_replace( '#\s*\btype\s*=\s*(["\']?)[^"\'\s>]*\1#i', '', $attrs );
					$extra     = ' type="text/plain" data-category="' . esc_attr( $category ) . '" data-jpx-blocked="1"';
					if ( 'module' === $type ) {
						$extra .= ' data-type="module"';
					}
					return '<script' . $extra . rtrim( $new_attrs ) . '>' . $content . '</script>';
				}
			}
		}

		return $full;
	}
}
