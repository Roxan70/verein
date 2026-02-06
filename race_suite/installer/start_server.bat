@echo off
set APP_DIR=%~dp0..
cd /d %APP_DIR%\public
start "EU Windhound Race Suite" "%APP_DIR%\runtime\php\php.exe" -S 0.0.0.0:8080 index.php
timeout /t 2 >nul
start "" http://localhost:8080/login
