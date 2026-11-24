#requires -Version 5.1
param([Parameter(Mandatory=$true)][string]$DspDirectory)
$ErrorActionPreference='Stop'
Import-Module (Join-Path $PSScriptRoot 'PbxLab/PbxLab.psm1') -Force -DisableNameChecking
. (Join-Path $PSScriptRoot 'ModemLineEcho.ps1')
Add-Type -Path @((Join-Path $PSScriptRoot 'VoiceDiagnostics.cs'),(Join-Path $PSScriptRoot 'LocalSipAudioEndpoint.cs'),(Join-Path $PSScriptRoot 'ModemLineEcho.cs'),(Join-Path $PSScriptRoot 'VoiceDiagnostics.Tests.cs'),(Join-Path $PSScriptRoot 'VoiceBridgeLifecycle.Tests.cs'))
$root=Join-Path $env:TEMP ('office-line-echo-setup-'+[guid]::NewGuid().ToString('N'))
try{
 $state=New-PbxLabProfile -Root $root -BindAddress '127.0.0.1' -SipPort 15660
 $dll=Initialize-ModemLineEchoLibrary -LabRoot $root -SourceDirectory $DspDirectory
 if((Initialize-ModemLineEchoLibrary -LabRoot $root -SourceDirectory $DspDirectory) -ne $dll){throw 'DSP setup is not idempotent'}
 $processor=[OfficeAutomation.Telephony.Bridge.ModemLineEcho]::new($dll)
 $session=[OfficeAutomation.Telephony.Diagnostics.VoiceSession]::new([DiagnosticFakePort]::new(),$root,300,1000,[BridgeLifecycleEndpoint]::new(),$null,$false,$processor)
 $session.Start();$watch=[Diagnostics.Stopwatch]::StartNew();while(-not $session.Completed -and $watch.ElapsedMilliseconds -lt 10000){Start-Sleep -Milliseconds 10}
 if(-not $session.Completed){$session.Cancel();throw 'DSP binding test timed out'}
 if($session.Result.Status -ne 'bridged' -or -not $session.Result.CleanupConfirmed -or $processor.Metrics.ProcessedSamples -lt 1000){throw 'Eight-argument PS5.1 binding or native duplex failed'}
 $rejected=$false;try{Initialize-ModemLineEchoLibrary -LabRoot '\host\share' -SourceDirectory $DspDirectory}catch{$rejected=$true};if(-not $rejected){throw 'UNC profile must be rejected'}
 [IO.File]::WriteAllText($dll,'tampered')
 $rejected=$false;try{Initialize-ModemLineEchoLibrary -LabRoot $root -SourceDirectory $DspDirectory}catch{$rejected=$true};if(-not $rejected -or [IO.File]::ReadAllText($dll) -ne 'tampered'){throw 'Tampered local DLL must be refused without overwrite'}
 $errors=$null;[void][Management.Automation.Language.Parser]::ParseFile((Join-Path $PSScriptRoot 'Start-ModemBridge.ps1'),[ref]$null,[ref]$errors);if($errors.Count){throw $errors[0]}
 Write-Host 'PASS: pinned local staging, idempotency, tamper/UNC refusal, PS5.1 optional constructor, actual native duplex, cleanup and launcher syntax; no physical modem'
}finally{
 $resolved=[IO.Path]::GetFullPath($root);$prefix=[IO.Path]::GetFullPath($env:TEMP).TrimEnd('\')+'\office-line-echo-setup-'
 if($resolved.StartsWith($prefix,[StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $resolved)){Remove-Item -LiteralPath $resolved -Recurse -Force}
}