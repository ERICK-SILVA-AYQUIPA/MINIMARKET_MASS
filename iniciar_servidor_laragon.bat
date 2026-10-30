@echo off
setlocal enabledelayedexpansion
title Servidor Local - Minimarket Mass (Laragon)
echo ====================================================
echo   INICIANDO Y CONFIGURANDO MINIMARKET MASS
echo ====================================================
echo.

:: 1. Detectar ejecutable de PHP (del sistema o de Laragon)
set "PHP_CMD=php"
where php >nul 2>nul
if errorlevel 1 (
    for /d %%D in ("C:\laragon\bin\php\php-*") do (
        if exist "%%D\php.exe" set "PHP_CMD=%%D\php.exe"
    )
)

:: 2. Detectar ejecutable de MySQL (del sistema o de Laragon)
set "MYSQL_CMD=mysql"
where mysql >nul 2>nul
if errorlevel 1 (
    for /d %%D in ("C:\laragon\bin\mysql\mysql-*") do (
        if exist "%%D\bin\mysql.exe" set "MYSQL_CMD=%%D\bin\mysql.exe"
    )
)

:: 3. Configurar archivo .env si no existe
if not exist ".env" (
    if exist ".env.laragon" (
        echo [INFO] Creando archivo .env desde .env.laragon...
        copy .env.laragon .env >nul
        echo [OK] Archivo .env configurado correctamente.
        echo.
    )
)

:: 4. Verificar conexion y base de datos (se instala sola si no existe)
echo [INFO] Verificando base de datos en Laragon...
"%MYSQL_CMD%" -u root -e "USE minimarket_mass; SELECT 1 FROM producto LIMIT 1;" >nul 2>nul
if errorlevel 1 (
    echo [INFO] Instalando base de datos minimarket_mass automaticamente...
    if exist "database\instalar_laragon (1).sql" (
        "%MYSQL_CMD%" --default-character-set=utf8mb4 -u root < "database\instalar_laragon (1).sql"
        echo [OK] Base de datos instalada exitosamente con todas las tablas y datos.
    ) else if exist "database\instalar_laragon.sql" (
        "%MYSQL_CMD%" --default-character-set=utf8mb4 -u root < "database\instalar_laragon.sql"
        echo [OK] Base de datos instalada exitosamente.
    ) else (
        echo [AVISO] Revisa que MySQL este iniciado en Laragon con el boton "Iniciar Todo".
    )
    echo.
) else (
    echo [OK] Base de datos minimarket_mass lista y conectada.
    echo.
)

:: 5. Abrir navegador
echo [INFO] Abriendo http://localhost:8000 en tu navegador...
start http://localhost:8000

:: 6. Iniciar servidor PHP
echo.
echo ====================================================
echo   Servidor iniciado en http://localhost:8000
echo   Presiona Ctrl + C para detener el servidor.
echo ====================================================
echo.
"%PHP_CMD%" -S localhost:8000 -t public

pause
