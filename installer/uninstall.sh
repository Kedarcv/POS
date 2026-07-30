#!/bin/bash
# BeamLink POS - Uninstaller
# Removes BeamLink POS completely

echo "BeamLink POS Uninstaller"
echo "========================"
echo ""
echo "This will remove BeamLink POS and all its data."
echo "WARNING: This cannot be undone!"
echo ""
read -p "Are you sure? (yes/no): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo "Cancelled."
    exit 0
fi

INSTALL_DIR="/Applications/BeamLink POS"
DATA_DIR="$INSTALL_DIR/data"

echo ""
echo "Stopping services..."

# Stop services
if [ -f "$DATA_DIR/pids/mariadb.pid" ]; then
    pid=$(cat "$DATA_DIR/pids/mariadb.pid")
    kill "$pid" 2>/dev/null || true
fi
if [ -f "$DATA_DIR/pids/php.pid" ]; then
    pid=$(cat "$DATA_DIR/pids/php.pid")
    kill "$pid" 2>/dev/null || true
fi

# Stop Caddy
if [ -f "$INSTALL_DIR/caddy/caddy" ]; then
    "$INSTALL_DIR/caddy/caddy" stop --config "$DATA_DIR/Caddyfile" --adapter caddyfile 2>/dev/null || true
fi

sleep 2

echo "Removing files..."
rm -rf "$INSTALL_DIR"

echo "Removing LaunchAgent..."
rm -f ~/Library/LaunchAgents/com.beamlink.pos.plist

echo "Removing Desktop shortcut..."
rm -f ~/Desktop/"BeamLink POS.app" 2>/dev/null || true

echo ""
echo "BeamLink POS has been removed."
echo ""
