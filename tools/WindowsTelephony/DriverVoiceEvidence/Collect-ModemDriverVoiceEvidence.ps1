#requires -Version 5.1
param([string]$PortName)
$ErrorActionPreference='Stop'
try{
    Import-Module (Join-Path $PSScriptRoot 'ModemVoiceDriverEvidence.psm1') -Force -DisableNameChecking
    $report=Get-ModemVoiceDriverEvidence -PortName $PortName
    $path=Join-Path $PSScriptRoot 'modem-driver-voice-evidence.json'
    [IO.File]::WriteAllText($path,($report|ConvertTo-Json -Depth 12),[Text.UTF8Encoding]::new($false))
    Write-Host ('Saved: '+$path) -ForegroundColor Green
    Write-Host ('Installed modem entries: '+$report.modems.Count)
    Write-Host 'Read-only: no modem opened, no registry/settings changes, no call/audio/phone or license data collected.'
}catch{
    Write-Host ('ERROR: '+$_.Exception.Message) -ForegroundColor Red
    exit 1
}
