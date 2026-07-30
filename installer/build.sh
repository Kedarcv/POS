#!/bin/bash
# BeamLink POS - Master Build Script
# Builds installers for macOS and Windows

set -e

INSTALL_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(cd "$INSTALL_DIR/.." && pwd)"
BUILD_DIR="$INSTALL_DIR/build"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() { echo -e "${GREEN}[Build]${NC} $1"; }
warn() { echo -e "${YELLOW}[Build]${NC} $1"; }

PLATFORM="${1:-mac}"  # mac, windows, or all

log "=== BeamLink POS Installer Builder ==="
log "Version: 3.4.2"
log "Platform: $PLATFORM"
echo ""

# Step 1: Download binaries
log "Step 1: Downloading binaries..."
if [ "$PLATFORM" = "mac" ] || [ "$PLATFORM" = "all" ]; then
    bash "$INSTALL_DIR/download-binaries.sh" mac
fi
if [ "$PLATFORM" = "windows" ] || [ "$PLATFORM" = "all" ]; then
    bash "$INSTALL_DIR/download-binaries.sh" windows
fi

# Step 2: Ensure composer dependencies are installed
log "Step 2: Installing app dependencies..."
cd "$ROOT_DIR"
if [ ! -d "vendor" ]; then
    log "Running composer install..."
    composer install --no-dev --optimize-autoloader
fi

# Build frontend assets
if [ ! -d "public/dist" ] && [ -f "package.json" ]; then
    log "Building frontend assets..."
    npm install 2>/dev/null || true
    npm run build 2>/dev/null || true
fi

# Step 3: Build platform-specific installer
if [ "$PLATFORM" = "mac" ] || [ "$PLATFORM" = "all" ]; then
    log "Step 3: Building macOS DMG..."
    chmod +x "$INSTALL_DIR/build-mac.sh"
    bash "$INSTALL_DIR/build-mac.sh"
fi

if [ "$PLATFORM" = "windows" ] || [ "$PLATFORM" = "all" ]; then
    log "Step 3: Building Windows installer..."
    if command -v makensis &> /dev/null; then
        cd "$INSTALL_DIR"
        makensis windows-installer.nsi
        mv "BeamLink-POS-3.4.2-windows-x64.exe" ~/Desktop/ 2>/dev/null || true
        log "Windows installer: ~/Desktop/BeamLink-POS-3.4.2-windows-x64.exe"
    else
        warn "NSIS not installed. Install with: brew install nsis"
        warn "Skipping Windows installer."
    fi
fi

log "========================================"
log "Build complete!"
log "========================================"
