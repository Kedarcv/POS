#!/bin/bash
# BeamLink POS - Binary Downloader
# Downloads static PHP, MariaDB, and Caddy for Windows (the only installer target)

set -e

INSTALL_DIR="$(cd "$(dirname "$0")" && pwd)"
BIN_DIR="$INSTALL_DIR/bin"
PLATFORM="${1:-windows}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() { echo -e "${GREEN}[Download]${NC} $1"; }
warn() { echo -e "${YELLOW}[Download]${NC} $1"; }
error() { echo -e "${RED}[Download]${NC} $1"; }

mkdir -p "$BIN_DIR"

download_file() {
    local url="$1"
    local output="$2"
    if [ -f "$output" ]; then
        log "Already exists: $output"
        return
    fi
    log "Downloading: $url"
    curl -fsSL -o "$output" "$url"
}

extract_file() {
    local archive="$1"
    local dest="$2"
    log "Extracting to: $dest"
    mkdir -p "$dest"
    case "$archive" in
        *.tar.gz) tar -xzf "$archive" -C "$dest" --strip-components=1 ;;
        *.tgz) tar -xzf "$archive" -C "$dest" --strip-components=1 ;;
        *.zip) unzip -q -o "$archive" -d "$dest" ;;
        *) error "Unknown archive format: $archive" ;;
    esac
}

if [ "$PLATFORM" = "windows" ]; then
    log "=== Downloading Windows x64 binaries ==="

    # PHP 8.2 NTS (NTS = Non-Thread Safe, for CLI/FPM)
    log "--- PHP 8.2 ---"
    download_file \
        "https://windows.php.net/downloads/releases/archives/php-8.2.28-nts-Win32-vs16-x64.zip" \
        "$BIN_DIR/php-win-x64.zip" || \
    download_file \
        "https://windows.php.net/downloads/releases/php-8.2.28-nts-Win32-vs16-x64.zip" \
        "$BIN_DIR/php-win-x64.zip"
    rm -rf "$BIN_DIR/php"
    extract_file "$BIN_DIR/php-win-x64.zip" "$BIN_DIR/php"
    rm -f "$BIN_DIR/php-win-x64.zip"
    
    log "Configuring PHP php.ini..."
    cp "$BIN_DIR/php/php.ini-production" "$BIN_DIR/php/php.ini"
    # Enable extensions directory (Windows uses extension_dir = "ext")
    sed -i 's/; extension_dir = "ext"/extension_dir = "ext"/g' "$BIN_DIR/php/php.ini"
    # Enable required extensions
    sed -i 's/;extension=curl/extension=curl/g' "$BIN_DIR/php/php.ini"
    sed -i 's/;extension=fileinfo/extension=fileinfo/g' "$BIN_DIR/php/php.ini"
    sed -i 's/;extension=gd/extension=gd/g' "$BIN_DIR/php/php.ini"
    sed -i 's/;extension=intl/extension=intl/g' "$BIN_DIR/php/php.ini"
    sed -i 's/;extension=mbstring/extension=mbstring/g' "$BIN_DIR/php/php.ini"
    sed -i 's/;extension=mysqli/extension=mysqli/g' "$BIN_DIR/php/php.ini"
    sed -i 's/;extension=openssl/extension=openssl/g' "$BIN_DIR/php/php.ini"
    sed -i 's/;extension=pdo_mysql/extension=pdo_mysql/g' "$BIN_DIR/php/php.ini"
    sed -i 's/;extension=zip/extension=zip/g' "$BIN_DIR/php/php.ini"

    # Caddy
    log "--- Caddy ---"
    download_file \
        "https://github.com/caddyserver/caddy/releases/download/v2.10.0/caddy_2.10.0_windows_amd64.zip" \
        "$BIN_DIR/caddy-win-x64.zip"
    rm -rf "$BIN_DIR/caddy"
    extract_file "$BIN_DIR/caddy-win-x64.zip" "$BIN_DIR/caddy"
    rm -f "$BIN_DIR/caddy-win-x64.zip"

    # MariaDB
    log "--- MariaDB ---"
    download_file \
        "https://archive.mariadb.org/mariadb-11.4.5/winx64-packages/mariadb-11.4.5-winx64.zip" \
        "$BIN_DIR/mariadb-win-x64.zip"
    rm -rf "$BIN_DIR/mariadb-win-x64-temp" "$BIN_DIR/mariadb"
    extract_file "$BIN_DIR/mariadb-win-x64.zip" "$BIN_DIR/mariadb-win-x64-temp"
    rm -f "$BIN_DIR/mariadb-win-x64.zip"
    mkdir -p "$BIN_DIR/mariadb"
    if [ -d "$BIN_DIR/mariadb-win-x64-temp/mariadb-11.4.5-winx64" ]; then
        cp -R "$BIN_DIR/mariadb-win-x64-temp/mariadb-11.4.5-winx64/"* "$BIN_DIR/mariadb/"
    else
        cp -R "$BIN_DIR/mariadb-win-x64-temp/"* "$BIN_DIR/mariadb/"
    fi
    rm -rf "$BIN_DIR/mariadb-win-x64-temp"

    log "=== Windows x64 binaries downloaded ==="
    log "PHP:  $BIN_DIR/php-win-x64/php.exe"
    log "Caddy: $BIN_DIR/caddy-win-x64/caddy.exe"
    log "MariaDB: $BIN_DIR/mariadb-win-x64/"

else
    error "Unknown platform: $PLATFORM (only windows is supported)"
    exit 1
fi

log "Done!"
