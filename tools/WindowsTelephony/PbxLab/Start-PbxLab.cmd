@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -STA -File "%~dp0Start-PbxLab.ps1" %*
set "OfficePbxExitCode=%ERRORLEVEL%"
pause
exit /b %OfficePbxExitCode%
