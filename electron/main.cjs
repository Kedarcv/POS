const { app, BrowserWindow, Menu, dialog, shell, ipcMain } = require('electron');
const path = require('path');
const { spawn } = require('child_process');
const fs = require('fs');
const net = require('net');
const http = require('http');
const waitOn = require('wait-on');
const treeKill = require('tree-kill');

let mainWindow = null;
let splashWindow = null;
let phpProcess = null;
let dbProcess = null;
let isQuitting = false;
let startupFailed = false;
let printJobSyncTimer = null;

const PRINT_JOB_SYNC_INTERVAL_MS = 30000;

const APP_NAME = 'BeamLink POS';

// Preferred ports; if taken we scan upwards for a free one.
const HTTP_PORT_START = 8080;
const DB_PORT_START = 3307;
let PORT = HTTP_PORT_START;
let DB_PORT = DB_PORT_START;

const isWin = process.platform === 'win32';
const exe = (name) => (isWin ? `${name}.exe` : name);

// Determine paths based on whether we are in dev or packaged mode
const isPackaged = app.isPackaged;
const rootPath = isPackaged ? process.resourcesPath : path.join(__dirname, '..');
const binPath = path.join(rootPath, 'installer', 'bin');

const mariadbDir = path.join(binPath, 'mariadb');
const phpDir = path.join(binPath, 'php');
const publicDir = path.join(rootPath, 'public');
const rewriteScript = path.join(rootPath, 'vendor', 'codeigniter4', 'framework', 'system', 'rewrite.php');
const sparkScript = path.join(rootPath, 'spark');

// User data path (C:\Users\<user>\AppData\Roaming\BeamLink POS on Windows)
const dataDir = app.getPath('userData');
const dbDir = path.join(dataDir, 'db');
const logsDir = path.join(dataDir, 'logs');
const myIniPath = path.join(dataDir, 'my.ini');
const envPath = path.join(dataDir, '.env');
const settingsPath = path.join(dataDir, 'settings.json');
const preloadPath = path.join(__dirname, 'preload.cjs');

const phpBinary = path.join(phpDir, isWin ? 'php.exe' : path.join('bin', 'php'));
const mariadbdPath = path.join(mariadbDir, 'bin', isWin ? 'mariadbd.exe' : 'mysqld');
const mariadbPath = path.join(mariadbDir, 'bin', exe('mariadb'));
const mariadbAdminPath = path.join(mariadbDir, 'bin', exe('mariadb-admin'));

// Inject the environment variable so CodeIgniter knows where to look for .env and writable/
process.env.OSPOS_DATA_DIR = dataDir;

/* ------------------------------------------------------------------ *
 * Logging - everything lands in <userData>/logs so a shipped install
 * can be diagnosed without a terminal attached.
 * ------------------------------------------------------------------ */

let logStream = null;

function initLogging() {
    fs.mkdirSync(logsDir, { recursive: true });
    const logFile = path.join(logsDir, 'main.log');

    // Keep the log from growing without bound across launches.
    try {
        if (fs.existsSync(logFile) && fs.statSync(logFile).size > 5 * 1024 * 1024) {
            fs.renameSync(logFile, path.join(logsDir, 'main.previous.log'));
        }
    } catch { /* non-fatal */ }

    logStream = fs.createWriteStream(logFile, { flags: 'a' });
    log(`===== ${APP_NAME} starting (packaged=${isPackaged}, pid=${process.pid}) =====`);
    log(`rootPath=${rootPath}`);
    log(`dataDir=${dataDir}`);
}

function log(...parts) {
    const line = `[${new Date().toISOString()}] ${parts.join(' ')}`;
    console.log(line);
    if (logStream) logStream.write(line + '\n');
}

// Pipe a child process's output into the main log with a prefix.
function pipeToLog(child, prefix) {
    const emit = (buf) => {
        const text = buf.toString().replace(/\s+$/, '');
        if (text) log(`${prefix}: ${text}`);
    };
    if (child.stdout) child.stdout.on('data', emit);
    if (child.stderr) child.stderr.on('data', emit);
}

/* ------------------------------------------------------------------ *
 * Small helpers
 * ------------------------------------------------------------------ */

// Run a command to completion, resolving with its exit code and output.
function run(cmd, args, opts = {}) {
    return new Promise((resolve) => {
        log(`exec: ${cmd} ${args.join(' ')}`);
        const child = spawn(cmd, args, opts);
        let output = '';

        const capture = (buf) => { output += buf.toString(); };
        if (child.stdout) child.stdout.on('data', capture);
        if (child.stderr) child.stderr.on('data', capture);
        pipeToLog(child, path.basename(cmd));

        child.on('error', (err) => {
            log(`exec error (${path.basename(cmd)}): ${err.message}`);
            resolve({ code: -1, output });
        });
        child.on('close', (code) => resolve({ code, output }));
    });
}

// Scan upwards from `start` for a port nothing is listening on.
function findFreePort(start, attempts = 50) {
    return new Promise((resolve, reject) => {
        let port = start;
        let tried = 0;

        const attempt = () => {
            if (tried++ >= attempts) {
                return reject(new Error(`No free port found in range ${start}-${start + attempts}`));
            }
            const server = net.createServer();
            server.once('error', () => {
                port++;
                attempt();
            });
            server.once('listening', () => server.close(() => resolve(port)));
            server.listen(port, '127.0.0.1');
        };

        attempt();
    });
}

/* ------------------------------------------------------------------ *
 * Desktop integration
 * ------------------------------------------------------------------ */

function readSettings() {
    try {
        return JSON.parse(fs.readFileSync(settingsPath, 'utf-8'));
    } catch {
        return {};
    }
}

function writeSettings(settings) {
    try {
        fs.writeFileSync(settingsPath, JSON.stringify(settings, null, 2), 'utf-8');
    } catch (err) {
        log(`Could not persist settings: ${err.message}`);
    }
}

const VALID_PRINTER_ROLES = new Set(['receipt_printer', 'invoice_printer', 'takings_printer']);

const VIRTUAL_PRINTER_PATTERN = /(anydesk|onenote|print to pdf|xps|fax|send to)/i;
const RECEIPT_PRINTER_PATTERN = /(receipt|thermal|epson|star|bixolon|xprinter|rongta|zywell|gprinter|hoin|snbc|pos[- _]?[58])/i;
const PRINTER_STATE_CACHE_MS = 10000;
const BLOCKING_PRINTER_ERRORS = new Set([4, 6, 7, 8, 9, 10, 11, 12, 13]);

let printerStateCache = { at: 0, states: null };

function printerText(printer) {
    return `${printer.displayName || ''} ${printer.name || ''}`.trim();
}

function isVirtualPrinter(printer) {
    return VIRTUAL_PRINTER_PATTERN.test(printerText(printer));
}

function isReceiptPrinter(printer) {
    return RECEIPT_PRINTER_PATTERN.test(printerText(printer));
}

function windowsPrinterIsReady(state) {
    if (!state) return true;
    if (state.WorkOffline === true || state.WorkOffline === 1 || String(state.WorkOffline).toLowerCase() === 'true') {
        return false;
    }

    const status = Number(state.PrinterStatus);
    const error = Number(state.DetectedErrorState);

    // Only block if explicitly offline or stopped printing
    if (status === 7 || error === 9) return false;
    if (status === 6) return false;
    if (BLOCKING_PRINTER_ERRORS.has(error)) return false;

    // Notice: Do NOT mark unavailable for status 1 ("Other"), 2 ("Unknown"), or error 0/1.
    // Thermal receipt printers on Windows (POS-58, POS-80, Xprinter, etc.) typically report
    // status 1 or 2 because their generic USB drivers lack full bidirectional status polling.
    return true;
}

function queryWindowsPrinterStates() {
    if (!isWin) return Promise.resolve(null);

    if (printerStateCache.states && Date.now() - printerStateCache.at < PRINTER_STATE_CACHE_MS) {
        return Promise.resolve(printerStateCache.states);
    }

    const script = 'Get-CimInstance Win32_Printer | Select-Object Name,Default,WorkOffline,PrinterStatus,DetectedErrorState | ConvertTo-Json -Compress';

    return new Promise((resolve) => {
        const child = spawn('powershell.exe', ['-NoProfile', '-NonInteractive', '-Command', script], { windowsHide: true });
        let stdout = '';

        child.stdout.on('data', (buf) => { stdout += buf.toString(); });
        child.on('error', () => resolve(printerStateCache.states));
        child.on('close', () => {
            let parsed;
            try {
                parsed = JSON.parse(stdout.trim() || '[]');
            } catch {
                return resolve(printerStateCache.states);
            }

            const rows = Array.isArray(parsed) ? parsed : [parsed];
            const states = new Map();
            for (const row of rows) {
                if (row && row.Name) states.set(String(row.Name).toLowerCase(), row);
            }

            printerStateCache = { at: Date.now(), states };
            resolve(states);
        });
    });
}

async function resolvePrinter(event, printerRole, preferredName = '') {
    const role = VALID_PRINTER_ROLES.has(printerRole) ? printerRole : 'receipt_printer';
    const settings = readSettings();
    const configured = preferredName || (settings.printers || {})[role] || '';

    let printers = [];
    try {
        printers = await event.sender.getPrintersAsync();
    } catch (err) {
        log(`getPrintersAsync failed: ${err.message}`);
    }

    const states = await queryWindowsPrinterStates();
    let defaultName = '';
    if (states) {
        for (const [name, state] of states) {
            if (state && state.Default) defaultName = name;
        }
    }
    if (!defaultName) {
        const sysDef = printers.find((p) => p.isDefault);
        if (sysDef) defaultName = sysDef.name.toLowerCase();
    }

    const candidates = printers.map((printer) => ({
        name: printer.name,
        displayName: printer.displayName || printer.name,
        available: states ? windowsPrinterIsReady(states.get(String(printer.name).toLowerCase())) : true,
        virtual: isVirtualPrinter(printer),
        isDefault: String(printer.name).toLowerCase() === defaultName,
        configured: configured !== '' && String(printer.name).toLowerCase() === configured.toLowerCase(),
    }));

    // If a configured printer matches an installed device, ALWAYS use it!
    const configuredCandidate = candidates.find((printer) => printer.configured);
    if (configuredCandidate) {
        if (!settings.printers || settings.printers[role] !== configuredCandidate.name) {
            settings.printers = settings.printers || {};
            settings.printers[role] = configuredCandidate.name;
            writeSettings(settings);
        }
        return {
            ...configuredCandidate,
            available: true,
            source: 'configured',
            configured: configuredCandidate.name,
        };
    }

    // Otherwise, find a connected non-virtual receipt printer or default
    const nonVirtual = candidates.filter((printer) => !printer.virtual);
    const detected = nonVirtual.find((printer) => printer.available && isReceiptPrinter(printer))
        || nonVirtual.find((printer) => isReceiptPrinter(printer))
        || nonVirtual.find((printer) => printer.available && printer.isDefault)
        || nonVirtual.find((printer) => printer.isDefault)
        || nonVirtual.find((printer) => printer.available)
        || nonVirtual[0]
        || candidates[0];

    if (detected) {
        if (!configured) {
            settings.printers = settings.printers || {};
            settings.printers[role] = detected.name;
            writeSettings(settings);
        }

        return {
            ...detected,
            available: true,
            source: 'detected',
            configured,
            configuredAvailable: Boolean(configuredCandidate),
        };
    }

    return {
        name: configured,
        displayName: configured,
        available: false,
        virtual: false,
        isDefault: false,
        configured,
        configuredAvailable: false,
        source: 'none',
    };
}

// Native printing — bypasses the OS print dialog and sends straight to the
// configured physical printer, falling back to whichever connected printer
// the OS reports when the configured one is missing.
ipcMain.handle('print-silently', async (event, printerRole, preferredName) => {
    const resolved = await resolvePrinter(event, printerRole, preferredName);

    if (!resolved.name) {
        return {
            success: false,
            error: 'No receipt printer was detected. Check that the printer is powered on and connected, then try again.',
            printer: null,
        };
    }

    return new Promise((resolve) => {
        event.sender.print(
            { silent: true, printBackground: true, deviceName: resolved.name, margins: { marginType: 'none' } },
            (success, failureReason) => resolve({
                success,
                error: success ? null : (failureReason || 'The printer did not accept the receipt.'),
                printer: resolved.name,
                source: resolved.source,
            }),
        );
    });
});

ipcMain.handle('list-printers', async (event) => {
    try {
        const printers = await event.sender.getPrintersAsync();
        const states = await queryWindowsPrinterStates();

        return printers.map((printer) => ({
            ...printer,
            available: states ? windowsPrinterIsReady(states.get(String(printer.name).toLowerCase())) : true,
            virtual: isVirtualPrinter(printer),
        }));
    } catch (err) {
        log(`getPrintersAsync failed: ${err.message}`);
        return [];
    }
});

ipcMain.handle('resolve-printer', (event, printerRole) => resolvePrinter(event, printerRole));

ipcMain.handle('get-selected-printer', (event, printerRole) => {
    const role = VALID_PRINTER_ROLES.has(printerRole) ? printerRole : 'receipt_printer';
    return (readSettings().printers || {})[role] || '';
});

ipcMain.handle('set-selected-printer', (event, printerRole, deviceName) => {
    const role = VALID_PRINTER_ROLES.has(printerRole) ? printerRole : 'receipt_printer';
    const settings = readSettings();
    settings.printers = settings.printers || {};
    settings.printers[role] = deviceName || '';
    writeSettings(settings);

    return true;
});

// Sends the user to the OS Bluetooth pairing screen. A Bluetooth receipt
// printer is paired at the OS level (Windows Settings > Bluetooth & devices),
// after which it appears in listPrinters() and can be selected like any
// USB/cable printer.
ipcMain.handle('open-bluetooth-settings', () => {
    if (process.platform === 'win32') {
        shell.openExternal('ms-settings:bluetooth').catch(() => {});
        return true;
    }
    if (process.platform === 'darwin') {
        shell.openExternal('x-apple.systempreferences:com.apple.BluetoothSettings').catch(() => {});
        return true;
    }

    // Linux desktops vary too much to guess a settings URL; the user can
    // pair from their desktop's Bluetooth panel and then click Detect.
    return false;
});

// A till is expected to come back up on its own after a power cut or reboot,
// so launch-at-login defaults to on. Users can turn it off from the menu.
function applyAutoLaunch(enabled) {
    if (!isPackaged) return; // dev runs would register the electron.exe stub
    try {
        app.setLoginItemSettings({ openAtLogin: enabled, path: process.execPath, args: [] });
        log(`Launch at login: ${enabled ? 'enabled' : 'disabled'}`);
    } catch (err) {
        log(`Could not set login item: ${err.message}`);
    }
}

function initAutoLaunch() {
    const settings = readSettings();
    if (settings.openAtLogin === undefined) {
        settings.openAtLogin = true;
        writeSettings(settings);
    }
    applyAutoLaunch(settings.openAtLogin);
    return settings.openAtLogin;
}

function buildMenu() {
    const settings = readSettings();

    const template = [
        {
            label: '&File',
            submenu: [
                {
                    label: 'Start automatically when I sign in',
                    type: 'checkbox',
                    checked: settings.openAtLogin !== false,
                    enabled: isPackaged,
                    click: (item) => {
                        const current = readSettings();
                        current.openAtLogin = item.checked;
                        writeSettings(current);
                        applyAutoLaunch(item.checked);
                    },
                },
                { type: 'separator' },
                {
                    label: 'Open logs folder',
                    click: () => shell.openPath(logsDir),
                },
                { type: 'separator' },
                { role: 'quit', label: 'Exit' },
            ],
        },
        {
            label: '&View',
            submenu: [
                { role: 'reload' },
                { role: 'forceReload' },
                { type: 'separator' },
                { role: 'resetZoom' },
                { role: 'zoomIn' },
                { role: 'zoomOut' },
                { type: 'separator' },
                { role: 'togglefullscreen' },
                { role: 'toggleDevTools' },
            ],
        },
        {
            label: '&Edit',
            submenu: [
                { role: 'undo' }, { role: 'redo' }, { type: 'separator' },
                { role: 'cut' }, { role: 'copy' }, { role: 'paste' }, { role: 'selectAll' },
            ],
        },
    ];

    Menu.setApplicationMenu(Menu.buildFromTemplate(template));
}

function setSplashStatus(text) {
    log(`status: ${text}`);
    if (splashWindow && !splashWindow.isDestroyed()) {
        splashWindow.webContents
            .executeJavaScript(`window.setStatus(${JSON.stringify(text)})`)
            .catch(() => { /* splash may be closing */ });
    }
}

/* ------------------------------------------------------------------ *
 * Filesystem / configuration setup
 * ------------------------------------------------------------------ */

function ensureDirectories() {
    const dirs = [
        dataDir, dbDir, logsDir,
        path.join(dataDir, 'sessions'),
        path.join(dataDir, 'writable'),
        path.join(dataDir, 'writable', 'cache'),
        path.join(dataDir, 'writable', 'logs'),
        path.join(dataDir, 'writable', 'session'),
        path.join(dataDir, 'writable', 'uploads'),
        path.join(dataDir, 'writable', 'backup'),
        path.join(dataDir, 'writable', 'debugbar'),
    ];
    dirs.forEach((dir) => {
        if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
    });

    // Copy .env.example as .env if not present (required for CodeIgniter to boot)
    if (!fs.existsSync(envPath)) {
        const envSource = path.join(rootPath, '.env.example');
        if (!fs.existsSync(envSource)) {
            throw new Error(`Missing .env.example at ${envSource}`);
        }
        fs.copyFileSync(envSource, envPath);
        log('Created .env from .env.example');
    }
}

// Does this .env line assign `key`? Returns the comment state so an active
// assignment can be preferred over a commented-out example of the same key.
function classifyEnvLine(line, key) {
    const trimmed = line.trim();
    const commented = trimmed.startsWith('#');
    const body = commented ? trimmed.replace(/^#+\s*/, '') : trimmed;

    if (!body.toLowerCase().startsWith(key.toLowerCase())) return null;
    // Must be `key =`, not a longer key that merely starts the same way.
    if (!/^\s*=/.test(body.slice(key.length))) return null;

    return { commented };
}

function setEnvKey(lines, key, value) {
    const assignment = `${key} = ${value}`;

    let commentedAt = -1;
    for (let i = 0; i < lines.length; i++) {
        const match = classifyEnvLine(lines[i], key);
        if (!match) continue;
        if (!match.commented) {
            lines[i] = assignment;
            return;
        }
        if (commentedAt === -1) commentedAt = i;
    }

    // No active assignment: uncomment the first example, else append.
    if (commentedAt !== -1) lines[commentedAt] = assignment;
    else lines.push(assignment);
}

// Ports are chosen at runtime, so the .env has to be re-pointed on every
// launch or CodeIgniter will dial the previous run's database port.
function syncEnvPorts() {
    const raw = fs.readFileSync(envPath, 'utf-8');
    const eol = raw.includes('\r\n') ? '\r\n' : '\n';
    const lines = raw.split(/\r?\n/);

    for (const group of ['default', 'development', 'tests']) {
        setEnvKey(lines, `database.${group}.port`, DB_PORT);
    }
    // Only consulted from the CLI (migrations); HTTP requests derive it from the Host header.
    setEnvKey(lines, 'app.baseURL', `'http://127.0.0.1:${PORT}/'`);

    fs.writeFileSync(envPath, lines.join(eol), 'utf-8');
    log(`.env synced (http=${PORT}, db=${DB_PORT})`);
}

function writeMyIni() {
    const fwd = (p) => p.replace(/\\/g, '/');
    let content = `[mysqld]\nport=${DB_PORT}\n`;

    if (isWin) {
        content += `basedir="${fwd(mariadbDir)}"\n`;
        content += `datadir="${fwd(dbDir)}"\n`;
        content += `plugin_dir="${fwd(mariadbDir)}/lib/plugin"\n`;
        content += `\n[client]\nport=${DB_PORT}\nplugin_dir="${fwd(mariadbDir)}/lib/plugin"\n`;
    } else {
        content += `datadir="${fwd(dbDir)}"\nsocket="${fwd(dataDir)}/mysql.sock"\n`;
        content += `\n[client]\nport=${DB_PORT}\nsocket="${fwd(dataDir)}/mysql.sock"\n`;
    }

    fs.writeFileSync(myIniPath, content);
}

/* ------------------------------------------------------------------ *
 * Database lifecycle
 * ------------------------------------------------------------------ */

async function initDatabaseIfNeeded() {
    if (fs.existsSync(path.join(dbDir, 'mysql'))) return;

    setSplashStatus('Preparing the database for first use\u2026');

    const initCmd = path.join(mariadbDir, 'bin', isWin ? 'mysql_install_db.exe' : 'mysql_install_db');
    const initArgs = isWin
        ? [`--datadir=${dbDir}`, '--password=', `--port=${DB_PORT}`]
        : [`--datadir=${dbDir}`, `--basedir=${mariadbDir}`];

    const { code } = await run(initCmd, initArgs);
    if (code !== 0) {
        throw new Error(`Failed to initialize the database (exit code ${code}).`);
    }
}

async function startDatabaseServer() {
    setSplashStatus('Starting the database\u2026');

    dbProcess = spawn(mariadbdPath, [`--defaults-file=${myIniPath}`]);
    pipeToLog(dbProcess, 'mariadb');

    dbProcess.on('exit', (code) => {
        log(`mariadbd exited with code ${code}`);
        dbProcess = null;
    });

    // mariadb-admin ping returns 0 once the server accepts connections.
    const maxAttempts = 60;
    for (let attempt = 1; attempt <= maxAttempts; attempt++) {
        if (!dbProcess) throw new Error('The database server stopped unexpectedly during startup.');

        const { code } = await run(mariadbAdminPath, [
            `--defaults-file=${myIniPath}`,
            '-u', 'root', '-P', String(DB_PORT), '-h', '127.0.0.1',
            'ping',
        ]);
        if (code === 0) {
            log(`MariaDB ready after ${attempt} attempt(s)`);
            return;
        }
        await new Promise((r) => setTimeout(r, 1000));
    }

    throw new Error(`The database did not become ready within ${maxAttempts} seconds.`);
}

async function provisionDatabase() {
    setSplashStatus('Configuring the database\u2026');

    const sql = [
        'CREATE DATABASE IF NOT EXISTS ospos CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;',
        "CREATE USER IF NOT EXISTS 'admin'@'127.0.0.1' IDENTIFIED BY 'pointofsale';",
        "CREATE USER IF NOT EXISTS 'admin'@'localhost' IDENTIFIED BY 'pointofsale';",
        "GRANT ALL PRIVILEGES ON ospos.* TO 'admin'@'127.0.0.1';",
        "GRANT ALL PRIVILEGES ON ospos.* TO 'admin'@'localhost';",
        'FLUSH PRIVILEGES;',
    ].join(' ');

    const { code } = await run(mariadbPath, [
        `--defaults-file=${myIniPath}`,
        '-u', 'root', '-P', String(DB_PORT), '-h', '127.0.0.1',
        '-e', sql,
    ]);

    if (code !== 0) {
        throw new Error(`Failed to create the application database (exit code ${code}).`);
    }
}

async function runMigrations() {
    if (!fs.existsSync(sparkScript)) {
        log(`spark script not found at ${sparkScript}, skipping migrations`);
        return;
    }

    setSplashStatus('Updating the database schema\u2026');

    const { code, output } = await run(phpBinary, [sparkScript, 'migrate', '--all'], {
        cwd: rootPath,
        env: { ...process.env, OSPOS_DATA_DIR: dataDir, PHPRC: phpDir },
    });

    // spark exits 0 even when a migration throws, so the output has to be
    // inspected too. A half-built schema is worse than a clear failure.
    const failure = /\[(?:\w+\\)*\w*Exception\]|Unable to connect to the database|PHP Fatal error/i.exec(output);

    if (code !== 0 || failure) {
        const detail = failure ? failure[0] : `exit code ${code}`;
        throw new Error(`Database migration failed (${detail}). See the log for details.`);
    }
}

// Ask MariaDB to close cleanly. A hard kill on a POS database risks losing
// or corrupting the most recent transactions.
function shutdownDatabase() {
    return new Promise((resolve) => {
        if (!dbProcess) return resolve();

        log('Requesting graceful MariaDB shutdown');
        const child = spawn(mariadbAdminPath, [
            `--defaults-file=${myIniPath}`,
            '-u', 'root', '-P', String(DB_PORT), '-h', '127.0.0.1',
            'shutdown',
        ]);
        pipeToLog(child, 'mariadb-admin');

        let settled = false;
        const finish = (how) => {
            if (settled) return;
            settled = true;
            log(`MariaDB shutdown: ${how}`);
            if (dbProcess && dbProcess.pid) treeKill(dbProcess.pid, 'SIGKILL', () => resolve());
            else resolve();
        };

        if (dbProcess) dbProcess.once('exit', () => finish('clean exit'));
        child.on('error', () => finish('admin command failed'));
        // Don't let a wedged database block quitting forever.
        setTimeout(() => finish('timed out'), 10000);
    });
}

/* ------------------------------------------------------------------ *
 * PHP application server
 * ------------------------------------------------------------------ */

function startPHP() {
    setSplashStatus('Starting the application\u2026');

    const bundledPhpIni = path.join(phpDir, 'php.ini');
    const extDir = path.join(phpDir, 'ext').replace(/\\/g, '/');
    const caCertPath = path.join(phpDir, 'extras', 'ssl', 'cacert.pem').replace(/\\/g, '/');

    // The bundled php.ini ships without absolute paths for a few settings
    // that require them; rewrite them so PHP works regardless of install
    // location. Handles both a bare directive and its commented-out form
    // (e.g. ";curl.cainfo =") since the shipped ini has the latter.
    if (fs.existsSync(bundledPhpIni)) {
        try {
            let ini = fs.readFileSync(bundledPhpIni, 'utf-8');
            const original = ini;

            const setDirective = (name, value) => {
                const re = new RegExp(`^;?\\s*${name}\\s*=.*$`, 'm');
                const line = `${name} = "${value}"`;
                ini = re.test(ini) ? ini.replace(re, line) : `${ini}\n${line}\n`;
            };

            setDirective('extension_dir', extDir);
            // Without an explicit CA bundle, cURL/OpenSSL in this bundled,
            // non-system-installed PHP can't verify any HTTPS certificate
            // chain at all — every outbound call (EcoCash, ZIMRA, Pesepay,
            // WhatsApp, etc.) fails with "unable to get local issuer
            // certificate". Point both at curl's maintained Mozilla bundle.
            if (fs.existsSync(caCertPath)) {
                setDirective('curl.cainfo', caCertPath);
                setDirective('openssl.cafile', caCertPath);
            } else {
                log(`CA bundle not found at ${caCertPath}; HTTPS calls may fail SSL verification`);
            }

            if (ini !== original) fs.writeFileSync(bundledPhpIni, ini, 'utf-8');
        } catch (err) {
            // Read-only install dir is fine as long as a previous run fixed it.
            log(`Could not rewrite php.ini: ${err.message}`);
        }
    }

    // PHP's built-in server with rewrite.php mimics the Apache/nginx routing.
    phpProcess = spawn(phpBinary, [
        '-S', `127.0.0.1:${PORT}`,
        '-t', publicDir,
        rewriteScript,
    ], {
        cwd: publicDir,
        env: { ...process.env, OSPOS_DATA_DIR: dataDir, PHPRC: phpDir },
    });

    pipeToLog(phpProcess, 'php');

    phpProcess.on('exit', (code) => {
        log(`php exited with code ${code}`);
        const wasRunning = phpProcess !== null;
        phpProcess = null;
        if (!isQuitting && !startupFailed && wasRunning) {
            fail(new Error(`The application server stopped unexpectedly (exit code ${code}).`));
        }
    });
}

/* ------------------------------------------------------------------ *
 * Windows
 * ------------------------------------------------------------------ */

function createSplash() {
    splashWindow = new BrowserWindow({
        width: 420,
        height: 320,
        frame: false,
        resizable: false,
        center: true,
        show: true,
        backgroundColor: '#2c3e50',
        webPreferences: { nodeIntegration: false, contextIsolation: true },
    });
    splashWindow.loadFile(path.join(__dirname, 'splash.html'));
    splashWindow.on('closed', () => { splashWindow = null; });
}

function createMainWindow() {
    mainWindow = new BrowserWindow({
        width: 1280,
        height: 800,
        show: false,
        autoHideMenuBar: true,
        title: APP_NAME,
        backgroundColor: '#ffffff',
        webPreferences: { nodeIntegration: false, contextIsolation: true, preload: preloadPath },
    });

    mainWindow.maximize();

    // Keep the app itself on localhost; send anything else to the real browser.
    const isLocal = (url) => {
        try {
            const u = new URL(url);
            return u.hostname === '127.0.0.1' && u.port === String(PORT);
        } catch {
            return false;
        }
    };

    mainWindow.webContents.setWindowOpenHandler(({ url }) => {
        if (!isLocal(url)) {
            shell.openExternal(url);
            return { action: 'deny' };
        }
        // A target="_blank" link (e.g. "Print Last Receipt") opens as a new
        // child BrowserWindow with its own fresh webPreferences — without
        // repeating the preload here, window.beamlinkPrint would simply be
        // undefined on that window and it'd silently fall back to the OS
        // print dialog instead of printing straight to the receipt printer.
        return {
            action: 'allow',
            overrideBrowserWindowOptions: {
                webPreferences: { nodeIntegration: false, contextIsolation: true, preload: preloadPath },
            },
        };
    });

    mainWindow.webContents.on('will-navigate', (event, url) => {
        if (!isLocal(url)) {
            event.preventDefault();
            shell.openExternal(url);
        }
    });

    mainWindow.on('closed', () => { mainWindow = null; });

    return mainWindow.loadURL(`http://127.0.0.1:${PORT}`).then(() => {
        mainWindow.show();
        if (splashWindow && !splashWindow.isDestroyed()) splashWindow.close();
    });
}

/* ------------------------------------------------------------------ *
 * Startup / shutdown
 * ------------------------------------------------------------------ */

function fail(err) {
    if (startupFailed) return;
    startupFailed = true;

    const message = err && err.message ? err.message : String(err);
    log(`STARTUP ERROR: ${message}`);
    if (err && err.stack) log(err.stack);

    if (splashWindow && !splashWindow.isDestroyed()) {
        splashWindow.webContents
            .executeJavaScript(`window.setError(${JSON.stringify(message)})`)
            .catch(() => {});
    }

    dialog.showErrorBox(
        `${APP_NAME} could not start`,
        `${message}\n\nA detailed log is available at:\n${path.join(logsDir, 'main.log')}`,
    );

    app.quit();
}

async function startup() {
    ensureDirectories();
    initAutoLaunch();
    buildMenu();

    PORT = await findFreePort(HTTP_PORT_START);
    DB_PORT = await findFreePort(DB_PORT_START);
    log(`Using http port ${PORT}, database port ${DB_PORT}`);

    syncEnvPorts();
    writeMyIni();

    await initDatabaseIfNeeded();
    await startDatabaseServer();
    await provisionDatabase();
    await runMigrations();

    startPHP();

    setSplashStatus('Almost ready\u2026');
    // CodeIgniter's routes are GET-only and 404 on HEAD, so force wait-on
    // past its default HEAD probe or it times out against a healthy server.
    await waitOn({ resources: [`http-get://127.0.0.1:${PORT}`], timeout: 60000, interval: 250 });

    await createMainWindow();
    startPrintJobAutoSync();
    log('Startup complete');
}

// Pulls new/updated print jobs from the BeamLink ERP on a timer so they
// show up on the Kanban board without anyone needing to click "Sync".
// Runs for as long as the app is open, independent of which page (if any)
// is on screen.
function startPrintJobAutoSync() {
    const poll = () => {
        if (!phpProcess) return; // server not up (or shutting down) \u2014 skip this tick
        const req = http.get(
            { host: '127.0.0.1', port: PORT, path: '/webhook/beamlink/pull-jobs', timeout: 15000 },
            (res) => {
                let body = '';
                res.on('data', (chunk) => (body += chunk));
                res.on('end', () => {
                    if (res.statusCode !== 200) {
                        log(`Print job auto-sync: HTTP ${res.statusCode}`);
                        return;
                    }
                    try {
                        const result = JSON.parse(body);
                        if (result.status === 'ok' && (result.imported || result.updated)) {
                            log(`Print job auto-sync: ${result.imported} imported, ${result.updated} updated`);
                        }
                    } catch (err) {
                        log(`Print job auto-sync: bad response (${err.message})`);
                    }
                });
            },
        );
        req.on('timeout', () => req.destroy());
        req.on('error', (err) => log(`Print job auto-sync: ${err.message}`));
    };

    printJobSyncTimer = setInterval(poll, PRINT_JOB_SYNC_INTERVAL_MS);
    poll(); // don't wait a full interval for the first pull
}

async function shutdown() {
    log('Shutting down');
    if (printJobSyncTimer) {
        clearInterval(printJobSyncTimer);
        printJobSyncTimer = null;
    }
    if (phpProcess && phpProcess.pid) {
        const pid = phpProcess.pid;
        phpProcess = null;
        await new Promise((resolve) => treeKill(pid, 'SIGTERM', () => resolve()));
    }
    await shutdownDatabase();
    log('Shutdown complete');
    if (logStream) logStream.end();
}

// A second instance would fight the first over the data directory and ports.
if (!app.requestSingleInstanceLock()) {
    app.quit();
} else {
    app.on('second-instance', () => {
        const win = mainWindow || splashWindow;
        if (win && !win.isDestroyed()) {
            if (win.isMinimized()) win.restore();
            win.focus();
        }
    });

    app.whenReady().then(async () => {
        initLogging();
        createSplash();
        try {
            await startup();
        } catch (err) {
            fail(err);
        }
    });

    app.on('window-all-closed', () => app.quit());

    app.on('before-quit', (event) => {
        if (isQuitting) return;
        isQuitting = true;
        event.preventDefault();
        shutdown()
            .catch((err) => log(`Shutdown error: ${err.message}`))
            .finally(() => app.exit(startupFailed ? 1 : 0));
    });
}
