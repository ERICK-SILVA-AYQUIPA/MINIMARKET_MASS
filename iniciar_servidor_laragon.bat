@echo off
title Servidor Local - Minimarket Mass
echo ====================================================
echo   INICIANDO SERVIDOR MINIMARKET MASS (LARAGON)
echo ====================================================
echo.

if not exist ".env" (
    if exist ".env.laragon" (
        echo [INFO] No se encontro .env. Creando .env desde .env.laragon...
        copy .env.laragon .env >nul
        echo [OK] Archivo .env configurado para Laragon.
        echo.
    )
)

echo Abriendo http://localhost:8000 en tu navegador...
start http://localhost:8000

echo Iniciando servidor PHP en el puerto 8000...
echo Presiona Ctrl + C para detener el servidor.
echo.
php -S localhost:8000 -t public
pause
