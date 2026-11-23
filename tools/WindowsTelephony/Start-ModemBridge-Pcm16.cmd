@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -STA -File "%~dp0Start-ModemBridge.ps1" -Pcm16Modem %*
set "OfficePcmExit=%ERRORLEVEL%"
pause
exit /b %OfficePcmExit%
