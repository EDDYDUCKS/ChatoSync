<#
.SYNOPSIS
    ChatoSync - Activar Zona Wi-Fi Local (Mobile Hotspot) en Windows 10/11
    Inicia el punto de acceso sin necesidad de abrir la ventana de Configuración.
#>

[CmdletBinding()]
param()

Clear-Host
Write-Host "=======================================================" -ForegroundColor Red
Write-Host "   ChatoSync - Iniciando Zona Wi-Fi Local (Hotspot)    " -ForegroundColor White
Write-Host "=======================================================" -ForegroundColor Red
Write-Host ""

# Cargar tipos de Windows Runtime
Add-Type -AssemblyName System.Runtime.WindowsRuntime
$asTaskGeneric = ([System.WindowsRuntimeSystemExtensions].GetMethods() | Where-Object { 
    $_.Name -eq 'AsTask' -and $_.GetParameters().Count -eq 1 -and $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncOperation`1' 
})[0]

function Await($WinRtTask, $ResultType) {
    $asTask = $asTaskGeneric.MakeGenericMethod($ResultType)
    $netTask = $asTask.Invoke($null, @($WinRtTask))
    $netTask.Wait(-1) | Out-Null
    return $netTask.Result
}

try {
    [Windows.Networking.NetworkOperators.NetworkOperatorTetheringManager, Windows.Networking.NetworkOperators, ContentType = WindowsRuntime] | Out-Null
    [Windows.Networking.Connectivity.NetworkInformation, Windows.Networking.Connectivity, ContentType = WindowsRuntime] | Out-Null

    # Obtener el perfil de red
    $connectionProfile = [Windows.Networking.Connectivity.NetworkInformation]::GetInternetConnectionProfile()
    if (-not $connectionProfile) {
        $profiles = [Windows.Networking.Connectivity.NetworkInformation]::GetConnectionProfiles()
        if ($profiles.Count -gt 0) {
            $connectionProfile = $profiles[0]
        }
    }

    if (-not $connectionProfile) {
        Write-Host "[-] No se encontro un perfil de red base en Windows." -ForegroundColor Yellow
        Write-Host "[*] Asegurate de tener el Wi-Fi de la laptop encendido." -ForegroundColor Gray
        exit 1
    }

    $tetheringManager = [Windows.Networking.NetworkOperators.NetworkOperatorTetheringManager]::CreateFromConnectionProfile($connectionProfile)

    if ($tetheringManager.TetheringOperationalState -eq 1) {
        Write-Host "[+] La Zona Wi-Fi ya se encuentra ENCENDIDA y activa." -ForegroundColor Green
        Write-Host "    Red:     Chato-Hub (o ULSA-Hub)" -ForegroundColor White
        Write-Host "    Servidor: 192.168.137.102" -ForegroundColor Cyan
    } else {
        Write-Host "[*] Activando antena Wi-Fi en modo Hotspot..." -ForegroundColor Yellow
        $res = Await ($tetheringManager.StartTetheringAsync()) ([Windows.Networking.NetworkOperators.NetworkOperatorTetheringOperationResult])
        
        if ($res.Status -eq 0) {
            Write-Host "[+] ZONA WI-FI ACTIVADA EXITOSAMENTE!" -ForegroundColor Green
            Write-Host "    Estado:   Transmitiendo senal local" -ForegroundColor White
            Write-Host "    Servidor: http://192.168.137.102/" -ForegroundColor Cyan
        } else {
            Write-Host "[-] Estado de encendido: $($res.Status)" -ForegroundColor Red
            if ($res.AdditionalErrorMessage) {
                Write-Host "    Detalle: $($res.AdditionalErrorMessage)" -ForegroundColor Yellow
            }
        }
    }
} catch {
    Write-Host "[-] Error al interactuar con el servicio de Hotspot: $_" -ForegroundColor Red
}

Write-Host ""
Write-Host "=======================================================" -ForegroundColor Gray
