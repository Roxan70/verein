@echo off
setlocal
set APP_DIR=%~dp0..
set PID_FILE=%APP_DIR%\runtime\server.pid
set PHP_EXE=%APP_DIR%\runtime\php\php.exe

if not exist "%PHP_EXE%" (
  echo Portable PHP runtime fehlt: %PHP_EXE%
  pause
  exit /b 1
)

if exist "%PID_FILE%" (
  set /p OLD_PID=<"%PID_FILE%"
  tasklist /FI "PID eq %OLD_PID%" | find "%OLD_PID%" >nul
  if not errorlevel 1 (
    start "" http://localhost:8080/login
    exit /b 0
  ) else (
    del "%PID_FILE%" >nul 2>&1
  )
)

cd /d "%APP_DIR%\public"
powershell -NoProfile -Command "$p=Start-Process -FilePath '%PHP_EXE%' -ArgumentList '-S 0.0.0.0:8080 index.php' -WorkingDirectory '%APP_DIR%\public' -WindowStyle Hidden -PassThru; Set-Content -Path '%PID_FILE%' -Value $p.Id"
timeout /t 2 >nul
start "" http://localhost:8080/login
endlocal
