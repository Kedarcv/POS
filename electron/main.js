const { app, BrowserWindow, dialog } = require('electron');
const path = require('path');
const { spawn } = require('child_process');
const fs = require('fs');
const waitOn = require('wait-on');
const treeKill = require('tree-kill');

let mainWindow;
let phpProcess = null;
let dbProcess = null;

const APP_NAME = "BeamLink POS";
const PORT = 8080;
const DB_PORT = 3307;

// Determine paths based on whether we are in dev or packaged mode
const isPackaged = app.isPackaged;
const resourcesPath = isPackaged ? process.resourcesPath : path.join(__dirname, '..', 'installer', 'bin');
const rootPath = isPackaged ? process.resourcesPath : path.join(__dirname, '..');

const mariadbDir = path.join(resourcesPath, 'mariadb');
const phpDir = path.join(resourcesPath, 'php');
const appDir = path.join(rootPath, 'app');
const publicDir = path.join(rootPath, 'public');
const rewriteScript = path.join(rootPath, 'vendor', 'codeigniter4', 'framework', 'system', 'rewrite.php');

// User data path (C:\Users\username\AppData\Roaming\BeamLink POS on Windows)
const dataDir = app.getPath('userData');
const dbDir = path.join(dataDir, 'db');
const logsDir = path.join(dataDir, 'logs');

// Inject the environment variable so CodeIgniter knows where to look for .env and writable/
process.env.OSPOS_DATA_DIR = dataDir;

function ensureDirectories() {
    [dataDir, dbDir, logsDir, path.join(dataDir, 'sessions')].forEach(dir => {
        if (!fs.existsSync(dir)) {
            fs.mkdirSync(dir, { recursive: true });
        }
    });
}

function startDatabase() {
    return new Promise((resolve, reject) => {
        const myIniPath = path.join(dataDir, 'my.ini');
        const mariadbdPath = path.join(mariadbDir, 'bin', process.platform === 'win32' ? 'mariadbd.exe' : 'mariadbd');
        const mariadbPath = path.join(mariadbDir, 'bin', process.platform === 'win32' ? 'mariadb.exe' : 'mariadb');

        // Forward slashes for MySQL paths
        const fwdDataDir = dataDir.replace(/\\/g, '/');
        const fwdMariadbDir = mariadbDir.replace(/\\/g, '/');

        if (!fs.existsSync(myIniPath)) {
            let myIniContent = `[mysqld]\nport=${DB_PORT}\n`;
            if (process.platform === 'win32') {
                myIniContent += `basedir="${fwdMariadbDir}"\ndatadir="${fwdDataDir}/db"\nplugin_dir="${fwdMariadbDir}/lib/plugin"\n`;
                myIniContent += `\n[client]\nport=${DB_PORT}\nplugin_dir="${fwdMariadbDir}/lib/plugin"\n`;
            } else {
                myIniContent += `datadir="${fwdDataDir}/db"\nsocket="${fwdDataDir}/mysql.sock"\n`;
                myIniContent += `\n[client]\nport=${DB_PORT}\nsocket="${fwdDataDir}/mysql.sock"\n`;
            }
            fs.writeFileSync(myIniPath, myIniContent);
        }

        const isInitialized = fs.existsSync(path.join(dbDir, 'mysql'));

        if (!isInitialized) {
            console.log("Initializing MariaDB...");
            let initCmd = mariadbdPath;
            let initArgs = ['--defaults-file=' + myIniPath, '--initialize-insecure'];
            
            // on macOS/Linux we use mariadb-install-db
            if (process.platform !== 'win32') {
                initCmd = path.join(mariadbDir, 'bin', 'mariadb-install-db');
                initArgs = ['--datadir=' + dbDir];
            }

            const initProcess = spawn(initCmd, initArgs);
            initProcess.on('close', (code) => {
                if (code !== 0) return reject(new Error('Failed to initialize database'));
                runDb(myIniPath, mariadbdPath, mariadbPath).then(resolve).catch(reject);
            });
        } else {
            runDb(myIniPath, mariadbdPath, mariadbPath).then(resolve).catch(reject);
        }
    });
}

function runDb(myIniPath, mariadbdPath, mariadbPath) {
    return new Promise((resolve, reject) => {
        console.log("Starting MariaDB...");
        dbProcess = spawn(mariadbdPath, ['--defaults-file=' + myIniPath]);

        dbProcess.stderr.on('data', data => console.log(`MariaDB: ${data}`));
        
        // Wait 3 seconds for MariaDB to boot up and listen
        setTimeout(() => {
            console.log("Setting up database and user...");
            let sql = "CREATE DATABASE IF NOT EXISTS ospos CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;";
            sql += "CREATE USER IF NOT EXISTS 'admin'@'127.0.0.1' IDENTIFIED BY 'pointofsale';";
            sql += "GRANT ALL PRIVILEGES ON ospos.* TO 'admin'@'127.0.0.1';";
            sql += "FLUSH PRIVILEGES;";
            
            let sockArgs = [];
            if (process.platform !== 'win32') {
                sockArgs = ['--socket=' + path.join(dataDir, 'mysql.sock')];
            }

            const setupProc = spawn(mariadbPath, ['-u', 'root', '-P', DB_PORT, '-h', '127.0.0.1', ...sockArgs, '-e', sql]);
            setupProc.on('close', (code) => {
                resolve();
            });
        }, 3000);
    });
}

function startPHP() {
    console.log("Starting PHP...");
    const phpBinary = path.join(phpDir, process.platform === 'win32' ? 'php.exe' : 'bin/php');
    
    // We run the PHP built-in server with rewrite.php to mimic Apache/nginx routing
    phpProcess = spawn(phpBinary, [
        '-S', `127.0.0.1:${PORT}`, 
        '-t', publicDir, 
        rewriteScript
    ], {
        env: { ...process.env, OSPOS_DATA_DIR: dataDir }
    });

    phpProcess.stdout.on('data', data => console.log(`PHP: ${data}`));
    phpProcess.stderr.on('data', data => console.log(`PHP Error: ${data}`));
}

function createWindow() {
    mainWindow = new BrowserWindow({
        width: 1280,
        height: 800,
        show: false,
        autoHideMenuBar: true,
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true
        }
    });

    mainWindow.maximize();
    
    // Wait for the PHP server to respond
    waitOn({
        resources: [`http://127.0.0.1:${PORT}`],
        timeout: 30000
    }).then(() => {
        let targetUrl = `http://127.0.0.1:${PORT}`;
        // If .env doesn't exist, we force them to setup wizard
        if (!fs.existsSync(path.join(dataDir, '.env'))) {
            targetUrl += '/setup/';
        }
        mainWindow.loadURL(targetUrl);
        mainWindow.show();
    }).catch(err => {
        dialog.showErrorBox("Startup Error", "Failed to start the local web server in time.\n\n" + err);
        app.quit();
    });

    mainWindow.on('closed', () => {
        mainWindow = null;
    });
}

app.whenReady().then(async () => {
    try {
        ensureDirectories();
        await startDatabase();
        startPHP();
        createWindow();
    } catch (e) {
        dialog.showErrorBox("Startup Error", e.toString());
        app.quit();
    }
});

app.on('window-all-closed', () => {
    app.quit();
});

app.on('will-quit', () => {
    if (phpProcess && phpProcess.pid) treeKill(phpProcess.pid);
    if (dbProcess && dbProcess.pid) treeKill(dbProcess.pid);
});
