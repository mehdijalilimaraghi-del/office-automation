#requires -Version 5.1
param([string]$EngineDirectory,[switch]$Pcm16)
$ErrorActionPreference='Stop'
Import-Module (Join-Path $PSScriptRoot '../PbxLab.psm1') -Force -DisableNameChecking
$root=Join-Path $env:TEMP ('office-pbx-provision-'+[guid]::NewGuid().ToString('N'))
$sourceFolder=Join-Path $env:TEMP ('office-pbx-prompt-'+[guid]::NewGuid().ToString('N'))
[void][IO.Directory]::CreateDirectory($sourceFolder)
$succeeded=$false
try {
 $state=New-PbxLabProfile -Root $root -BindAddress '127.0.0.1' -SipPort 15660
 $original=$state.accounts[0].password
 $writer=[IO.BinaryWriter]::new([IO.File]::Create((Join-Path $sourceFolder 'greeting.wav')))
 try{$writer.Write([Text.Encoding]::ASCII.GetBytes('RIFF'));$writer.Write([int]32036);$writer.Write([Text.Encoding]::ASCII.GetBytes('WAVEfmt '));$writer.Write([int]16);$writer.Write([int16]1);$writer.Write([int16]1);$writer.Write([int]8000);$writer.Write([int]16000);$writer.Write([int16]2);$writer.Write([int16]16);$writer.Write([Text.Encoding]::ASCII.GetBytes('data'));$writer.Write([int]32000);for($i=0;$i -lt 16000;$i++){$writer.Write([int16](3000*[Math]::Sin(2*[Math]::PI*440*$i/8000)))}}finally{$writer.Dispose()}
 $config=[pscustomobject]@{schema_version=1;type='office_telephony_configuration';extensions=@([pscustomobject]@{number='1001'},[pscustomobject]@{number='1002'},[pscustomobject]@{number='1003'});ivr=[pscustomobject]@{enabled=$true;timeout_seconds=3;retries=1;operator_number='1002';choices=@([pscustomobject]@{digit='1';number='1002'});audio_file='greeting.wav'}}
 $config.ivr.operator_number='1003'
 Import-PbxOfficeConfiguration $root $config $sourceFolder
 Set-PbxModemGateway $root '9197'
 $state=Read-PbxLabState $root
 if($state.accounts[0].password -ne $original -or $state.accounts.Count -ne 3){throw 'Provisioning did not preserve accounts'}
 $xml=[xml][IO.File]::ReadAllText((Join-Path $root 'conf/freeswitch.xml'))
 if(-not $xml.SelectSingleNode('//menu[@name="office_main"]/entry[@digits="1"]')){throw 'IVR route missing'}
 if(-not ([IO.File]::ReadAllText((Join-Path $root 'accounts.html'))).Contains('SIP Proxy')){throw 'Proxy instructions missing'}
 $before=(Get-FileHash (Join-Path $root 'lab-state.json')).Hash
 $config.ivr.choices[0].number='9999';$rejected=$false;try{Import-PbxOfficeConfiguration $root $config $sourceFolder}catch{$rejected=$true}
 if(-not $rejected -or (Get-FileHash (Join-Path $root 'lab-state.json')).Hash -ne $before){throw 'Invalid configuration mutated state'}
 $config.ivr.choices[0].number='1002'
 if($EngineDirectory){
  & (Join-Path $PSScriptRoot '../Start-PbxLab.ps1') -LabRoot $root -EngineDirectory $EngineDirectory -NoBrowser -HideConsole
  try{
   Add-Type -Path @((Join-Path $PSScriptRoot '../../VoiceDiagnostics.cs'),(Join-Path $PSScriptRoot '../../LocalSipAudioEndpoint.cs'),(Join-Path $PSScriptRoot '../../ModemBridge.Tests.cs'),(Join-Path $PSScriptRoot 'NativeSipSmoke.cs'))
   [ModemBridgeIntegration]::Run($state.sip_port,$state.modem_gateway.password,$state.accounts[1].password,[bool]$Pcm16)|ConvertTo-Json -Depth 6
  }catch{Write-Host $_.Exception.Message;throw}finally{& (Join-Path $PSScriptRoot '../Stop-PbxLab.ps1') -LabRoot $root;$owned=Get-Content (Join-Path $root 'lab-session.json') -Raw|ConvertFrom-Json;if(Get-Process -Id $owned.process_id -ErrorAction SilentlyContinue){throw 'Owned native process has not stopped; profile retained'}}
 }
 $succeeded=$true
 Write-Host 'PASS: simulated modem to real native PBX, inband DTMF/IVR routing, two-way RTP and phone hangup'
}finally{
 if(-not $succeeded){Write-Host ('Failed test profile retained: '+$root)}
 $active=Get-Process -Name FreeSwitchConsole -ErrorAction SilentlyContinue
 foreach($pair in @(@($root,'office-pbx-provision-'),@($sourceFolder,'office-pbx-prompt-'))){$resolved=[IO.Path]::GetFullPath($pair[0]);$prefix=[IO.Path]::GetFullPath($env:TEMP).TrimEnd('\')+'\'+$pair[1];if($succeeded -and -not $active -and $resolved.StartsWith($prefix,[StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $resolved)){Remove-Item -LiteralPath $resolved -Recurse -Force}}
}
