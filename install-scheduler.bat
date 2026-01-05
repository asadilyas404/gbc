@echo off
setlocal enabledelayedexpansion

REM =============================
REM CONFIG (ONLY PROJECT PATH)
REM =============================
set "TASK_NAME=MalekAlPizza Laravel Scheduler"
set "PROJECT_DIR=C:\xampp\htdocs\restaurant"
set "LOGFILE=%PROJECT_DIR%\storage\logs\scheduler-task.log"

REM =============================
REM AUTO-DETECT XAMPP + PHP
REM =============================
set "PHP_EXE="

if exist "C:\xampp\php\php.exe" (
    set "PHP_EXE=C:\xampp\php\php.exe"
) else if exist "C:\xampp7.4\php\php.exe" (
    set "PHP_EXE=C:\xampp7.4\php\php.exe"
) else (
    echo [ERROR] php.exe not found in C:\xampp or C:\xampp7.4
    echo Please edit the script and set PHP_EXE manually.
    pause
    exit /b 1
)

REM =============================
REM VALIDATION
REM =============================
if not exist "%PROJECT_DIR%\artisan" (
    echo [ERROR] artisan not found at: %PROJECT_DIR%\artisan
    pause
    exit /b 1
)

if not exist "%PROJECT_DIR%\storage\logs" (
    mkdir "%PROJECT_DIR%\storage\logs" >nul 2>&1
)

echo [INFO] Using PHP: %PHP_EXE%
echo.

REM =============================
REM 1) Ensure Windows Time Service
REM =============================
echo [INFO] Ensuring Windows Time service is running...
sc config w32time start= auto >nul 2>&1
sc start w32time >nul 2>&1

w32tm /query /status >nul 2>&1
w32tm /resync >nul 2>&1

REM =============================
REM 2) Create run-schedule.cmd
REM =============================
set "RUNNER_CMD=%PROJECT_DIR%\run-schedule.cmd"

echo [INFO] Creating %RUNNER_CMD%
(
    echo @echo off
    echo cd /d "%PROJECT_DIR%"
    echo "%PHP_EXE%" artisan schedule:run ^>^>"%LOGFILE%" 2^>^&1
) > "%RUNNER_CMD%"

REM =============================
REM 3) Create hidden VBS runner
REM =============================
set "RUNNER_VBS=%PROJECT_DIR%\run-schedule-hidden.vbs"

echo [INFO] Creating %RUNNER_VBS%
(
    echo Option Explicit
    echo Dim shell
    echo Set shell = CreateObject("WScript.Shell"^)
    echo shell.Run "cmd /c ""%RUNNER_CMD%""", 0, False
    echo Set shell = Nothing
) > "%RUNNER_VBS%"

REM =============================
REM 4) Remove existing task
REM =============================
echo [INFO] Removing existing task (if any)...
schtasks /delete /tn "%TASK_NAME%" /f >nul 2>&1

REM =============================
REM 5) Create scheduled task (BACKGROUND)
REM =============================
echo [INFO] Creating scheduled task...

schtasks /create ^
    /tn "%TASK_NAME%" ^
    /tr "wscript.exe ""%RUNNER_VBS%""" ^
    /sc minute ^
    /mo 1 ^
    /ru SYSTEM ^
    /rl HIGHEST ^
    /f

echo.
echo ======================================
echo [SUCCESS] Scheduler installed
echo Task Name : %TASK_NAME%
echo PHP Path  : %PHP_EXE%
echo Log File  : %LOGFILE%
echo ======================================
echo.
echo Verify with:
echo   schtasks /query /tn "%TASK_NAME%"
echo   type "%LOGFILE%"
echo.
pause
endlocal
