; BeamLink POS - Windows NSIS Installer
; Creates a Windows installer (.exe) with bundled PHP, MariaDB, and Caddy

!include "MUI2.nsh"
!include "LogicLib.nsh"

;--------------------------------
; General
!define APP_NAME "BeamLink POS"
!define APP_VERSION "3.4.2"
!define APP_PUBLISHER "Second Spark Intelligence"
!define APP_URL "https://secondspark.co.zw"
!define INSTALL_DIR "$LOCALAPPDATA\BeamLink POS"
!define DATA_DIR "$LOCALAPPDATA\BeamLink POS\data"

;--------------------------------
; Installer Attributes
Name "${APP_NAME} ${APP_VERSION}"
OutFile "BeamLink-POS-${APP_VERSION}-windows-x64.exe"
InstallDir "${INSTALL_DIR}"
RequestExecutionLevel admin

;--------------------------------
; Interface
!define MUI_ABORTWARNING
!define MUI_ICON "assets\icon.ico"
!define MUI_UNICON "assets\icon.ico"
!define MUI_WELCOMEPAGE_TITLE "Welcome to ${APP_NAME} Setup"
!define MUI_WELCOMEPAGE_TEXT "This wizard will guide you through the installation of ${APP_NAME} ${APP_VERSION}.$\r$\n$\r$\nThe installer will bundle:${\n}- PHP 8.2 (no installation needed)$\r$\n- MariaDB (database server)$\r$\n- Caddy (web server)$\r$\n$\r$\nClick Next to continue."
!define MUI_FINISHPAGE_RUN "$INSTDIR\BeamLink POS.exe"
!define MUI_FINISHPAGE_RUN_TEXT "Launch ${APP_NAME}"
!define MUI_FINISHPAGE_LINK "Visit our website"
!define MUI_FINISHPAGE_LINK_LOCATION "${APP_URL}"

;--------------------------------
; Pages
!insertmacro MUI_PAGE_WELCOME
!insertmacro MUI_PAGE_LICENSE "LICENSE"
!insertmacro MUI_PAGE_DIRECTORY
!insertmacro MUI_PAGE_INSTFILES
!insertmacro MUI_PAGE_FINISH

!insertmacro MUI_UNPAGE_CONFIRM
!insertmacro MUI_UNPAGE_INSTFILES

;--------------------------------
; Languages
!insertmacro MUI_LANGUAGE "English"

;--------------------------------
; Installer Sections
Section "Install" SecInstall
    SetOutPath "$INSTDIR"

    ; Create directories
    CreateDirectory "$INSTDIR\php"
    CreateDirectory "$INSTDIR\mariadb"
    CreateDirectory "$INSTDIR\caddy"
    CreateDirectory "$INSTDIR\app"
    CreateDirectory "$DATA_DIR"
    CreateDirectory "$DATA_DIR\logs"
    CreateDirectory "$DATA_DIR\sessions"
    CreateDirectory "$DATA_DIR\pids"

    ; Copy PHP
    DetailPrint "Installing PHP 8.2..."
    SetOutPath "$INSTDIR\php"
    File /r "bin\php-win-x64\*.*"

    ; Copy MariaDB
    DetailPrint "Installing MariaDB..."
    SetOutPath "$INSTDIR\mariadb"
    File /r "bin\mariadb-win-x64\*.*"

    ; Copy Caddy
    DetailPrint "Installing Caddy..."
    SetOutPath "$INSTDIR\caddy"
    File /r "bin\caddy-win-x64\*.*"

    ; Copy app files
    DetailPrint "Installing application..."
    SetOutPath "$INSTDIR\app"
    File /r "..\app\*.*"

    ; Copy launcher
    SetOutPath "$INSTDIR"
    File "launcher.bat"

    ; Copy setup wizard
    SetOutPath "$INSTDIR\setup"
    File "setup\index.php"

    ; Copy config
    SetOutPath "$INSTDIR\config"
    File "config\.env.example"

    ; Create launcher executable (batch wrapper)
    FileOpen $0 "$INSTDIR\BeamLink POS.exe" w
    FileWrite $0 '@echo off$\r$\n'
    FileWrite $0 'cd /d "$INSTDIR"$\r$\n'
    FileWrite $0 'call launcher.bat$\r$\n'
    FileClose $0

    ; Initialize MariaDB
    DetailPrint "Initializing database..."
    nsExec::ExecToLog '"$INSTDIR\mariadb\bin\mariadb-install-db.exe" --datadir="$DATA_DIR\db" --basedir="$INSTDIR\mariadb"'

    ; Start MariaDB temporarily
    DetailPrint "Starting MariaDB for setup..."
    nsExec::ExecToLog '"$INSTDIR\mariadb\bin\mariadbd.exe" --defaults-file="$INSTDIR\mariadb\my.ini" --console'

    ; Wait for MariaDB
    Sleep 5000

    ; Create database and user
    DetailPrint "Creating database..."
    nsExec::ExecToLog '"$INSTDIR\mariadb\bin\mariadb.exe" -u root -e "CREATE DATABASE IF NOT EXISTS beamlink_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"'
    nsExec::ExecToLog '"$INSTDIR\mariadb\bin\mariadb.exe" -u root -e "CREATE USER IF NOT EXISTS '\''beamlink'\''@'\''127.0.0.1'\'' IDENTIFIED BY '\''beamlink_secure_pw'\'';"'
    nsExec::ExecToLog '"$INSTDIR\mariadb\bin\mariadb.exe" -u root -e "GRANT ALL PRIVILEGES ON beamlink_pos.* TO '\''beamlink'\''@'\''127.0.0.1'\'';"'
    nsExec::ExecToLog '"$INSTDIR\mariadb\bin\mariadb.exe" -u root -e "FLUSH PRIVILEGES;"'

    ; Stop MariaDB
    nsExec::ExecToLog '"$INSTDIR\mariadb\bin\mariadb.exe" -u root -e "SHUTDOWN;"'

    ; Create .env
    DetailPrint "Creating configuration..."
    FileOpen $0 "$INSTDIR\.env" w
    FileWrite $0 'CI_ENVIRONMENT = production$\r$\n'
    FileWrite $0 'app.baseURL = http://localhost:8080$\r$\n'
    FileWrite $0 '$\r$\n'
    FileWrite $0 'database.default.hostname = 127.0.0.1$\r$\n'
    FileWrite $0 'database.default.database = beamlink_pos$\r$\n'
    FileWrite $0 'database.default.username = beamlink$\r$\n'
    FileWrite $0 'database.default.password = beamlink_secure_pw$\r$\n'
    FileWrite $0 'database.default.DBPrefix = ospos_$\r$\n'
    FileWrite $0 'database.default.port = 3307$\r$\n'
    FileWrite $0 '$\r$\n'
    FileWrite $0 'appTimezone = Africa/Harare$\r$\n'
    FileWrite $0 'BEAMLINK_API_URL = https://beamlink-erp.vercel.app$\r$\n'
    FileWrite $0 'BEAMLINK_API_KEY = beamlink-pos-local-2026$\r$\n'
    FileWrite $0 'WHATSAPP_TOKEN = EAAHQnEhuTywB...$\r$\n'
    FileWrite $0 'WHATSAPP_PHONE_NUMBER_ID = 1122313694297443$\r$\n'
    FileWrite $0 'WHATSAPP_BUSINESS_ACCOUNT_ID = 998404866041041$\r$\n'
    FileWrite $0 'WHATSAPP_VERIFY_TOKEN = beamlink-erp-webhook-2026$\r$\n'
    FileWrite $0 'WHATSAPP_BOT_NUMBER = +263717174443$\r$\n'
    FileWrite $0 'WHATSAPP_WEBHOOK_URL = http://localhost:8080/webhook/whatsapp$\r$\n'
    FileWrite $0 'OSPOS_WEBHOOK_SECRET = beamlink-webhook-2026$\r$\n'
    FileClose $0

    ; Create Start Menu shortcuts
    CreateDirectory "$SMPROGRAMS\${APP_NAME}"
    CreateShortCut "$SMPROGRAMS\${APP_NAME}\${APP_NAME}.lnk" "$INSTDIR\BeamLink POS.exe"
    CreateShortCut "$SMPROGRAMS\${APP_NAME}\Uninstall.lnk" "$INSTDIR\Uninstall.exe"

    ; Create Desktop shortcut
    CreateShortCut "$DESKTOP\${APP_NAME}.lnk" "$INSTDIR\BeamLink POS.exe"

    ; Store uninstaller
    WriteUninstaller "$INSTDIR\Uninstall.exe"

    ; Add to Programs and Features
    WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\${APP_NAME}" "DisplayName" "${APP_NAME}"
    WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\${APP_NAME}" "UninstallString" '"$INSTDIR\Uninstall.exe"'
    WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\${APP_NAME}" "InstallLocation" "$INSTDIR"
    WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\${APP_NAME}" "DisplayVersion" "${APP_VERSION}"
    WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\${APP_NAME}" "Publisher" "${APP_PUBLISHER}"
    WriteRegDWORD HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\${APP_NAME}" "NoModify" 1
    WriteRegDWORD HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\${APP_NAME}" "NoRepair" 1
SectionEnd

;--------------------------------
; Uninstaller Section
Section "Uninstall"
    ; Stop services
    nsExec::ExecToLog 'taskkill /IM mariadbd.exe /F'
    nsExec::ExecToLog 'taskkill /IM php.exe /F'
    nsExec::ExecToLog 'taskkill /IM caddy.exe /F'

    Sleep 2000

    ; Remove files
    RMDir /r "$INSTDIR\php"
    RMDir /r "$INSTDIR\mariadb"
    RMDir /r "$INSTDIR\caddy"
    RMDir /r "$INSTDIR\app"
    RMDir /r "$INSTDIR\setup"
    RMDir /r "$INSTDIR\config"
    Delete "$INSTDIR\BeamLink POS.exe"
    Delete "$INSTDIR\launcher.bat"
    Delete "$INSTDIR\.env"
    Delete "$INSTDIR\Uninstall.exe"
    RMDir /r "$INSTDIR"

    ; Remove shortcuts
    RMDir /r "$SMPROGRAMS\${APP_NAME}"
    Delete "$DESKTOP\${APP_NAME}.lnk"

    ; Remove registry entries
    DeleteRegKey HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\${APP_NAME}"
SectionEnd
