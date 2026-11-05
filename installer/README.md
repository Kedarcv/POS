# BeamLink POS - Desktop Installer

Native desktop app for macOS and Windows, built with Electron. Bundles PHP 8.2 and a portable MariaDB — no Docker, no external dependencies, no system database install required.

Builds are produced by `electron-builder` (see the `build` key in [package.json](../package.json)), driven by [electron/main.cjs](../electron/main.cjs). This directory holds the binaries it bundles and the GitHub Actions CI builds them on every push (`.github/workflows/build-mac-installer.yml`, `.github/workflows/build-windows-installer.yml`).

## Quick Start

```bash
cd installer
chmod +x download-binaries.sh
./download-binaries.sh mac      # or: windows
cd ..
npm install
composer install --no-dev --optimize-autoloader
npm run build

npm run electron:build-mac      # -> dist/electron/*.dmg
npm run electron:build-win      # -> dist/electron/*.exe
npm run electron:dist           # both, if building on a machine that supports it
```

`download-binaries.sh` is a no-op if the binaries are already present under `installer/bin/` (they're currently checked into the repo).

## Architecture

The app is a single Electron process ([electron/main.cjs](../electron/main.cjs)) that, on launch:

1. Picks a free HTTP port and a free DB port (starting at 8080 / 3307, scanning upward if taken).
2. Initializes the bundled MariaDB data directory on first run (`mysql_install_db` / `--initialize-insecure`).
3. Starts `mariadbd` and waits for it to accept connections.
4. Creates the `ospos` database and runs CodeIgniter migrations via the bundled PHP CLI.
5. Starts PHP's built-in server (`php -S`) in front of the CodeIgniter app.
6. Opens the app window pointed at that local server.

On quit, it asks `mariadb-admin` to shut the database down gracefully.

```
installer/
├── bin/
│   ├── php/            <- Windows PHP 8.2 (bundled into the Windows build)
│   ├── mariadb/        <- Windows MariaDB (bundled into the Windows build)
│   ├── php-mac-arm64/   <- macOS PHP 8.2 (bundled into the Mac build)
│   └── mariadb-mac-arm64/ <- macOS MariaDB (bundled into the Mac build)
├── download-binaries.sh  <- Fetches the binaries above if missing
└── assets/                <- App icons
```

## Services (desktop app)

| Service | Port (default, auto-bumped if taken) | Purpose |
|---------|---------------------------------------|---------|
| MariaDB | 3307 | Database |
| PHP built-in server | 8080 | Application + web server |

## Requirements for Building

- **Both platforms**: Node.js, npm, Composer, curl (for `download-binaries.sh`)
- Cross-building Windows installers from macOS/Linux, or vice versa, is not supported by electron-builder for native installers — use the matching CI job (GitHub Actions) for the platform you don't have.

## Notes

- MariaDB data lives in the Electron app's `userData` directory (not inside the app bundle), so it persists across app updates.
- `.env` and `my.ini`/`my.cnf` are generated at runtime with the ports chosen for that run — don't hand-edit the bundled templates expecting them to stick.
- The pre-Electron architecture (a hand-rolled `.pkg`/`.dmg`/NSIS builder using Caddy as a reverse proxy) was replaced by the Electron app above; `build.sh`, `build-mac.sh`, `build-pkg.sh`, and `create-app-bundle.sh` in this directory are leftovers from that era and are not part of the current build.
