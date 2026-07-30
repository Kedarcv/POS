#!/bin/bash
# BeamLink POS - macOS PKG Installer Builder
# Creates a proper .pkg installer with native macOS UI
# No Docker or Homebrew dependencies - fully self-contained

set -e

INSTALL_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(cd "$INSTALL_DIR/.." && pwd)"
BUILD_DIR="$INSTALL_DIR/build"
APP_NAME="BeamLink POS"
APP_VERSION="3.4.2"
PKG_NAME="BeamLink-POS-${APP_VERSION}"

RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'

log() { echo -e "${GREEN}[PKG]${NC} $1"; }
error() { echo -e "${RED}[PKG]${NC} $1"; exit 1; }

# Clean
rm -rf "$BUILD_DIR"
rm -rf ~/Desktop/"$PKG_NAME.pkg"

# Create staging directory
STAGING="$BUILD_DIR/staging"
mkdir -p "$STAGING/data"
mkdir -p "$STAGING/data/logs"
mkdir -p "$STAGING/data/sessions"
mkdir -p "$STAGING/data/stock"
mkdir -p "$STAGING/data/db"

# ============================================
# COPY BINARIES
# ============================================

log "Copying binaries..."

# PHP (fully static - no dependencies)
if [ ! -d "$INSTALL_DIR/bin/php-mac-arm64" ]; then
    error "Run ./download-binaries.sh mac first!"
fi
mkdir -p "$STAGING/php"
cp "$INSTALL_DIR/bin/php-mac-arm64/php" "$STAGING/php/php"
chmod +x "$STAGING/php/php"

# MariaDB (only use mariadbd - fully static, skip mysql/mariadb clients which need gnutls)
if [ ! -d "$INSTALL_DIR/bin/mariadb-mac-arm64" ]; then
    error "Run MariaDB binaries first!"
fi
mkdir -p "$STAGING/mariadb/bin"
mkdir -p "$STAGING/mariadb/scripts"
cp "$INSTALL_DIR/bin/mariadb-mac-arm64/bin/mariadbd" "$STAGING/mariadb/bin/"
cp "$INSTALL_DIR/bin/mariadb-mac-arm64/scripts/mariadb-install-db" "$STAGING/mariadb/scripts/"
chmod +x "$STAGING/mariadb/bin/"* "$STAGING/mariadb/scripts/"* 2>/dev/null || true

# Caddy (fully static)
if [ ! -d "$INSTALL_DIR/bin/caddy-mac-arm64" ]; then
    error "Run ./download-binaries.sh mac first!"
fi
mkdir -p "$STAGING/caddy"
cp "$INSTALL_DIR/bin/caddy-mac-arm64/caddy" "$STAGING/caddy/caddy"
chmod +x "$STAGING/caddy/caddy"

# ============================================
# COPY APP FILES
# ============================================

log "Copying application..."
mkdir -p "$STAGING/app"

rsync -a --exclude='.git' \
          --exclude='node_modules' \
          --exclude='vendor' \
          --exclude='build' \
          --exclude='.env' \
          --exclude='installer' \
          --exclude='tests' \
          "$ROOT_DIR/" "$STAGING/app/"

# Install composer deps
log "Installing composer dependencies..."
cd "$STAGING/app"
if [ -f "$ROOT_DIR/composer.json" ]; then
    cp "$ROOT_DIR/composer.json" "$ROOT_DIR/composer.lock" "$STAGING/app/" 2>/dev/null || true
    if command -v php &> /dev/null; then
        php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" 2>/dev/null || true
        php composer-setup.php --quiet 2>/dev/null || true
        php composer.phar install --no-dev --optimize-autoloader 2>/dev/null || true
        rm -f composer-setup.php composer.phar
    fi
fi

# Copy compiled assets
if [ -d "$ROOT_DIR/public/dist" ]; then
    mkdir -p "$STAGING/app/public/dist"
    cp -R "$ROOT_DIR/public/dist/"* "$STAGING/app/public/dist/"
fi

# ============================================
# COPY STOCK DATA
# ============================================

log "Copying stock data..."
mkdir -p "$STAGING/app/stock-data"
if [ -f "$ROOT_DIR/stock-data/STOCK.XLS" ]; then
    cp "$ROOT_DIR/stock-data/STOCK.XLS" "$STAGING/app/stock-data/"
fi
if [ -f "$ROOT_DIR/stock-data/STOCKQTY.XLS" ]; then
    cp "$ROOT_DIR/stock-data/STOCKQTY.XLS" "$STAGING/app/stock-data/"
fi
cp "$ROOT_DIR/stock-data/STOCK.XLS" "$STAGING/data/stock/" 2>/dev/null || true

# ============================================
# COPY CONFIG + SCRIPTS
# ============================================

log "Creating installer scripts..."

# Create scripts directory
SCRIPTS_DIR="$BUILD_DIR/scripts"
mkdir -p "$SCRIPTS_DIR"

# Preinstall - stops old services
cat > "$SCRIPTS_DIR/preinstall" << 'PREINSTALL'
#!/bin/bash
INSTALL_DIR="/Applications/BeamLink POS"
DATA_DIR="$INSTALL_DIR/data"

# Stop existing services
if [ -f "$DATA_DIR/pids/mariadb.pid" ]; then
    pid=$(cat "$DATA_DIR/pids/mariadb.pid")
    kill "$pid" 2>/dev/null || true
fi
if [ -f "$DATA_DIR/pids/php.pid" ]; then
    pid=$(cat "$DATA_DIR/pids/php.pid")
    kill "$pid" 2>/dev/null || true
fi
if [ -f "$INSTALL_DIR/caddy/caddy" ]; then
    "$INSTALL_DIR/caddy/caddy" stop --config "$DATA_DIR/Caddyfile" --adapter caddyfile 2>/dev/null || true
fi

# Backup existing data
if [ -f "$INSTALL_DIR/.env" ]; then
    cp "$INSTALL_DIR/.env" /tmp/beamlink-pos-env-backup 2>/dev/null || true
fi
if [ -d "$DATA_DIR" ]; then
    mkdir -p /tmp/beamlink-pos-data-backup
    cp -R "$DATA_DIR" /tmp/beamlink-pos-data-backup/ 2>/dev/null || true
fi

exit 0
PREINSTALL
chmod +x "$SCRIPTS_DIR/preinstall"

# Postinstall - initializes everything using PHP (no MariaDB client needed)
cat > "$SCRIPTS_DIR/postinstall" << 'POSTINSTALL'
#!/bin/bash
INSTALL_DIR="/Applications/BeamLink POS"
DATA_DIR="$INSTALL_DIR/data"
LOG_DIR="$DATA_DIR/logs"
PHP="$INSTALL_DIR/php/php"
MARIADBD="$INSTALL_DIR/mariadb/bin/mariadbd"
SETUP_LOG="/tmp/beamlink-pos-install.log"

exec > "$SETUP_LOG" 2>&1

echo "=== BeamLink POS Installation ==="
echo "Starting setup..."

# Create directories
mkdir -p "$DATA_DIR" "$LOG_DIR" "$DATA_DIR/sessions" "$DATA_DIR/pids" "$DATA_DIR/stock" "$DATA_DIR/db"

# Generate DB password
DB_PASS=$(openssl rand -hex 16)
echo "$DB_PASS" > "$DATA_DIR/.db_pass"
chmod 600 "$DATA_DIR/.db_pass"

# Create my.cnf
cat > "$DATA_DIR/my.cnf" << EOF
[mysqld]
port=3307
basedir=$INSTALL_DIR/mariadb
datadir=$DATA_DIR/db
socket=$DATA_DIR/mariadb.sock
skip-name-resolve
innodb_buffer_pool_size=64M
innodb_log_file_size=8M
max_allowed_packet=64M
character-set-server=utf8mb4
collation-server=utf8mb4_general_ci
bind-address=127.0.0.1

[client]
port=3307
socket=$DATA_DIR/mariadb.sock
default-character-set=utf8mb4
EOF

# Create php.ini
cat > "$DATA_DIR/php.ini" << EOF
memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 100M
post_max_size = 100M
max_input_time = 300
error_reporting = E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED
display_errors = Off
log_errors = On
error_log = $LOG_DIR/php_error.log
session.save_path = $DATA_DIR/sessions
date.timezone = Africa/Harare
extension=mysqli
extension=pdo_mysql
extension=gd
extension=zip
extension=mbstring
extension=curl
extension=openssl
EOF

# Create Caddyfile
cat > "$DATA_DIR/Caddyfile" << EOF
:8080 {
    root * $INSTALL_DIR/app/public

    php_fastcgi 127.0.0.1:9000 {
        root $INSTALL_DIR/app/public
    }

    file_server
    encode gzip
}
EOF

# Create .env
ENCRYPTION_KEY=$(openssl rand -hex 32)
WEBHOOK_SECRET=$(openssl rand -hex 16)

cat > "$DATA_DIR/.env" << EOF
CI_ENVIRONMENT = 'production'
app.baseURL = 'http://localhost:8080'
app.allowedHostnames = 'localhost'

database.default.hostname = '127.0.0.1'
database.default.database = 'beamlink_pos'
database.default.username = 'beamlink'
database.default.password = '$DB_PASS'
database.default.DBDriver = 'MySQLi'
database.default.DBPrefix = 'ospos_'
database.default.port = 3307

encryption.key = '$ENCRYPTION_KEY'

logger.threshold = 4
app.db_log_enabled = false

honeypot.hidden = true
honeypot.label = 'Fill This Field'
honeypot.name = 'honeypot'
honeypot.template = '<label>{label}</label><input type="text" name="{name}" value="">'
honeypot.container = '<div style="display:none">{template}</div>'

BEAMLINK_WEBHOOK_URL = 'https://beamlink-erp.vercel.app/api/webhooks/whatsapp'
BEAMLINK_API_URL = 'https://beamlink-erp.vercel.app'
BEAMLINK_API_KEY = 'beamlink-pos-local-2026'
BEAMLINK_SYNC_SECRET = 'beamlink-pos-local-2026'

WHATSAPP_ACCESS_TOKEN = 'EAAHQnEhuTywBRHeJVQJYReR2xwWlBgZBBF6luqgSKBDZAlZCt4WpsKPUfJNSpAf6VxLlCdYHgJXL7UjNqZCHN4BqkyS0dMZCjUB18x1KPx73RDKCoXuFcZB01xzMs7ZAoudgM7EI6wf2yRsmdnCb4BJR7SlqS51CCWuuyRitCcZCzXanjgB9QMPm3wRuw5eUzBOAXwZDZD'
WHATSAPP_PHONE_NUMBER_ID = '1122313694297443'
WHATSAPP_BUSINESS_ACCOUNT_ID = '998404866041041'
WHATSAPP_VERIFY_TOKEN = 'beamlink-erp-webhook-2026'
WHATSAPP_BOT_NUMBER = '+263717174443'
WHATSAPP_WEBHOOK_URL = 'http://localhost:8080/webhook/whatsapp'

PESEPAY_INTEGRATION_KEY = '8246a8cd-72b7-45fa-81d8-381b757f1179'
PESEPAY_ENCRYPTION_KEY = 'cddedb32a9bf49e7b832efe4b2b1a5a3'
PESEPAY_ECOCASH_CODE = 'PZW211'
PESEPAY_ONEMONEY_CODE = 'ONEMONEY'
PESEPAY_CURRENCY_CODE = 'USD'

PAYNOW_INTEGRATION_ID = '24171'
PAYNOW_INTEGRATION_KEY = '2427ae68-0833-46f0-8eba-81dfdfa61de3'

ECOCASH_API_KEY = ''
ECOCASH_MODE = 'sandbox'

OSPOS_WEBHOOK_SECRET = '$WEBHOOK_SECRET'

appTimezone = 'Africa/Harare'
EOF

cp "$DATA_DIR/.env" "$INSTALL_DIR/app/.env"

# ============================================
# INITIALIZE MARIADB
# ============================================
echo "[1/3] Initializing MariaDB..."

# Initialize data directory
"$INSTALL_DIR/mariadb/scripts/mariadb-install-db" \
    --datadir="$DATA_DIR/db" \
    --user="$(whoami)" \
    --basedir="$INSTALL_DIR/mariadb" > "$LOG_DIR/install_db.log" 2>&1

# Start MariaDB
echo "  Starting MariaDB..."
"$MARIADBD" --defaults-file="$DATA_DIR/my.cnf" --console &
MARIADB_PID=$!

# Wait for MariaDB
for i in {1..30}; do
    if "$PHP" -r "
        try {
            \$pdo = new PDO('mysql:host=127.0.0.1;port=3307', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            echo 'connected';
        } catch (Exception \$e) {
            echo 'waiting';
        }
    " 2>/dev/null | grep -q "connected"; then
        break
    fi
    sleep 1
done

# Create database and user using PHP PDO
echo "  Creating database and user..."
"$PHP" -r "
    try {
        \$pdo = new PDO('mysql:host=127.0.0.1;port=3307', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        \$pdo->exec('CREATE DATABASE IF NOT EXISTS beamlink_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
        \$pdo->exec(\"CREATE USER IF NOT EXISTS 'beamlink'@'127.0.0.1' IDENTIFIED BY '$DB_PASS'\");
        \$pdo->exec(\"CREATE USER IF NOT EXISTS 'beamlink'@'localhost' IDENTIFIED BY '$DB_PASS'\");
        \$pdo->exec(\"GRANT ALL PRIVILEGES ON beamlink_pos.* TO 'beamlink'@'127.0.0.1'\");
        \$pdo->exec(\"GRANT ALL PRIVILEGES ON beamlink_pos.* TO 'beamlink'@'localhost'\");
        \$pdo->exec('FLUSH PRIVILEGES');
        echo 'Database and user created successfully\n';
    } catch (Exception \$e) {
        echo 'Error: ' . \$e->getMessage() . '\n';
        exit(1);
    }
"

# ============================================
# RUN MIGRATIONS + SEED STOCK
# ============================================
echo "[2/3] Running migrations..."

cd "$INSTALL_DIR/app"
"$PHP" spark migrate --all 2>&1 | tail -20

echo "[3/3] Seeding stock data..."
if [ -f "$INSTALL_DIR/app/stock-data/STOCK.XLS" ]; then
    "$PHP" spark stock:import 2>&1 | tail -10
fi

# Stop MariaDB
echo "  Stopping MariaDB..."
"$PHP" -r "
    try {
        \$pdo = new PDO('mysql:host=127.0.0.1;port=3307', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        \$pdo->exec('SHUTDOWN');
    } catch (Exception \$e) {}
" 2>/dev/null || true

sleep 2

# Restore backup if upgrading
if [ -f /tmp/beamlink-pos-env-backup ]; then
    cp /tmp/beamlink-pos-env-backup "$DATA_DIR/.env" 2>/dev/null || true
    cp "$DATA_DIR/.env" "$INSTALL_DIR/app/.env"
fi

# Mark as installed
date '+%Y-%m-%d %H:%M:%S' > "$DATA_DIR/.installed"

# ============================================
# CREATE .APP BUNDLE
# ============================================
echo "Creating BeamLink POS.app..."
APP_BUNDLE="/Applications/BeamLink POS.app"
rm -rf "$APP_BUNDLE"
mkdir -p "$APP_BUNDLE/Contents/MacOS"
mkdir -p "$APP_BUNDLE/Contents/Resources"

cat > "$APP_BUNDLE/Contents/MacOS/BeamLink POS" << 'APPLAUNCHER'
#!/bin/bash
INSTALL_DIR="/Applications/BeamLink POS"
DATA_DIR="$INSTALL_DIR/data"
PORT=8080

mkdir -p "$DATA_DIR/pids" "$DATA_DIR/logs"

if [ -f "$DATA_DIR/pids/mariadb.pid" ]; then
    pid=$(cat "$DATA_DIR/pids/mariadb.pid")
    if kill -0 "$pid" 2>/dev/null; then
        open "http://localhost:$PORT"
        exit 0
    fi
fi

osascript -e 'display notification "Starting BeamLink POS..." with title "BeamLink POS"'

"$INSTALL_DIR/mariadb/bin/mariadbd" --defaults-file="$DATA_DIR/my.cnf" --console &>/dev/null &
echo $! > "$DATA_DIR/pids/mariadb.pid"

for i in {1..30}; do
    if "$INSTALL_DIR/php/php" -r "
        try { new PDO('mysql:host=127.0.0.1;port=3307', 'root', ''); exit(0); } catch (Exception \$e) { exit(1); }
    " 2>/dev/null; then
        break
    fi
    sleep 1
done

DB_PASS=$(cat "$DATA_DIR/.db_pass" 2>/dev/null || echo "")
if [ -n "$DB_PASS" ]; then
    "$INSTALL_DIR/php/php" -r "
        try {
            \$pdo = new PDO('mysql:host=127.0.0.1;port=3307', 'root', '');
            \$pdo->exec(\"CREATE USER IF NOT EXISTS 'beamlink'@'127.0.0.1' IDENTIFIED BY '$DB_PASS'\");
            \$pdo->exec(\"CREATE USER IF NOT EXISTS 'beamlink'@'localhost' IDENTIFIED BY '$DB_PASS'\");
            \$pdo->exec(\"GRANT ALL PRIVILEGES ON beamlink_pos.* TO 'beamlink'@'127.0.0.1'\");
            \$pdo->exec(\"GRANT ALL PRIVILEGES ON beamlink_pos.* TO 'beamlink'@'localhost'\");
            \$pdo->exec('FLUSH PRIVILEGES');
        } catch (Exception \$e) {}
    " 2>/dev/null || true
fi

if [ -f "$DATA_DIR/.env" ]; then
    cp "$DATA_DIR/.env" "$INSTALL_DIR/app/.env"
fi

"$INSTALL_DIR/php/php" -S 127.0.0.1:9000 -t "$INSTALL_DIR/app/public" -c "$DATA_DIR/php.ini" &>/dev/null &
echo $! > "$DATA_DIR/pids/php.pid"

"$INSTALL_DIR/caddy/caddy" start --config "$DATA_DIR/Caddyfile" --adapter caddyfile &>/dev/null &
echo $! > "$DATA_DIR/pids/caddy.pid"

sleep 2

if [ ! -f "$DATA_DIR/.installed" ]; then
    open "http://localhost:$PORT/setup"
else
    open "http://localhost:$PORT"
fi
APPLAUNCHER
chmod +x "$APP_BUNDLE/Contents/MacOS/BeamLink POS"

cat > "$APP_BUNDLE/Contents/Info.plist" << 'PLIST'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>CFBundleName</key>
    <string>BeamLink POS</string>
    <key>CFBundleDisplayName</key>
    <string>BeamLink POS</string>
    <key>CFBundleIdentifier</key>
    <string>com.secondspark.beamlink-pos</string>
    <key>CFBundleVersion</key>
    <string>3.4.2</string>
    <key>CFBundleShortVersionString</key>
    <string>3.4.2</string>
    <key>CFBundlePackageType</key>
    <string>APPL</string>
    <key>CFBundleExecutable</key>
    <string>BeamLink POS</string>
    <key>LSMinimumSystemVersion</key>
    <string>11.0</string>
    <key>NSHighResolutionCapable</key>
    <true/>
    <key>LSApplicationCategoryType</key>
    <string>public.app-category.business</string>
    <key>NSHumanReadableCopyright</key>
    <string>Second Spark Intelligence © 2026</string>
</dict>
</plist>
PLIST

# Cleanup
rm -rf /tmp/beamlink-pos-env-backup /tmp/beamlink-pos-data-backup

echo "=== Installation Complete ==="
echo "Launch BeamLink POS from Applications folder."
exit 0
POSTINSTALL
chmod +x "$SCRIPTS_DIR/postinstall"

# ============================================
# BUILD PKG
# ============================================

log "Building PKG..."

pkgbuild \
    --root "$STAGING" \
    --identifier "com.secondspark.beamlink-pos" \
    --version "$APP_VERSION" \
    --scripts "$SCRIPTS_DIR" \
    --install-location "/Applications/BeamLink POS" \
    "$BUILD_DIR/$PKG_NAME.pkg"

productbuild \
    --package "$BUILD_DIR/$PKG_NAME.pkg" \
    "$BUILD_DIR/$PKG_NAME-final.pkg"

mv "$BUILD_DIR/$PKG_NAME-final.pkg" ~/Desktop/ 2>/dev/null || true

PKG_SIZE=$(du -h ~/Desktop/"$PKG_NAME.pkg" | cut -f1)

log "========================================"
log "PKG BUILD COMPLETE"
log "PKG: ~/Desktop/$PKG_NAME.pkg"
log "Size: $PKG_SIZE"
log "========================================"
