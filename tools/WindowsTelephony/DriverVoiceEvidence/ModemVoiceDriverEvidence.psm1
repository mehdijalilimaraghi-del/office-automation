#requires -Version 5.1
Set-StrictMode -Version 2
$script:ModemClass='{4d36e96d-e325-11ce-bfc1-08002be10318}'
$script:RootNames=@('VoiceProfile','VoiceBaudRate','VoiceManufacturerID','HandsetCloseDelay','SpeakerPhoneSpecs')
$script:WaveNames=@('BaudRate','XformID','XformModule','WaveHardwareID','WaveDevices','XformInput','XformOutput','WaveFormatEx','WaveInstance')
$script:CommandKeys=@('LineSetPlayFormat','LineSetRecordFormat','HandsetSetPlayFormat','HandsetSetRecordFormat','StartPlay','StopPlay','StartRecord','StopRecord','VoiceAnswer','OpenHandset','CloseHandset','SpeakerPhoneEnable','SpeakerPhoneDisable','SpeakerPhoneSetVolumeGain','VoiceToDataAnswer')
function Get-VoiceCommandFragments([string]$Text) {
    if($Text.Length -gt 2048){return @()}
    $pattern='(?i)(?:AT)?[+#](?:FCLASS|VSM|VGT|VGR|VLS|VTX|VTR|VRX|IFC|VSD|VSP|VDX|VGS|VGM|VPR|CLS|VBS|VSR|VBT)(?:=\?|\?|=(?:[0-9]+|<[A-Za-z][A-Za-z0-9_]{0,39}>)(?:[ \t]*,[ \t]*(?:[0-9]+|<[A-Za-z][A-Za-z0-9_]{0,39}>))*)?'
    $result=@([regex]::Matches($Text,$pattern)|ForEach-Object {$_.Value})
    $controls=@([regex]::Matches($Text,'(?i)<(?:h[0-9a-f]{2}|cr|lf|DLE|ETX|ESC)>')|ForEach-Object {$_.Value})
    if($result.Count){return ,([ordered]@{voice_commands=$result;control_markers=$controls})}
    if($Text -match '^(?i)(None|NoResponse|Success|OK|ERROR)$'){return ,([ordered]@{technical_marker=$Text})}
    if($controls.Count -and $Text -match '^(?i)(?:<(?:h[0-9a-f]{2}|cr|lf|DLE|ETX|ESC)>)+$'){return ,([ordered]@{control_markers=$controls})}
    return @()
}
function ConvertTo-ModemVoiceTechnicalValue {
    param([string]$Section,[string]$Name,$Value,[string]$Kind='Unknown')
    $allowed=($Section -eq 'root' -and $Name -in $script:RootNames) -or
             ($Section -eq 'WaveDriver' -and $Name -in $script:WaveNames) -or
             ($Section -eq 'WaveFormats' -and $Name -match '^[0-9A-Fa-fx_ .,-]{0,64}$')
    if(-not $allowed -or $null -eq $Value){return $null}
    if($Value -is [byte[]]){
        if($Value.Length -gt 256){return [ordered]@{kind=$Kind;omitted='oversized_technical_blob'}}
        $number=$null
        if($Value.Length -eq 1){$number=[uint64]$Value[0]}
        elseif($Value.Length -eq 2){$number=[uint64][BitConverter]::ToUInt16($Value,0)}
        elseif($Value.Length -eq 4){$number=[uint64][BitConverter]::ToUInt32($Value,0)}
        return [ordered]@{kind=$Kind;bytes=$Value.Length;hex=([BitConverter]::ToString($Value));unsigned_little_endian=$number}
    }
    if($Value -is [int] -or $Value -is [long] -or $Value -is [uint32] -or $Value -is [uint64]){
        return [ordered]@{kind=$Kind;value=$Value}
    }
    if($Value -is [string]){
        if($Name -eq 'XformModule'){
            $leaf=[IO.Path]::GetFileName($Value)
            if($leaf -match '^[a-zA-Z0-9_.-]{1,120}\.dll$'){return [ordered]@{kind=$Kind;module_file=$leaf}}
        }
        elseif($Name -eq 'WaveHardwareID' -and $Value -match '^[a-zA-Z0-9_.-]{1,120}$'){
            return [ordered]@{kind=$Kind;value=$Value}
        }
        elseif($Value -match '^(?:[0-9]{1,20}|0x[0-9a-fA-F]{1,16})$'){
            return [ordered]@{kind=$Kind;value=$Value}
        }
    }
    return [ordered]@{kind=$Kind;omitted='unrecognized_technical_type'}
}
function Read-KnownValues($Key,[string]$Section,[string[]]$Names) {
    $result=[ordered]@{}
    foreach($name in $Names){
        if($Key.GetValueNames() -notcontains $name){continue}
        $value=$Key.GetValue($name,$null,[Microsoft.Win32.RegistryValueOptions]::DoNotExpandEnvironmentNames)
        $safe=ConvertTo-ModemVoiceTechnicalValue -Section $Section -Name $name -Value $value -Kind ([string]$Key.GetValueKind($name))
        if($null -ne $safe){$result[$name]=$safe}
    }
    return $result
}
function Test-ModemVoiceDriverKey([string]$DriverKey) {
    return $DriverKey -match ('^'+[regex]::Escape($script:ModemClass)+'\\[0-9]{4}$')
}
function Get-ModemVoiceDriverEvidence {
    param([string]$PortName)
    if($PortName -and $PortName -notmatch '^COM[1-9][0-9]{0,4}$'){throw 'Invalid COM port filter'}
    $evidence=New-Object Collections.Generic.List[object]
    $errors=New-Object Collections.Generic.List[string]
    $base=[Microsoft.Win32.RegistryKey]::OpenBaseKey([Microsoft.Win32.RegistryHive]::LocalMachine,[Microsoft.Win32.RegistryView]::Registry64)
    try {
        $candidates=@{}
        try {
            foreach($modem in @(Get-CimInstance -ClassName Win32_POTSModem -ErrorAction Stop)){
                if($PortName -and [string]$modem.AttachedTo -ne $PortName){continue}
                $enum=$null
                try {
                    if(-not $modem.PNPDeviceID){continue}
                    $enum=$base.OpenSubKey('SYSTEM\CurrentControlSet\Enum\'+[string]$modem.PNPDeviceID,$false)
                    if(-not $enum){continue}
                    $driver=[string]$enum.GetValue('Driver','')
                    if(Test-ModemVoiceDriverKey $driver){
                        $candidates[$driver]=[pscustomobject]@{port=[string]$modem.AttachedTo;match='installed_PNP_driver'}
                    }
                }catch{$errors.Add('Could not resolve one installed modem driver key.')}
                finally{if($enum){$enum.Dispose()}}
            }
        }catch{$errors.Add('Installed modem inventory unavailable; checking modem-class keys only.')}
        if(-not $candidates.Count){
            $class=$base.OpenSubKey('SYSTEM\CurrentControlSet\Control\Class\'+$script:ModemClass,$false)
            if($class){
                try {
                    foreach($name in @($class.GetSubKeyNames()|Where-Object {$_ -match '^[0-9]{4}$'})){
                        $key=$class.OpenSubKey($name,$false)
                        try{
                            $port=[string]$key.GetValue('AttachedTo','')
                            if($port -notmatch '^COM[1-9][0-9]{0,4}$' -or ($PortName -and $port -ne $PortName)){continue}
                            $candidates[$script:ModemClass+'\'+$name]=[pscustomobject]@{port=$port;match='modem_class_AttachedTo'}
                        }finally{$key.Dispose()}
                    }
                }finally{$class.Dispose()}
            }
        }
        foreach($driver in @($candidates.Keys|Sort-Object|Select-Object -First 16)){
            $key=$base.OpenSubKey('SYSTEM\CurrentControlSet\Control\Class\'+$driver,$false)
            if(-not $key){$errors.Add('One resolved driver key is unavailable.');continue}
            try {
                $item=[ordered]@{port=$candidates[$driver].port;match_method=$candidates[$driver].match;driver_instance=($driver -split '\\')[-1];technical_metadata=[ordered]@{};missing_fields=@();voice_properties=(Read-KnownValues $key 'root' $script:RootNames);wave_driver=[ordered]@{};wave_formats=@();command_sequences=[ordered]@{}}
                foreach($name in $script:RootNames){if($key.GetValueNames() -notcontains $name){$item.missing_fields+=('root/'+$name)}}
                foreach($section in @('WaveDriver','WaveFormats','WaveDriver\WaveFormats')+$script:CommandKeys){
                    $present=$key.OpenSubKey($section,$false)
                    if($present){$present.Dispose()}else{$item.missing_fields+=$section}
                }
                foreach($name in @('DriverDesc','ProviderName','DriverVersion','InfPath','InfSection')){
                    $value=[string]$key.GetValue($name,'')
                    if($value.Length -gt 256 -or $value -match '[\x00-\x1F]'){continue}
                    if($name -eq 'InfPath'){$value=[IO.Path]::GetFileName($value)}
                    if($value){$item.technical_metadata[$name]=$value}
                }
                $wave=$key.OpenSubKey('WaveDriver',$false)
                if($wave){try{$item.wave_driver=Read-KnownValues $wave 'WaveDriver' $script:WaveNames}finally{$wave.Dispose()}}
                foreach($parentName in @('WaveFormats','WaveDriver\WaveFormats')){
                    $formats=$key.OpenSubKey($parentName,$false)
                    if(-not $formats){continue}
                    try {
                        $item.wave_formats+=,[ordered]@{location=$parentName;values=(Read-KnownValues $formats 'WaveFormats' @($formats.GetValueNames()))}
                        foreach($formatName in @($formats.GetSubKeyNames()|Where-Object {$_ -match '^[0-9A-Fa-fx_ .,-]{1,64}$'}|Select-Object -First 32)){
                            $format=$formats.OpenSubKey($formatName,$false)
                            try{$item.wave_formats+=,[ordered]@{location=$parentName+'\'+$formatName;values=(Read-KnownValues $format 'WaveFormats' @($format.GetValueNames()))}}finally{$format.Dispose()}
                        }
                    }finally{$formats.Dispose()}
                }
                foreach($commandName in $script:CommandKeys){
                    $commands=$key.OpenSubKey($commandName,$false)
                    if(-not $commands){continue}
                    try{
                        $sequence=@()
                        foreach($name in @($commands.GetValueNames()|Where-Object {$_ -match '^[0-9]{1,3}$'}|Sort-Object {[int]$_}|Select-Object -First 32)){
                            $value=$commands.GetValue($name,$null,[Microsoft.Win32.RegistryValueOptions]::DoNotExpandEnvironmentNames)
                            if($value -isnot [string]){continue}
                            $safe=@(Get-VoiceCommandFragments $value)
                            if($safe.Count){$sequence+=,[ordered]@{index=[int]$name;evidence=$safe[0]}}
                        }
                        if($sequence.Count){$item.command_sequences[$commandName]=$sequence}
                    }finally{$commands.Dispose()}
                }
                foreach($name in @('TerminatePlay','AbortPlay','TerminateRecord')){
                    $value=$key.GetValue($name,$null,[Microsoft.Win32.RegistryValueOptions]::DoNotExpandEnvironmentNames)
                    if($value -is [string]){$safe=@(Get-VoiceCommandFragments $value);if($safe.Count){$item.command_sequences[$name]=$safe[0]}}
                }
                $evidence.Add($item)
            }catch{$errors.Add('Technical evidence unavailable for one modem instance.')}
            finally{$key.Dispose()}
        }
    }finally{$base.Dispose()}
    return [ordered]@{report_type='installed_modem_voice_driver_evidence';schema_version=1;matched_modems=$evidence.Count;status=$(if($evidence.Count){'collected'}else{'no_matching_installed_modem'});collected_at=[DateTimeOffset]::Now.ToString('o');modems=@($evidence.ToArray());errors=@($errors.ToArray());modem_opened=$false;registry_changed=$false;settings_changed=$false;audio_copied=$false}
}
Export-ModuleMember -Function Get-ModemVoiceDriverEvidence,ConvertTo-ModemVoiceTechnicalValue,Get-VoiceCommandFragments,Test-ModemVoiceDriverKey
