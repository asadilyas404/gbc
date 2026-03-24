@echo off
cd /d "C:\xampp\htdocs\restaurant"
"C:\xampp\php\php.exe" artisan schedule:run >>"C:\xampp\htdocs\restaurant\storage\logs\scheduler-task.log" 2>&1
