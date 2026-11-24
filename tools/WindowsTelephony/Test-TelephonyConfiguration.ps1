#requires -Version 5.1
param([Parameter(Mandatory=$true)][string]$EngineDirectory)
$ErrorActionPreference='Stop'
Import-Module (Join-Path $PSScriptRoot 'TelephonyConnection.psm1') -Force -DisableNameChecking
Import-Module (Join-Path $PSScriptRoot 'TelephonyConfiguration.psm1') -Force -DisableNameChecking
Import-Module (Join-Path $PSScriptRoot 'PbxLab/PbxLab.psm1') -Force -DisableNameChecking
Add-Type -Path @((Join-Path $PSScriptRoot 'PbxLab/tests/NativeSipSmoke.cs'),(Join-Path $PSScriptRoot 'PbxLab/tests/NativeConfigurationCall.cs'))
$testRoot=Join-Path $env:TEMP ('office-configuration-test-'+[guid]::NewGuid().ToString('N'))
$root=Join-Path $testRoot 'PbxLab';$data=Join-Path $testRoot 'Connection'
$connectionModule=Get-Module TelephonyConnection
function Set-TestPending($Manifest) {
 $metadata=Get-TelephonyConnection -DataRoot $data
 $directory=Join-Path $data ('pending-'+[guid]::NewGuid().ToString('N'));[void][IO.Directory]::CreateDirectory($directory)
 $json=$Manifest|ConvertTo-Json -Depth 15 -Compress
 [IO.File]::WriteAllText((Join-Path $directory 'office-telephony.json'),$json,[Text.UTF8Encoding]::new($false))
 $hash=[Security.Cryptography.SHA256]::Create();try{$revision=[BitConverter]::ToString($hash.ComputeHash([Text.Encoding]::UTF8.GetBytes($json+([string][char]10)))).Replace('-','').ToLowerInvariant()}finally{$hash.Dispose()}
 & $connectionModule {param($Data,$Directory,$Revision) $state=Read-TelephonySyncState $Data;$state.pending_path=$Directory;$state.pending_revision=$Revision;Write-TelephonyPrivateJson (Join-Path $Data 'sync-state.json') $state} $data $directory $revision
 return $revision
}
$succeeded=$false
try{
 $state=New-PbxLabProfile -Root $root -BindAddress '127.0.0.1' -SipPort 15660
 Set-PbxModemGateway -Root $root -Destination '1002'
 $original=Read-PbxLabState $root;$originalSecret=$original.modem_gateway.password
 $configuration=[pscustomobject]@{schema_version=1;type='office_telephony_configuration';extensions=@([pscustomobject]@{number='1001';label='A'},[pscustomobject]@{number='1002';label='B'});ivr=[pscustomobject]@{enabled=$false;timeout_seconds=10;retries=2;operator_number=$null;choices=@();audio_file=$null};incoming=[pscustomobject]@{mode='extension';number='1002'}}
 $revision=Set-TestPending $configuration
 $applied=Apply-TelephonyConfiguration -LabRoot $root -EngineDirectory $EngineDirectory -DataRoot $data
 $state=Read-PbxLabState $root
 if(-not $applied.configuration_applied -or $applied.connection.state.applied_revision -ne $revision -or $state.modem_gateway.password -ne $originalSecret -or $state.accounts[0].password -ne $original.accounts[0].password){throw 'Initial configuration lost credentials or failed to start'}
 $configuration.extensions[1].label='Updated B';$pending=Set-TestPending $configuration
 $before=Get-TelephonyPbxStatus -LabRoot $root
 $call=[NativeConfigurationCall]::Start($state.sip_port,'1001',$state.accounts[0].password,'1002',$state.accounts[1].password)
 $clock=[Diagnostics.Stopwatch]::StartNew();$busy=$false
 while($clock.ElapsedMilliseconds -lt 7000 -and -not $call.IsCompleted){if((Get-TelephonyPbxStatus -LabRoot $root).active_calls -gt 0){$busy=$true;break};Start-Sleep -Milliseconds 40}
 if(-not $busy){throw 'Native call never became active'}
 $rejected=$false;try{Apply-TelephonyConfiguration -LabRoot $root -EngineDirectory $EngineDirectory -DataRoot $data|Out-Null}catch{if($_.Exception.Message -eq 'configuration_busy'){$rejected=$true}else{throw}}
 if(-not $rejected -or (Get-TelephonyPbxStatus -LabRoot $root).process_id -ne $before.process_id -or (Get-TelephonyConnection -DataRoot $data).state.applied_revision -ne $revision){throw 'Busy configuration application disturbed the live PBX'}
 if(-not $call.Wait(10000) -or -not $call.Result.HangupAcknowledged -or $call.Result.ReceivedPacketsA -lt 10 -or $call.Result.ReceivedPacketsB -lt 10){throw 'Call failed during deferred configuration'}
 $idle=[Diagnostics.Stopwatch]::StartNew();while((Get-TelephonyPbxStatus -LabRoot $root).active_calls -gt 0 -and $idle.ElapsedMilliseconds -lt 5000){Start-Sleep -Milliseconds 40}
 $applied=Apply-TelephonyConfiguration -LabRoot $root -EngineDirectory $EngineDirectory -DataRoot $data
 if($applied.connection.state.applied_revision -ne $pending -or (Read-PbxLabState $root).accounts[1].password -ne $original.accounts[1].password){throw 'Idle application did not preserve passwords'}
 $configuration.extensions[1].number='1001';$null=Set-TestPending $configuration
 $before=Get-TelephonyPbxStatus -LabRoot $root;$configurationHash=(Get-FileHash (Join-Path $root 'conf/freeswitch.xml')).Hash
 $rejected=$false;try{Apply-TelephonyConfiguration -LabRoot $root -EngineDirectory $EngineDirectory -DataRoot $data|Out-Null}catch{$rejected=$true}
 if(-not $rejected -or (Get-TelephonyPbxStatus -LabRoot $root).process_id -ne $before.process_id -or (Get-FileHash (Join-Path $root 'conf/freeswitch.xml')).Hash -ne $configurationHash){throw 'Invalid settings changed the running profile'}
 $succeeded=$true
 Write-Output 'PASS: authenticated-data contract applied to real Windows PBX, original SIP/gateway passwords preserved, live two-way call protected, idle update succeeded and invalid configuration rejected before stop; no modem opened'
}finally{
 if(Test-Path -LiteralPath (Join-Path $root 'lab-session.json')){& (Join-Path $PSScriptRoot 'PbxLab/Stop-PbxLab.ps1') -LabRoot $root}
 if($succeeded -and -not (Get-Process -Name FreeSwitchConsole -ErrorAction SilentlyContinue)){
  $resolved=[IO.Path]::GetFullPath($testRoot);$prefix=[IO.Path]::GetFullPath($env:TEMP).TrimEnd('\')+'\office-configuration-test-'
  if($resolved.StartsWith($prefix,[StringComparison]::OrdinalIgnoreCase)){Remove-Item -LiteralPath $resolved -Recurse -Force}
 }else{Write-Output ('Retained configuration test profile: '+$testRoot)}
}
