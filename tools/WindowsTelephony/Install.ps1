#requires -Version 5.1
[CmdletBinding(SupportsShouldProcess)]
param([string]$ServerUrl)
$ErrorActionPreference = 'Stop'
if (-not $ServerUrl) {
    $ServerUrl = (Get-Content -LiteralPath (Join-Path $PSScriptRoot 'settings.json') -Raw | ConvertFrom-Json).server_url
}
$uri = [uri]$ServerUrl
if (-not $uri.IsAbsoluteUri -or $uri.Scheme -notin @('http','https') -or $uri.UserInfo -or $uri.Query -or $uri.Fragment -or $uri.AbsolutePath -ne '/') {
    throw 'ServerUrl must be the exact HTTP(S) application origin, including its port if any.'
}
$destination = Join-Path $env:LOCALAPPDATA 'OfficeAutomation\Telephony'
$protocolKey = 'HKCU:\Software\Classes\office-telephony'
$commandKey = Join-Path $protocolKey 'shell\open\command'
if (Test-Path -LiteralPath $commandKey) {
    $oldCommand = (Get-Item -LiteralPath $commandKey).GetValue('')
    if ($oldCommand -notlike '*OfficeAutomation\Telephony\ModemHelper.ps1*') { throw 'This protocol is already registered to another program.' }
}
if ($PSCmdlet.ShouldProcess($destination, 'Install the per-user Office Automation modem helper')) {
    New-Item -ItemType Directory -Force -Path $destination | Out-Null
    foreach ($file in @('ModemHelper.ps1', 'VoiceTest.ps1', 'VoiceDiagnostics.cs')) {
        Copy-Item -LiteralPath (Join-Path $PSScriptRoot $file) -Destination (Join-Path $destination $file) -Force
    }
    @{server_url=$uri.GetLeftPart([UriPartial]::Authority)} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $destination 'settings.json') -Encoding utf8
    New-Item -Path $protocolKey -Force | Out-Null
    Set-Item -Path $protocolKey -Value 'Office Automation Telephony'
    New-ItemProperty -Path $protocolKey -Name 'URL Protocol' -Value '' -Force | Out-Null
    New-Item -Path $commandKey -Force | Out-Null
    $command = '"{0}" -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -STA -File "{1}" -ProtocolUri "%1"' -f "$env:SystemRoot\System32\WindowsPowerShell\v1.0\powershell.exe", (Join-Path $destination 'ModemHelper.ps1')
    Set-Item -Path $commandKey -Value $command
    Write-Output 'Helper installed for the current Windows user. Open the application on this computer and start modem discovery.'
}
