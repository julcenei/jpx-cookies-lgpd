<?php
/**
 * Teste do resumo de navegador. Uso: php tests/unit/ua-summary.php <pasta-do-site>
 *
 * @package JPX_Cookies_LGPD
 */

require __DIR__ . '/../helpers/_boot.php';

$jpxcc_cases = array(
	'Mozilla/5.0 (Linux; Android 14; SM-A546E) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36' => 'Chrome 141 / Android',
	'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1' => 'Safari 18 / iOS',
	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0' => 'Edge 141 / Windows',
	'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:143.0) Gecko/20100101 Firefox/143.0' => 'Firefox 143 / macOS',
	'Mozilla/5.0 (Linux; Android 14; SAMSUNG SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/28.0 Chrome/130.0.0.0 Mobile Safari/537.36' => 'Samsung Internet 28 / Android',
	'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/141.0.7390.41 Mobile/15E148 Safari/604.1' => 'Chrome 141 / iOS',
	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 OPR/122.0.0.0' => 'Opera 122 / Windows',
	'Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36' => 'Chrome 141 / ChromeOS',
	'curl/8.5.0' => 'Outro navegador / outro sistema',
	'' => '',
);

$jpxcc_fail = 0;
foreach ( $jpxcc_cases as $jpxcc_ua => $jpxcc_expected ) {
	$jpxcc_got = JPXCC_Consent_Log::summarize_ua( $jpxcc_ua );
	$jpxcc_ok  = $jpxcc_got === $jpxcc_expected;
	$jpxcc_fail += $jpxcc_ok ? 0 : 1;
	printf( "%s  %s%s\n", $jpxcc_ok ? 'PASS' : 'FAIL', $jpxcc_expected ? $jpxcc_expected : '(vazio)', $jpxcc_ok ? '' : "  — obtido: {$jpxcc_got}" );
}
printf( "\n%d/%d ok\n", count( $jpxcc_cases ) - $jpxcc_fail, count( $jpxcc_cases ) );
exit( $jpxcc_fail ? 1 : 0 );
