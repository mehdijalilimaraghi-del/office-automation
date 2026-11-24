#requires -Version 5.1
param([Parameter(Mandatory=$true)][string]$DspDirectory)
$ErrorActionPreference='Stop'
Add-Type -Path @((Join-Path $PSScriptRoot 'VoiceDiagnostics.cs'),(Join-Path $PSScriptRoot 'LocalSipAudioEndpoint.cs'),(Join-Path $PSScriptRoot 'ModemLineEcho.cs'),(Join-Path $PSScriptRoot 'ModemLineEcho.Tests.cs'))
[NativeEchoChecks]::Run((Join-Path $DspDirectory 'libspeexdsp-1.dll'))
