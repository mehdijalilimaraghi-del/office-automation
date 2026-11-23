@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -STA -File "%~dp0Record-IvrGreeting.ps1" %*
set "OfficeIvrExit=%ERRORLEVEL%"
pause
exit /b %OfficeIvrExit%
