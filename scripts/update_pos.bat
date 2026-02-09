@echo off
setlocal ENABLEEXTENSIONS ENABLEDELAYEDEXPANSION

echo ===============================
echo   POS UPDATE STARTED
echo ===============================

REM Go to Laravel project root (one level up from /scripts)
cd /d "%~dp0.."

if not exist artisan (
    echo ERROR: artisan file not found. Are you in Laravel root?
    pause
    exit /b 1
)

echo.
echo [1/5] Stashing local changes...
git stash
if errorlevel 1 goto :error

echo.
echo [2/5] Pulling latest code from main...
git pull origin main
if errorlevel 1 goto :error

echo.
echo [3/5] Clearing optimization cache...
php artisan optimize:clear
if errorlevel 1 goto :error

echo.
echo [4/5] Optimizing application...
php artisan optimize
if errorlevel 1 goto :error

echo.
echo [5/5] Caching config...
php artisan config:cache
if errorlevel 1 goto :error

echo.
echo ===============================
echo   POS UPDATE COMPLETED
echo ===============================
pause
exit /b 0

:error
echo.
echo ❌ UPDATE FAILED – check messages above
pause
exit /b 1
