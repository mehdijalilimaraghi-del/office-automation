#requires -Version 5.1
param([Parameter(Mandatory=$true)][string]$DspDirectory)
$ErrorActionPreference='Stop'
Add-Type -Path @((Join-Path $PSScriptRoot 'VoiceDiagnostics.cs'),(Join-Path $PSScriptRoot 'VoiceDiagnostics.Tests.cs'),(Join-Path $PSScriptRoot 'LocalSipAudioEndpoint.cs'),(Join-Path $PSScriptRoot 'VoiceBridgeLifecycle.Tests.cs'),(Join-Path $PSScriptRoot 'ModemLineEcho.cs'),(Join-Path $PSScriptRoot 'IncomingReceiver.Tests.cs'))
[IncomingReceiverChecks]::Run((Join-Path $DspDirectory 'libspeexdsp-1.dll'))
$errors=$null;[void][Management.Automation.Language.Parser]::ParseFile((Join-Path $PSScriptRoot 'Start-ModemBridge.ps1'),[ref]$null,[ref]$errors);if($errors.Count){throw $errors[0]}
