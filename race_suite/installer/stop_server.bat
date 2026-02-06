@echo off
setlocal
set APP_DIR=%~dp0..
set PID_FILE=%APP_DIR%\runtime\server.pid

if not exist "%PID_FILE%" (
  echo Kein laufender Server (keine PID-Datei).
  exit /b 0
)

set /p PID=<"%PID_FILE%"
taskkill /PID %PID% /F >nul 2>&1
if errorlevel 1 (
  echo Server konnte nicht per PID beendet werden. Versuche Port 8080.
  for /f "tokens=5" %%a in ('netstat -aon ^| find ":8080" ^| find "LISTENING"') do taskkill /PID %%a /F >nul 2>&1
)

del "%PID_FILE%" >nul 2>&1
echo Server gestoppt.
endlocal
