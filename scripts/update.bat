@echo off
setlocal

REM Args:
REM %1 = repo path
REM %2 = git.exe path
REM %3 = php.exe path
REM %4 = branch name

set REPO=%~1
set GIT=%~2
set PHP=%~3
set BRANCH=%~4

cd /d "%REPO%" || (echo FAILED: cannot cd to repo & exit /b 1)

echo ===== POS UPDATE START =====
echo Repo: %REPO%
echo Branch: %BRANCH%

echo --- git pull origin %BRANCH% ---
"%GIT%" pull origin %BRANCH%
if errorlevel 1 (
  echo FAILED: git pull
  exit /b 1
)

echo --- php artisan config:cache ---
"%PHP%" artisan config:cache
if errorlevel 1 (
  echo FAILED: artisan config:cache
  exit /b 1
)

echo ===== POS UPDATE OK =====
exit /b 0
