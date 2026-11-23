#requires -Version 5.1
$ErrorActionPreference='Stop'
Import-Module (Join-Path $PSScriptRoot 'ModemVoiceDriverEvidence.psm1') -Force -DisableNameChecking
function Check([bool]$Condition,[string]$Message){if(-not $Condition){throw $Message}}
$baud=ConvertTo-ModemVoiceTechnicalValue 'WaveDriver' 'BaudRate' ([byte[]]@(0,194,1,0)) 'Binary'
Check ($baud.unsigned_little_endian -eq 115200 -and $baud.hex -eq '00-C2-01-00') 'Driver baud binary decode failed'
$xform=ConvertTo-ModemVoiceTechnicalValue 'WaveDriver' 'XformID' ([byte[]]@(3,0)) 'Binary'
Check ($xform.unsigned_little_endian -eq 3) 'Transform id decode failed'
$module=ConvertTo-ModemVoiceTechnicalValue 'WaveDriver' 'XformModule' 'C:\Windows\System32\umdmxfrm.dll' 'String'
Check ($module.module_file -eq 'umdmxfrm.dll') 'Module basename filtering failed'
foreach($name in @('Password','PhoneNumber','License','UserName','DialPrefix')){
    Check ($null -eq (ConvertTo-ModemVoiceTechnicalValue 'root' $name 'secret')) 'Unapproved root value leaked'
    Check ($null -eq (ConvertTo-ModemVoiceTechnicalValue 'WaveDriver' $name 'secret')) 'Unapproved wave value leaked'
}
$omit=ConvertTo-ModemVoiceTechnicalValue 'WaveDriver' 'XformID' 'password=private' 'String'
Check ($omit.omitted -eq 'unrecognized_technical_type') 'Arbitrary value leaked'
$omit=ConvertTo-ModemVoiceTechnicalValue 'root' 'VoiceProfile' ([byte[]]::new(257)) 'Binary'
Check ($omit.omitted -eq 'oversized_technical_blob') 'Unbounded binary exported'
$commands=@(Get-VoiceCommandFragments 'AT+FCLASS=8<cr>AT+VSM=4,8000<cr>ATD555123456<cr> password=secret AT+VTX<cr>')
$json=$commands|ConvertTo-Json -Depth 5 -Compress
Check ($commands.Count -eq 1 -and $commands[0].voice_commands.Count -eq 3) 'Voice command sequence extraction failed'
Check ($json -notmatch '555123456|secret|ATD') 'Dial/password content leaked'
$commands=@(Get-VoiceCommandFragments 'at#vsm=130,8000<cr>')
Check ($commands[0].voice_commands[0] -eq 'at#vsm=130,8000') 'Alternate voice command prefix lost'
Check (@(Get-VoiceCommandFragments 'license=1234 phone=555123456').Count -eq 0) 'Unrelated data was collected'
Check (@(Get-VoiceCommandFragments '<h10><h03>')[0].control_markers.Count -eq 2) 'Stop markers missing'
Check (@(Get-VoiceCommandFragments 'NoResponse')[0].technical_marker -eq 'NoResponse') 'Technical response marker missing'
$compound=@(Get-VoiceCommandFragments 'AT+FCLASS=8+VLS=1+VGT=0<cr>')
Check ($compound[0].voice_commands.Count -eq 3 -and $compound[0].voice_commands[1] -eq '+VLS=1') 'Compound AT sequence lost voice commands'
$placeholders=@(Get-VoiceCommandFragments 'AT+VSM=<wave_format>,<SampleRate>,0,0<cr>')
Check ($placeholders[0].voice_commands[0] -eq 'AT+VSM=<wave_format>,<SampleRate>,0,0') 'Bounded technical placeholders lost arguments'
$oversized=@(Get-VoiceCommandFragments ('AT+VSM=<'+('a'*41)+'>'))
Check ($oversized[0].voice_commands[0] -eq 'AT+VSM') 'Oversized placeholder should be omitted'
$mappedDriver='{4D36E96D-E325-11CE-BFC1-08002BE10318}\0007'
Check (Test-ModemVoiceDriverKey $mappedDriver) 'Installed PNP driver mapping rejected the modem class'
foreach($bad in @('Software\User\Password','{00000000-0000-0000-0000-000000000000}\0007','{4d36e96d-e325-11ce-bfc1-08002be10318}\0007\Other','..\0007','')){Check (-not (Test-ModemVoiceDriverKey $bad)) 'Unexpected registry class/path accepted'}
Write-Output 'PASS: mocked baud/format decode, technical-value allowlist, bounded data, voice/stop-marker extraction and omission of dial/password/license content. No registry or modem accessed.'
