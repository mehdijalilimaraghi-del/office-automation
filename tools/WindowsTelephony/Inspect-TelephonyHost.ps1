#requires -Version 5.1
[CmdletBinding()]
param([string]$OutputPath, [switch]$NoUI)
$ErrorActionPreference='Stop'

function Get-TelephonyHostInventory {
    $system=Get-CimInstance Win32_ComputerSystem
    $os=Get-CimInstance Win32_OperatingSystem
    $processors=@(Get-CimInstance Win32_Processor)
    $disks=@(Get-CimInstance Win32_LogicalDisk -Filter 'DriveType=3')
    [ordered]@{
        report_type='telephony_host_inventory'
        schema_version=1
        collected_at=[DateTimeOffset]::Now.ToString('o')
        computer_name=$env:COMPUTERNAME
        windows=[ordered]@{edition=$os.Caption;version=$os.Version;build=$os.BuildNumber;architecture=$os.OSArchitecture}
        memory=[ordered]@{total_bytes=[long]$system.TotalPhysicalMemory;available_bytes=([long]$os.FreePhysicalMemory*1024)}
        processors=@($processors | ForEach-Object {
            [ordered]@{name=$_.Name;cores=$_.NumberOfCores;logical_processors=$_.NumberOfLogicalProcessors;address_width=$_.AddressWidth}
        })
        disks=@($disks | ForEach-Object {[ordered]@{drive=$_.DeviceID;size_bytes=$_.Size;free_bytes=$_.FreeSpace}})
        notes=@('Read-only inventory; no installation, modem access, network upload, service change or restart.', 'Target deployment is native Windows only; no Linux or virtual machine required by this inventory.', 'Free memory and disk space are a snapshot, not a guarantee of installation readiness.')
    }
}

if($MyInvocation.InvocationName -ne '.') {
    try {
        $report=Get-TelephonyHostInventory
        if(-not $OutputPath) {
            if($NoUI) {throw 'OutputPath is required with NoUI.'}
            Add-Type -AssemblyName System.Windows.Forms
            $dialog=New-Object Windows.Forms.SaveFileDialog
            $dialog.Title='ذخیره گزارش کامپیوتر برای تلفن سازمانی'
            $dialog.Filter='JSON report (*.json)|*.json'
            $dialog.FileName='telephony-host-report.json'
            $dialog.InitialDirectory=$PSScriptRoot
            try {
                if($dialog.ShowDialog() -ne 'OK') {return}
                $OutputPath=$dialog.FileName
            } finally {$dialog.Dispose()}
        }
        [IO.File]::WriteAllText([IO.Path]::GetFullPath($OutputPath),($report|ConvertTo-Json -Depth 8),[Text.UTF8Encoding]::new($false))
        if(-not $NoUI) {
            Add-Type -AssemblyName System.Windows.Forms
            [Windows.Forms.MessageBox]::Show('گزارش مشخصات ذخیره شد. این بررسی چیزی نصب نکرد و تنظیمات مودم یا ویندوز را تغییر نداد. فایل telephony-host-report.json را برای ادامه بررسی ارسال کنید.','بررسی کامپیوتر تلفن سازمانی') | Out-Null
        } else {'Host inventory saved; no system configuration changed.'}
    } catch {
        if($NoUI) {throw}
        Add-Type -AssemblyName System.Windows.Forms
        [Windows.Forms.MessageBox]::Show('خواندن یا ذخیره گزارش ممکن نشد. بسته را در پوشه قابل نوشتن استخراج و دوباره اجرا کنید.','بررسی کامپیوتر تلفن سازمانی') | Out-Null
        exit 1
    }
}
