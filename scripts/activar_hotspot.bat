@echo off
title ChatoSync - Activar Zona Wi-Fi Local
color 0C
cls
echo =======================================================
echo   ChatoSync Hub - Iniciando Zona Wi-Fi Local
echo =======================================================
echo.

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0activar_hotspot.ps1"

echo.
echo Presiona cualquier tecla para cerrar esta ventana...
pause >nul
