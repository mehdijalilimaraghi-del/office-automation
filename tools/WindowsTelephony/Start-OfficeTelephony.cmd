@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -STA -File "%~dp0Start-ModemBridge.ps1" -ReceiveCalls %*
set "OfficeReceiverExit=%ERRORLEVEL%"
pause
exit /b %OfficeReceiverExit%
