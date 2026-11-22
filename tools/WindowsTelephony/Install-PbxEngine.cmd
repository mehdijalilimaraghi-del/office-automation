@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0Install-PbxEngine.ps1" -InstallerPath "%~dp0FreeSWITCH-1.11.4-Release-x64.msi" %*
set "OfficePbxExitCode=%ERRORLEVEL%"
pause
exit /b %OfficePbxExitCode%
