@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0Apply-OfficeConfig.ps1" %*
set "OfficePbxExitCode=%ERRORLEVEL%"
pause
exit /b %OfficePbxExitCode%
