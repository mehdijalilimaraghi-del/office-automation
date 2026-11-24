#requires -Version 5.1
param([Parameter(Mandatory=$true)][string]$EngineDirectory)
$ErrorActionPreference='Stop'
Import-Module (Join-Path $PSScriptRoot 'PbxLab/PbxLab.psm1') -Force -DisableNameChecking
Import-Module (Join-Path $PSScriptRoot 'TelephonyConnection.psm1') -Force -DisableNameChecking
Import-Module (Join-Path $PSScriptRoot 'TelephonyConfiguration.psm1') -Force -DisableNameChecking
Add-Type -Path (Join-Path $PSScriptRoot 'PbxLab/tests/NativeSipSmoke.cs')
$testRoot=Join-Path $env:TEMP ('office-standalone-test-'+[guid]::NewGuid().ToString('N'))
$root=Join-Path $testRoot 'PbxLab';$data=Join-Path $testRoot 'Connection'
try{
 $state=New-PbxLabProfile -Root $root -BindAddress '127.0.0.1' -SipPort 15660
 Set-PbxModemGateway -Root $root -Destination '1002';$before=Read-PbxLabState $root
 $folder=Join-Path $root ('local-settings-'+[guid]::NewGuid().ToString('N'));[void][IO.Directory]::CreateDirectory($folder)
 $manifest=[pscustomobject]@{schema_version=1;type='office_telephony_configuration';extensions=@([pscustomobject]@{number='1001';label='Reception'},[pscustomobject]@{number='1002';label='Office'});ivr=[pscustomobject]@{enabled=$false;timeout_seconds=10;retries=2;operator_number=$null;choices=@();audio_file=$null};incoming=[pscustomobject]@{mode='extension';number='1002'}}
 $path=Join-Path $folder 'office-telephony.json';[IO.File]::WriteAllText($path,($manifest|ConvertTo-Json -Depth 12),[Text.UTF8Encoding]::new($false))
 $result=Apply-TelephonyConfiguration -LabRoot $root -DataRoot $data -EngineDirectory $EngineDirectory -LocalConfigurationPath $path
 if(-not $result.configuration_applied -or $result.connection.connected){throw 'Independent setup incorrectly required a paired automation account'}
 $after=Read-PbxLabState $root
 if($after.accounts[0].password -ne $before.accounts[0].password -or $after.modem_gateway.password -ne $before.modem_gateway.password){throw 'Local editing replaced existing credentials'}
 $call=[NativeSipSmoke]::Run($after.sip_port,'1001',$after.accounts[0].password,'1002',$after.accounts[1].password)
 if(-not $call.HangupAcknowledged -or $call.ReceivedPacketsA -lt 10 -or $call.ReceivedPacketsB -lt 10){throw 'Independent two-way internal call failed'}
 $invalid=Join-Path $testRoot 'outside.json';[IO.File]::WriteAllText($invalid,'{}')
 $rejected=$false;try{Apply-TelephonyConfiguration -LabRoot $root -DataRoot $data -EngineDirectory $EngineDirectory -LocalConfigurationPath $invalid|Out-Null}catch{if($_.Exception.Message -eq 'configuration_storage_invalid'){$rejected=$true}else{throw}}
 if(-not $rejected){throw 'Local settings outside protected profile were accepted'}
 Write-Output 'PASS: independent configuration and native two-way call without automation/token, retained SIP and gateway credentials, and confined local settings.'
}finally{
 if(Test-Path -LiteralPath (Join-Path $root 'lab-session.json')){& (Join-Path $PSScriptRoot 'PbxLab/Stop-PbxLab.ps1') -LabRoot $root}
}
