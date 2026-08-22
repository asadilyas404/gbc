@echo off

cd /d C:\xampp\htdocs\restaurant

C:\xampp\php\php.exe artisan websockets:serve --host=0.0.0.0 --port=6001 >> C:\xampp\htdocs\restaurant\storage\logs\websocket-server.log 2>&1