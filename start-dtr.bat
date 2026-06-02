@echo off
title DTR System - Running

echo ============================================
echo    DTR System - Starting Services...
echo ============================================
echo.

:: Start Apache
echo [1/3] Starting Apache...
start "" /B "C:\xampp\apache\bin\httpd.exe"
timeout /t 2 /nobreak >nul

:: Start MySQL
echo [2/3] Starting MySQL...
start "" /B "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone
timeout /t 3 /nobreak >nul

:: Start Laravel
echo [3/3] Starting Laravel...
start "" /B cmd /c "cd /d C:\xampp\htdocs\siarelco && C:\xampp\php\php.exe artisan serve --host=0.0.0.0 --port=8000"
timeout /t 2 /nobreak >nul

echo.
echo ============================================
echo    All services running!
echo.
echo    Access via Apache:  http://localhost
echo    Access via Laravel: http://localhost:8000
echo.
echo    Press any key to STOP all services...
echo ============================================
pause >nul

:: Stop everything
echo.
echo Stopping services...
taskkill /F /IM httpd.exe >nul 2>&1
taskkill /F /IM mysqld.exe >nul 2>&1
taskkill /F /FI "WINDOWTITLE eq C:\xampp\php\php.exe*" >nul 2>&1
echo Done. Goodbye.
timeout /t 2 >nul
