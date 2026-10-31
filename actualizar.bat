@echo off
setlocal enabledelayedexpansion
cd /d "%~dp0"
title Minimarket Mass - actualizar con Docker
:: Deja Minimarket Mass igual que en GitHub en esta PC, con Docker. Doble clic.
:: Sirve la primera vez y despues de cada cambio del equipo. Hace, en orden:
::   1) crea .env desde .env.example si no existe
::   2) baja lo ultimo de main (git pull), solo si estas en la rama main
::   3) si cambiaron los scripts de backend\database\, recrea la base (pregunta antes: borra los datos de prueba)
::   4) levanta MySQL y la pagina web + API (docker compose) y espera a que esten listas
:: En Linux o Mac es actualizar.sh. Guia: docs\equipo\docker.md
set "COMPOSE_PROFILES=web"

echo ====================================================
echo   MINIMARKET MASS: ACTUALIZAR CON DOCKER
echo ====================================================
echo.

docker info >nul 2>nul
if errorlevel 1 (
    echo [ERROR] Docker no esta corriendo. Abre Docker Desktop, espera a que diga "running"
    echo         y vuelve a hacer doble clic en actualizar.bat.
    goto :fin
)

if not exist ".env" (
    copy .env.example .env >nul
    echo [OK] Se creo .env desde .env.example ^(claves de ejemplo: bien para tu PC, nunca para un servidor^).
)

docker compose config -q >nul 2>nul
if errorlevel 1 (
    echo [ERROR] Tu .env no sirve para Docker ^(por ejemplo, es el de Laragon y no tiene clave^).
    echo         Renombralo a .env.viejo y vuelve a correr esto: se crea uno nuevo desde .env.example.
    goto :fin
)

where git >nul 2>nul
if errorlevel 1 (
    echo [AVISO] No se encontro git: no se baja nada. Actualiza con GitHub Desktop ^(Fetch / Pull^) y vuelve a correr esto.
) else (
    set "RAMA="
    for /f "delims=" %%B in ('git rev-parse --abbrev-ref HEAD 2^>nul') do set "RAMA=%%B"
    if /i "!RAMA!"=="main" (
        echo [..] Bajando lo ultimo de GitHub ^(main^)...
        git pull --ff-only origin main
        if errorlevel 1 echo [AVISO] No se pudo hacer git pull ^(cambios tuyos sin guardar o sin internet^). Se sigue con lo que tienes.
    ) else (
        echo [AVISO] Estas en la rama "!RAMA!", no en main: no se baja nada. Para lo ultimo del equipo: git checkout main
    )
)

echo [..] Revisando si la base esta al dia con backend\database\...
set "ESTADO="
for /f "delims=" %%R in ('docker compose run --rm --no-deps -T --entrypoint sh mysql /usr/local/bin/revisar_version.sh 2^>nul') do set "ESTADO=%%R"
if "!ESTADO!"=="DISTINTO" (
    echo.
    echo Los scripts de la base cambiaron desde que se creo tu base ^(o tu base es antigua^).
    echo Hay que recrearla: se BORRAN los datos de prueba de esta PC y se cargan otra vez los de GitHub.
    set "RESP=S"
    set /p "RESP=Recrear la base ahora? [S/n] "
    if /i "!RESP:~0,1!"=="n" (
        echo [AVISO] La base se queda como estaba; algunas pantallas o la app pueden fallar.
    ) else (
        docker compose down -v
    )
)

echo [..] Levantando MySQL y la pagina web ^(la primera vez tarda unos minutos^)...
docker compose up -d --build --wait
if errorlevel 1 (
    echo [ERROR] Algo no levanto. Mira el detalle con: docker compose logs
    goto :fin
)

set "PUERTO=8000"
for /f "tokens=2 delims==" %%P in ('findstr /b "WEB_PORT=" .env 2^>nul') do set "PUERTO=%%P"

:: Sin expansion retrasada para poder escribir el "!" de la clave de prueba
setlocal disabledelayedexpansion
echo.
echo ====================================================
echo   LISTO
echo   Pagina web:             http://localhost:%PUERTO%   (DNI 70000001, clave Mass2026!)
echo   App Android (emulador): http://10.0.2.2:%PUERTO%/
echo   App Android (celular en el mismo Wi-Fi): http://IP-de-esta-PC:%PUERTO%/
echo   La IP de esta PC es una de estas (la del Wi-Fi, casi siempre 192.168...):
for /f "tokens=2 delims=:" %%A in ('ipconfig ^| findstr /c:"IPv4"') do echo      %%A
echo   Apagar todo: docker compose stop
echo ====================================================
start http://localhost:%PUERTO%
endlocal

:fin
echo.
pause
