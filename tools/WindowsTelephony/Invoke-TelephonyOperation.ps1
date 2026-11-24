#requires -Version 5.1
[CmdletBinding()]
param([ValidateSet('Connect','Sync','Apply','Snapshot','LocalApply')][string]$Action='Snapshot',[string]$ServerUrl,[string]$PairingCode,[string]$DataRoot,[string]$LabRoot,[string]$EngineDirectory,[ValidateSet('ready','busy','stopped','error')][string]$RuntimeState='stopped',[switch]$Initialize,[string]$LocalConfigurationPath)
$ErrorActionPreference='Stop'
Import-Module (Join-Path $PSScriptRoot 'TelephonyConnection.psm1') -DisableNameChecking
Import-Module (Join-Path $PSScriptRoot 'TelephonyConfiguration.psm1') -DisableNameChecking
$paired=$false;$metadata=$null;$status=$null
try{
 if($Action -eq 'LocalApply'){
  $applied=Apply-TelephonyConfiguration -LabRoot $LabRoot -EngineDirectory $EngineDirectory -DataRoot $DataRoot -LocalConfigurationPath $LocalConfigurationPath
  [pscustomobject]@{success=$true;paired=$false;action=$Action;connection=$applied.connection;pbx=(Get-TelephonyPbxStatus -LabRoot $LabRoot);error_code=$null}
  return
 }elseif($Action -eq 'Connect'){
  if($PairingCode){$metadata=Connect-TelephonyAutomation -ServerUrl $ServerUrl -PairingCode $PairingCode -DataRoot $DataRoot;$paired=$true}
  else{$metadata=Get-TelephonyConnection -DataRoot $DataRoot;if(-not $metadata.connected){throw 'connection_missing'}}
  $metadata=Sync-TelephonyAutomation -RuntimeState $RuntimeState -DataRoot $DataRoot
  if($metadata.state.error_code){throw $metadata.state.error_code}
  if($Initialize){
   if(-not $metadata.state.pending_revision){throw 'configuration_not_ready'}
   $applied=Apply-TelephonyConfiguration -LabRoot $LabRoot -EngineDirectory $EngineDirectory -DataRoot $DataRoot
   $metadata=$applied.connection
  }
 }elseif($Action -eq 'Apply'){
  $metadata=Get-TelephonyConnection -DataRoot $DataRoot
  if(-not $metadata.connected){throw 'connection_missing'}
  $applied=Apply-TelephonyConfiguration -LabRoot $LabRoot -EngineDirectory $EngineDirectory -DataRoot $DataRoot;$metadata=$applied.connection
 }elseif($Action -eq 'Sync'){$metadata=Sync-TelephonyAutomation -RuntimeState $RuntimeState -DataRoot $DataRoot}
 else{$metadata=Get-TelephonyConnection -DataRoot $DataRoot}
 $status=Get-TelephonyPbxStatus -LabRoot $LabRoot
 [pscustomobject]@{success=(-not $metadata.state.error_code);paired=$paired;action=$Action;connection=$metadata;pbx=$status;error_code=$metadata.state.error_code}
}catch{
 $errorCode=[string]$_.Exception.Message
 if($errorCode -notmatch '\A(configuration_[a-z_]+|connection_[a-z_]+|invalid_[a-z_]+)\z'){
  if($_.Exception.Response -and [int]$_.Exception.Response.StatusCode -in @(401,403)){$errorCode=if($Action -eq 'Connect'){'pairing_rejected'}else{'connection_revoked'}}
  else{$errorCode=if($Action -in @('Apply','LocalApply')){'configuration_apply_failed'}else{'connection_failed'}}
 }
 try{Set-TelephonyConfigurationError -ErrorCode $errorCode -DataRoot $DataRoot;$metadata=Get-TelephonyConnection -DataRoot $DataRoot}catch{}
 [pscustomobject]@{success=$false;paired=$paired;action=$Action;connection=$metadata;pbx=$null;error_code=$errorCode}
}
