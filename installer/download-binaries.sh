#!/bin/bash
# BeamLink POS - Binary Downloader
# Downloads static PHP, MariaDB, and Caddy for the target platform

set -e

INSTALL_DIR="$(cd "$(dirname "$0")" && pwd)"
BIN_DIR="$INSTALL_DIR/bin"
PLATFORM="${1:-mac}"  # mac or windows

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

if [ "$PLATFORM" = "mac" ]; then
    log "=== Downloading macOS arm64 binaries ==="

    # PHP 8.2 static build
    log "--- PHP 8.2 ---"
    download_file \
        "https://dl.static-php.dev/static-php-cli/common/php-8.2.28-cli-macos-aarch64.tar.gz" \
        "$BIN_DIR/php-mac-arm64.tar.gz"
    extract_file "$BIN_DIR/php-mac-arm64.tar.gz" "$BIN_DIR/php-mac-arm64"
    chmod +x "$BIN_DIR/php-mac-arm64/php" 2>/dev/null || true
    rm -f "$BIN_DIR/php-mac-arm64.tar.gz"

    # Caddy
    log "--- Caddy ---"
    download_file \
        "https://github.com/caddyserver/caddy/releases/download/v2.10.0/caddy_2.10.0_mac_arm64.tar.gz" \
        "$BIN_DIR/caddy-mac-arm64.tar.gz"
    extract_file "$BIN_DIR/caddy-mac-arm64.tar.gz" "$BIN_DIR/caddy-mac-arm64"
    chmod +x "$BIN_DIR/caddy-mac-arm64/caddy" 2>/dev/null || true
    rm -f "$BIN_DIR/caddy-mac-arm64.tar.gz"

    # MySQL Community Server - copy from Homebrew (no official macOS arm64 tarball available)
    log "--- MySQL ---"
    if [ ! -f "$BIN_DIR/mysql-mac-arm64/bin/mysqld" ] || [ ! -s "$BIN_DIR/mysql-mac-arm64/bin/mysqld" ]; then
        rm -rf "$BIN_DIR/mysql-mac-arm64"
        BREW_MYSQL="$(brew --prefix mysql 2>/dev/null || echo "")"
        if [ -z "$BREW_MYSQL" ] || [ ! -d "$BREW_MYSQL" ]; then
            log "Installing MySQL via Homebrew..."
            brew install mysql
            BREW_MYSQL="$(brew --prefix mysql)"
        fi
        log "Copying MySQL from Homebrew: $BREW_MYSQL"
        mkdir -p "$BIN_DIR/mysql-mac-arm64/bin"
        mkdir -p "$BIN_DIR/mysql-mac-arm64/share"
        mkdir -p "$BIN_DIR/mysql-mac-arm64/lib"
        # Copy essential binaries
        for bin in mysqld mysql mysqld_safe mysql_install_db mysqladmin; do
            if [ -f "$BREW_MYSQL/bin/$bin" ]; then
                cp "$BREW_MYSQL/bin/$bin" "$BIN_DIR/mysql-mac-arm64/bin/"
                chmod +x "$BIN_DIR/mysql-mac-arm64/bin/$bin"
            fi
        done
        # Copy share (needed for mysql_install_db initialization)
        if [ -d "$BREW_MYSQL/share/mysql" ]; then
            cp -R "$BREW_MYSQL/share/mysql" "$BIN_DIR/mysql-mac-arm64/share/"
        fi
        # Copy required libraries
        if [ -d "$BREW_MYSQL/lib" ]; then
            cp -R "$BREW_MYSQL/lib/"*.dylib "$BIN_DIR/mysql-mac-arm64/lib/" 2>/dev/null || true
        fi
        # Copy plugin directory (auth plugins etc.)
        if [ -d "$BREW_MYSQL/lib/plugin" ]; then
            cp -R "$BREW_MYSQL/lib/plugin" "$BIN_DIR/mysql-mac-arm64/lib/"
        fi
        log "MySQL binaries copied successfully."
    else
        log "Already exists: $BIN_DIR/mysql-mac-arm64/bin/mysqld"
    fi

    log "=== macOS arm64 binaries downloaded ==="
    log "PHP:  $BIN_DIR/php-mac-arm64/php"
    log "Caddy: $BIN_DIR/caddy-mac-arm64/caddy"
    log "MySQL: $BIN_DIR/mysql-mac-arm64/"

elif [ "$PLATFORM" = "windows" ]; then
    log "=== Downloading Windows x64 binaries ==="

    # PHP 8.2 NTS (NTS = Non-Thread Safe, for CLI/FPM)
    log "--- PHP 8.2 ---"
    download_file \
        "https://windows.php.net/downloads/releases/archives/php-8.2.28-nts-Win32-vs16-x64.zip" \
        "$BIN_DIR/php-win-x64.zip" || \
    download_file \
        "https://windows.php.net/downloads/releases/php-8.2.28-nts-Win32-vs16-x64.zip" \
        "$BIN_DIR/php-win-x64.zip"
    extract_file "$BIN_DIR/php-win-x64.zip" "$BIN_DIR/php-win-x64"
    rm -f "$BIN_DIR/php-win-x64.zip"
    
    log "Configuring PHP php.ini..."
    cp "$BIN_DIR/php-win-x64/php.ini-production" "$BIN_DIR/php-win-x64/php.ini"
    # Enable extensions directory (Windows uses extension_dir = "ext")
    sed -i 's/; extension_dir = "ext"/extension_dir = "ext"/g' "$BIN_DIR/php-win-x64/php.ini"
    # Enable required extensions
    sed -i 's/;extension=curl/extension=curl/g' "$BIN_DIR/php-win-x64/php.ini"
    sed -i 's/;extension=fileinfo/extension=fileinfo/g' "$BIN_DIR/php-win-x64/php.ini"
    sed -i 's/;extension=gd/extension=gd/g' "$BIN_DIR/php-win-x64/php.ini"
    sed -i 's/;extension=intl/extension=intl/g' "$BIN_DIR/php-win-x64/php.ini"
    sed -i 's/;extension=mbstring/extension=mbstring/g' "$BIN_DIR/php-win-x64/php.ini"
    sed -i 's/;extension=mysqli/extension=mysqli/g' "$BIN_DIR/php-win-x64/php.ini"
    sed -i 's/;extension=pdo_mysql/extension=pdo_mysql/g' "$BIN_DIR/php-win-x64/php.ini"
    sed -i 's/;extension=zip/extension=zip/g' "$BIN_DIR/php-win-x64/php.ini"

    # Caddy
    log "--- Caddy ---"
    download_file \
        "https://github.com/caddyserver/caddy/releases/download/v2.10.0/caddy_2.10.0_windows_amd64.zip" \
        "$BIN_DIR/caddy-win-x64.zip"
    extract_file "$BIN_DIR/caddy-win-x64.zip" "$BIN_DIR/caddy-win-x64"
    rm -f "$BIN_DIR/caddy-win-x64.zip"

    # MariaDB
    log "--- MariaDB ---"
    download_file \
        "https://archive.mariadb.org/mariadb-11.4.5/winx64-packages/mariadb-11.4.5-winx64.zip" \
        "$BIN_DIR/mariadb-win-x64.zip"
    rm -rf "$BIN_DIR/mariadb-win-x64-temp" "$BIN_DIR/mariadb-win-x64"
    extract_file "$BIN_DIR/mariadb-win-x64.zip" "$BIN_DIR/mariadb-win-x64-temp"
    rm -f "$BIN_DIR/mariadb-win-x64.zip"
    mkdir -p "$BIN_DIR/mariadb-win-x64"
    if [ -d "$BIN_DIR/mariadb-win-x64-temp/mariadb-11.4.5-winx64" ]; then
        cp -R "$BIN_DIR/mariadb-win-x64-temp/mariadb-11.4.5-winx64/"* "$BIN_DIR/mariadb-win-x64/"
    else
        cp -R "$BIN_DIR/mariadb-win-x64-temp/"* "$BIN_DIR/mariadb-win-x64/"
    fi
    rm -rf "$BIN_DIR/mariadb-win-x64-temp"

    log "=== Windows x64 binaries downloaded ==="
    log "PHP:  $BIN_DIR/php-win-x64/php.exe"
    log "Caddy: $BIN_DIR/caddy-win-x64/caddy.exe"
    log "MariaDB: $BIN_DIR/mariadb-win-x64/"

else
    error "Unknown platform: $PLATFORM (use mac or windows)"
    exit 1
fi

log "Done!"
