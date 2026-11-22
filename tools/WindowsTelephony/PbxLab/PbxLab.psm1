#requires -Version 5.1
$ErrorActionPreference='Stop'
function New-LabSecret { $rng=[Security.Cryptography.RandomNumberGenerator]::Create();try{$data=New-Object byte[] 24;$rng.GetBytes($data);return ([BitConverter]::ToString($data)).Replace('-','').ToLowerInvariant()}finally{$rng.Dispose()} }
function Read-PbxLabState([string]$Root){$state=Get-Content -LiteralPath (Join-Path $Root 'lab-state.json') -Raw -Encoding UTF8|ConvertFrom-Json;if($state.report_type -ne 'office_pbx_lab' -or $state.schema_version -ne 1){throw 'Not an Office Automation lab profile'};return $state}
function Write-LabConfiguration([string]$Root,$LabState){
    $template=[IO.File]::ReadAllText((Join-Path $PSScriptRoot 'freeswitch.template.xml'))
    $users=New-Object Text.StringBuilder
    foreach($account in $LabState.accounts){[void]$users.Append(('<user id="'+$account.extension+'"><params><param name="password" value="'+$account.password+'"/></params><variables><variable name="user_context" value="office_lab"/><variable name="effective_caller_id_number" value="'+$account.extension+'"/><variable name="effective_caller_id_name" value="Extension '+$account.extension+'"/></variables></user>'))}
    $xml=$template.Replace('__IP__',$LabState.bind_address).Replace('__SIP_PORT__',[string]$LabState.sip_port).Replace('__ESL_PORT__',[string]$LabState.esl_port).Replace('__ESL_SECRET__',$LabState.esl_secret).Replace('__EXTENSIONS__',(($LabState.accounts|ForEach-Object {$_.extension})-join '|')).Replace('__USERS__',$users.ToString())
    $validation=New-Object Xml.XmlDocument;$validation.XmlResolver=$null;$validation.PreserveWhitespace=$true;$validation.LoadXml($xml)
    if($LabState.ivr -and $LabState.ivr.enabled){
        $menu=$LabState.ivr
        $prompt=[Security.SecurityElement]::Escape([string]$menu.prompt_path)
        $timeout=[int]$menu.timeout_seconds*1000;$retries=[int]$menu.retries;$operator=$menu.operator_number
        $entries=New-Object Text.StringBuilder
        foreach($choice in $menu.choices){[void]$entries.Append(('<entry action="menu-exec-app" digits="'+$choice.digit+'" param="transfer '+$choice.number+' XML office_lab"/>'))}
        foreach($account in $LabState.accounts){[void]$entries.Append(('<entry action="menu-exec-app" digits="'+$account.extension+'" param="transfer '+$account.extension+' XML office_lab"/>'))}
        $fragment=$validation.CreateDocumentFragment()
        $fragment.InnerXml='<configuration name="ivr.conf"><menus><menu name="office_main" greet-long="'+$prompt+'" greet-short="'+$prompt+'" timeout="'+$timeout+'" inter-digit-timeout="1500" max-failures="'+$retries+'" max-timeouts="'+$retries+'" digit-len="6" exec-on-max-failures="transfer '+$operator+' XML office_lab" exec-on-max-timeouts="transfer '+$operator+' XML office_lab">'+$entries.ToString()+'</menu></menus></configuration>'
        [void]$validation.SelectSingleNode('/document/section[@name="configuration"]').AppendChild($fragment)
        $fragment=$validation.CreateDocumentFragment();$fragment.InnerXml='<extension name="office_ivr"><condition field="destination_number" expression="^9197$"><action application="answer"/><action application="ivr" data="office_main"/></condition></extension>'
        [void]$validation.SelectSingleNode('/document/section[@name="dialplan"]/context').AppendChild($fragment)
        $xml=$validation.OuterXml
    }
    [IO.File]::WriteAllText((Join-Path $Root 'conf\freeswitch.xml'),$xml,[Text.UTF8Encoding]::new($false))
    [IO.File]::WriteAllText((Join-Path $Root 'lab-state.json'),($LabState|ConvertTo-Json -Depth 6),[Text.UTF8Encoding]::new($false))
    $rows=($LabState.accounts|ForEach-Object {'<tr><td>'+ $_.extension+'</td><td>'+ $_.extension+'</td><td><code>'+ $_.password+'</code></td></tr>'})-join ''
    $html='<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="referrer" content="no-referrer"><title>داخلی‌های آزمایشی</title><style>body{font-family:Tahoma,sans-serif;max-width:950px;margin:40px auto;padding:24px;line-height:2;background:#f8fafc;color:#172554}table{border-collapse:collapse;width:100%;background:white}td,th{padding:12px;border:1px solid #cbd5e1}code,bdi{direction:ltr;unicode-bidi:embed;user-select:all}h1{font-size:24px}</style><h1>مرکز تلفن آزمایشی</h1><p>آدرس سرور: <bdi>'+ $LabState.bind_address+':'+$LabState.sip_port+'</bdi> — SIP Proxy: <bdi>'+ $LabState.bind_address+':'+$LabState.sip_port+'</bdi> — دامنه: <bdi>'+$LabState.bind_address+'</bdi> — پروتکل: UDP</p><table><tr><th>داخلی</th><th>نام کاربری و شناسه ورود</th><th>رمز اختصاصی</th></tr>'+$rows+'</table><p>هر حساب را در یک نرم‌افزار تلفن SIP روی یک کامپیوتر تنظیم کنید؛ سپس شماره داخلی دیگر را بگیرید. برای تست صدای همان حساب، 9196 را بگیرید.</p><p>این صفحه شامل رمزهاست؛ آن را برای بررسی یا در گزارش ارسال نکنید. تنظیم داخلی‌ها در اتوماسیون و اتصال مودم هنوز مرحله بعد است.</p></html>'
    [IO.File]::WriteAllText((Join-Path $Root 'accounts.html'),$html,[Text.UTF8Encoding]::new($false))
}
function New-PbxLabProfile {
    param([string]$Root,[string]$BindAddress,[string[]]$Extensions=@('1001','1002'),[int]$SipPort=5160,[int]$EslPort=18021)
    $ip=$null
    if(-not [Net.IPAddress]::TryParse($BindAddress,[ref]$ip) -or $ip.AddressFamily -ne [Net.Sockets.AddressFamily]::InterNetwork){throw 'Select an IPv4 LAN address'}
    $octets=$ip.GetAddressBytes();if(-not ($octets[0] -eq 10 -or $octets[0] -eq 127 -or ($octets[0] -eq 192 -and $octets[1] -eq 168) -or ($octets[0] -eq 172 -and $octets[1] -ge 16 -and $octets[1] -le 31))){throw 'This lab requires a private LAN or loopback address'}
    if($SipPort -lt 1024 -or $SipPort -gt 65535 -or $EslPort -lt 1024 -or $EslPort -gt 65535 -or $SipPort -eq $EslPort -or ($SipPort -ge 17000 -and $SipPort -le 17199) -or ($EslPort -ge 17000 -and $EslPort -le 17199)){throw 'Invalid or overlapping lab ports'}
    if(-not $Extensions.Count -or $Extensions.Count -gt 1000 -or @($Extensions|Select-Object -Unique).Count -ne $Extensions.Count){throw 'Provide unique extensions'}
    foreach($number in $Extensions){if($number -notmatch '\A[1-9][0-9]{2,5}\z' -or $number -in @('9196','9197')){throw 'Extensions must be 3-6 digits; 9196 is reserved for echo testing'}}
    if(Test-Path -LiteralPath $Root){throw 'Profile already exists; existing credentials/configuration will not be overwritten'}
    [void][IO.Directory]::CreateDirectory($Root)
    $acl=New-Object Security.AccessControl.DirectorySecurity
    $sid=[Security.Principal.WindowsIdentity]::GetCurrent().User
    $acl.SetOwner($sid);$acl.SetAccessRuleProtection($true,$false)
    foreach($identity in @($sid,(New-Object Security.Principal.SecurityIdentifier('S-1-5-18')))){$rule=New-Object Security.AccessControl.FileSystemAccessRule($identity,'FullControl','ContainerInherit,ObjectInherit','None','Allow');[void]$acl.AddAccessRule($rule)}
    Set-Acl -LiteralPath $Root -AclObject $acl
    foreach($name in @('conf','log','db','run','cache','temp','storage','recordings','scripts','sounds','certs','grammar','htdocs')){[void][IO.Directory]::CreateDirectory((Join-Path $Root $name))}
    $state=[pscustomobject]@{report_type='office_pbx_lab';schema_version=1;profile_id=[Guid]::NewGuid().ToString('N');bind_address=$ip.ToString();sip_port=$SipPort;esl_port=$EslPort;esl_secret=(New-LabSecret);accounts=@($Extensions|ForEach-Object {[pscustomobject]@{extension=$_;password=(New-LabSecret)}})}
    Write-LabConfiguration $Root $state
    return $state
}
function Read-EslFrame($Stream){
    $bytes=New-Object 'Collections.Generic.List[byte]'
    while($true){$value=$Stream.ReadByte();if($value -lt 0){throw 'ESL connection closed'};[void]$bytes.Add([byte]$value);if($bytes.Count -gt 16384){throw 'ESL header too large'};$text=[Text.Encoding]::ASCII.GetString($bytes.ToArray()).Replace("`r",'');if($text.EndsWith("`n`n")){break}}
    $headers=@{};foreach($line in $text.Trim().Split("`n")){if($line -match '^([^:]+):\s*(.*)$'){$headers[$matches[1].ToLowerInvariant()]=$matches[2]}}
    $length=0;if($headers.ContainsKey('content-length')){if(-not [int]::TryParse($headers['content-length'],[ref]$length) -or $length -lt 0 -or $length -gt 1048576){throw 'Invalid ESL body length'}}
    $body=New-Object byte[] $length;$offset=0;while($offset -lt $length){$read=$Stream.Read($body,$offset,$length-$offset);if($read -le 0){throw 'Truncated ESL body'};$offset+=$read}
    return @{headers=$headers;body=[Text.Encoding]::UTF8.GetString($body)}
}
function Invoke-PbxLabCommand($LabState,[string]$Command){
    if($Command -notin @('status','sofia status profile office_lab','sofia status profile office_lab reg','show calls as json','shutdown')){throw 'Command is not allowed by the lab controller'}
    $client=New-Object Net.Sockets.TcpClient
    try{$pending=$client.BeginConnect('127.0.0.1',[int]$LabState.esl_port,$null,$null);try{if(-not $pending.AsyncWaitHandle.WaitOne(2000)){throw 'ESL connect timeout'};$client.EndConnect($pending)}finally{$pending.AsyncWaitHandle.Close()};$stream=$client.GetStream();$stream.ReadTimeout=3000;$stream.WriteTimeout=3000
        $frame=Read-EslFrame $stream;if($frame.headers['content-type'] -ne 'auth/request'){throw 'Unexpected ESL greeting'}
        $data=[Text.Encoding]::ASCII.GetBytes('auth '+$LabState.esl_secret+"`n`n");$stream.Write($data,0,$data.Length)
        $frame=Read-EslFrame $stream;if($frame.headers['reply-text'] -notmatch '^\+OK accepted'){throw 'ESL authentication rejected'}
        $data=[Text.Encoding]::ASCII.GetBytes('api '+$Command+"`n`n");$stream.Write($data,0,$data.Length);$frame=Read-EslFrame $stream;return $frame.body
    }finally{$client.Close()}
}
function Get-PbxLabArguments([string]$Root,[string]$EngineDirectory){
    $items=@('-nonat','-nonatmap','-np','-base',('"'+$Root+'"'),'-mod',('"'+(Join-Path $EngineDirectory 'mod')+'"'))
    foreach($name in @('conf','log','db','run','cache','temp','storage','recordings','scripts','sounds','certs','grammar','htdocs')){$items+=@('-'+$name,('"'+(Join-Path $Root $name)+'"'))}
    return ($items -join ' ')
}
function Get-PbxLabLocalAddress([string]$Preferred){
    $addresses=@(Get-NetIPAddress -AddressFamily IPv4 -AddressState Preferred | Where-Object {$_.IPAddress -match '^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)' -and -not $_.SkipAsSource})
    if($Preferred -and @($addresses|Where-Object IPAddress -eq $Preferred).Count){return $Preferred}
    $unique=@($addresses.IPAddress|Select-Object -Unique)
    if($unique.Count -eq 1){return $unique[0]}
    $gatewayInterfaces=@(Get-NetIPConfiguration|Where-Object {$_.IPv4DefaultGateway}|ForEach-Object {$_.InterfaceIndex})
    $primary=@($addresses|Where-Object {$_.InterfaceIndex -in $gatewayInterfaces}|Select-Object -ExpandProperty IPAddress -Unique)
    if($primary.Count -eq 1){return $primary[0]}
    throw 'Cannot choose a unique LAN IPv4 address. Check active network adapters; no settings were changed.'
}
function Sync-PbxLabAddress([string]$Root){
    if(Get-Process -Name FreeSwitchConsole -ErrorAction SilentlyContinue){throw 'Stop the PBX lab before updating its network address.'}
    $service=Get-Service -Name FreeSWITCH -ErrorAction SilentlyContinue
    if($service -and $service.Status -ne 'Stopped'){throw 'Stop the PBX service before updating lab settings.'}
    $state=Read-PbxLabState $Root
    # Retain loopback-only developer tests; normal lab profiles follow a real LAN adapter.
    if($state.bind_address -eq '127.0.0.1'){return $state}
    $address=Get-PbxLabLocalAddress $state.bind_address
    if($address -ne $state.bind_address){
        $state.bind_address=$address
        Write-LabConfiguration $Root $state
        Write-Host ('Lab network address updated: '+$address+'. Existing extension passwords were preserved.')
    }
    return $state
}
function Import-PbxOfficeConfiguration([string]$Root,$Configuration,[string]$SourceDirectory){
    if(Get-Process -Name FreeSwitchConsole -ErrorAction SilentlyContinue){throw 'Stop the PBX lab before applying office settings.'}
    $service=Get-Service -Name FreeSWITCH -ErrorAction SilentlyContinue
    if($service -and $service.Status -ne 'Stopped'){throw 'The Windows PBX service is active.'}
    if($Configuration.type -ne 'office_telephony_configuration' -or $Configuration.schema_version -ne 1){throw 'Unsupported office configuration'}
    $extensions=@($Configuration.extensions)
    if($extensions.Count -lt 1 -or $extensions.Count -gt 1000 -or @($extensions.number|Select-Object -Unique).Count -ne $extensions.Count){throw 'Invalid extension list'}
    foreach($extension in $extensions){if($extension.number -notmatch '\A[1-9][0-9]{2,5}\z' -or $extension.number -in @('9196','9197')){throw 'Invalid or reserved extension number'}}
    $ivr=$Configuration.ivr
    if($ivr.enabled -isnot [bool] -or [int]$ivr.timeout_seconds -lt 3 -or [int]$ivr.timeout_seconds -gt 30 -or [int]$ivr.retries -lt 1 -or [int]$ivr.retries -gt 3){throw 'Invalid IVR settings'}
    if(@($ivr.choices).Count -gt 10 -or @($ivr.choices.digit|Select-Object -Unique).Count -ne @($ivr.choices).Count){throw 'Invalid IVR choices'}
    foreach($choice in @($ivr.choices)){if($choice.digit -notmatch '\A[0-9]\z' -or $choice.number -notin $extensions.number){throw 'Invalid IVR destination'}}
    if($ivr.enabled -and $ivr.operator_number -notin $extensions.number){throw 'Invalid operator destination'}
    if($ivr.audio_file -and $ivr.audio_file -ne 'greeting.wav'){throw 'Invalid prompt path'}
    if($ivr.enabled -and -not $ivr.audio_file){throw 'IVR requires a prompt'}
    $scratch=$null;$newPrompt=$null;$backup=$null;$created=$false
    try {
        if($ivr.audio_file){
            if(-not ('OfficePromptWave' -as [type])){Add-Type -Path (Join-Path $PSScriptRoot 'Normalize-Prompt.cs')}
            $scratch=[IO.Path]::GetTempFileName()
            [OfficePromptWave]::Convert((Join-Path $SourceDirectory 'greeting.wav'),$scratch)
        }
        if(Test-Path -LiteralPath (Join-Path $Root 'lab-state.json')){$state=Read-PbxLabState $Root}else{$state=New-PbxLabProfile -Root $Root -BindAddress (Get-PbxLabLocalAddress) -Extensions $extensions.number;$created=$true}
        $backup=Join-Path $Root ('backup-'+[guid]::NewGuid().ToString('N'));[void][IO.Directory]::CreateDirectory($backup)
        foreach($name in @('lab-state.json','accounts.html','conf/freeswitch.xml')){Copy-Item -LiteralPath (Join-Path $Root $name) -Destination (Join-Path $backup ([IO.Path]::GetFileName($name)))}
        $accounts=@();foreach($extension in $extensions){$existing=@($state.accounts|Where-Object extension -eq $extension.number);$password=if($existing.Count){$existing[0].password}else{New-LabSecret};$accounts+=[pscustomobject]@{extension=$extension.number;password=$password}}
        $state.accounts=$accounts
        $menu=[pscustomobject]@{enabled=$ivr.enabled;timeout_seconds=[int]$ivr.timeout_seconds;retries=[int]$ivr.retries;operator_number=$ivr.operator_number;choices=@($ivr.choices);prompt_path=$null}
        if($scratch){$newPrompt=Join-Path $Root ('sounds/greeting-'+[guid]::NewGuid().ToString('N')+'.wav');Copy-Item -LiteralPath $scratch -Destination $newPrompt;$menu.prompt_path=$newPrompt}
        $state|Add-Member -NotePropertyName ivr -NotePropertyValue $menu -Force
        Write-LabConfiguration $Root $state
    }catch{
        if($backup){foreach($name in @('lab-state.json','accounts.html','conf/freeswitch.xml')){$old=Join-Path $backup ([IO.Path]::GetFileName($name));if(Test-Path -LiteralPath $old){Copy-Item -LiteralPath $old -Destination (Join-Path $Root $name) -Force}}}
        if($newPrompt -and (Test-Path -LiteralPath $newPrompt)){Remove-Item -LiteralPath $newPrompt -Force}
        throw
    }finally{if($scratch -and (Test-Path -LiteralPath $scratch)){Remove-Item -LiteralPath $scratch -Force}}
}
Export-ModuleMember -Function New-PbxLabProfile,Read-PbxLabState,Invoke-PbxLabCommand,Get-PbxLabArguments,Get-PbxLabLocalAddress,Sync-PbxLabAddress,Import-PbxOfficeConfiguration
