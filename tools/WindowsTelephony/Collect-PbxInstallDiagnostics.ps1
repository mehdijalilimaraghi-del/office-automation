#requires -Version 5.1
param([string]$OutputDirectory)
$ErrorActionPreference='Stop'
if(-not $OutputDirectory){$OutputDirectory=Join-Path $PSScriptRoot ('Pbx-Diagnostics-'+[DateTime]::Now.ToString('yyyyMMdd-HHmmss')+'-'+[Guid]::NewGuid().ToString('N').Substring(0,8))}
if((Test-Path -LiteralPath $OutputDirectory) -and @(Get-ChildItem -LiteralPath $OutputDirectory -Force).Count){throw 'Output directory must be new or empty.'}
[void][IO.Directory]::CreateDirectory($OutputDirectory)
$report=[ordered]@{report_type='pbx_install_diagnostics';schema_version=1;collected_at=[DateTimeOffset]::Now.ToString('o');computer_name=$env:COMPUTERNAME;installation_executed=$false;service=$null;binaries=@();installed_products=@();events=@();copied_files=@();warnings=@()}
try{$service=Get-CimInstance Win32_Service -Filter "Name='FreeSWITCH'";if($service){$report.service=@{name=$service.Name;state=$service.State;start_mode=$service.StartMode}}}catch{$report.warnings+=('Service query: '+$_.Exception.Message)}
foreach($base in @($env:ProgramFiles,[Environment]::GetEnvironmentVariable('ProgramFiles(x86)'))){
    if(-not $base){continue}
    foreach($name in @('FreeSwitchConsole.exe','freeswitch.exe','fs_cli.exe')){$path=Join-Path $base ('FreeSWITCH\'+$name);if(Test-Path -LiteralPath $path){$file=Get-Item -LiteralPath $path;$report.binaries+=@{name=$name;path=$path;version=$file.VersionInfo.FileVersion;bytes=$file.Length}}}
}
# Avoid Win32_Product: its enumeration can trigger MSI repair.
foreach($root in @('HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall','HKLM:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall')){
    try{foreach($key in @(Get-ChildItem -LiteralPath $root)){$entry=Get-ItemProperty -LiteralPath $key.PSPath -ErrorAction SilentlyContinue;if($entry.DisplayName -like 'FreeSWITCH*'){$report.installed_products+=@{name=$entry.DisplayName;version=$entry.DisplayVersion;location=$entry.InstallLocation}}}}catch{$report.warnings+=('Product query: '+$_.Exception.Message)}
}
foreach($provider in @('MsiInstaller','Application Error','RestartManager')){
    try{foreach($event in @(Get-WinEvent -FilterHashtable @{LogName='Application';ProviderName=$provider;StartTime=[DateTime]::Now.AddHours(-24)} -MaxEvents 100)){$message=$event.Message;if($message -match '(?i)FreeSWITCH|FreeSwitchConsole|explorer\.exe|msiexec\.exe'){$report.events+=@{provider=$provider;id=$event.Id;time=$event.TimeCreated.ToString('o');message=$message.Substring(0,[Math]::Min(12000,$message.Length))}}}}catch{$report.warnings+=('Event query '+$provider+': '+$_.Exception.Message)}
}
if($env:LOCALAPPDATA){$setup=Join-Path $env:LOCALAPPDATA 'OfficeAutomation\Telephony\Setup';if(Test-Path -LiteralPath $setup){foreach($pattern in @('freeswitch-install-*.log','pbx-engine-install-*.json','pbx-engine-install-*.txt')){foreach($file in @(Get-ChildItem -LiteralPath $setup -File -Filter $pattern|Sort-Object LastWriteTime -Descending|Select-Object -First 3)){try{Copy-Item -LiteralPath $file.FullName -Destination (Join-Path $OutputDirectory $file.Name);$report.copied_files+=$file.Name}catch{$report.warnings+=('Log copy: '+$_.Exception.Message)}}}}}
[IO.File]::WriteAllText((Join-Path $OutputDirectory 'pbx-install-diagnostics.json'),($report|ConvertTo-Json -Depth 8),[Text.UTF8Encoding]::new($false))
Write-Host 'Diagnostics saved. No installation, repair, service restart or system change was performed.' -ForegroundColor Green
Write-Host ('Report folder: '+$OutputDirectory)

try{Add-Type -AssemblyName System.IO.Compression.FileSystem;$archive=[IO.Path]::GetFullPath($OutputDirectory)+'.zip';[IO.Compression.ZipFile]::CreateFromDirectory([IO.Path]::GetFullPath($OutputDirectory),$archive);Write-Host ('Diagnostics archive: '+$archive)}catch{Write-Host ('Archive creation failed; use the report folder: '+$_.Exception.Message)}
