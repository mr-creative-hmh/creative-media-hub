@echo off
setlocal enabledelayedexpansion
title Creative Media Hub
cd /d "%~dp0"

echo ===================================================
echo             CREATIVE MEDIA HUB
echo ===================================================
echo.

:: Detect PHP (portable in ./php or system PATH)
set "PHP_BIN=php"
if exist "%~dp0php\php.exe" (
    set "PHP_BIN=%~dp0php\php.exe"
    echo [*] Using bundled portable PHP: !PHP_BIN!
) else (
    where php >nul 2>&1
    if !errorlevel! equ 0 (
        echo [*] Using system PHP from PATH
    ) else (
        echo [!] ERROR: PHP executable not found!
        echo [*] Please either install PHP on your system or place a portable PHP folder at:
        echo     "%~dp0php\php.exe"
        echo.
        pause
        exit /b 1
    )
)

:: Ensure .env exists
if not exist ".env" (
    echo [*] Creating .env configuration from .env.example...
    copy ".env.example" ".env" >nul
    !PHP_BIN! artisan key:generate --force
)

:: Ensure SQLite database exists
if not exist "database\database.sqlite" (
    echo [*] Initializing SQLite database...
    type nul > "database\database.sqlite"
    !PHP_BIN! artisan migrate --force
)

:: Set port
set "PORT=8088"
set "HOST=127.0.0.1"
set "URL=http://%HOST%:%PORT%"

:: Check if Electron is installed for native desktop experience
if exist "node_modules\electron\dist\electron.exe" (
    echo [*] Starting Creative Media Hub in Native Desktop Window...
    start "" "node_modules\electron\dist\electron.exe" "%~dp0desktop\main.cjs"
    exit /b 0
)

echo [*] Starting Creative Media Hub Web Server on %URL% ...
echo [*] Press Ctrl+C or close this window to stop the server.
echo.

:: Open default browser after 1.5 seconds
start "" cmd /c "timeout /t 2 /nobreak >nul & start %URL%"

:: Start PHP Built-in Server
!PHP_BIN! artisan serve --host=%HOST% --port=%PORT%

pause
