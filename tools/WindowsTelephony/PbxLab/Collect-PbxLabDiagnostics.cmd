@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0Collect-PbxLabDiagnostics.ps1" %*
set result=%errorlevel%
pause
exit /b %result%
