#requires -Version 5.1
[CmdletBinding()]
param([string]$InstallerPath,[switch]$CheckOnly)
$ErrorActionPreference='Stop'
$expectedHash='331cdd876c7ee73cc6d60bbdd7c9b7e60c157d3760226986fd7d6e604c83d8b6'
try {
    $scriptDirectory=$PSScriptRoot
    if([string]::IsNullOrWhiteSpace($scriptDirectory)){
        $scriptFile=$PSCommandPath
        if([string]::IsNullOrWhiteSpace($scriptFile)){$scriptFile=$MyInvocation.MyCommand.Path}
        if(-not [string]::IsNullOrWhiteSpace($scriptFile)){$scriptDirectory=[IO.Path]::GetDirectoryName($scriptFile)}
    }
    if([string]::IsNullOrWhiteSpace($InstallerPath)){
        if([string]::IsNullOrWhiteSpace($scriptDirectory)){throw 'مسیر بسته مشخص نیست. فایل Install-PbxEngine.cmd را از پوشه استخراج‌شده اجرا کنید.'}
        $InstallerPath=Join-Path $scriptDirectory 'FreeSWITCH-1.11.4-Release-x64.msi'
    }
    if(-not [Environment]::Is64BitOperatingSystem -or [Environment]::OSVersion.Version.Major -lt 10){throw 'این بسته برای ویندوز ۱۰ یا جدیدتر با معماری ۶۴ بیتی آماده شده است.'}
    if(-not [Environment]::Is64BitProcess){throw 'این ابزار باید با Windows PowerShell نسخه ۶۴ بیتی اجرا شود.'}
    $package=Get-Item -LiteralPath $InstallerPath -ErrorAction Stop
    if($package.PSIsContainer -or $package.Length -ne 166219776){throw 'فایل نصب کامل یا معتبر نیست؛ بسته رسمی را دوباره دریافت کنید.'}
    if((Get-FileHash -LiteralPath $package.FullName -Algorithm SHA256).Hash.ToLowerInvariant() -ne $expectedHash){throw 'هش فایل نصب با نسخه رسمی مطابقت ندارد؛ نصب متوقف شد.'}
    $signature=Get-AuthenticodeSignature -LiteralPath $package.FullName
    if($signature.Status -ne 'Valid' -or $signature.SignerCertificate.Subject -notmatch 'O="?SignalWire, Inc\.') {throw 'اعتبار امضای نصب‌کننده تأیید نشد؛ وضعیت گواهی و اتصال اینترنت این کامپیوتر را بررسی کنید.'}
    $existing=Get-Service -Name FreeSWITCH -ErrorAction SilentlyContinue
    $destination=Join-Path $env:ProgramFiles 'FreeSWITCH'
    if($existing -or (Test-Path -LiteralPath $destination)){throw 'یک نصب یا پوشه قبلی FreeSWITCH وجود دارد. برای حفظ تنظیمات، نصب خودکار روی آن انجام نمی‌شود.'}
    if($CheckOnly){[pscustomobject]@{status='package_verified';version='1.11.4';architecture='x64';sha256=$expectedHash;signature='Valid';installation_executed=$false};return}
    Write-Host 'فایل رسمی تأیید شد. اکنون نصب هسته مرکز تلفن با دسترسی مدیریتی ویندوز اجرا می‌شود.'
    Write-Host 'در این مرحله، مرکز تلفن و خط مودم فعال نمی‌شوند. پس از نصب، گزارش نتیجه را ارسال کنید.'
    $logDirectory=Join-Path $env:LOCALAPPDATA 'OfficeAutomation\Telephony\Setup'
    [void][IO.Directory]::CreateDirectory($logDirectory)
    $logPath=Join-Path $logDirectory ('freeswitch-install-'+[Guid]::NewGuid().ToString('N')+'.log')
    # Cache the verified package locally so elevation does not depend on mapped/UNC drive access.
    $cachedMsi=Join-Path $logDirectory ('freeswitch-'+[Guid]::NewGuid().ToString('N')+'.msi')
    Copy-Item -LiteralPath $package.FullName -Destination $cachedMsi -ErrorAction Stop
    if((Get-FileHash -LiteralPath $cachedMsi -Algorithm SHA256).Hash.ToLowerInvariant() -ne $expectedHash){throw 'فایل کپی‌شده معتبر نیست؛ نصب متوقف شد.'}
    # ProductFeature installs the core. Exclude sample configuration and the optional codec download.
    $arguments='/i "'+$cachedMsi+'" ADDLOCAL=ProductFeature /passive /norestart /L*v "'+$logPath+'"'
    $process=Start-Process -FilePath (Join-Path $env:SystemRoot 'System32\msiexec.exe') -ArgumentList $arguments -Verb RunAs -WindowStyle Normal -Wait -PassThru
    if($process.ExitCode -notin @(0,3010)){throw ('نصب کامل نشد. کد نتیجه: '+$process.ExitCode+'. فایل گزارش: '+$logPath)}
    $service=Get-Service -Name FreeSWITCH -ErrorAction SilentlyContinue
    if(-not $service -or $service.Status -ne 'Stopped'){throw 'وضعیت نهایی سرویس مطابق انتظار نیست. پیش از ادامه باید بررسی شود.'}
    $binary=Join-Path $destination 'freeswitch.exe'
    if(-not (Test-Path -LiteralPath $binary)){throw 'فایل اجرایی نصب‌شده پیدا نشد؛ گزارش نصب را بررسی کنید.'}
    $result=[ordered]@{report_type='pbx_engine_install';schema_version=1;version='1.11.4';computer_name=$env:COMPUTERNAME;installed_at=[DateTimeOffset]::Now.ToString('o');installer_sha256=$expectedHash;exit_code=$process.ExitCode;restart_required=($process.ExitCode -eq 3010);service_status=[string]$service.Status;binary_path=$binary;calls_tested=$false}
    $reportPath=Join-Path $logDirectory 'pbx-engine-install-result.json'
    [IO.File]::WriteAllText($reportPath,($result|ConvertTo-Json),[Text.UTF8Encoding]::new($false))
    Write-Host ('هسته نصب شد. سرویس هنوز فعال نشده است. گزارش نصب: '+$reportPath) -ForegroundColor Green
    if($process.ExitCode -eq 3010){Write-Host 'ویندوز درخواست راه‌اندازی مجدد دارد؛ راه‌اندازی مجدد خودکار انجام نشده است.'}
    if($scriptDirectory -and (Test-Path -LiteralPath (Join-Path $scriptDirectory 'Install-PbxEngine.cmd'))){
        try {Copy-Item -LiteralPath $reportPath -Destination (Join-Path $scriptDirectory ('pbx-engine-install-result-'+[Guid]::NewGuid().ToString('N')+'.json')) -ErrorAction Stop} catch {Write-Host 'گزارش را از مسیر نمایش‌داده‌شده بردارید؛ کپی کنار بسته ممکن نشد.'}
    }
} catch {
    Write-Error $_.Exception.Message -ErrorAction Continue
    exit 1
}
