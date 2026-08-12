@echo off
chcp 65001 >nul
set ROOT=%~dp0

echo ============================================
echo   ZAKI LOGISTICS - Lancement soutenance
echo ============================================
echo.

echo Verification backend...
cd /d "%ROOT%backend-new"

set DB_CREATED=0
if not exist "database\database.sqlite" (
  echo Creation database SQLite...
  type nul > database\database.sqlite
  set DB_CREATED=1
)

if not exist ".env" (
  echo Creation .env backend...
  copy .env.example .env
)

if not exist "vendor" (
  echo Installation dependances PHP...
  call composer install --no-dev --optimize-autoloader
  if errorlevel 1 goto :error
)

set APP_KEY_VALUE=
for /f "tokens=1,* delims==" %%A in ('findstr /B /C:"APP_KEY=" .env') do set APP_KEY_VALUE=%%B
if not defined APP_KEY_VALUE php artisan key:generate --ansi
if errorlevel 1 goto :error
php artisan config:clear >nul 2>&1
php artisan migrate --force
if errorlevel 1 goto :error
if "%DB_CREATED%"=="1" php artisan db:seed --force
if errorlevel 1 goto :error

echo.
echo Verification frontend...
cd /d "%ROOT%frontend"

if not exist ".env" (
  echo Creation .env frontend...
  copy .env.example .env
)

if not exist "node_modules" (
  echo Installation dependances Node.js...
  call npm ci
  if errorlevel 1 goto :error
)

echo.
echo Backend:  http://127.0.0.1:8000
echo Frontend: http://127.0.0.1:5173
start "ZAKI BACKEND" cmd /k "cd /d ""%ROOT%backend-new"" && php artisan serve --host=127.0.0.1 --port=8000"
start "ZAKI FRONTEND" cmd /k "cd /d ""%ROOT%frontend"" && npm run dev -- --host 127.0.0.1 --port 5173"

echo.
echo Ouvre: http://127.0.0.1:5173
echo.
echo Comptes de test:
echo   admin@logistics.ma / admin123
echo   expediteur@test.ma / test123
echo   livreur@test.ma / test123
echo   destinataire@test.ma / test123
echo   voyageur@test.ma / test123
echo.
pause
exit /b 0

:error
echo.
echo ERREUR: le lancement a ete interrompu. Consultez le message ci-dessus.
pause
exit /b 1
