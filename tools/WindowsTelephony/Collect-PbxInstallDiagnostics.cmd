@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0Collect-PbxInstallDiagnostics.ps1"
set "OfficePbxExitCode=%ERRORLEVEL%"
pause
exit /b %OfficePbxExitCode%
