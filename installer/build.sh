#!/bin/bash
# BeamLink POS - Windows Installer Build Script
# Downloads the bundled binaries, installs dependencies, then drives electron-builder.

set -e

INSTALL_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(cd "$INSTALL_DIR/.." && pwd)"

GREEN='\033[0;32m'
NC='\033[0m'

log() { echo -e "${GREEN}[Build]${NC} $1"; }

log "=== BeamLink POS Windows Installer Builder ==="
echo ""

# Step 1: Download binaries
log "Step 1: Downloading binaries..."
bash "$INSTALL_DIR/download-binaries.sh" windows

# Step 2: Ensure dependencies are installed
log "Step 2: Installing app dependencies..."
cd "$ROOT_DIR"
if [ ! -d "vendor" ]; then
    log "Running composer install..."
    composer install --no-dev --optimize-autoloader
fi

if [ ! -d "public/dist" ] && [ -f "package.json" ]; then
    log "Building frontend assets..."
    npm install
    npm run build
fi

# Step 3: Build the Windows installer
log "Step 3: Building Windows installer..."
npm run electron:build-win

log "========================================"
log "Build complete: dist/electron/"
log "========================================"
