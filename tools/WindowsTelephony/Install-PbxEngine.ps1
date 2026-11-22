#requires -Version 5.1
[CmdletBinding()]
param([string]$InstallerPath,[switch]$CheckOnly)
$ErrorActionPreference='Stop'
$phase='preflight'
$logDirectory=$null
$logPath=$null
$msiExitCode=$null
$expectedHash='331cdd876c7ee73cc6d60bbdd7c9b7e60c157d3760226986fd7d6e604c83d8b6'
try {
    if(-not $CheckOnly){
        $base=$env:LOCALAPPDATA
        if([string]::IsNullOrWhiteSpace($base)){$base=[IO.Path]::GetTempPath()}
        $logDirectory=Join-Path $base 'OfficeAutomation\Telephony\Setup'
        [void][IO.Directory]::CreateDirectory($logDirectory)
    }
    $scriptDirectory=$PSScriptRoot
    if([string]::IsNullOrWhiteSpace($scriptDirectory)){
        $scriptFile=$PSCommandPath
        if([string]::IsNullOrWhiteSpace($scriptFile)){$scriptFile=$MyInvocation.MyCommand.Path}
        if(-not [string]::IsNullOrWhiteSpace($scriptFile)){$scriptDirectory=[IO.Path]::GetDirectoryName($scriptFile)}
    }
    if([string]::IsNullOrWhiteSpace($InstallerPath)){
        if([string]::IsNullOrWhiteSpace($scriptDirectory)){throw 'Package path is unknown. Run Install-PbxEngine.cmd from the extracted directory.'}
        $InstallerPath=Join-Path $scriptDirectory 'FreeSWITCH-1.11.4-Release-x64.msi'
    }
    if(-not [Environment]::Is64BitOperatingSystem -or [Environment]::OSVersion.Version.Major -lt 10){throw 'Windows 10 or later, 64-bit, is required.'}
    if(-not [Environment]::Is64BitProcess){throw 'Use 64-bit Windows PowerShell.'}
    $package=Get-Item -LiteralPath $InstallerPath -ErrorAction Stop
    if($package.PSIsContainer -or $package.Length -ne 166219776){throw 'Installer file is incomplete or invalid.'}
    if((Get-FileHash -LiteralPath $package.FullName -Algorithm SHA256).Hash.ToLowerInvariant() -ne $expectedHash){throw 'Installer SHA256 does not match the official release. Installation stopped.'}
    $signature=Get-AuthenticodeSignature -LiteralPath $package.FullName
    if($signature.Status -ne 'Valid' -or $signature.SignerCertificate.Subject -notmatch 'O="?SignalWire, Inc\.') {throw 'SignalWire installer signature could not be verified.'}
    $existing=Get-Service -Name FreeSWITCH -ErrorAction SilentlyContinue
    $destination=Join-Path $env:ProgramFiles 'FreeSWITCH'
    if($existing -or (Test-Path -LiteralPath $destination)){throw 'Existing FreeSWITCH detected. Run Collect-PbxInstallDiagnostics.cmd; do not reinstall or delete it.'}
    if($CheckOnly){[pscustomobject]@{status='package_verified';version='1.11.4';architecture='x64';sha256=$expectedHash;signature='Valid';installation_executed=$false};return}
    Write-Host 'Official package verified. Windows will request Administrator permission.'
    Write-Host 'Core installation only. PBX service and modem activation are not performed.'
    [void][IO.Directory]::CreateDirectory($logDirectory)
    $logPath=Join-Path $logDirectory ('freeswitch-install-'+[Guid]::NewGuid().ToString('N')+'.log')
    # Cache the verified package locally so elevation does not depend on mapped/UNC drive access.
    $cachedMsi=Join-Path $logDirectory ('freeswitch-'+[Guid]::NewGuid().ToString('N')+'.msi')
    Copy-Item -LiteralPath $package.FullName -Destination $cachedMsi -ErrorAction Stop
    if((Get-FileHash -LiteralPath $cachedMsi -Algorithm SHA256).Hash.ToLowerInvariant() -ne $expectedHash){throw 'Cached installer SHA256 mismatch.'}
    # ProductFeature installs the core. Exclude sample configuration and the optional codec download.
    $arguments='/i "'+$cachedMsi+'" ADDLOCAL=ProductFeature MSIRESTARTMANAGERCONTROL=Disable REBOOT=ReallySuppress /passive /norestart /L*v "'+$logPath+'"'
    $phase='run_msi'
    $process=Start-Process -FilePath (Join-Path $env:SystemRoot 'System32\msiexec.exe') -ArgumentList $arguments -Verb RunAs -WindowStyle Normal -Wait -PassThru
    $msiExitCode=$process.ExitCode
    if($process.ExitCode -notin @(0,3010)){throw ('MSI installation failed. Exit code: '+$process.ExitCode+'. MSI log: '+$logPath)}
    $phase='verify_installation'
    $service=Get-Service -Name FreeSWITCH -ErrorAction SilentlyContinue
    if(-not $service -or $service.Status -ne 'Stopped'){throw 'Expected a stopped FreeSWITCH service. Collect diagnostics before any changes.'}
    $binary=Join-Path $destination 'FreeSwitchConsole.exe'
    if(-not (Test-Path -LiteralPath $binary)){throw 'Installed FreeSwitchConsole.exe was not found. Collect diagnostics before retrying.'}
    $result=[ordered]@{report_type='pbx_engine_install';schema_version=1;version='1.11.4';computer_name=$env:COMPUTERNAME;installed_at=[DateTimeOffset]::Now.ToString('o');installer_sha256=$expectedHash;exit_code=$process.ExitCode;restart_required=($process.ExitCode -eq 3010);service_status=[string]$service.Status;binary_path=$binary;calls_tested=$false}
    $reportPath=Join-Path $logDirectory 'pbx-engine-install-result.json'
    [IO.File]::WriteAllText($reportPath,($result|ConvertTo-Json),[Text.UTF8Encoding]::new($false))
    Write-Host ('Core installed. Service remains stopped. Report: '+$reportPath) -ForegroundColor Green
    if($process.ExitCode -eq 3010){Write-Host 'Windows reported a pending reboot. No automatic reboot was requested.'}
    if($scriptDirectory -and (Test-Path -LiteralPath (Join-Path $scriptDirectory 'Install-PbxEngine.cmd'))){
        try {Copy-Item -LiteralPath $reportPath -Destination (Join-Path $scriptDirectory ('pbx-engine-install-result-'+[Guid]::NewGuid().ToString('N')+'.json')) -ErrorAction Stop} catch {Write-Host 'Report copy beside package failed. Use the original report path shown above.'}
    }
} catch {
    $failure=$_
    Write-Host ('ERROR: '+$failure.Exception.Message) -ForegroundColor Red
    if(-not $CheckOnly){
        try {
            if([string]::IsNullOrWhiteSpace($logDirectory)){$logDirectory=Join-Path ([IO.Path]::GetTempPath()) 'OfficeAutomation-PbxSetup'}
            [void][IO.Directory]::CreateDirectory($logDirectory)
            $failureId=[Guid]::NewGuid().ToString('N')
            $failureReport=Join-Path $logDirectory ('pbx-engine-install-failed-'+$failureId+'.json')
            $failureText=Join-Path $logDirectory ('pbx-engine-install-failed-'+$failureId+'.txt')
            $details=[ordered]@{report_type='pbx_engine_install_failure';schema_version=1;computer_name=$env:COMPUTERNAME;time=[DateTimeOffset]::Now.ToString('o');stage=$phase;msi_exit_code=$msiExitCode;error=$failure.Exception.Message;script_line=$failure.InvocationInfo.ScriptLineNumber;stack=$failure.ScriptStackTrace;msi_log=$logPath}
            [IO.File]::WriteAllText($failureReport,($details|ConvertTo-Json -Depth 5),[Text.UTF8Encoding]::new($false))
            [IO.File]::WriteAllText($failureText,('ERROR: '+$failure.Exception.Message+[Environment]::NewLine+'Stage: '+$phase+[Environment]::NewLine+'MSI exit code: '+$msiExitCode+[Environment]::NewLine+'MSI log: '+$logPath),[Text.UTF8Encoding]::new($false))
            Write-Host ('Failure report: '+$failureReport)
            if($scriptDirectory){foreach($file in @($failureReport,$failureText,$logPath)){if($file -and (Test-Path -LiteralPath $file)){try{Copy-Item -LiteralPath $file -Destination (Join-Path $scriptDirectory ([IO.Path]::GetFileName($file))) -ErrorAction Stop}catch{Write-Host ('Report copy failed. Original: '+$file)}}}}
        } catch {Write-Host ('REPORT SAVE ERROR: '+$_.Exception.Message) -ForegroundColor Red}
    }
    exit 1
}
