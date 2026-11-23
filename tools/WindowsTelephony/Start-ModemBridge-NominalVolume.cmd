@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -STA -File "%~dp0Start-ModemBridge.ps1" -NominalTransmitVolume %*
set "OfficeGainExit=%ERRORLEVEL%"
pause
exit /b %OfficeGainExit%
