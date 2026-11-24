#requires -Version 5.1
param([Parameter(Mandatory=$true)][string]$SetupPath)
$ErrorActionPreference='Stop'
$setup=(Resolve-Path -LiteralPath $SetupPath).Path
$root=Join-Path $env:TEMP ('office-setup-test-'+[guid]::NewGuid().ToString('N'))
function Run-Checked([string]$File,[string]$Arguments) {
 $process=Start-Process -FilePath $File -ArgumentList $Arguments -WindowStyle Hidden -Wait -PassThru
 if($process.ExitCode -ne 0){throw ('Installer check failed: '+$process.ExitCode)}
}
Run-Checked $setup '--verify'
Run-Checked $setup ('--test-extract "'+$root+'"')
$marker=Join-Path $root 'current-release.txt';$first=[IO.File]::ReadAllText($marker)
Run-Checked (Join-Path $root 'OfficeAutomation.Telephony.exe') '--verify-runtime'
# An unrelated file represents existing user state. An upgrade must preserve it.
$preserved=Join-Path $root 'existing-user-state.txt';[IO.File]::WriteAllText($preserved,'preserve-me')
Run-Checked $setup ('--test-extract "'+$root+'"')
$second=[IO.File]::ReadAllText($marker)
if($first -eq $second -or -not (Test-Path -LiteralPath (Join-Path $root ('Releases/'+$first)))){throw 'Upgrade replaced an existing release'}
if([IO.File]::ReadAllText($preserved) -ne 'preserve-me'){throw 'Upgrade modified user state'}
if([IO.File]::ReadAllText($marker+'.previous') -ne $first){throw 'Previous release marker was not retained'}
Run-Checked (Join-Path $root 'OfficeAutomation.Telephony.exe') '--verify-runtime'
Write-Output 'PASS: embedded integrity, extraction, native launcher, upgrade, previous release and user-state preservation.'
Write-Output ('Isolated test installation: '+$root)
