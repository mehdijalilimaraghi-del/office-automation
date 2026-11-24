#requires -Version 5.1
$ErrorActionPreference='Stop'
Import-Module (Join-Path $PSScriptRoot 'TelephonyConnection.psm1') -Force -DisableNameChecking
Add-Type -AssemblyName System.IO.Compression;Add-Type -AssemblyName System.IO.Compression.FileSystem
$testRoot=Join-Path $env:TEMP ('office-connection-test-'+[guid]::NewGuid().ToString('N'));[void][IO.Directory]::CreateDirectory($testRoot)
$dataRoot=Join-Path $testRoot 'Connection';$fixture=Join-Path $testRoot 'configuration.zip'
$manifest=@{schema_version=1;type='office_telephony_configuration';extensions=@(@{number='1001';label='Office';owner_type='user';user_id=1;department_id=$null});ivr=@{enabled=$false;timeout_seconds=10;retries=2;operator_number=$null;choices=@();audio_file=$null}}|ConvertTo-Json -Depth 12 -Compress
$json=[Text.UTF8Encoding]::new($false).GetBytes($manifest)
$zip=[IO.Compression.ZipFile]::Open($fixture,[IO.Compression.ZipArchiveMode]::Create)
try{$entry=$zip.CreateEntry('office-telephony.json');$stream=$entry.Open();try{$stream.Write($json,0,$json.Length)}finally{$stream.Dispose()}}finally{$zip.Dispose()}
$hash=[Security.Cryptography.SHA256]::Create();try{$revision=[BitConverter]::ToString($hash.ComputeHash([Text.Encoding]::UTF8.GetBytes($manifest+([string][char]10)))).Replace('-','').ToLowerInvariant()}finally{$hash.Dispose()}
$module=Get-Module TelephonyConnection
& $module {
 param($Fixture,$Revision)
 $script:Fixture=$Fixture;$script:Revision=$Revision;$script:Broken=$false;$script:NetworkFailure=$false;$script:Requests=0
 function script:Invoke-WebRequest {
  param($Uri,$Method,$Headers,[switch]$UseBasicParsing,$TimeoutSec,$MaximumRedirection,$ContentType,$Body,$OutFile,[switch]$PassThru,$ErrorAction)
  $script:Requests++
  if($MaximumRedirection -ne 0 -or $Uri.Host -ne 'automation.example.test' -or $Uri.AbsolutePath -notlike '/app/telephony/agent/*'){throw 'Unsafe HTTP request'}
  if($script:NetworkFailure){throw ('network '+('a'*64))}
  if($Uri.AbsolutePath.EndsWith('/pair')){
   if($Headers.ContainsKey('Authorization')){throw 'Pairing must not send an existing bearer token'}
   return [pscustomobject]@{Content=(@{device_id=1;name='Test PBX';token=('a'*64)}|ConvertTo-Json)}
  }
  if($Headers.Authorization -ne ('Bearer '+('a'*64))){throw 'Missing scoped device credential'}
  if($Uri.AbsolutePath.EndsWith('/heartbeat')){return [pscustomobject]@{Content=(@{configuration_ready=$true;configuration_revision=$(if($script:Broken){'b'*64}else{$script:Revision})}|ConvertTo-Json)}}
  if($Uri.AbsolutePath.EndsWith('/configuration')){Copy-Item -LiteralPath $script:Fixture -Destination $OutFile;return [pscustomobject]@{Headers=@{'X-Office-Configuration-Revision'=$(if($script:Broken){'b'*64}else{$script:Revision})}}}
  throw 'Unexpected endpoint'
 }
} $fixture $revision
try {
 $connected=Connect-TelephonyAutomation -ServerUrl 'https://automation.example.test/app/' -PairingCode ('c'*64) -DataRoot $dataRoot
 if(-not $connected.connected -or $connected.device_id -ne 1){throw 'Pairing failed'}
 $stored=[IO.File]::ReadAllText((Join-Path $dataRoot 'connection.json'))
 if($stored.Contains('a'*64) -or $connected.PSObject.Properties.Name -contains 'token' -or $connected.PSObject.Properties.Name -contains 'protected_token'){throw 'Plaintext credential leaked'}
 if(-not (Get-Acl -LiteralPath $dataRoot).AreAccessRulesProtected){throw 'Credential directory is not protected'}
 $synced=Sync-TelephonyAutomation -RuntimeState 'busy' -DataRoot $dataRoot
 if($synced.state.error_code -or $synced.state.pending_revision -ne $revision -or -not (Test-Path -LiteralPath (Join-Path $synced.state.pending_path 'office-telephony.json'))){throw ('Pending package failed: '+$synced.state.error_code)}
 if($synced.state.applied_revision){throw 'Downloaded configuration must not be marked applied while busy'}
 $applied=Complete-TelephonyConfiguration -Revision $revision -DataRoot $dataRoot
 if($applied.state.applied_revision -ne $revision -or $applied.state.pending_path){throw 'Applied configuration status failed'}
 & $module {$script:Broken=$true}
 $broken=Sync-TelephonyAutomation -DataRoot $dataRoot
 if($broken.state.error_code -ne 'configuration_hash_mismatch' -or $broken.state.applied_revision -ne $revision){throw 'Unverified package changed applied configuration'}
 & $module {$script:NetworkFailure=$true}
 $failure=Sync-TelephonyAutomation -DataRoot $dataRoot
 if($failure.state.error_code -ne 'connection_failed' -or ($failure|ConvertTo-Json -Depth 12).Contains('a'*64)){throw 'Network failure leaked credentials or changed error contract'}
 foreach($url in @('ftp://automation.example.test','https://user:secret@automation.example.test','https://automation.example.test/?token=secret')){
  $rejected=$false;try{Connect-TelephonyAutomation -ServerUrl $url -PairingCode ('c'*64) -DataRoot $dataRoot|Out-Null}catch{$rejected=$true};if(-not $rejected){throw 'Unsafe server URL accepted'}
 }
 Write-Output 'PASS: one-time pairing client, DPAPI/protected storage, scoped same-origin requests, busy-state download without apply, revision validation and secret-free failure handling; no network/modem/PBX opened'
}finally{
 Remove-Module TelephonyConnection -Force
 $resolved=[IO.Path]::GetFullPath($testRoot);$prefix=[IO.Path]::GetFullPath($env:TEMP).TrimEnd('\')+'\office-connection-test-'
 if($resolved.StartsWith($prefix,[StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $resolved)){Remove-Item -LiteralPath $resolved -Recurse -Force}
}
