#requires -Version 5.1
$ErrorActionPreference='Stop'
Add-Type -Path @((Join-Path $PSScriptRoot 'VoiceDiagnostics.cs'),(Join-Path $PSScriptRoot 'LocalSipAudioEndpoint.cs'),(Join-Path $PSScriptRoot 'RtpPlayout.Tests.cs'))
[RtpPlayoutChecks]::Run()
