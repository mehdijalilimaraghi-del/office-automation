#requires -Version 5.1
[CmdletBinding()]
param([string]$LabRoot,[string]$EngineDirectory,[switch]$NoBrowser,[switch]$HideConsole)
$ErrorActionPreference='Stop'
try {
    Import-Module (Join-Path $PSScriptRoot 'PbxLab.psm1') -Force -DisableNameChecking
    if(-not $LabRoot){$LabRoot=Join-Path $env:LOCALAPPDATA 'OfficeAutomation\Telephony\PbxLab'}
    $LabRoot=[IO.Path]::GetFullPath($LabRoot)
    if(-not (Test-Path -LiteralPath (Join-Path $LabRoot 'lab-state.json'))){$LabRoot=& (Join-Path $PSScriptRoot 'Configure-PbxLab.ps1') -LabRoot $LabRoot}
    $labState=Read-PbxLabState $LabRoot
    if(-not $EngineDirectory){$EngineDirectory=Join-Path $env:ProgramFiles 'FreeSWITCH'}
    $EngineDirectory=[IO.Path]::GetFullPath($EngineDirectory);$binary=Join-Path $EngineDirectory 'FreeSwitchConsole.exe'
    if(-not (Test-Path -LiteralPath $binary)){throw 'Installed FreeSwitchConsole.exe was not found'}
    $service=Get-Service -Name FreeSWITCH -ErrorAction SilentlyContinue
    if($service -and $service.Status -ne 'Stopped'){throw 'The Windows PBX service is already active. No changes were made.'}
    if(Get-Process -Name FreeSwitchConsole -ErrorAction SilentlyContinue){throw 'A PBX console is already active. Use Stop-PbxLab.cmd before starting another instance.'}
    if(-not @(Get-NetIPAddress -AddressFamily IPv4|Where-Object IPAddress -eq $labState.bind_address).Count){throw 'The saved LAN address is not assigned to this computer'}
    if(Get-NetUDPEndpoint -LocalPort $labState.sip_port -ErrorAction SilentlyContinue){throw 'Lab SIP port is already in use'}
    if(Get-NetTCPConnection -LocalPort $labState.esl_port -State Listen -ErrorAction SilentlyContinue){throw 'Lab control port is already in use'}
    $arguments=Get-PbxLabArguments $LabRoot $EngineDirectory
    Write-Host 'Starting the isolated internal-call lab. Modem and Windows service are unchanged.'
    $windowStyle=if($HideConsole){'Hidden'}else{'Normal'}
    $native=Start-Process -FilePath $binary -ArgumentList $arguments -WorkingDirectory $EngineDirectory -WindowStyle $windowStyle -PassThru
    $session=[ordered]@{profile_id=$labState.profile_id;process_id=$native.Id;process_start_ticks=$native.StartTime.ToUniversalTime().Ticks;binary_path=$binary;started_at=[DateTimeOffset]::Now.ToString('o')}
    [IO.File]::WriteAllText((Join-Path $LabRoot 'lab-session.json'),($session|ConvertTo-Json),[Text.UTF8Encoding]::new($false))
    $timer=[Diagnostics.Stopwatch]::StartNew();$ready=$false;$lastError='Startup timeout'
    while($timer.Elapsed.TotalSeconds -lt 40){
        if($native.HasExited){throw ('PBX console exited with code '+$native.ExitCode+'. See '+(Join-Path $LabRoot 'log'))}
        try{$status=Invoke-PbxLabCommand $labState 'status';$sip=Invoke-PbxLabCommand $labState 'sofia status profile office_lab';if($status -match 'UP' -and $sip -match '(?m)^Name\s+office_lab\s*$' -and $sip.Contains(('sip:mod_sofia@'+$labState.bind_address+':'+$labState.sip_port))){$ready=$true;break}}catch{$lastError=$_.Exception.Message}
        Start-Sleep -Milliseconds 500
    }
    if(-not $ready){throw ('Lab did not become ready: '+$lastError+'. See '+(Join-Path $LabRoot 'log'))}
    $result=[ordered]@{report_type='pbx_lab_start';schema_version=1;ready=$true;bind_address=$labState.bind_address;sip_port=$labState.sip_port;extensions=@($labState.accounts|ForEach-Object {$_.extension});modem_connected=$false;windows_service_changed=$false;status=$status;sip_profile=$sip}
    [IO.File]::WriteAllText((Join-Path $LabRoot 'lab-start-result.json'),($result|ConvertTo-Json -Depth 5),[Text.UTF8Encoding]::new($false))
    Write-Host ('Lab ready: '+$labState.bind_address+':'+$labState.sip_port) -ForegroundColor Green
    Write-Host ('Private account page: '+(Join-Path $LabRoot 'accounts.html'))
    Write-Host 'Use two SIP clients, one account each. Call the other extension; 9196 is the optional local echo test.'
    if(-not $NoBrowser){Start-Process -FilePath (Join-Path $LabRoot 'accounts.html')}
} catch {Write-Host ('ERROR: '+$_.Exception.Message) -ForegroundColor Red;exit 1}