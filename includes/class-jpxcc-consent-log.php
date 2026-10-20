<?php
/**
 * Registro de consentimentos (prova de consentimento — LGPD art. 8º, §2º).
 *
 * @package JPX_Cookies_LGPD
 */

defined( 'ABSPATH' ) || exit;

class JPXCC_Consent_Log {

	const CRON_HOOK   = 'jpxcc_purge_consent_log';
	const REST_NS     = 'jpx-cookies/v1';
	const ACTIONS     = array( 'accept_all', 'reject_all', 'custom', 'update' );
	// Limites anti-abuso por janela de 10 minutos. O teto por IP é alto porque, no
	// celular, muitos visitantes saem pelo mesmo IP da operadora (CGNAT); o limite
	// apertado é por ID de consentimento (um mesmo navegador repetindo envios).
	const RATE_LIMIT_IP = 300;
	const RATE_LIMIT_ID = 10;
	const RATE_WINDOW   = 600;
	const PURGE_BATCH = 5000; // Linhas apagadas por lote na limpeza.
	const PURGE_MAX   = 40;   // Lotes por execução (o resto continua em 5 minutos).

	/** Nome da tabela. */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'jpxcc_consent_log';
	}

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'purge' ) );
		add_action( self::CRON_HOOK . '_continue', array( __CLASS__, 'purge' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
	}

	/** Ativação: cria a tabela e agenda a limpeza diária. */
	public static function activate() {
		self::install();
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_clear_scheduled_hook( self::CRON_HOOK . '_continue' );
	}

	/** Garante tabela e cron (ex.: após atualização ou multisite). */
	public static function maybe_install() {
		if ( get_option( 'jpxcc_db_version' ) !== JPXCC_DB_VERSION ) {
			self::install();
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			consent_id CHAR(36) NOT NULL,
			action VARCHAR(20) NOT NULL,
			categories VARCHAR(191) NOT NULL DEFAULT '',
			revision SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			ip VARCHAR(45) NOT NULL DEFAULT '',
			user_agent VARCHAR(255) NOT NULL DEFAULT '',
			page VARCHAR(255) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY consent_id (consent_id),
			KEY created_at (created_at),
			KEY action_created (action, created_at)
		) {$charset};";

		dbDelta( $sql );
		update_option( 'jpxcc_db_version', JPXCC_DB_VERSION, false );
	}

	public static function register_routes() {
		register_rest_route(
			self::REST_NS,
			'/consent',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_log' ),
				// Endpoint público: a página costuma vir do cache, então não há nonce válido.
				// Proteções: validação estrita, checagem de origem, rate limit e deduplicação.
				'permission_callback' => '__return_true',
				'args'                => array(
					'consent_id' => array(
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => static function ( $v ) {
							return is_string( $v ) && (bool) preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $v );
						},
					),
					'action'     => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => self::ACTIONS,
					),
					'categories' => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array( 'type' => 'string' ),
					),
					'revision'   => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'page'       => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * Recebe o registro do navegador.
	 *
	 * @param WP_REST_Request $request Requisição.
	 * @return WP_REST_Response
	 */
	public static function rest_log( WP_REST_Request $request ) {
		nocache_headers();

		if ( ! JPXCC_Settings::get( 'log_enabled' ) ) {
			return new WP_REST_Response( null, 204 );
		}

		if ( ! self::same_origin( $request ) ) {
			return new WP_REST_Response( array( 'error' => 'origin' ), 403 );
		}

		$ip = self::client_ip();
		if ( self::rate_limited( 'ip' . $ip, self::RATE_LIMIT_IP ) || self::rate_limited( 'id' . strtolower( $request['consent_id'] ), self::RATE_LIMIT_ID ) ) {
			return new WP_REST_Response( array( 'error' => 'rate_limited' ), 429 );
		}

		$allowed    = array_merge( array( 'necessary' ), JPXCC_Settings::active_optional_categories() );
		$categories = array_values( array_intersect( $allowed, array_map( 'strval', (array) $request['categories'] ) ) );
		if ( ! in_array( 'necessary', $categories, true ) ) {
			array_unshift( $categories, 'necessary' );
		}

		$page = (string) wp_parse_url( (string) $request['page'], PHP_URL_PATH );

		$row = array(
			'consent_id' => strtolower( $request['consent_id'] ),
			'action'     => $request['action'],
			'categories' => implode( ',', $categories ),
			'revision'   => max( 0, min( 65535, (int) $request['revision'] ) ),
			'ip'         => 'anon' === JPXCC_Settings::get( 'log_ip' ) && $ip ? wp_privacy_anonymize_ip( $ip ) : '',
			'user_agent' => JPXCC_Settings::get( 'log_ua' ) && isset( $_SERVER['HTTP_USER_AGENT'] ) ? self::summarize_ua( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '',
			'page'       => substr( sanitize_text_field( $page ), 0, 255 ),
			'created_at' => gmdate( 'Y-m-d H:i:s' ),
		);

		if ( self::is_duplicate( $row ) ) {
			return new WP_REST_Response( array( 'ok' => true, 'duplicate' => true ), 200 );
		}

		global $wpdb;
		$wpdb->insert( self::table(), $row, array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		/**
		 * Disparado após registrar um consentimento.
		 *
		 * @param array $row Linha gravada.
		 */
		do_action( 'jpxcc_consent_logged', $row );

		return new WP_REST_Response( array( 'ok' => true ), 201 );
	}

	/** Rejeita chamadas vindas de outros domínios (quando o navegador informa a origem). */
	private static function same_origin( WP_REST_Request $request ) {
		$origin = $request->get_header( 'origin' );
		if ( ! $origin ) {
			return true;
		}
		$origin_host = wp_parse_url( $origin, PHP_URL_HOST );
		$home_host   = wp_parse_url( home_url(), PHP_URL_HOST );
		$site_host   = wp_parse_url( site_url(), PHP_URL_HOST );
		return $origin_host && ( strcasecmp( $origin_host, $home_host ) === 0 || strcasecmp( $origin_host, $site_host ) === 0 );
	}

	/** IP do visitante (considera Cloudflare). Filtrável via `jpxcc_client_ip`. */
	public static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$cf = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			if ( filter_var( $cf, FILTER_VALIDATE_IP ) ) {
				$ip = $cf;
			}
		}
		$ip = apply_filters( 'jpxcc_client_ip', $ip );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Conta uma requisição para a chave e diz se o limite foi excedido.
	 *
	 * @param string $subject IP ou ID de consentimento (com prefixo).
	 * @param int    $limit   Máximo na janela.
	 * @return bool
	 */
	private static function rate_limited( $subject, $limit ) {
		$key   = 'jpxcc_rl_' . md5( $subject . wp_salt( 'nonce' ) );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return true;
		}
		set_transient( $key, $count + 1, self::RATE_WINDOW );
		return false;
	}

	/** Evita linhas repetidas (duplo clique, duas abas). */
	private static function is_duplicate( array $row ) {
		global $wpdb;
		$table = self::table();
		$since = gmdate( 'Y-m-d H:i:s', time() - 10 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$found = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE consent_id = %s AND action = %s AND categories = %s AND created_at >= %s LIMIT 1", $row['consent_id'], $row['action'], $row['categories'], $since ) );
		return ! empty( $found );
	}

	/**
	 * Resume o user agent em "Navegador versão / Sistema" (ex.: "Chrome 141 / Android").
	 * Guarda o suficiente para a prova e ocupa bem menos espaço que o texto completo.
	 *
	 * @param string $ua User agent.
	 * @return string
	 */
	public static function summarize_ua( $ua ) {
		$ua = (string) $ua;
		if ( '' === trim( $ua ) ) {
			return '';
		}

		$browsers = array(
			'Edge'             => '#Edg(?:e|A|iOS)?/(\d+)#',
			'Opera'            => '#(?:OPR|OPiOS)/(\d+)#',
			'Samsung Internet' => '#SamsungBrowser/(\d+)#',
			'Firefox'          => '#(?:Firefox|FxiOS)/(\d+)#',
			'Chrome'           => '#(?:Chrome|CriOS)/(\d+)#',
			'Safari'           => '#Version/(\d+)[\d.]* (?:Mobile/\S+ )?Safari/#',
		);
		$browser = __( 'Outro navegador', 'jpx-cookies-lgpd' );
		foreach ( $browsers as $name => $re ) {
			if ( preg_match( $re, $ua, $m ) ) {
				$browser = $name . ' ' . $m[1];
				break;
			}
		}
		if ( preg_match( '#bot|crawler|spider|crawling#i', $ua ) ) {
			$browser = __( 'Robô', 'jpx-cookies-lgpd' );
		}

		$systems = array(
			'Android' => '#Android#',
			'iOS'     => '#iPhone|iPad|iPod#',
			'Windows' => '#Windows#',
			'macOS'   => '#Mac OS X|Macintosh#',
			'ChromeOS' => '#CrOS#',
			'Linux'   => '#Linux#',
		);
		$system = __( 'outro sistema', 'jpx-cookies-lgpd' );
		foreach ( $systems as $name => $re ) {
			if ( preg_match( $re, $ua ) ) {
				$system = $name;
				break;
			}
		}

		return substr( $browser . ' / ' . $system, 0, 60 );
	}

	/**
	 * Exclui registros mais antigos que o prazo de retenção, em lotes, para não
	 * travar a tabela. Se sobrar muita coisa (ex.: retenção reduzida de uma vez),
	 * agenda a continuação para daqui a 5 minutos.
	 *
	 * @return int Linhas excluídas nesta execução.
	 */
	public static function purge() {
		global $wpdb;
		$days    = (int) JPXCC_Settings::get( 'retention_days', 730 );
		$limit   = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$table   = self::table();
		$deleted = 0;

		for ( $i = 0; $i < self::PURGE_MAX; $i++ ) {
			// Maior id do lote: apagar por faixa de id é rápido (chave primária) e portátil.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$max_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(id) FROM (SELECT id FROM {$table} WHERE created_at < %s ORDER BY id ASC LIMIT %d) AS lote", $limit, self::PURGE_BATCH ) );
			if ( ! $max_id ) {
				return $deleted;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$deleted += (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id <= %d AND created_at < %s", $max_id, $limit ) );
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK . '_continue' ) ) {
			wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, self::CRON_HOOK . '_continue' );
		}
		return $deleted;
	}

	/**
	 * Tamanho aproximado da tabela (linhas e bytes), sem contar linha a linha.
	 *
	 * @return array{rows: int, bytes: int|null, exact: bool}
	 */
	public static function table_info() {
		global $wpdb;
		$table = self::table();

		// MySQL/MariaDB: estatística do próprio banco, instantânea.
		$suppress = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$info = $wpdb->get_row( $wpdb->prepare( 'SELECT TABLE_ROWS AS r, DATA_LENGTH + INDEX_LENGTH AS b FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ), ARRAY_A );
		$wpdb->suppress_errors( $suppress );

		// Estimativa pela faixa de ids (só a chave primária). Serve de reserva quando
		// a estatística do banco está zerada ou desatualizada (tabela recém-criada).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$range    = $wpdb->get_row( "SELECT MIN(id) AS lo, MAX(id) AS hi FROM {$table}", ARRAY_A );
		$by_range = ( $range && $range['hi'] ) ? (int) $range['hi'] - (int) $range['lo'] + 1 : 0;

		$rows  = ( $info && (int) $info['r'] > 0 ) ? (int) $info['r'] : $by_range;
		$bytes = ( $info && (int) $info['b'] > 0 ) ? (int) $info['b'] : null;

		return array(
			'rows'  => $rows,
			'bytes' => $bytes,
			'exact' => false,
		);
	}

	/**
	 * Totais por ação nos últimos N dias (somente primeira escolha de cada visitante).
	 *
	 * @param int $days Dias.
	 * @return array
	 */
	public static function stats( $days = 30 ) {
		global $wpdb;
		$table = self::table();
		$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT action, COUNT(*) AS total FROM {$table} WHERE created_at >= %s GROUP BY action", $since ), ARRAY_A );

		$out = array_fill_keys( self::ACTIONS, 0 );
		foreach ( (array) $rows as $r ) {
			if ( isset( $out[ $r['action'] ] ) ) {
				$out[ $r['action'] ] = (int) $r['total'];
			}
		}

		// Taxa de aceite de estatísticas entre as primeiras escolhas.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$with_analytics = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND action IN ('accept_all','reject_all','custom') AND categories LIKE %s", $since, '%analytics%' ) );

		$first             = $out['accept_all'] + $out['reject_all'] + $out['custom'];
		$out['first']      = $first;
		$out['analytics']  = $with_analytics;
		$out['rate_all']   = $first ? round( 100 * $out['accept_all'] / $first, 1 ) : 0;
		$out['rate_stats'] = $first ? round( 100 * $with_analytics / $first, 1 ) : 0;
		return $out;
	}

	/**
	 * Normaliza a busca: só caracteres de UUID, em minúsculas.
	 *
	 * @param string $search Texto digitado.
	 * @return string
	 */
	public static function normalize_search( $search ) {
		return substr( preg_replace( '/[^0-9a-f\-]/', '', strtolower( (string) $search ) ), 0, 36 );
	}

	/**
	 * Lista paginada por cursor (rápida com milhões de linhas: não usa OFFSET nem COUNT).
	 *
	 * @param array $args {
	 *     @type int    $before   Lista ids menores que este (páginas mais antigas).
	 *     @type int    $after    Lista ids maiores que este (páginas mais recentes).
	 *     @type int    $per_page Itens por página.
	 *     @type string $search   Início do ID de consentimento (usa o índice).
	 * }
	 * @return array{rows: array, has_older: bool, has_newer: bool}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$table  = self::table();
		$per    = max( 1, (int) ( $args['per_page'] ?? 50 ) );
		$before = (int) ( $args['before'] ?? 0 );
		$after  = (int) ( $args['after'] ?? 0 );
		$search = self::normalize_search( $args['search'] ?? '' );

		$where  = array( '1=1' );
		$params = array();
		if ( '' !== $search ) {
			$where[]  = 'consent_id LIKE %s';
			$params[] = $wpdb->esc_like( $search ) . '%';
		}

		$cond = $where;
		if ( $after ) {
			$cond[] = 'id > %d';
			$order  = 'ASC';
			$cparams = array_merge( $params, array( $after ) );
		} else {
			if ( $before ) {
				$cond[] = 'id < %d';
			}
			$order   = 'DESC';
			$cparams = $before ? array_merge( $params, array( $before ) ) : $params;
		}

		$sql  = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $cond ) . " ORDER BY id {$order} LIMIT %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $cparams, array( $per + 1 ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows = (array) $rows;

		$more = count( $rows ) > $per;
		$rows = array_slice( $rows, 0, $per );
		if ( 'ASC' === $order ) {
			$rows = array_reverse( $rows );
		}

		$has_older = $after ? true : $more;
		$has_newer = $after ? $more : (bool) $before;

		// Confirma se há mesmo algo além das bordas (evita botão para página vazia).
		if ( $rows ) {
			$first = (int) $rows[0]['id'];
			$last  = (int) end( $rows )['id'];
			$base  = implode( ' AND ', $where );
			if ( $after ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
				$has_older = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE {$base} AND id < %d LIMIT 1", array_merge( $params, array( $last ) ) ) );
			} elseif ( $before ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
				$has_newer = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE {$base} AND id > %d LIMIT 1", array_merge( $params, array( $first ) ) ) );
			}
		}

		return array(
			'rows'      => $rows,
			'has_older' => $has_older,
			'has_newer' => $has_newer,
		);
	}

	/**
	 * Exclui todos os registros de um ID (pedido de eliminação do titular).
	 *
	 * @param string $consent_id ID.
	 * @return int Linhas excluídas.
	 */
	public static function delete_by_consent_id( $consent_id ) {
		global $wpdb;
		return (int) $wpdb->delete( self::table(), array( 'consent_id' => strtolower( $consent_id ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/** Envia CSV (em blocos, para tabelas grandes). */
	public static function export_csv( $search = '' ) {
		global $wpdb;
		$table = self::table();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=consentimentos-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM para o Excel abrir acentos corretamente.
		fputcsv( $out, array( 'id', 'consent_id', 'acao', 'categorias', 'revisao', 'ip', 'navegador', 'pagina', 'data_utc' ), ';' );

		$last = PHP_INT_MAX;
		do {
			if ( '' !== $search ) {
				$like = $wpdb->esc_like( self::normalize_search( $search ) ) . '%';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE id < %d AND consent_id LIKE %s ORDER BY id DESC LIMIT 2000", $last, $like ), ARRAY_A );
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE id < %d ORDER BY id DESC LIMIT 2000", $last ), ARRAY_A );
			}
			foreach ( (array) $rows as $r ) {
				fputcsv( $out, array_map( array( __CLASS__, 'csv_safe' ), array( $r['id'], $r['consent_id'], $r['action'], $r['categories'], $r['revision'], $r['ip'], $r['user_agent'], $r['page'], $r['created_at'] ) ), ';' );
				$last = (int) $r['id'];
			}
		} while ( ! empty( $rows ) && count( $rows ) === 2000 );

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/** Neutraliza fórmulas (o user agent vem do visitante). */
	public static function csv_safe( $value ) {
		$value = (string) $value;
		return ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) ? "'" . $value : $value;
	}
}
