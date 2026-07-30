#!/bin/bash
# BeamLink POS - Creates macOS .app bundle
# Run this after PKG installation to create the clickable app

INSTALL_DIR="/Applications/BeamLink POS"
APP_BUNDLE="/Applications/BeamLink POS.app"

# Remove old bundle if exists
rm -rf "$APP_BUNDLE"

# Create .app bundle structure
mkdir -p "$APP_BUNDLE/Contents/MacOS"
mkdir -p "$APP_BUNDLE/Contents/Resources"

# Create launcher script
cat > "$APP_BUNDLE/Contents/MacOS/BeamLink POS" << 'LAUNCHER'
#!/bin/bash
# BeamLink POS - macOS App Launcher
INSTALL_DIR="/Applications/BeamLink POS"
DATA_DIR="$INSTALL_DIR/data"
PORT=8080

mkdir -p "$DATA_DIR/pids" "$DATA_DIR/logs"

# Check if already running
if [ -f "$DATA_DIR/pids/mariadb.pid" ]; then
    pid=$(cat "$DATA_DIR/pids/mariadb.pid")
    if kill -0 "$pid" 2>/dev/null; then
        open "http://localhost:$PORT"
        exit 0
    fi
fi

# Show notification
osascript -e 'display notification "Starting BeamLink POS..." with title "BeamLink POS"'

# Start MariaDB
"$INSTALL_DIR/mariadb/bin/mariadbd" \
    --defaults-file="$DATA_DIR/my.cnf" \
    --console &>/dev/null &
echo $! > "$DATA_DIR/pids/mariadb.pid"

# Wait for MariaDB
for i in {1..30}; do
    if "$INSTALL_DIR/mariadb/bin/mariadb" \
        --socket="$DATA_DIR/mariadb.sock" \
        -u root -e "SELECT 1" &>/dev/null; then
        break
    fi
    sleep 1
done

# Create DB user if needed
DB_PASS=$(cat "$DATA_DIR/.db_pass" 2>/dev/null || echo "")
if [ -n "$DB_PASS" ]; then
    "$INSTALL_DIR/mariadb/bin/mariadb" \
        --socket="$DATA_DIR/mariadb.sock" \
        -u root &>/dev/null << EOSQL 2>/dev/null
CREATE USER IF NOT EXISTS 'beamlink'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';
CREATE USER IF NOT EXISTS 'beamlink'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON beamlink_pos.* TO 'beamlink'@'127.0.0.1';
GRANT ALL PRIVILEGES ON beamlink_pos.* TO 'beamlink'@'localhost';
FLUSH PRIVILEGES;
EOSQL
fi

# Copy .env to app
if [ -f "$DATA_DIR/.env" ]; then
    cp "$DATA_DIR/.env" "$INSTALL_DIR/app/.env"
fi

# Start PHP
"$INSTALL_DIR/php/php" \
    -S 127.0.0.1:9000 \
    -t "$INSTALL_DIR/app/public" \
    -c "$DATA_DIR/php.ini" \
    &>/dev/null &
echo $! > "$DATA_DIR/pids/php.pid"

# Start Caddy
"$INSTALL_DIR/caddy/caddy" start \
    --config "$DATA_DIR/Caddyfile" \
    --adapter caddyfile \
    &>/dev/null &
echo $! > "$DATA_DIR/pids/caddy.pid"

sleep 2

# Open browser
if [ ! -f "$DATA_DIR/.installed" ]; then
    open "http://localhost:$PORT/setup"
else
    open "http://localhost:$PORT"
fi
LAUNCHER
chmod +x "$APP_BUNDLE/Contents/MacOS/BeamLink POS"

# Create Info.plist
cat > "$APP_BUNDLE/Contents/Info.plist" << PLIST
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
    <key>CFBundleIconFile</key>
    <string>AppIcon</string>
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

# Create a simple icon (using text-based icon)
cat > "$APP_BUNDLE/Contents/Resources/AppIcon.png" << 'ICONPLACEHOLDER'
ICONPLACEHOLDER

# Copy the actual icon if it exists
if [ -f "/Users/mnkomo/Desktop/opensourcepos/installer/assets/icon.icns" ]; then
    cp "/Users/mnkomo/Desktop/opensourcepos/installer/assets/icon.icns" "$APP_BUNDLE/Contents/Resources/AppIcon.icns"
fi

echo "BeamLink POS.app created in Applications"
