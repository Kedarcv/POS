@echo off
setlocal enabledelayedexpansion

:: BeamLink POS - Windows Launcher
:: Starts MariaDB, PHP, and Caddy, then opens the browser

title BeamLink POS
color 0A

set "APP_NAME=BeamLink POS"
set "INSTALL_DIR=%~dp0"
set "DATA_DIR=%INSTALL_DIR%data"
set "LOG_DIR=%DATA_DIR%\logs"
set "PID_DIR=%DATA_DIR%\pids"
set "MARIADB_DIR=%INSTALL_DIR%mariadb"

:: Auto-detect nested MariaDB folder if needed
if not exist "%MARIADB_DIR%\bin\mariadbd.exe" (
    for /d %%d in ("%MARIADB_DIR%\mariadb-*") do (
        if exist "%%d\bin\mariadbd.exe" (
            set "MARIADB_DIR=%%d"
        )
    )
)

set "PHP_DIR=%INSTALL_DIR%php"
set "CADDY_DIR=%INSTALL_DIR%caddy"
set "APP_DIR=%INSTALL_DIR%app"
set "PORT=8080"

echo [%APP_NAME%] Starting services...

:: Create directories
if not exist "%DATA_DIR%" mkdir "%DATA_DIR%"
if not exist "%LOG_DIR%" mkdir "%LOG_DIR%"
if not exist "%PID_DIR%" mkdir "%PID_DIR%"
if not exist "%DATA_DIR%\sessions" mkdir "%DATA_DIR%\sessions"
if not exist "%DATA_DIR%\db" mkdir "%DATA_DIR%\db"

:: Create CodeIgniter 4 writable directories
if not exist "%APP_DIR%\writable" mkdir "%APP_DIR%\writable"
if not exist "%APP_DIR%\writable\cache" mkdir "%APP_DIR%\writable\cache"
if not exist "%APP_DIR%\writable\debugbar" mkdir "%APP_DIR%\writable\debugbar"
if not exist "%APP_DIR%\writable\logs" mkdir "%APP_DIR%\writable\logs"
if not exist "%APP_DIR%\writable\session" mkdir "%APP_DIR%\writable\session"
if not exist "%APP_DIR%\writable\uploads" mkdir "%APP_DIR%\writable\uploads"
if not exist "%APP_DIR%\writable\backup" mkdir "%APP_DIR%\writable\backup"

:: Generate forward-slash paths for MariaDB and Caddy
set "FWD_MARIADB_DIR=%MARIADB_DIR:\=/%"
set "FWD_DATA_DIR=%DATA_DIR:\=/%"
set "FWD_APP_DIR=%APP_DIR:\=/%"

:: Create my.ini BEFORE initializing database
(
    echo [mysqld]
    echo port=3307
    echo basedir="%FWD_MARIADB_DIR%"
    echo datadir="%FWD_DATA_DIR%/db"
    echo plugin_dir="%FWD_MARIADB_DIR%/lib/plugin"
    echo lc-messages-dir="%FWD_MARIADB_DIR%/share"
    echo socket="%FWD_DATA_DIR%/mariadb.sock"
    echo skip-name-resolve
    echo innodb_buffer_pool_size=64M
    echo innodb_log_file_size=8M
    echo max_allowed_packet=64M
    echo character-set-server=utf8mb4
    echo collation-server=utf8mb4_general_ci
    echo bind-address=127.0.0.1
    echo.
    echo [client]
    echo port=3307
    echo socket="%FWD_DATA_DIR%/mariadb.sock"
    echo default-character-set=utf8mb4
) > "%DATA_DIR%\my.ini"

:: Check if already running
if exist "%PID_DIR%\mariadb.pid" (
    set /p PID=<"%PID_DIR%\mariadb.pid"
    tasklist /FI "PID eq !PID!" 2>NUL | find /I "mariadbd" >NUL
    if !errorlevel! equ 0 (
        echo [%APP_NAME%] Already running on port %PORT%
        start http://localhost:%PORT%
        exit /b
    )
)

:: Initialize MariaDB if needed
if not exist "%DATA_DIR%\db\mysql" (
    echo [%APP_NAME%] Initializing database...
    if exist "%DATA_DIR%\db" rmdir /s /q "%DATA_DIR%\db" 2>NUL
    mkdir "%DATA_DIR%\db"
    
    if exist "%MARIADB_DIR%\bin\mariadb-install-db.exe" (
        "%MARIADB_DIR%\bin\mariadb-install-db.exe" --datadir="%FWD_DATA_DIR%/db" > "!LOG_DIR!\db_init.log" 2>&1
    ) else if exist "%MARIADB_DIR%\bin\mysql_install_db.exe" (
        "%MARIADB_DIR%\bin\mysql_install_db.exe" --datadir="%FWD_DATA_DIR%/db" > "!LOG_DIR!\db_init.log" 2>&1
    ) else if exist "%MARIADB_DIR%\bin\mariadbd.exe" (
        "%MARIADB_DIR%\bin\mariadbd.exe" --defaults-file="%DATA_DIR%\my.ini" --initialize-insecure > "!LOG_DIR!\db_init.log" 2>&1
    )
    if !errorlevel! neq 0 (
        echo [%APP_NAME%] ERROR: Failed to initialize database.
        if exist "!LOG_DIR!\db_init.log" (
            echo --- Log Output ---
            type "!LOG_DIR!\db_init.log"
            echo ------------------
        )
        pause
        exit /b 1
    )
    echo [%APP_NAME%] Database initialized.
)

:: Generate DB password if not exists
if not exist "%DATA_DIR%\.db_pass" (
    powershell -Command "[System.Guid]::NewGuid().ToString('N')" > "%DATA_DIR%\.db_pass"
)

set /p DB_PASS=<"%DATA_DIR%\.db_pass"

:: Start MariaDB
echo [%APP_NAME%] Starting MariaDB on port 3307...
start /B "" "%MARIADB_DIR%\bin\mariadbd.exe" --defaults-file="%DATA_DIR%\my.ini" --console
echo !PID! > "%PID_DIR%\mariadb.pid"

:: Wait for MariaDB
echo [%APP_NAME%] Waiting for MariaDB...
timeout /t 5 /nobreak >NUL

:: Create user and database
"%MARIADB_DIR%\bin\mariadb.exe" -u root -e "CREATE DATABASE IF NOT EXISTS beamlink_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;" 2>NUL
"%MARIADB_DIR%\bin\mariadb.exe" -u root -e "CREATE USER IF NOT EXISTS 'beamlink'@'127.0.0.1' IDENTIFIED BY '%DB_PASS%';" 2>NUL
"%MARIADB_DIR%\bin\mariadb-admin.exe" -u root reload 2>NUL || "%MARIADB_DIR%\bin\mariadb.exe" -u root -e "FLUSH PRIVILEGES;" 2>NUL
"%MARIADB_DIR%\bin\mariadb.exe" -u root -e "GRANT ALL PRIVILEGES ON beamlink_pos.* TO 'beamlink'@'127.0.0.1';" 2>NUL
"%MARIADB_DIR%\bin\mariadb.exe" -u root -e "FLUSH PRIVILEGES;" 2>NUL

echo [%APP_NAME%] MariaDB ready.

:: Create CodeIgniter 4 configuration if needed
:: (Setup wizard handles this now, but we just ensure the DB pass is ready)

:: Create php.ini
(
    echo memory_limit = 256M
    echo max_execution_time = 300
    echo upload_max_filesize = 100M
    echo post_max_size = 100M
    echo max_input_time = 300
    echo error_reporting = E_ALL ^& ~E_NOTICE ^& ~E_STRICT ^& ~E_DEPRECATED
    echo display_errors = Off
    echo log_errors = On
    echo error_log = %LOG_DIR%\php_error.log
    echo session.save_path = %DATA_DIR%\sessions
    echo date.timezone = Africa/Harare
    echo extension_dir = "%PHP_DIR%\ext"
    echo extension=mysqli
    echo extension=pdo_mysql
    echo extension=gd
    echo extension=zip
    echo extension=mbstring
    echo extension=curl
    echo extension=openssl
    echo extension=intl
    echo extension=fileinfo
    echo extension=bcmath
) > "%DATA_DIR%\php.ini"

:: Start PHP
echo [%APP_NAME%] Starting PHP on port 9000...
start /B "" "%PHP_DIR%\php.exe" -S 127.0.0.1:9000 -t "%APP_DIR%\public" -c "%DATA_DIR%\php.ini"
echo !PID! > "%PID_DIR%\php.pid"
echo [%APP_NAME%] PHP ready.

:: Create Caddyfile
(
    echo :8080 {
    echo     reverse_proxy 127.0.0.1:9000
    echo }
) > "%DATA_DIR%\Caddyfile"

:: Start Caddy
echo [%APP_NAME%] Starting Caddy on port %PORT%...
start /B "" "%CADDY_DIR%\caddy.exe" start --config "%DATA_DIR%\Caddyfile" --adapter caddyfile
echo !PID! > "%PID_DIR%\caddy.pid"
echo [%APP_NAME%] Caddy ready.

:: Wait for services
timeout /t 3 /nobreak >NUL

:: Check if first run
if not exist "%DATA_DIR%\.installed" (
    echo [%APP_NAME%] First run detected - opening setup wizard...
    start http://localhost:%PORT%/setup/
) else (
    echo [%APP_NAME%] Running at http://localhost:%PORT%
    start http://localhost:%PORT%
)

echo.
echo [%APP_NAME%] Press any key to stop all services...
pause >NUL

:: Stop services
echo [%APP_NAME%] Stopping services...
FOR /F "tokens=5" %%a IN ('netstat -aon ^| find ":8080" ^| find "LISTENING"') DO taskkill /PID %%a /F /T >NUL 2>&1
FOR /F "tokens=5" %%a IN ('netstat -aon ^| find ":9000" ^| find "LISTENING"') DO taskkill /PID %%a /F /T >NUL 2>&1
FOR /F "tokens=5" %%a IN ('netstat -aon ^| find ":3307" ^| find "LISTENING"') DO taskkill /PID %%a /F /T >NUL 2>&1

:: Clean up PID files just in case
if exist "%PID_DIR%\caddy.pid" del "%PID_DIR%\caddy.pid"
if exist "%PID_DIR%\php.pid" del "%PID_DIR%\php.pid"
if exist "%PID_DIR%\mariadb.pid" del "%PID_DIR%\mariadb.pid"

echo [%APP_NAME%] All services stopped.
