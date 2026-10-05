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
echo [1/6] Stashing local changes...
git stash
if errorlevel 1 goto :error

echo.
echo [2/6] Pulling latest code from main...
git pull origin main
if errorlevel 1 goto :error

echo.
echo [3/6] Clearing optimization cache...
php artisan optimize:clear
if errorlevel 1 goto :error

echo.
echo [4/6] Optimizing application...
php artisan optimize
if errorlevel 1 goto :error

echo.
echo [5/6] Caching config...
php artisan config:cache
php artisan queue:restart
if errorlevel 1 goto :error

echo.
echo [6/6] Syncronizing Database...
php artisan sync:schema ORDERS
php artisan sync:schema ORDER_DETAILS
php artisan sync:schema POS_ORDER_ADDITIONAL_DTL
php artisan sync:schema ORDER_WHATSAPP_MSG_LOG
php artisan sync:schema TRANSLATIONS
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
