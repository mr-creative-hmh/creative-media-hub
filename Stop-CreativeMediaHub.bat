@echo off
title Stop Creative Media Hub
echo [*] Stopping Creative Media Hub server on port 8088...

for /f "tokens=5" %%a in ('netstat -aon ^| findstr ":8088" ^| findstr "LISTENING"') do (
    echo [*] Terminating process PID %%a
    taskkill /F /PID %%a >nul 2>&1
)

echo [*] Creative Media Hub stopped successfully.
timeout /t 2 >nul
