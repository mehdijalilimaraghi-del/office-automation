#requires -Version 5.1
$ErrorActionPreference='Stop'
Import-Module (Join-Path $PSScriptRoot 'PbxLab/PbxLab.psm1') -Force -DisableNameChecking
$root=Join-Path $env:TEMP ('office-ivr-activation-test-'+[guid]::NewGuid().ToString('N'))
$success=$false
try{
 $state=New-PbxLabProfile -Root $root -BindAddress '127.0.0.1' -Extensions @('1001','1002','1003')
 $passwords=@{};foreach($account in $state.accounts){$passwords[$account.extension]=$account.password}
 $wave=Join-Path $root 'input.wav'
 $writer=[IO.BinaryWriter]::new([IO.File]::Create($wave))
 try{
  $writer.Write([Text.Encoding]::ASCII.GetBytes('RIFF'));$writer.Write([int]16036)
  $writer.Write([Text.Encoding]::ASCII.GetBytes('WAVEfmt '));$writer.Write([int]16)
  $writer.Write([int16]1);$writer.Write([int16]1);$writer.Write([int]8000);$writer.Write([int]16000);$writer.Write([int16]2);$writer.Write([int16]16)
  $writer.Write([Text.Encoding]::ASCII.GetBytes('data'));$writer.Write([int]16000)
  for($i=0;$i -lt 8000;$i++){$writer.Write([int16](2000*[Math]::Sin(2*[Math]::PI*440*$i/8000)))}
 }finally{$writer.Dispose()}
 & (Join-Path $PSScriptRoot 'Enable-ModemIvr.ps1') -LabRoot $root -PromptPath $wave -NoConfirm
 $state=Read-PbxLabState $root
 if($state.modem_gateway.destination -ne '9197' -or -not $state.ivr.enabled){throw 'IVR gateway not enabled'}
 if($state.accounts.Count -ne 3){throw 'Extra account removed'}
 foreach($account in $state.accounts){if($passwords[$account.extension] -ne $account.password){throw 'Password changed'}}
 if($state.ivr.choices[0].digit -ne '1' -or $state.ivr.choices[0].number -ne '1001' -or $state.ivr.choices[1].number -ne '1002' -or $state.ivr.operator_number -ne '1002'){throw 'Wrong routes'}
 $prompt=$state.ivr.prompt_path;$secret=$state.modem_gateway.password
 & (Join-Path $PSScriptRoot 'Enable-ModemIvr.ps1') -LabRoot $root -NoConfirm
 $state=Read-PbxLabState $root
 if($state.ivr.prompt_path -ne $prompt -or $state.modem_gateway.password -ne $secret){throw 'Reuse replaced prompt or gateway password'}
 $custom=[pscustomobject]@{type='office_telephony_configuration';schema_version=1;extensions=@($state.accounts|ForEach-Object{[pscustomobject]@{number=$_.extension}});ivr=[pscustomobject]@{enabled=$true;timeout_seconds=11;retries=3;operator_number='1003';audio_file='greeting.wav';choices=@([pscustomobject]@{digit='9';number='1003'})}}
 Copy-Item -LiteralPath $wave -Destination (Join-Path $root 'greeting.wav')
 Import-PbxOfficeConfiguration -Root $root -Configuration $custom -SourceDirectory $root
 & (Join-Path $PSScriptRoot 'Enable-ModemIvr.ps1') -LabRoot $root -PromptPath $wave -NoConfirm
 $state=Read-PbxLabState $root
 if($state.ivr.operator_number -ne '1003' -or $state.ivr.timeout_seconds -ne 11 -or $state.ivr.retries -ne 3 -or $state.ivr.choices.Count -ne 1 -or $state.ivr.choices[0].digit -ne '9' -or $state.ivr.choices[0].number -ne '1003'){throw 'Replacing greeting changed custom routes'}
 $before=(Get-FileHash (Join-Path $root 'lab-state.json')).Hash
 $invalid=Join-Path $root 'invalid.wav';[IO.File]::WriteAllText($invalid,'not audio')
 $rejected=$false
 try{& (Join-Path $PSScriptRoot 'Enable-ModemIvr.ps1') -LabRoot $root -PromptPath $invalid -NoConfirm}catch{$rejected=$true}
 if(-not $rejected -or (Get-FileHash (Join-Path $root 'lab-state.json')).Hash -ne $before){throw 'Invalid WAV changed state'}
 $success=$true
 Write-Host 'PASS: IVR activation, both key routes, all credentials retained, existing menu reused, invalid WAV rollback.'
}finally{
 $resolved=[IO.Path]::GetFullPath($root)
 $prefix=[IO.Path]::GetFullPath($env:TEMP).TrimEnd('\')+'\office-ivr-activation-test-'
 if($success -and $resolved.StartsWith($prefix,[StringComparison]::OrdinalIgnoreCase)){Remove-Item -LiteralPath $resolved -Recurse -Force}
}
