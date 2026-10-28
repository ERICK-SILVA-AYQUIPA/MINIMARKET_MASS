# Respaldo de la base minimarket_mass en respaldos\minimarket_mass_AAAA-MM-DD_HHMM.sql (Windows / PowerShell)
#   powershell -ExecutionPolicy Bypass -File scripts\respaldo.ps1
# La clave de root no se escribe aqui: la lee MySQL dentro del contenedor (variable MYSQL_ROOT_PASSWORD).
# Los respaldos llevan DNIs y hashes de claves: la carpeta respaldos\ esta en .gitignore y NO se sube a GitHub.
$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..')

$contenedor = 'minimarket_mass_db'
New-Item -ItemType Directory -Force -Path respaldos | Out-Null
$archivo = "respaldos\minimarket_mass_$(Get-Date -Format 'yyyy-MM-dd_HHmm').sql"

# El respaldo se escribe dentro del contenedor y luego se copia: asi no cambia la codificacion (UTF-8)
docker exec $contenedor sh -c 'MYSQL_PWD=$MYSQL_ROOT_PASSWORD exec mysqldump -uroot --single-transaction --routines --triggers --databases minimarket_mass --result-file=/tmp/respaldo.sql'
if ($LASTEXITCODE -ne 0) { Write-Error 'No se pudo hacer el respaldo. Revisa que el contenedor este corriendo (docker compose ps).' }
docker cp "${contenedor}:/tmp/respaldo.sql" $archivo
docker exec $contenedor rm -f /tmp/respaldo.sql

if (-not (Get-Content $archivo -Tail 1 | Select-String 'Dump completed')) {
    Remove-Item $archivo -ErrorAction SilentlyContinue
    Write-Error 'El respaldo no se completo.'
}
Write-Host "Respaldo listo: $archivo"

# Se guardan los ultimos 30 respaldos (un mes si es diario); los mas antiguos se borran
$guardar = 30
Get-ChildItem respaldos\minimarket_mass_*.sql | Sort-Object LastWriteTime -Descending | Select-Object -Skip $guardar | ForEach-Object {
    Remove-Item $_.FullName
    Write-Host "Borrado por antiguo: $($_.Name)"
}
