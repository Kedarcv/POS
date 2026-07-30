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

    # MariaDB - copy from Homebrew (no official macOS arm64 tarball available)
    log "--- MariaDB ---"
    if [ ! -f "$BIN_DIR/mariadb-mac-arm64/bin/mariadbd" ] || [ ! -s "$BIN_DIR/mariadb-mac-arm64/bin/mariadbd" ]; then
        rm -rf "$BIN_DIR/mariadb-mac-arm64"
        BREW_MARIADB="$(brew --prefix mariadb 2>/dev/null || echo "")"
        if [ -z "$BREW_MARIADB" ] || [ ! -d "$BREW_MARIADB" ]; then
            log "Installing MariaDB via Homebrew..."
            brew install mariadb
            BREW_MARIADB="$(brew --prefix mariadb)"
        fi
        log "Copying MariaDB from Homebrew: $BREW_MARIADB"
        mkdir -p "$BIN_DIR/mariadb-mac-arm64/bin"
        mkdir -p "$BIN_DIR/mariadb-mac-arm64/share"
        mkdir -p "$BIN_DIR/mariadb-mac-arm64/lib"
        # Copy essential binaries
        for bin in mariadbd mariadb mariadb-install-db mariadbd-safe mysql_install_db; do
            if [ -f "$BREW_MARIADB/bin/$bin" ]; then
                cp "$BREW_MARIADB/bin/$bin" "$BIN_DIR/mariadb-mac-arm64/bin/"
                chmod +x "$BIN_DIR/mariadb-mac-arm64/bin/$bin"
            fi
        done
        # Copy share (needed for mysql_install_db / mariadb-install-db)
        if [ -d "$BREW_MARIADB/share/mariadb" ]; then
            cp -R "$BREW_MARIADB/share/mariadb" "$BIN_DIR/mariadb-mac-arm64/share/"
        elif [ -d "$BREW_MARIADB/share/mysql" ]; then
            cp -R "$BREW_MARIADB/share/mysql" "$BIN_DIR/mariadb-mac-arm64/share/mariadb"
        fi
        # Copy required libraries
        if [ -d "$BREW_MARIADB/lib" ]; then
            cp -R "$BREW_MARIADB/lib/"*.dylib "$BIN_DIR/mariadb-mac-arm64/lib/" 2>/dev/null || true
        fi
        # Copy plugin directory
        if [ -d "$BREW_MARIADB/lib/plugin" ]; then
            cp -R "$BREW_MARIADB/lib/plugin" "$BIN_DIR/mariadb-mac-arm64/lib/"
        fi
        log "MariaDB binaries copied successfully."
    else
        log "Already exists: $BIN_DIR/mariadb-mac-arm64/bin/mariadbd"
    fi

    log "=== macOS arm64 binaries downloaded ==="
    log "PHP:  $BIN_DIR/php-mac-arm64/php"
    log "Caddy: $BIN_DIR/caddy-mac-arm64/caddy"
    log "MariaDB: $BIN_DIR/mariadb-mac-arm64/"

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
    extract_file "$BIN_DIR/mariadb-win-x64.zip" "$BIN_DIR/mariadb-win-x64"
    rm -f "$BIN_DIR/mariadb-win-x64.zip"

    log "=== Windows x64 binaries downloaded ==="
    log "PHP:  $BIN_DIR/php-win-x64/php.exe"
    log "Caddy: $BIN_DIR/caddy-win-x64/caddy.exe"
    log "MariaDB: $BIN_DIR/mariadb-win-x64/"

else
    error "Unknown platform: $PLATFORM (use mac or windows)"
    exit 1
fi

log "Done!"
