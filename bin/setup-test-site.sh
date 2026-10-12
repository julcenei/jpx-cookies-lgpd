#!/usr/bin/env bash
# Monta um WordPress local com SQLite para os testes de navegador.
# Uso: bin/setup-test-site.sh [pasta] [porta]
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SITE="${1:-$ROOT/.test-site}"
PORT="${2:-8099}"
WP_VERSION="${WP_VERSION:-6.8.1}"
TMP="$(mktemp -d)"

command -v php >/dev/null || { echo "PHP não encontrado."; exit 1; }
php -m | grep -qi pdo_sqlite || { echo "A extensão pdo_sqlite do PHP é necessária."; exit 1; }

if [ -e "$SITE" ]; then
	echo "A pasta $SITE já existe. Apague-a para recriar o site de testes."
	exit 1
fi

echo "→ Baixando WordPress $WP_VERSION e a integração SQLite…"
git clone -q --depth 1 --branch "$WP_VERSION" https://github.com/WordPress/WordPress "$TMP/wp"
git clone -q --depth 1 https://github.com/WordPress/sqlite-database-integration "$TMP/sqlite"

cp -r "$TMP/wp" "$SITE"
rm -rf "$SITE/.git"

SQL="$SITE/wp-content/plugins/sqlite-database-integration"
cp -rL "$TMP/sqlite/packages/plugin-sqlite-database-integration" "$SQL"
sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$SQL#" \
    -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#g" \
    "$SQL/db.copy" > "$SITE/wp-content/db.php"
mkdir -p "$SITE/wp-content/database" "$SITE/wp-content/mu-plugins"

cat > "$SITE/wp-config.php" <<'PHP'
<?php
define( 'DB_NAME', 'wp' ); define( 'DB_USER', '' ); define( 'DB_PASSWORD', '' ); define( 'DB_HOST', '' );
define( 'DB_CHARSET', 'utf8' ); define( 'DB_COLLATE', '' );
define( 'DB_DIR', __DIR__ . '/wp-content/database/' ); define( 'DB_FILE', '.ht.sqlite' );
foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as $k ) { define( $k, 'jpxcc-test-' . $k ); }
$table_prefix = 'wp_';
define( 'WP_DEBUG', true ); define( 'WP_DEBUG_LOG', true ); define( 'WP_DEBUG_DISPLAY', false );
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
require_once ABSPATH . 'wp-settings.php';
PHP

ln -s "$ROOT" "$SITE/wp-content/plugins/jpx-cookies-lgpd"
cp "$ROOT/tests/fixtures/test-scripts.php" "$SITE/wp-content/mu-plugins/"

echo "→ Instalando o WordPress e ativando o plugin…"
php "$ROOT/tests/helpers/install.php" "$SITE" "localhost:$PORT"
php "$ROOT/tests/helpers/setup.php" "$SITE"

rm -rf "$TMP"
echo
echo "Pronto. Inicie o servidor com:"
echo "  php -S localhost:$PORT -t \"$SITE\""
echo "e rode os testes com: npm test"
