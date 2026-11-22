@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -STA -File "%~dp0Start-ModemBridge.ps1" %*
set "OfficeBridgeExit=%ERRORLEVEL%"
pause
exit /b %OfficeBridgeExit%
