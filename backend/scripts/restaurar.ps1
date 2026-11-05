# Restaura un respaldo hecho con backend\scripts\respaldo.ps1 (o respaldo.sh). REEMPLAZA todos los datos actuales
# de minimarket_mass y las fotos de producto.
#   powershell -ExecutionPolicy Bypass -File backend\scripts\restaurar.ps1 respaldos\minimarket_mass_2026-11-03_2300.tar.gz.gpg
#   powershell -ExecutionPolicy Bypass -File backend\scripts\restaurar.ps1 respaldos\minimarket_mass_2026-11-03_2300.tar.gz
#   powershell -ExecutionPolicy Bypass -File backend\scripts\restaurar.ps1 respaldos\minimarket_mass_2026-10-27_1530.sql   (antiguos: solo la base)
# Un respaldo .gpg pide la misma RESPALDO_CLAVE con la que se hizo (en .env). Agregar -Si para no preguntar.
# Sirve con Docker y sin Docker, por ejemplo en Laragon (RESPALDO_MODO; ver backend\scripts\mysql_local.ps1).
param([Parameter(Mandatory = $true)][string]$Archivo, [switch]$Si)
$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..\..')
$raiz = (Get-Location).Path

$contenedor = 'minimarket_mass_db'
$fotos = Join-Path $raiz 'frontend\public\img\productos'
if (-not (Test-Path $Archivo)) { Write-Error "No existe el archivo $Archivo" }
$Archivo = (Resolve-Path $Archivo).Path

function Leer-Env([string]$nombre) {
    $valor = [Environment]::GetEnvironmentVariable($nombre)
    if (-not $valor -and (Test-Path .env)) {
        $linea = Get-Content .env | Where-Object { $_ -match "^$nombre=" } | Select-Object -Last 1
        if ($linea) { $valor = ($linea -replace "^$nombre=", '').Trim().Trim('"').Trim("'") }
    }
    return $valor
}

$temporal = Join-Path ([IO.Path]::GetTempPath()) "mass_$([guid]::NewGuid().ToString('N'))"
New-Item -ItemType Directory -Path $temporal | Out-Null
try {
    # 1. Se abre el respaldo en una carpeta temporal y se revisa ANTES de tocar nada
    $base = Join-Path $temporal 'base.sql'
    $abierto = Join-Path $temporal 'abierto'
    $tarGz = $null
    if ($Archivo -like '*.tar.gz.gpg') {
        $clave = Leer-Env 'RESPALDO_CLAVE'
        if (-not $clave) { throw 'El respaldo esta cifrado: pon en .env la RESPALDO_CLAVE con la que se hizo.' }
        if (-not (Get-Command gpg -ErrorAction SilentlyContinue)) { throw 'Falta gpg: instala Gpg4win (https://gpg4win.org).' }
        $archivoClave = Join-Path $temporal 'clave'
        [IO.File]::WriteAllText($archivoClave, $clave)
        $tarGz = Join-Path $temporal 'respaldo.tar.gz'
        gpg --batch --quiet --pinentry-mode loopback --passphrase-file $archivoClave -o $tarGz -d $Archivo
        if ($LASTEXITCODE -ne 0) { throw "No se pudo descifrar ${Archivo}: la clave no es la correcta o el archivo esta danado." }
    } elseif ($Archivo -like '*.tar.gz') {
        $tarGz = $Archivo
    } elseif ($Archivo -like '*.sql') {
        Copy-Item $Archivo $base
    } else {
        throw "No reconozco el archivo ${Archivo}: debe ser .tar.gz.gpg, .tar.gz o .sql"
    }
    if ($tarGz) {
        New-Item -ItemType Directory -Path $abierto | Out-Null
        tar -xzf $tarGz -C $abierto
        if ($LASTEXITCODE -ne 0 -or -not (Test-Path (Join-Path $abierto 'base.sql'))) { throw "$Archivo no es un respaldo de respaldo.ps1." }
        Move-Item (Join-Path $abierto 'base.sql') $base
    }
    if (-not (Get-Content $base -Tail 1 | Select-String 'Dump completed')) {
        throw "La base del respaldo esta cortada (no termina en 'Dump completed'): no se restaura nada."
    }
    $traeFotos = Test-Path (Join-Path $abierto 'productos')

    # Donde esta MySQL (RESPALDO_MODO; ver mysql_local.ps1). Se revisa antes de preguntar.
    . (Join-Path $PSScriptRoot 'mysql_local.ps1')
    $modo = Modo-Mysql
    if ($modo -eq 'local') {
        $mysql = Buscar-Programa 'mysql'
        $cnf = Preparar-MysqlLocal $temporal
        if (-not (Probar-MysqlLocal $mysql $cnf)) { throw "No se pudo entrar a MySQL como $usuarioLocal. Revisa que este iniciado (Laragon: Iniciar todo) y RESPALDO_DB_USER en .env." }
    }

    if (-not $Si) {
        $que = 'TODOS los datos de minimarket_mass'
        if ($traeFotos) { $que += ' y las fotos de producto' }
        $respuesta = Read-Host "Esto reemplaza $que con $Archivo. Escribe SI para seguir"
        if ($respuesta -ne 'SI') { Write-Host 'Cancelado: no se cambio nada.'; exit 0 }
    }

    # 2. La base, sin pasar por la codificacion de PowerShell: con Docker se copia al contenedor y MySQL la lee alli;
    #    sin Docker, el cliente mysql la lee con "source" (ruta con / para que no confunda las \)
    if ($modo -eq 'docker') {
        docker cp $base "${contenedor}:/tmp/restaurar.sql"
        docker exec $contenedor sh -c 'MYSQL_PWD=$MYSQL_ROOT_PASSWORD mysql -uroot < /tmp/restaurar.sql; status=$?; rm -f /tmp/restaurar.sql; exit $status'
    } else {
        & $mysql "--defaults-extra-file=$cnf" -e "source $($base -replace '\\', '/')"
    }
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo restaurar la base.' }
    Write-Host "Base restaurada desde $Archivo"

    # 3. Las fotos: se cambian las de ahora por las del respaldo (se deja el .gitkeep)
    if ($traeFotos) {
        New-Item -ItemType Directory -Force -Path $fotos | Out-Null
        Get-ChildItem $fotos -Force | Where-Object { $_.Name -ne '.gitkeep' } | Remove-Item -Recurse -Force
        Copy-Item (Join-Path $abierto 'productos\*') $fotos -Recurse -Force
        $cuantas = @(Get-ChildItem $fotos -File -Recurse | Where-Object { $_.Name -ne '.gitkeep' }).Count
        Write-Host "Fotos restauradas: $cuantas"
    } else {
        Write-Host "Este respaldo no trae fotos: se dejan las de $fotos como estan."
    }
} finally {
    Remove-Item -Recurse -Force $temporal -ErrorAction SilentlyContinue
}
