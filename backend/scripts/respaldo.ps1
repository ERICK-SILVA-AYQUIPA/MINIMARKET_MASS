# Respaldo completo de Minimarket Mass (Windows / PowerShell): la base minimarket_mass y las fotos de producto
# (frontend\public\img\productos\), juntas en un solo archivo:
#   respaldos\minimarket_mass_AAAA-MM-DD_HHMM.tar.gz        (sin cifrar)
#   respaldos\minimarket_mass_AAAA-MM-DD_HHMM.tar.gz.gpg    (cifrado, si .env tiene RESPALDO_CLAVE)
#
#   powershell -ExecutionPolicy Bypass -File backend\scripts\respaldo.ps1
#
# Opcional, en .env (ver docs\despliegue.md, seccion 10):
#   RESPALDO_CLAVE=...            cifra con gpg (instalar Gpg4win). Sin esa clave NO se puede restaurar: guardala fuera de la PC.
#   RESPALDO_REMOTO=drive:mass    copia el respaldo fuera de la PC con rclone (rclone.org; configurarlo con: rclone config).
#   RESPALDO_DIAS_REMOTO=90       dias que se guardan en el remoto (por defecto 90).
#
# La clave de root no se escribe aqui: la lee MySQL dentro del contenedor (variable MYSQL_ROOT_PASSWORD).
# Los respaldos llevan DNIs y hashes de claves: la carpeta respaldos\ esta en .gitignore y NO se sube a GitHub.
$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..\..')
$raiz = (Get-Location).Path

$contenedor = 'minimarket_mass_db'
$guardar = 30

# Lee una variable del entorno o, si no esta, del .env
function Leer-Env([string]$nombre) {
    $valor = [Environment]::GetEnvironmentVariable($nombre)
    if (-not $valor -and (Test-Path .env)) {
        $linea = Get-Content .env | Where-Object { $_ -match "^$nombre=" } | Select-Object -Last 1
        if ($linea) { $valor = ($linea -replace "^$nombre=", '').Trim().Trim('"').Trim("'") }
    }
    return $valor
}
$clave = Leer-Env 'RESPALDO_CLAVE'
$remoto = Leer-Env 'RESPALDO_REMOTO'
$diasRemoto = Leer-Env 'RESPALDO_DIAS_REMOTO'
if (-not $diasRemoto) { $diasRemoto = '90' }

if ($clave -and -not (Get-Command gpg -ErrorAction SilentlyContinue)) {
    Write-Error 'RESPALDO_CLAVE esta puesta pero falta gpg: instala Gpg4win (https://gpg4win.org) y vuelve a abrir la consola.'
}
if ($remoto -and -not (Get-Command rclone -ErrorAction SilentlyContinue)) {
    Write-Error 'RESPALDO_REMOTO esta puesto pero falta rclone: instalalo (winget install Rclone.Rclone) y configuralo con: rclone config'
}

New-Item -ItemType Directory -Force -Path respaldos | Out-Null
$nombre = "minimarket_mass_$(Get-Date -Format 'yyyy-MM-dd_HHmm')"
$temporal = Join-Path ([IO.Path]::GetTempPath()) "mass_$([guid]::NewGuid().ToString('N'))"
New-Item -ItemType Directory -Path $temporal | Out-Null
try {
    # 1. La base: se escribe dentro del contenedor y luego se copia, asi no cambia la codificacion (UTF-8)
    docker exec $contenedor sh -c 'MYSQL_PWD=$MYSQL_ROOT_PASSWORD exec mysqldump -uroot --single-transaction --routines --triggers --databases minimarket_mass --result-file=/tmp/respaldo.sql'
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo hacer el respaldo. Revisa que el contenedor este corriendo (docker compose ps).' }
    docker cp "${contenedor}:/tmp/respaldo.sql" (Join-Path $temporal 'base.sql')
    docker exec $contenedor rm -f /tmp/respaldo.sql
    if (-not (Get-Content (Join-Path $temporal 'base.sql') -Tail 1 | Select-String 'Dump completed')) {
        throw 'El respaldo de la base no se completo.'
    }

    # 2. Base + fotos en un .tar.gz (tar.exe viene con Windows 10 y 11)
    $padreFotos = Join-Path $raiz 'frontend\public\img'
    New-Item -ItemType Directory -Force -Path (Join-Path $padreFotos 'productos') | Out-Null
    $fotos = @(Get-ChildItem (Join-Path $padreFotos 'productos') -File -Recurse | Where-Object { $_.Name -ne '.gitkeep' }).Count
    $tarGz = Join-Path $temporal "$nombre.tar.gz"
    tar -czf $tarGz -C $temporal base.sql -C $padreFotos productos
    if ($LASTEXITCODE -ne 0 -or -not ((tar -tzf $tarGz) -contains 'base.sql')) { throw 'El archivo del respaldo quedo danado.' }

    # 3. Cifrado (opcional). La clave va en un archivo temporal, no en la linea de comandos.
    if ($clave) {
        $archivo = "respaldos\$nombre.tar.gz.gpg"
        $archivoClave = Join-Path $temporal 'clave'
        [IO.File]::WriteAllText($archivoClave, $clave)
        gpg --batch --yes --quiet --pinentry-mode loopback --passphrase-file $archivoClave --symmetric --cipher-algo AES256 -o $archivo $tarGz
        if ($LASTEXITCODE -ne 0) { throw 'No se pudo cifrar el respaldo con gpg.' }
        $detalle = "base + $fotos fotos, cifrado"
    } else {
        $archivo = "respaldos\$nombre.tar.gz"
        Move-Item -Force $tarGz $archivo
        $detalle = "base + $fotos fotos"
    }
} finally {
    Remove-Item -Recurse -Force $temporal -ErrorAction SilentlyContinue
}
Write-Host "Respaldo listo: $archivo ($detalle)"

# 4. Se guardan los ultimos 30 respaldos (un mes si es diario); los mas antiguos se borran
Get-ChildItem respaldos -File | Where-Object { $_.Name -match '^minimarket_mass_.*\.(sql|tar\.gz|tar\.gz\.gpg)$' } |
    Sort-Object LastWriteTime -Descending | Select-Object -Skip $guardar | ForEach-Object {
        Remove-Item $_.FullName
        Write-Host "Borrado por antiguo: $($_.Name)"
    }

# 5. Copia fuera de la PC (opcional). Si falla, el respaldo local ya quedo hecho, pero se avisa con error.
if (-not $remoto) {
    Write-Host 'AVISO: el respaldo solo esta en este disco. Configura RESPALDO_REMOTO en .env (docs\despliegue.md, seccion 10).'
    exit 0
}
if (-not $clave) { Write-Host 'AVISO: se copia afuera SIN cifrar. Pon RESPALDO_CLAVE en .env para cifrarlo.' }
rclone copy $archivo $remoto
if ($LASTEXITCODE -ne 0) { Write-Error "No se pudo copiar $archivo a $remoto (revisa internet y rclone config). El respaldo local si quedo." }
Write-Host "Copiado fuera de la PC: $remoto/$(Split-Path $archivo -Leaf)"
rclone delete $remoto --min-age "${diasRemoto}d" --include 'minimarket_mass_*'
if ($LASTEXITCODE -ne 0) { Write-Host "AVISO: no se pudieron borrar los respaldos de mas de $diasRemoto dias en $remoto." }
