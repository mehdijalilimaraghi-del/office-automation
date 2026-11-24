#requires -Version 5.1
$ErrorActionPreference='Stop'
$pbxFolder=if(Test-Path -LiteralPath (Join-Path $PSScriptRoot 'PbxLab.psm1')){$PSScriptRoot}else{Join-Path $PSScriptRoot 'PbxLab'}
Import-Module (Join-Path $pbxFolder 'PbxLab.psm1') -DisableNameChecking
Import-Module (Join-Path $PSScriptRoot 'TelephonyConnection.psm1') -DisableNameChecking
function Get-TelephonyOwnedPbxStatus {
 [CmdletBinding()]param([string]$LabRoot)
 if(-not $LabRoot){$LabRoot=Join-Path $env:LOCALAPPDATA 'OfficeAutomation\Telephony\PbxLab'}
 if(-not (Test-Path -LiteralPath (Join-Path $LabRoot 'lab-state.json'))){return [pscustomobject]@{configured=$false;running=$false;active_calls=0}}
 $state=Read-PbxLabState $LabRoot
 $sessionPath=Join-Path $LabRoot 'lab-session.json';$process=$null
 if(Test-Path -LiteralPath $sessionPath){
  $session=[IO.File]::ReadAllText($sessionPath)|ConvertFrom-Json
  $candidate=Get-Process -Id $session.process_id -ErrorAction SilentlyContinue
  if($candidate){
   if($session.profile_id -ne $state.profile_id -or $candidate.Path -ne $session.binary_path -or $candidate.StartTime.ToUniversalTime().Ticks -ne $session.process_start_ticks){throw 'configuration_process_identity_changed'}
   $process=$candidate
  }
 }
 $active=0
 if($process){$calls=(Invoke-PbxLabCommand $state 'show calls as json')|ConvertFrom-Json;if('row_count' -notin $calls.PSObject.Properties.Name){throw 'configuration_call_state_unknown'};$active=[int]$calls.row_count;if($active -lt 0){throw 'configuration_call_state_unknown'}}
 return [pscustomobject]@{configured=$true;running=[bool]$process;active_calls=$active;profile=$state;process=$process}
}
function Apply-TelephonyConfiguration {
 [CmdletBinding()]param([string]$LabRoot,[Parameter(Mandatory=$true)][string]$EngineDirectory,[string]$DataRoot,[string]$LocalConfigurationPath)
 if(-not $LabRoot){$LabRoot=Join-Path $env:LOCALAPPDATA 'OfficeAutomation\Telephony\PbxLab'}
 $LabRoot=[IO.Path]::GetFullPath($LabRoot)
 if($LabRoot.StartsWith('\\')){throw 'configuration_requires_local_profile'}
 if($LocalConfigurationPath){
  $source=[IO.Path]::GetFullPath($LocalConfigurationPath)
  if(-not $source.StartsWith(($LabRoot.TrimEnd('\')+'\local-settings-'),[StringComparison]::OrdinalIgnoreCase)){throw 'configuration_storage_invalid'}
  $pending=[pscustomobject]@{manifest=([IO.File]::ReadAllText($source)|ConvertFrom-Json);path=[IO.Path]::GetDirectoryName($source);revision=$null}
 }else{$pending=Get-TelephonyPendingConfiguration -DataRoot $DataRoot}
 if(-not $pending){throw 'configuration_not_ready'}
 Assert-PbxOfficeConfiguration $pending.manifest
 $status=Get-TelephonyOwnedPbxStatus -LabRoot $LabRoot
 $service=Get-Service -Name FreeSWITCH -ErrorAction SilentlyContinue
 if($service -and $service.Status -ne 'Stopped'){throw 'configuration_service_active'}
 if($status.active_calls -gt 0){throw 'configuration_busy'}
 if((Get-Process -Name FreeSwitchConsole -ErrorAction SilentlyContinue) -and -not $status.running){throw 'configuration_other_process_running'}
 $wasRunning=$status.running
 if($wasRunning){
  Invoke-PbxLabCommand $status.profile 'fsctl shutdown elegant'|Out-Null
  if(-not $status.process.WaitForExit(15000)){throw 'configuration_shutdown_pending'}
 }
 $backup=$null;$files=@('lab-state.json','accounts.html','conf/freeswitch.xml')
 if($status.configured){
  $backup=Join-Path $LabRoot ('connection-backup-'+[guid]::NewGuid().ToString('N'));[void][IO.Directory]::CreateDirectory($backup)
  foreach($name in $files){Copy-Item -LiteralPath (Join-Path $LabRoot $name) -Destination (Join-Path $backup ([IO.Path]::GetFileName($name)))}
 }
 try{
  $configuration=$pending.manifest;$numbers=@($configuration.extensions.number)
  $destination=$null
  if($configuration.incoming){
   if($configuration.incoming.mode -eq 'ivr' -and $configuration.ivr.enabled){$destination='9197'}
   elseif($configuration.incoming.mode -eq 'extension' -and $configuration.incoming.number -in $numbers){$destination=[string]$configuration.incoming.number}
   else{throw 'configuration_destination_invalid'}
  }
  if($destination -and $status.configured -and $status.profile.modem_gateway){
   $status.profile.modem_gateway.destination=$destination
   $temporary=Join-Path $LabRoot ('state-'+[guid]::NewGuid().ToString('N')+'.new')
   [IO.File]::WriteAllText($temporary,($status.profile|ConvertTo-Json -Depth 20),[Text.UTF8Encoding]::new($false))
   [IO.File]::Replace($temporary,(Join-Path $LabRoot 'lab-state.json'),[System.Management.Automation.Language.NullString]::Value)
  }
  Import-PbxOfficeConfiguration -Root $LabRoot -Configuration $configuration -SourceDirectory $pending.path
  $state=Read-PbxLabState $LabRoot
  if(-not $destination){$destination=if($state.modem_gateway){[string]$state.modem_gateway.destination}elseif($state.ivr -and $state.ivr.enabled){'9197'}else{[string]$state.accounts[0].extension}}
  Set-PbxModemGateway -Root $LabRoot -Destination $destination
  & (Join-Path $pbxFolder 'Start-PbxLab.ps1') -LabRoot $LabRoot -EngineDirectory $EngineDirectory -NoBrowser -HideConsole|Out-Null
  $ready=Get-TelephonyOwnedPbxStatus -LabRoot $LabRoot
  if(-not $ready.running){throw 'configuration_pbx_start_failed'}
  $metadata=if($LocalConfigurationPath){Reset-TelephonyAppliedRevision -DataRoot $DataRoot;Get-TelephonyConnection -DataRoot $DataRoot}else{Complete-TelephonyConfiguration -Revision $pending.revision -DataRoot $DataRoot}
  return [pscustomobject]@{connection=$metadata;profile_id=$ready.profile.profile_id;bind_address=$ready.profile.bind_address;sip_port=$ready.profile.sip_port;destination=$ready.profile.modem_gateway.destination;configuration_applied=$true}
 }catch{
  # Never stop an unowned process or force a running call down during rollback.
  $originalError=$_;$current=$null;try{$current=Get-TelephonyOwnedPbxStatus -LabRoot $LabRoot}catch{}
  if($current -and $current.running -and $current.active_calls -eq 0){Invoke-PbxLabCommand $current.profile 'fsctl shutdown elegant'|Out-Null;[void]$current.process.WaitForExit(15000)}
  if($backup -and -not (Get-Process -Name FreeSwitchConsole -ErrorAction SilentlyContinue)){
   foreach($name in $files){Copy-Item -LiteralPath (Join-Path $backup ([IO.Path]::GetFileName($name))) -Destination (Join-Path $LabRoot $name) -Force}
   if($wasRunning){& (Join-Path $pbxFolder 'Start-PbxLab.ps1') -LabRoot $LabRoot -EngineDirectory $EngineDirectory -NoBrowser -HideConsole|Out-Null}
  }
  throw $originalError
 }
}
function Get-TelephonyPbxStatus {
 [CmdletBinding()]param([string]$LabRoot)
 $status=Get-TelephonyOwnedPbxStatus -LabRoot $LabRoot
 return [pscustomobject]@{configured=$status.configured;running=$status.running;active_calls=$status.active_calls;process_id=$(if($status.process){$status.process.Id}else{$null});bind_address=$(if($status.profile){$status.profile.bind_address}else{$null});sip_port=$(if($status.profile){$status.profile.sip_port}else{$null});destination=$(if($status.profile -and $status.profile.modem_gateway){$status.profile.modem_gateway.destination}else{$null});extension_count=$(if($status.profile){@($status.profile.accounts).Count}else{0})}
}
Export-ModuleMember -Function Get-TelephonyPbxStatus,Apply-TelephonyConfiguration
