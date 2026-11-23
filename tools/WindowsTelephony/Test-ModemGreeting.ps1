#requires -Version 5.1
$ErrorActionPreference='Stop'
Add-Type -Path @((Join-Path $PSScriptRoot 'VoiceDiagnostics.cs'),(Join-Path $PSScriptRoot 'VoiceDiagnostics.Tests.cs'),(Join-Path $PSScriptRoot 'LocalSipAudioEndpoint.cs'),(Join-Path $PSScriptRoot 'GreetingPrompt.cs'),(Join-Path $PSScriptRoot 'ModemGreetingBridge.cs'),(Join-Path $PSScriptRoot 'ModemGreetingBridge.Tests.cs'))
[ModemGreetingChecks]::Run()
