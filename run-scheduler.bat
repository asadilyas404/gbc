@echo off
cd /d C:\xampp\htdocs\restaurant

REM Run Laravel scheduler once
C:\xampp\php\php.exe artisan schedule:run >> storage\logs\scheduler.log 2>&1
