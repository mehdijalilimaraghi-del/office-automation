@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -STA -File "%~dp0Collect-ModemDriverVoiceEvidence.ps1" %*
set "OfficeDriverEvidenceExit=%ERRORLEVEL%"
pause
exit /b %OfficeDriverEvidenceExit%
