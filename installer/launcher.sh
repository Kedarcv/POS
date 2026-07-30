#!/bin/bash
# BeamLink POS - macOS Launcher
# Starts MariaDB, PHP, and Caddy, then opens the browser
# Works for both direct install and .app bundle

set -e

APP_NAME="BeamLink POS"

# Detect if running inside .app bundle or direct install
if [[ "$0" == *".app/Contents/MacOS/"* ]]; then
    # Inside .app bundle
    APP_BUNDLE="$(cd "$(dirname "$0")/.." && pwd)"
    INSTALL_DIR="$APP_BUNDLE/Resources"
else
    # Direct install / dev
    INSTALL_DIR="$(cd "$(dirname "$0")" && pwd)"
fi

DATA_DIR="$INSTALL_DIR/data"
LOG_DIR="$DATA_DIR/logs"
PID_DIR="$DATA_DIR/pids"
MARIADB_DIR="$INSTALL_DIR/mariadb"
MARIADB_BIN="$MARIADB_DIR/bin"
PHP_DIR="$INSTALL_DIR/php"
CADDY_DIR="$INSTALL_DIR/caddy"
APP_DIR="$INSTALL_DIR/app"
PORT=8080

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() { echo -e "${GREEN}[$APP_NAME]${NC} $1"; }
warn() { echo -e "${YELLOW}[$APP_NAME]${NC} $1"; }
error() { echo -e "${RED}[$APP_NAME]${NC} $1"; exit 1; }

# Create directories
mkdir -p "$DATA_DIR" "$LOG_DIR" "$PID_DIR" "$DATA_DIR/sessions" "$DATA_DIR/stock"

# Check if already running
check_running() {
    if [ -f "$PID_DIR/mariadb.pid" ]; then
        pid=$(cat "$PID_DIR/mariadb.pid")
        if kill -0 "$pid" 2>/dev/null; then
            return 0
        fi
    fi
    return 1
}

if check_running; then
    warn "$APP_NAME is already running on port $PORT"
    open "http://localhost:$PORT"
    exit 0
fi

# Stop any existing instances
stop_all() {
    log "Stopping services..."
    # Stop Caddy
    if [ -f "$PID_DIR/caddy.pid" ]; then
        pid=$(cat "$PID_DIR/caddy.pid")
        kill "$pid" 2>/dev/null || true
        wait "$pid" 2>/dev/null || true
        rm -f "$PID_DIR/caddy.pid"
    fi
    # Kill PHP
    if [ -f "$PID_DIR/php.pid" ]; then
        pid=$(cat "$PID_DIR/php.pid")
        kill "$pid" 2>/dev/null || true
        wait "$pid" 2>/dev/null || true
        rm -f "$PID_DIR/php.pid"
    fi
    # Kill MariaDB
    if [ -f "$PID_DIR/mariadb.pid" ]; then
        pid=$(cat "$PID_DIR/mariadb.pid")
        kill "$pid" 2>/dev/null || true
        wait "$pid" 2>/dev/null || true
        rm -f "$PID_DIR/mariadb.pid"
    fi
}

trap stop_all EXIT INT TERM

# ============================================
# MARIADB
# ============================================

# Initialize MariaDB if needed
if [ ! -d "$DATA_DIR/db" ] || [ ! -f "$DATA_DIR/db/ibdata1" ]; then
    log "Initializing database..."
    mkdir -p "$DATA_DIR/db"
    "$MARIADB_BIN/mariadb-install-db" \
        --datadir="$DATA_DIR/db" \
        --user="$(whoami)" \
        --basedir="$MARIADB_DIR" \
        > "$LOG_DIR/install_db.log" 2>&1 || true
    log "Database initialized."
fi

# Generate DB password if not exists
if [ ! -f "$DATA_DIR/.db_pass" ]; then
    openssl rand -hex 16 > "$DATA_DIR/.db_pass"
    chmod 600 "$DATA_DIR/.db_pass"
fi
DB_PASS=$(cat "$DATA_DIR/.db_pass")

# Write my.cnf
cat > "$DATA_DIR/my.cnf" << EOF
[mysqld]
port=3307
basedir=$MARIADB_DIR
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

# Start MariaDB
log "Starting MariaDB on port 3307..."
"$MARIADB_BIN/mariadbd" \
    --defaults-file="$DATA_DIR/my.cnf" \
    --console &
MARIADB_PID=$!
echo $MARIADB_PID > "$PID_DIR/mariadb.pid"

# Wait for MariaDB
log "Waiting for MariaDB..."
for i in {1..30}; do
    if "$MARIADB_BIN/mariadb" \
        --socket="$DATA_DIR/mariadb.sock" \
        -u root \
        -e "SELECT 1" > /dev/null 2>&1; then
        break
    fi
    if [ $i -eq 30 ]; then
        error "MariaDB failed to start. Check logs: $LOG_DIR/"
    fi
    sleep 1
done

# Create user and database
"$MARIADB_BIN/mariadb" \
    --socket="$DATA_DIR/mariadb.sock" \
    -u root << EOSQL
CREATE DATABASE IF NOT EXISTS beamlink_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER IF NOT EXISTS 'beamlink'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';
CREATE USER IF NOT EXISTS 'beamlink'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON beamlink_pos.* TO 'beamlink'@'127.0.0.1';
GRANT ALL PRIVILEGES ON beamlink_pos.* TO 'beamlink'@'localhost';
FLUSH PRIVILEGES;
EOSQL

log "MariaDB ready."

# ============================================
# .env FILE
# ============================================

if [ ! -f "$INSTALL_DIR/.env" ]; then
    WEBHOOK_SECRET=$(openssl rand -hex 16)
    ENCRYPTION_KEY=$(openssl rand -hex 32)

    cat > "$INSTALL_DIR/.env" << EOF
CI_ENVIRONMENT = 'production'
app.baseURL = 'http://localhost:$PORT'
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
WHATSAPP_WEBHOOK_URL = 'http://localhost:$PORT/webhook/whatsapp'

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

    log "Configuration created."
fi

# Copy .env to app directory (CI4 reads from there)
cp "$INSTALL_DIR/.env" "$APP_DIR/.env"

# ============================================
# PHP
# ============================================

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

# Start PHP built-in server
log "Starting PHP server on port 9000..."
"$PHP_DIR/php" \
    -S 127.0.0.1:9000 \
    -t "$APP_DIR/public" \
    -c "$DATA_DIR/php.ini" \
    > "$LOG_DIR/php.log" 2>&1 &
PHP_PID=$!
echo $PHP_PID > "$PID_DIR/php.pid"
log "PHP ready."

# ============================================
# CADDY
# ============================================

# Write Caddyfile
cat > "$DATA_DIR/Caddyfile" << EOF
:8080 {
	root * $APP_DIR/public

	php_fastcgi 127.0.0.1:9000 {
		root $APP_DIR/public
	}

	file_server
	encode gzip
}
EOF

# Start Caddy
log "Starting Caddy on port $PORT..."
"$CADDY_DIR/caddy" start \
    --config "$DATA_DIR/Caddyfile" \
    --adapter caddyfile \
    > "$LOG_DIR/caddy.log" 2>&1 &
CADDY_PID=$!
echo $CADDY_PID > "$PID_DIR/caddy.pid"
log "Caddy ready."

# ============================================
# FIRST RUN CHECK
# ============================================

sleep 2

if [ ! -f "$DATA_DIR/.installed" ]; then
    log "First run - opening setup wizard..."
    open "http://localhost:$PORT/setup" 2>/dev/null || osascript -e 'open location "http://localhost:8080/setup"' 2>/dev/null || true
else
    log "$APP_NAME is running at http://localhost:$PORT"
    open "http://localhost:$PORT" 2>/dev/null || osascript -e 'open location "http://localhost:8080"' 2>/dev/null || true
fi

log "Press Ctrl+C to stop all services."
wait
