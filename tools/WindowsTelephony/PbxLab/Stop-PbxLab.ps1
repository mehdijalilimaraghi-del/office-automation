#requires -Version 5.1
param([string]$LabRoot)
$ErrorActionPreference='Stop'
try {
    Import-Module (Join-Path $PSScriptRoot 'PbxLab.psm1') -Force -DisableNameChecking
    if(-not $LabRoot){$LabRoot=Join-Path $env:LOCALAPPDATA 'OfficeAutomation\Telephony\PbxLab'}
    $labState=Read-PbxLabState $LabRoot;$session=Get-Content -LiteralPath (Join-Path $LabRoot 'lab-session.json') -Raw|ConvertFrom-Json
    if($session.profile_id -ne $labState.profile_id){throw 'Profile/session mismatch'}
    $process=Get-Process -Id $session.process_id -ErrorAction SilentlyContinue
    if(-not $process){Write-Host 'Lab is already stopped.';return}
    if($process.Path -ne $session.binary_path -or $process.StartTime.ToUniversalTime().Ticks -ne $session.process_start_ticks){throw 'Process identity changed. No stop command was sent.'}
    Invoke-PbxLabCommand $labState 'shutdown'|Out-Null
    if(-not $process.WaitForExit(10000)){throw 'Lab has not exited yet. No forced termination was performed.'}
    Write-Host 'Lab stopped. Windows service was not changed.' -ForegroundColor Green
} catch {Write-Host ('ERROR: '+$_.Exception.Message) -ForegroundColor Red;exit 1}