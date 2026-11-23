#requires -Version 5.1
$ErrorActionPreference='Stop'
Add-Type -Path @((Join-Path $PSScriptRoot 'VoiceDiagnostics.cs'),(Join-Path $PSScriptRoot 'VoiceDiagnostics.Tests.cs'),(Join-Path $PSScriptRoot 'LocalSipAudioEndpoint.cs'),(Join-Path $PSScriptRoot 'GreetingModeComparison.cs'),(Join-Path $PSScriptRoot 'GreetingModeComparison.Tests.cs'))
[GreetingComparisonChecks]::Run()
