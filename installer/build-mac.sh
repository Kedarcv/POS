#!/bin/bash
# BeamLink POS - macOS DMG Builder
# Builds a .app bundle and packages it as a .dmg

set -e

INSTALL_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(cd "$INSTALL_DIR/.." && pwd)"
BUILD_DIR="$INSTALL_DIR/build"
APP_NAME="BeamLink POS"
APP_VERSION="3.4.2"
DMG_NAME="BeamLink-POS-${APP_VERSION}-mac-arm64"
VOLUME_NAME="BeamLink POS"

RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'

log() { echo -e "${GREEN}[Build]${NC} $1"; }
error() { echo -e "${RED}[Build]${NC} $1"; exit 1; }

# Clean build
rm -rf "$BUILD_DIR"

# Create .app bundle structure
APP_DIR="$BUILD_DIR/$DMG_NAME.app"
mkdir -p "$APP_DIR/Contents/MacOS"
mkdir -p "$APP_DIR/Contents/Resources"

# ============================================
# COPY BINARIES
# ============================================

log "Checking binaries..."

# PHP
if [ ! -d "$INSTALL_DIR/bin/php-mac-arm64" ]; then
    error "Run ./download-binaries.sh mac first!"
fi
log "Copying PHP..."
mkdir -p "$APP_DIR/Contents/Resources/php"
cp -R "$INSTALL_DIR/bin/php-mac-arm64/"* "$APP_DIR/Contents/Resources/php/"
chmod +x "$APP_DIR/Contents/Resources/php/php" 2>/dev/null || true

# MariaDB
if [ ! -d "$INSTALL_DIR/bin/mariadb-mac-arm64" ]; then
    error "Run ./download-binaries.sh mac first!"
fi
log "Copying MariaDB..."
cp -R "$INSTALL_DIR/bin/mariadb-mac-arm64" "$APP_DIR/Contents/Resources/mariadb"
chmod +x "$APP_DIR/Contents/Resources/mariadb/bin/mariadbd" 2>/dev/null || true
chmod +x "$APP_DIR/Contents/Resources/mariadb/bin/mariadb" 2>/dev/null || true
chmod +x "$APP_DIR/Contents/Resources/mariadb/bin/mariadb-install-db" 2>/dev/null || true

# Caddy
if [ ! -d "$INSTALL_DIR/bin/caddy-mac-arm64" ]; then
    error "Run ./download-binaries.sh mac first!"
fi
log "Copying Caddy..."
mkdir -p "$APP_DIR/Contents/Resources/caddy"
cp -R "$INSTALL_DIR/bin/caddy-mac-arm64/"* "$APP_DIR/Contents/Resources/caddy/"
chmod +x "$APP_DIR/Contents/Resources/caddy/caddy" 2>/dev/null || true

# ============================================
# COPY APP FILES
# ============================================

log "Copying application files..."
mkdir -p "$APP_DIR/Contents/Resources/app"

# Copy the entire OSPOS app (exclude dev-only stuff)
rsync -a --exclude='.git' \
          --exclude='node_modules' \
          --exclude='vendor' \
          --exclude='build' \
          --exclude='.env' \
          --exclude='installer' \
          --exclude='tests' \
          "$ROOT_DIR/" "$APP_DIR/Contents/Resources/app/"

# Install composer dependencies (production) if vendor directory doesn't exist
log "Checking composer dependencies..."
cd "$APP_DIR/Contents/Resources/app"
if [ -d "$ROOT_DIR/vendor" ]; then
    log "Copying existing vendor directory..."
    mkdir -p "$APP_DIR/Contents/Resources/app/vendor"
    cp -R "$ROOT_DIR/vendor/"* "$APP_DIR/Contents/Resources/app/vendor/"
elif [ -f "$ROOT_DIR/composer.json" ]; then
    cp "$ROOT_DIR/composer.json" "$ROOT_DIR/composer.lock" "$APP_DIR/Contents/Resources/app/" 2>/dev/null || true
    SYSTEM_PHP=$(which php@8.2 2>/dev/null || which php 2>/dev/null || echo "")
    if [ -n "$SYSTEM_PHP" ]; then
        "$SYSTEM_PHP" -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" 2>/dev/null || true
        "$SYSTEM_PHP" composer-setup.php --quiet 2>/dev/null || true
        "$SYSTEM_PHP" composer.phar install --no-dev --optimize-autoloader 2>/dev/null || true
        rm -f composer-setup.php composer.phar
    fi
fi

# Copy compiled assets
if [ -d "$ROOT_DIR/public/dist" ]; then
    mkdir -p "$APP_DIR/Contents/Resources/app/public/dist"
    cp -R "$ROOT_DIR/public/dist/"* "$APP_DIR/Contents/Resources/app/public/dist/"
fi

# ============================================
# COPY STOCK DATA
# ============================================

log "Copying stock data..."
mkdir -p "$APP_DIR/Contents/Resources/app/stock-data"
if [ -f "$ROOT_DIR/stock-data/STOCK.XLS" ]; then
    cp "$ROOT_DIR/stock-data/STOCK.XLS" "$APP_DIR/Contents/Resources/app/stock-data/"
fi
if [ -f "$ROOT_DIR/stock-data/STOCKQTY.XLS" ]; then
    cp "$ROOT_DIR/stock-data/STOCKQTY.XLS" "$APP_DIR/Contents/Resources/app/stock-data/"
fi

# ============================================
# COPY LAUNCHER + CONFIG
# ============================================

log "Setting up launcher..."
cp "$INSTALL_DIR/launcher.sh" "$APP_DIR/Contents/Resources/launcher.sh"
chmod +x "$APP_DIR/Contents/Resources/launcher.sh"

mkdir -p "$APP_DIR/Contents/Resources/config"
cp "$INSTALL_DIR/config/.env.example" "$APP_DIR/Contents/Resources/config/.env.example"

mkdir -p "$APP_DIR/Contents/Resources/setup"
cp "$INSTALL_DIR/setup/index.php" "$APP_DIR/Contents/Resources/setup/index.php"

# ============================================
# CREATE MACOS LAUNCHER
# ============================================

cat > "$APP_DIR/Contents/MacOS/$APP_NAME" << 'LAUNCHER'
#!/bin/bash
# BeamLink POS - macOS App Launcher
BUNDLE_DIR="$(cd "$(dirname "$0")/.." && pwd)"
RESOURCES="$BUNDLE_DIR/Resources"
exec "$RESOURCES/launcher.sh"
LAUNCHER
chmod +x "$APP_DIR/Contents/MacOS/$APP_NAME"

# ============================================
# Info.plist
# ============================================

cat > "$APP_DIR/Contents/Info.plist" << PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>CFBundleName</key>
    <string>${APP_NAME}</string>
    <key>CFBundleDisplayName</key>
    <string>${APP_NAME}</string>
    <key>CFBundleIdentifier</key>
    <string>com.secondspark.beamlink-pos</string>
    <key>CFBundleVersion</key>
    <string>${APP_VERSION}</string>
    <key>CFBundleShortVersionString</key>
    <string>${APP_VERSION}</string>
    <key>CFBundlePackageType</key>
    <string>APPL</string>
    <key>CFBundleExecutable</key>
    <string>${APP_NAME}</string>
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

# Copy icon if exists
if [ -f "$INSTALL_DIR/assets/icon.icns" ]; then
    cp "$INSTALL_DIR/assets/icon.icns" "$APP_DIR/Contents/Resources/"
fi

log "App bundle created: $APP_DIR"

# ============================================
# CREATE PKG / INSTALLER
# ============================================

log "Creating macOS Installer Package..."
PKG_OUTPUT="$BUILD_DIR/$DMG_NAME.pkg"
rm -f "$PKG_OUTPUT" ~/Desktop/"$DMG_NAME.pkg"

pkgbuild \
    --root "$APP_DIR" \
    --identifier "com.secondspark.beamlink-pos" \
    --version "$APP_VERSION" \
    --install-location "/Applications/$APP_NAME.app" \
    "$PKG_OUTPUT"

cp "$PKG_OUTPUT" ~/Desktop/ 2>/dev/null || true

PKG_SIZE=$(du -h "$PKG_OUTPUT" | cut -f1)

log "========================================"
log "BUILD COMPLETE"
log "Package: ~/Desktop/$DMG_NAME.pkg"
log "Size: $PKG_SIZE"
log "========================================"
