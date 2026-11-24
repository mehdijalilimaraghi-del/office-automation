#requires -Version 5.1
param([string]$EngineDirectory,[switch]$Pcm16,[switch]$LocalGreeting,[switch]$BargeIn,[string]$DspDirectory,[switch]$Receiver)
$ErrorActionPreference='Stop'
if($BargeIn){$LocalGreeting=$true}
if($Receiver -and -not $DspDirectory){throw 'Receiver test requires the verified DSP directory'}
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
 $config=[pscustomobject]@{schema_version=1;type='office_telephony_configuration';extensions=@([pscustomobject]@{number='1001'},[pscustomobject]@{number='1002'},[pscustomobject]@{number='1003'});ivr=[pscustomobject]@{enabled=$true;timeout_seconds=3;retries=1;operator_number='1002';choices=@([pscustomobject]@{digit='1';number='1002'},[pscustomobject]@{digit='2';number='1002'});audio_file='greeting.wav'}}
 $config.ivr.operator_number='1003'
 Import-PbxOfficeConfiguration $root $config $sourceFolder
 Set-PbxModemGateway $root '9197'
 $state=Read-PbxLabState $root
 $gatewaySecret=$state.modem_gateway.password
 Set-PbxModemGateway $root '9197'
 $state=Read-PbxLabState $root
 if($state.modem_gateway.password -ne $gatewaySecret -or $state.accounts[0].password -ne $original -or $state.modem_gateway.greeting_protocol -ne 'vtx_initial_v3'){throw 'Gateway upgrade changed credentials or lacks greeting support'}
 if($state.accounts[0].password -ne $original -or $state.accounts.Count -ne 3){throw 'Provisioning did not preserve accounts'}
 $xml=[xml][IO.File]::ReadAllText((Join-Path $root 'conf/freeswitch.xml'))
 if(-not $xml.SelectSingleNode('//menu[@name="office_main"]/entry[@digits="1"]')){throw 'IVR route missing'}
 if(-not $xml.SelectSingleNode('//user[@id="office_modem"]/variables/variable[@name="rtp_rewrite_timestamps" and @value="true"]')){throw 'Modem timestamp rewriting missing'}
 foreach($route in @('modem_local_greeting','modem_selected_destination')){if(-not $xml.SelectSingleNode('//extension[@name="'+$route+'"]//action[@application="export" and @data="nolocal:jitterbuffer_msec=60:200"]')){throw 'Called-party receive buffer missing'}}
 if($xml.SelectSingleNode('//user[not(@id="office_modem")]/variables/variable[@name="rtp_rewrite_timestamps"]') -or $xml.SelectSingleNode('//context[@name="office_lab"]//action[@data="nolocal:jitterbuffer_msec=60:200"]')){throw 'Modem timing changes leaked into ordinary internal calls'}
 $normalMenu=$xml.SelectSingleNode('//menu[@name="office_main"]')
 $modemMenu=$xml.SelectSingleNode('//menu[@name="office_modem_after_greeting"]')
 if($modemMenu.GetAttribute('greet-long') -ne 'silence_stream://100' -or $modemMenu.GetAttribute('greet-short') -ne $normalMenu.GetAttribute('greet-short') -or $modemMenu.InnerXml -ne $normalMenu.InnerXml){throw 'Initial greeting duplicated or menu choices/retry prompt changed'}
 foreach($attribute in @('timeout','inter-digit-timeout','max-failures','max-timeouts','exec-on-max-failures','exec-on-max-timeouts')){if($modemMenu.GetAttribute($attribute) -ne $normalMenu.GetAttribute($attribute)){throw 'Menu retry/operator policy changed'}}
 if(-not $xml.SelectSingleNode('//context[@name="office_modem_inbound"]/extension[@name="modem_local_greeting"]') -or $xml.SelectSingleNode('//context[@name="office_lab"]/extension[@name="modem_local_greeting"]')){throw 'Modem greeting park scope is incorrect'}
 if(-not ([IO.File]::ReadAllText((Join-Path $root 'accounts.html'))).Contains('SIP Proxy')){throw 'Proxy instructions missing'}
 $before=(Get-FileHash (Join-Path $root 'lab-state.json')).Hash
 $config.ivr.choices[0].number='9999';$rejected=$false;try{Import-PbxOfficeConfiguration $root $config $sourceFolder}catch{$rejected=$true}
 if(-not $rejected -or (Get-FileHash (Join-Path $root 'lab-state.json')).Hash -ne $before){throw 'Invalid configuration mutated state'}
 $config.ivr.choices[0].number='1002'
 if($EngineDirectory){
  & (Join-Path $PSScriptRoot '../Start-PbxLab.ps1') -LabRoot $root -EngineDirectory $EngineDirectory -NoBrowser -HideConsole
  try{
   Add-Type -Path @((Join-Path $PSScriptRoot '../../VoiceDiagnostics.cs'),(Join-Path $PSScriptRoot '../../LocalSipAudioEndpoint.cs'),(Join-Path $PSScriptRoot '../../GreetingPrompt.cs'),(Join-Path $PSScriptRoot '../../ModemGreetingBridge.cs'),(Join-Path $PSScriptRoot '../../ModemBridge.Tests.cs'),(Join-Path $PSScriptRoot '../../ModemLineEcho.cs'),(Join-Path $PSScriptRoot 'NativeSipSmoke.cs'))
   for($call=0;$call -lt $(if($Receiver){2}else{1});$call++){
   $processor=$null;if($DspDirectory){$processor=[OfficeAutomation.Telephony.Bridge.ModemLineEcho]::new((Join-Path $DspDirectory 'libspeexdsp-1.dll'))}
   if($LocalGreeting){[ModemBridgeIntegration]::Run($state.sip_port,$state.modem_gateway.password,$state.accounts[1].password,$false,$state.esl_port,$state.esl_secret,$state.ivr.prompt_path,[bool]$BargeIn,$processor,[bool]$Receiver)|ConvertTo-Json -Depth 6}else{[ModemBridgeIntegration]::Run($state.sip_port,$state.modem_gateway.password,$state.accounts[1].password,[bool]$Pcm16,0,$null,$null,$false,$processor,[bool]$Receiver)|ConvertTo-Json -Depth 6}
   if($processor -and ($processor.Metrics.ProcessedSamples -lt 8000 -or $processor.Metrics.Epochs -ne 1)){throw 'DSP did not process native duplex media'}
   }
  }catch{Write-Host $_.Exception.Message;throw}finally{if($processor){$processor.Dispose()};& (Join-Path $PSScriptRoot '../Stop-PbxLab.ps1') -LabRoot $root;$owned=Get-Content (Join-Path $root 'lab-session.json') -Raw|ConvertFrom-Json;if(Get-Process -Id $owned.process_id -ErrorAction SilentlyContinue){throw 'Owned native process has not stopped; profile retained'}}
 }
 Set-PbxModemGateway $root '1002'
 $direct=[xml][IO.File]::ReadAllText((Join-Path $root 'conf/freeswitch.xml'))
 if(-not $direct.SelectSingleNode('//user[@id="office_modem"]//variable[@name="rtp_rewrite_timestamps" and @value="true"]') -or -not $direct.SelectSingleNode('//extension[@name="modem_selected_destination"]//action[@application="export" and @data="nolocal:jitterbuffer_msec=60:200"]') -or $direct.SelectSingleNode('//extension[@name="modem_local_greeting"]')){throw 'Direct-extension gateway timing scope is incorrect'}
 if((Read-PbxLabState $root).modem_gateway.password -ne $gatewaySecret){throw 'Direct gateway update changed credentials'}
 $succeeded=$true
 Write-Host 'PASS: simulated modem to real native PBX, inband DTMF/IVR routing, two-way RTP and phone hangup'
}finally{
 if(-not $succeeded){Write-Host ('Failed test profile retained: '+$root)}
 $active=Get-Process -Name FreeSwitchConsole -ErrorAction SilentlyContinue
 foreach($pair in @(@($root,'office-pbx-provision-'),@($sourceFolder,'office-pbx-prompt-'))){$resolved=[IO.Path]::GetFullPath($pair[0]);$prefix=[IO.Path]::GetFullPath($env:TEMP).TrimEnd('\')+'\'+$pair[1];if($succeeded -and -not $active -and $resolved.StartsWith($prefix,[StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $resolved)){Remove-Item -LiteralPath $resolved -Recurse -Force}}
}
