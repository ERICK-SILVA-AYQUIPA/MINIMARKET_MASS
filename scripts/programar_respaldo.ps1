# Programa el respaldo diario (23:00) en el Programador de tareas de Windows. Se puede correr dos veces sin duplicarlo.
#   powershell -ExecutionPolicy Bypass -File scripts\programar_respaldo.ps1           # programa
#   powershell -ExecutionPolicy Bypass -File scripts\programar_respaldo.ps1 -Quitar   # lo quita
# Corre con el usuario que inicio sesion, que es quien tiene Docker Desktop abierto.
param([switch]$Quitar)
$ErrorActionPreference = 'Stop'

$nombre = 'Minimarket Mass - respaldo diario'
if ($Quitar) {
    Unregister-ScheduledTask -TaskName $nombre -Confirm:$false -ErrorAction SilentlyContinue
    Write-Host 'Respaldo diario quitado del Programador de tareas.'
    exit 0
}

$script = Join-Path $PSScriptRoot 'respaldo.ps1'
$accion = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$script`""
$cuando = New-ScheduledTaskTrigger -Daily -At '23:00'
# Si la PC estaba apagada a las 23:00, corre apenas se prenda
$opciones = New-ScheduledTaskSettingsSet -StartWhenAvailable
Register-ScheduledTask -TaskName $nombre -Action $accion -Trigger $cuando -Settings $opciones -Force | Out-Null
Write-Host "Listo: '$nombre' corre todos los dias a las 23:00. Se ve en el Programador de tareas."
