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
	const RATE_LIMIT  = 30;  // Requisições por IP...
	const RATE_WINDOW = 600; // ...a cada 10 minutos.

	/** Nome da tabela. */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'jpxcc_consent_log';
	}

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'purge' ) );
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
		if ( self::rate_limited( $ip ) ) {
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
			'user_agent' => JPXCC_Settings::get( 'log_ua' ) && isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '',
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

	private static function rate_limited( $ip ) {
		$key   = 'jpxcc_rl_' . md5( $ip . wp_salt( 'nonce' ) );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT ) {
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

	/** Exclui registros mais antigos que o prazo de retenção. */
	public static function purge() {
		global $wpdb;
		$days  = (int) JPXCC_Settings::get( 'retention_days', 730 );
		$limit = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $limit ) );
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
	 * Lista paginada.
	 *
	 * @param int    $page     Página (1+).
	 * @param int    $per_page Itens por página.
	 * @param string $search   ID de consentimento (parcial).
	 * @return array{rows: array, total: int}
	 */
	public static function query( $page = 1, $per_page = 50, $search = '' ) {
		global $wpdb;
		$table  = self::table();
		$offset = max( 0, ( $page - 1 ) * $per_page );

		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE consent_id LIKE %s", $like ) );
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE consent_id LIKE %s ORDER BY id DESC LIMIT %d OFFSET %d", $like, $per_page, $offset ), ARRAY_A );
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );
			// phpcs:enable
		}
		return array(
			'rows'  => (array) $rows,
			'total' => $total,
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
		fputcsv( $out, array( 'id', 'consent_id', 'acao', 'categorias', 'revisao', 'ip', 'user_agent', 'pagina', 'data_utc' ), ';' );

		$last = PHP_INT_MAX;
		do {
			if ( '' !== $search ) {
				$like = '%' . $wpdb->esc_like( $search ) . '%';
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
