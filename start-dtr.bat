@echo off
title DTR System - Starting Services...

echo ============================================
echo    DTR System - Auto Start
echo ============================================
echo.

:: Start Apache
echo [1/3] Starting Apache...
start "" /B "C:\xampp\apache\bin\httpd.exe"
timeout /t 2 /nobreak >nul

:: Start MySQL
echo [2/3] Starting MySQL...
start "" /B "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini"
timeout /t 3 /nobreak >nul

:: Wait for MySQL to be ready
echo [3/3] Waiting for services to be ready...
timeout /t 3 /nobreak >nul

echo.
echo ============================================
echo    All services started!
echo    Access: http://localhost
echo ============================================
echo.
echo Press any key to stop all services...
pause >nul

:: Stop services when user presses a key
echo.
echo Stopping services...
taskkill /F /IM httpd.exe >nul 2>&1
taskkill /F /IM mysqld.exe >nul 2>&1
echo Done.
