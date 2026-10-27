# Restaura un respaldo hecho con scripts\respaldo.ps1. REEMPLAZA todos los datos actuales de minimarket_mass.
#   powershell -ExecutionPolicy Bypass -File scripts\restaurar.ps1 respaldos\minimarket_mass_2026-10-27_1530.sql
param([Parameter(Mandatory = $true)][string]$Archivo)
$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..')

$contenedor = 'minimarket_mass_db'
if (-not (Test-Path $Archivo)) { Write-Error "No existe el archivo $Archivo" }
$respuesta = Read-Host "Esto reemplaza TODOS los datos de minimarket_mass con $Archivo. Escribe SI para seguir"
if ($respuesta -ne 'SI') { Write-Host 'Cancelado: no se cambio nada.'; exit 0 }

# Se copia el archivo al contenedor y MySQL lo lee alli (sin pasar por la codificacion de PowerShell)
docker cp $Archivo "${contenedor}:/tmp/restaurar.sql"
docker exec $contenedor sh -c 'MYSQL_PWD=$MYSQL_ROOT_PASSWORD mysql -uroot < /tmp/restaurar.sql; status=$?; rm -f /tmp/restaurar.sql; exit $status'
if ($LASTEXITCODE -ne 0) { Write-Error 'No se pudo restaurar.' }
Write-Host "Base restaurada desde $Archivo"
