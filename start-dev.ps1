# Start development environment for OSPOS on Windows
# Run from project root: .\start-dev.ps1

$ErrorActionPreference = "Stop"

$rootPath = "C:\Users\BEAM LINK\POS"
$binPath = Join-Path $rootPath "dist\electron\win-unpacked\resources\installer\bin"
$mariadbDir = Join-Path $binPath "mariadb"
$phpDir = Join-Path $binPath "php"
$publicDir = Join-Path $rootPath "dist\electron\win-unpacked\resources\public"
$rewriteScript = Join-Path $rootPath "vendor\codeigniter4\framework\system\rewrite.php"

$dataDir = Join-Path $env:APPDATA "@opensourcepos\opensourcepos"
$dbDir = Join-Path $dataDir "db"
$myIniPath = Join-Path $dataDir "my.ini"
$envPath = Join-Path $dataDir ".env"

$PORT = 8080
$DB_PORT = 3307

Write-Host "=== OSPOS Development Environment ===" -ForegroundColor Cyan
Write-Host "Data directory: $dataDir"
Write-Host "MariaDB dir: $mariadbDir"
Write-Host "PHP dir: $phpDir"

# Kill any existing processes
Write-Host "Cleaning up existing processes..."
Stop-Process -Name mariadbd -Force -ErrorAction SilentlyContinue
Stop-Process -Name mariadb -Force -ErrorAction SilentlyContinue
Stop-Process -Name "mariadb-admin" -Force -ErrorAction SilentlyContinue
Stop-Process -Name php -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 1

# Ensure directories
$dirs = @(
    $dataDir
    $dbDir
    (Join-Path $dataDir "sessions")
    (Join-Path $dataDir "writable")
    (Join-Path $dataDir "writable\cache")
    (Join-Path $dataDir "writable\logs")
    (Join-Path $dataDir "writable\session")
    (Join-Path $dataDir "writable\uploads")
    (Join-Path $dataDir "writable\backup")
    (Join-Path $dataDir "logs")
)
foreach ($dir in $dirs) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
        Write-Host "Created: $dir"
    }
}

# Copy .env.example as .env if not present
if (-not (Test-Path $envPath)) {
    $envSource = Join-Path $rootPath ".env.example"
    if (Test-Path $envSource) {
        Copy-Item $envSource $envPath -Force
        Write-Host "Created .env from .env.example"
    }
}

# Write my.ini with forward slashes
$fwdDataDir = $dataDir -replace '\\', '/'
$fwdMariadbDir = $mariadbDir -replace '\\', '/'

$myIniContent = "[mysqld]`r`nport=$DB_PORT`r`nbasedir=`"$fwdMariadbDir`"`r`ndatadir=`"$fwdDataDir/db`"`r`nplugin_dir=`"$fwdMariadbDir/lib/plugin`"`r`n`r`n[client]`r`nport=$DB_PORT`r`nplugin_dir=`"$fwdMariadbDir/lib/plugin`"`r`n"

Set-Content -Path $myIniPath -Value $myIniContent -Encoding ASCII
Write-Host "Created my.ini"

# Initialize MariaDB if needed
$isInitialized = Test-Path (Join-Path $dbDir "mysql")
if (-not $isInitialized) {
    Write-Host "Initializing MariaDB..."
    $installDb = Join-Path $mariadbDir "bin\mysql_install_db.exe"
    & $installDb "--datadir=$dbDir" "--password=" "--port=$DB_PORT" 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Error "Failed to initialize MariaDB"
        exit 1
    }
    Write-Host "MariaDB initialized"
}

# Fix PHP extension_dir in php.ini
$phpIni = Join-Path $phpDir "php.ini"
if (Test-Path $phpIni) {
    $iniContent = Get-Content $phpIni -Raw
    $extDir = (Join-Path $phpDir "ext") -replace '\\', '/'
    $iniContent = $iniContent -replace '^extension_dir\s*=\s*"ext".*$', "extension_dir = `"$extDir`""
    Set-Content -Path $phpIni -Value $iniContent -Encoding UTF8
    Write-Host "Fixed PHP extension_dir"
}

# Start MariaDB
Write-Host "Starting MariaDB..."
$mariadbd = Join-Path $mariadbDir "bin\mariadbd.exe"
$mariadbLog = Join-Path $dataDir "logs\mariadb.log"
$dbProcess = Start-Process -FilePath $mariadbd -ArgumentList "--defaults-file=`"$myIniPath`"" -PassThru -WindowStyle Hidden -RedirectStandardError $mariadbLog

# Give it a moment to start
Start-Sleep -Seconds 2

# Check if process is still alive
if (-not (Get-Process -Id $dbProcess.Id -ErrorAction SilentlyContinue)) {
    Write-Error "MariaDB process died. Check log: $mariadbLog"
    Get-Content $mariadbLog -ErrorAction SilentlyContinue
    exit 1
}

# Wait for MariaDB to be ready using mariadb-admin ping
$mariadbAdmin = Join-Path $mariadbDir "bin\mariadb-admin.exe"
$attempts = 0
$maxAttempts = 30

while ($attempts -lt $maxAttempts) {
    $attempts++
    Write-Host "Waiting for MariaDB... (attempt $attempts/$maxAttempts)"
    $pingProc = Start-Process -FilePath $mariadbAdmin -ArgumentList "--defaults-file=`"$myIniPath`"", "-u", "root", "-P", $DB_PORT, "-h", "127.0.0.1", "ping" -PassThru -Wait -NoNewWindow
    if ($pingProc.ExitCode -eq 0) {
        Write-Host "MariaDB is ready!" -ForegroundColor Green
        break
    }
    Start-Sleep -Seconds 1
}

if ($attempts -ge $maxAttempts) {
    Write-Error "MariaDB failed to start after $maxAttempts seconds"
    Stop-Process -Id $dbProcess.Id -Force
    exit 1
}

# Setup database and user
Write-Host "Setting up database and user..."
$mariadb = Join-Path $mariadbDir "bin\mariadb.exe"
$sql = "CREATE DATABASE IF NOT EXISTS ospos CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci; CREATE USER IF NOT EXISTS 'admin'@'127.0.0.1' IDENTIFIED BY 'pointofsale'; CREATE USER IF NOT EXISTS 'admin'@'localhost' IDENTIFIED BY 'pointofsale'; GRANT ALL PRIVILEGES ON ospos.* TO 'admin'@'127.0.0.1'; GRANT ALL PRIVILEGES ON ospos.* TO 'admin'@'localhost'; FLUSH PRIVILEGES;"

$setupArgs = @(
    "--defaults-file=$myIniPath",
    "-u", "root",
    "-P", "$DB_PORT",
    "-h", "127.0.0.1",
    "--execute=$sql"
)
& $mariadb $setupArgs
if ($LASTEXITCODE -ne 0) {
    Write-Error "Failed to setup database user"
    Stop-Process -Id $dbProcess.Id -Force
    exit 1
}
Write-Host "Database setup complete" -ForegroundColor Green

# Start PHP built-in server
Write-Host "Starting PHP server on http://127.0.0.1:$PORT ..."
$phpBinary = Join-Path $phpDir "php.exe"
$env:OSPOS_DATA_DIR = $dataDir
$env:PHPRC = $phpDir

$phpProcess = Start-Process -FilePath $phpBinary -ArgumentList "-S", "127.0.0.1:$PORT", "-t", "`"$publicDir`"", "`"$rewriteScript`"" -PassThru -WindowStyle Hidden

# Wait for PHP server to respond
Write-Host "Waiting for PHP server..."
$phpReady = $false
for ($i = 1; $i -le 30; $i++) {
    try {
        $response = Invoke-WebRequest -Uri "http://127.0.0.1:$PORT" -UseBasicParsing -TimeoutSec 2 -ErrorAction Stop
        if ($response.StatusCode -eq 200) {
            $phpReady = $true
            break
        }
    } catch {
        Start-Sleep -Seconds 1
    }
}

if (-not $phpReady) {
    Write-Error "PHP server failed to start"
    Stop-Process -Id $dbProcess.Id -Force
    Stop-Process -Id $phpProcess.Id -Force
    exit 1
}

Write-Host "`n=== Server Running ===" -ForegroundColor Cyan
Write-Host "OSPOS: http://127.0.0.1:$PORT"
Write-Host "Setup: http://127.0.0.1:$PORT/setup/"
Write-Host "`nPress Ctrl+C to stop all services`n"

# Keep script running and handle cleanup
try {
    while ($true) {
        Start-Sleep -Seconds 1
    }
}
finally {
    Write-Host "`nShutting down..."
    if ($phpProcess) { Stop-Process -Id $phpProcess.Id -Force -ErrorAction SilentlyContinue }
    if ($dbProcess) { Stop-Process -Id $dbProcess.Id -Force -ErrorAction SilentlyContinue }
    Write-Host "Stopped"
}