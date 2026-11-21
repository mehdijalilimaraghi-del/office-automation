#requires -Version 5.1
[CmdletBinding()]
param(
    [string]$ProtocolUri,
    [switch]$DiscoverOnly,
    [switch]$Offline,
    [string]$OutputPath
)
$ErrorActionPreference = 'Stop'
function Get-HelperIdentity {
    $directory = Join-Path $env:LOCALAPPDATA 'OfficeAutomation\Telephony'
    New-Item -ItemType Directory -Force -Path $directory | Out-Null
    $identityFile = Join-Path $directory 'helper-id.txt'
    if (-not (Test-Path -LiteralPath $identityFile)) {
        [IO.File]::WriteAllText($identityFile, [guid]::NewGuid().ToString('D'))
    }
    return ([guid]([IO.File]::ReadAllText($identityFile).Trim())).ToString('D')
}
function Get-ModemDevices([string]$ComputerKey) {
    $devices = @()
    foreach ($modem in @(Get-CimInstance Win32_POTSModem)) {
        $identity = if ($modem.PNPDeviceID) { [string]$modem.PNPDeviceID } else { [string]$modem.DeviceID }
        $hasher = [Security.Cryptography.SHA256]::Create()
        try { $key = ([BitConverter]::ToString($hasher.ComputeHash([Text.Encoding]::UTF8.GetBytes($ComputerKey + '|' + $identity)))).Replace('-', '').ToLowerInvariant() } finally { $hasher.Dispose() }
        $port = if ([string]$modem.AttachedTo -match '^COM[1-9][0-9]{0,4}$') { [string]$modem.AttachedTo } else { $null }
        $devices += [pscustomobject]@{
            device_key = $key; name = [string]$modem.Name; port = $port
            driver_status = if ($modem.Status -eq 'OK') { 'OK' } elseif ($modem.Status) { 'unavailable' } else { 'unknown' }
            probe_status = 'not_tested'; evidence = @{}
        }
    }
    return $devices
}
function Read-ModemCapabilities([string]$Port) {
    if ($Port -notmatch '^COM[1-9][0-9]{0,4}$') { throw 'درگاه مودم معتبر نیست.' }
    $serial = New-Object IO.Ports.SerialPort($Port, 115200, [IO.Ports.Parity]::None, 8, [IO.Ports.StopBits]::One)
    $serial.ReadTimeout = 1500
    $serial.WriteTimeout = 1500
    $serial.DtrEnable = $false
    $serial.RtsEnable = $false
    $evidence = @{}
    try {
        $serial.Open()
        foreach ($query in @(@('identity','ATI'), @('classes','AT+FCLASS=?'), @('voice_formats','AT+VSM=?'))) {
            $serial.DiscardInBuffer()
            $serial.Write($query[1] + [char]13)
            $watch = [Diagnostics.Stopwatch]::StartNew()
            $reply = ''
            while ($watch.ElapsedMilliseconds -lt 1800) {
                Start-Sleep -Milliseconds 80
                $reply += $serial.ReadExisting()
                if ($reply.Length -gt 4096) { throw 'پاسخ مودم بیش از اندازه مجاز است.' }
                if ($reply -match '(?m)^(OK|ERROR|NO CARRIER)\s*$') { break }
            }
            $evidence[$query[0]] = [regex]::Replace($reply, '[^\x09\x0A\x0D\x20-\x7E]', '')
        }
        return $evidence
    } finally {
        if ($serial.IsOpen) { $serial.Close() }
        $serial.Dispose()
    }
}
$computerKey = Get-HelperIdentity
$devices = @(Get-ModemDevices $computerKey)
if ($DiscoverOnly) {
    $report = @{ computer_key = $computerKey; computer_name = $env:COMPUTERNAME; devices = @($devices) } | ConvertTo-Json -Depth 6
    if ($OutputPath) { [IO.File]::WriteAllText([IO.Path]::GetFullPath($OutputPath), $report, [Text.UTF8Encoding]::new($false)) } else { Write-Output $report }
    return
}
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
[Windows.Forms.Application]::EnableVisualStyles()
try {
    if (-not $Offline) {
    $config = Get-Content -LiteralPath (Join-Path $PSScriptRoot 'settings.json') -Raw | ConvertFrom-Json
    $allowed = [uri]$config.server_url
    $protocol = [uri]$ProtocolUri
    if ($protocol.Scheme -ne 'office-telephony' -or $protocol.Host -ne 'discover') { throw 'برنامه را از صفحه تلفن سازمانی باز کنید.' }
    if ($protocol.Query -notmatch '^\?upload=([^&]+)$') { throw 'درخواست اتصال معتبر نیست.' }
    $upload = [uri]::UnescapeDataString($Matches[1])
    $uploadUri = [uri]$upload
    if ($uploadUri.Scheme -notin @('http','https') -or $uploadUri.Authority -ne $allowed.Authority -or $uploadUri.Scheme -ne $allowed.Scheme -or $uploadUri.UserInfo -or $uploadUri.Query -or $uploadUri.Fragment -or $uploadUri.AbsolutePath -notmatch '^/telephony/agent/reports/[a-f0-9]{64}$') { throw 'نشانی دریافت گزارش با سامانه نصب‌شده مطابقت ندارد.' }
    }
    $form = New-Object Windows.Forms.Form
    $form.Text = 'واسط مودم اتوماسیون اداری'
    $form.Width = 780
    $form.Height = 520
    $form.MinimumSize = New-Object Drawing.Size(620,440)
    $form.StartPosition = 'CenterScreen'
    $form.RightToLeft = 'Yes'
    $form.RightToLeftLayout = $true
    $form.Font = New-Object Drawing.Font('Segoe UI',10)
    $layout = New-Object Windows.Forms.TableLayoutPanel
    $layout.Dock = 'Fill'; $layout.Padding = New-Object Windows.Forms.Padding(16)
    $layout.ColumnCount = 1; $layout.RowCount = 4
    [void]$layout.RowStyles.Add((New-Object Windows.Forms.RowStyle([Windows.Forms.SizeType]::Absolute,65)))
    [void]$layout.RowStyles.Add((New-Object Windows.Forms.RowStyle([Windows.Forms.SizeType]::Absolute,55)))
    [void]$layout.RowStyles.Add((New-Object Windows.Forms.RowStyle([Windows.Forms.SizeType]::Percent,100)))
    [void]$layout.RowStyles.Add((New-Object Windows.Forms.RowStyle([Windows.Forms.SizeType]::Absolute,60)))
    $label = New-Object Windows.Forms.Label
    $label.Dock = 'Fill'; $label.Text = 'مودم را انتخاب و قابلیت آن را بررسی کنید. این برنامه شماره‌گیری نمی‌کند. قبل از بررسی، نرم‌افزارهای فکس و اینترنت دایال‌آپ را ببندید.'
    $choice = New-Object Windows.Forms.ComboBox
    $choice.Dock = 'Top'; $choice.DropDownStyle = 'DropDownList'; $choice.DisplayMember = 'name'
    foreach ($device in $devices) { [void]$choice.Items.Add($device) }
    if ($choice.Items.Count) { $choice.SelectedIndex = 0 }
    $output = New-Object Windows.Forms.TextBox
    $output.Dock = 'Fill'; $output.Multiline = $true; $output.ReadOnly = $true; $output.ScrollBars = 'Vertical'
    $output.Text = if ($devices.Count) { 'مودم شناسایی شد. برای بررسی، دکمه پایین را بزنید.' } else { 'مودمی شناسایی نشد. نصب درایور را در مدیریت دستگاه‌های ویندوز بررسی کنید.' }
    $buttons = New-Object Windows.Forms.FlowLayoutPanel
    $buttons.Dock = 'Fill'
    $probe = New-Object Windows.Forms.Button
    $probe.Text = 'بررسی قابلیت'; $probe.AutoSize = $true; $probe.Enabled = $devices.Count -gt 0
    $send = New-Object Windows.Forms.Button
    $send.Text = if ($Offline) { 'ذخیره گزارش بررسی' } else { 'ارسال گزارش به سامانه' }; $send.AutoSize = $true
    $probe.Add_Click({
        $device = $choice.SelectedItem
        $probe.Enabled = $false
        $form.UseWaitCursor = $true
        $output.Text = 'در حال خواندن قابلیت‌های مودم…'
        $form.Refresh()
        try {
            $device.evidence = Read-ModemCapabilities $device.port
            $device.probe_status = 'queried'
            $output.Text = ($device.evidence | ConvertTo-Json -Depth 3) + [Environment]::NewLine + 'این نتیجه به‌تنهایی تأیید مکالمه دوطرفه نیست.'
        } catch {
            $device.probe_status = 'failed'
            $output.Text = 'بررسی انجام نشد. ممکن است درگاه در اختیار برنامه دیگری باشد یا دسترسی صوتی توسط درایور فراهم نشده باشد.'
        } finally { $probe.Enabled = $true; $form.UseWaitCursor = $false }
    })
    $send.Add_Click({
        $send.Enabled = $false
        $handler = $null; $client = $null
        try {
            $body = @{ computer_key = $computerKey; computer_name = $env:COMPUTERNAME; devices = @($devices) } | ConvertTo-Json -Depth 6
            if ($Offline) {
                $dialog = New-Object Windows.Forms.SaveFileDialog
                $dialog.Filter = 'JSON report (*.json)|*.json'
                $dialog.FileName = 'modem-report.json'
                if ($dialog.ShowDialog() -eq 'OK') {
                    [IO.File]::WriteAllText($dialog.FileName, $body, [Text.UTF8Encoding]::new($false))
                    $output.Text = 'گزارش بررسی ذخیره شد. این بررسی خط را فعال نکرده است.'
                }
                $dialog.Dispose()
                $send.Enabled = $true
                return
            }
            Add-Type -AssemblyName System.Net.Http
            $handler = New-Object Net.Http.HttpClientHandler
            $handler.AllowAutoRedirect = $false
            $client = New-Object Net.Http.HttpClient($handler)
            $client.Timeout = [TimeSpan]::FromSeconds(15)
            $client.DefaultRequestHeaders.Accept.ParseAdd('application/json')
            $body = @{ computer_key = $computerKey; computer_name = $env:COMPUTERNAME; devices = @($devices) } | ConvertTo-Json -Depth 6
            $content = New-Object Net.Http.StringContent($body, [Text.Encoding]::UTF8, 'application/json')
            try { $response = $client.PostAsync($uploadUri, $content).GetAwaiter().GetResult() } finally { $content.Dispose() }
            if (-not $response.IsSuccessStatusCode) { throw 'درخواست پذیرفته نشد. از سامانه درخواست جدید ایجاد کنید.' }
            $output.Text = 'گزارش ارسال شد؛ به صفحه تلفن سازمانی در مرورگر بازگردید.'
            $probe.Enabled = $false
        } catch {
            $output.Text = 'ارسال گزارش ممکن نشد. اتصال شبکه و اعتبار درخواست ده‌دقیقه‌ای را بررسی کنید.'
            $send.Enabled = $true
        } finally {
            if ($client) { $client.Dispose() }
            if ($handler) { $handler.Dispose() }
        }
    })
    [void]$buttons.Controls.Add($probe); [void]$buttons.Controls.Add($send)
    [void]$layout.Controls.Add($label,0,0); [void]$layout.Controls.Add($choice,0,1)
    [void]$layout.Controls.Add($output,0,2); [void]$layout.Controls.Add($buttons,0,3)
    [void]$form.Controls.Add($layout)
    [void]$form.ShowDialog()
} catch {
    [Windows.Forms.MessageBox]::Show('برنامه واسط آماده نیست. نصب برنامه و بازکردن آن از صفحه تلفن سازمانی را بررسی کنید.','واسط مودم') | Out-Null
    exit 1
}
