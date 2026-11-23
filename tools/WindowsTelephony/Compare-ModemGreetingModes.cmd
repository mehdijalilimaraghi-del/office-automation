@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -STA -File "%~dp0Start-ModemBridge.ps1" -CompareGreetingModes %*
set "OfficeModesExit=%ERRORLEVEL%"
pause
exit /b %OfficeModesExit%
