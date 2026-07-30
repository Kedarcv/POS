# BeamLink POS - Desktop Installer

Native desktop installer for macOS and Windows. Bundles PHP 8.2, MariaDB, and Caddy — no external dependencies required.

## Quick Start

### macOS (.dmg)

```bash
cd installer
chmod +x download-binaries.sh build.sh build-mac.sh
./build.sh mac
```

Output: `~/Desktop/BeamLink-POS-3.4.2-mac-arm64.dmg`

### Windows (.exe)

Requires [NSIS](https://nsis.sourceforge.io/Download) installed.

```bash
cd installer
chmod +x download-binaries.sh build.sh
./build.sh windows
```

Output: `~/Desktop/BeamLink-POS-3.4.2-windows-x64.exe`

### Build Both

```bash
./build.sh all
```

## Architecture

```
BeamLink POS.app/
├── Contents/
│   ├── MacOS/
│   │   └── BeamLink POS          ← Launcher (opens browser)
│   ├── Resources/
│   │   ├── php/                   ← Static PHP 8.2 binary
│   │   ├── mariadb/               ← Portable MariaDB
│   │   ├── caddy/                 ← Caddy web server
│   │   ├── app/                   ← OSPOS CodeIgniter 4
│   │   ├── setup/                 ← First-run wizard
│   │   └── launcher.sh            ← Service manager
│   └── Info.plist
└── Contents/
```

## First Run

1. Launch "BeamLink POS" from Applications/Desktop
2. Setup wizard opens automatically at `localhost:8080/setup`
3. Database is pre-initialized (port 3307)
4. Set admin password and company details
5. Done! Login page opens

## Services

| Service | Port | Purpose |
|---------|------|---------|
| MariaDB | 3307 | Database |
| PHP | 9000 | Application server |
| Caddy | 8080 | Web server / reverse proxy |

## Ports

All services run on non-standard ports to avoid conflicts:
- **3307** (not 3306) — MariaDB
- **9000** — PHP-FPM
- **8080** — Caddy (web browser)

## Files

| File | Purpose |
|------|---------|
| `download-binaries.sh` | Fetches PHP, MariaDB, Caddy binaries |
| `build.sh` | Master build script |
| `build-mac.sh` | Creates macOS .app + .dmg |
| `windows-installer.nsi` | NSIS script for Windows .exe |
| `launcher.sh` | macOS service manager |
| `launcher.bat` | Windows service manager |
| `setup/index.php` | First-run setup wizard |
| `config/.env.example` | Environment config template |
| `config/my.cnf` | MariaDB config template |

## Requirements for Building

- **macOS**: macOS 11+, Xcode Command Line Tools, Homebrew
- **Windows**: NSIS, Windows SDK (for service management)
- **Both**: curl (for downloading binaries)

## Notes

- Binaries are downloaded from official sources during build
- Total installer size: ~200-300 MB (PHP ~8MB, MariaDB ~100MB, Caddy ~25MB, App ~50MB)
- MariaDB data is stored in `data/db/` (persists across updates)
- All config files are generated on first run
- WhatsApp/ERP sync works immediately after setup (requires internet)
