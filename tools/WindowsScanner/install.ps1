$ErrorActionPreference = 'Stop'
$installRoot = Join-Path $env:LOCALAPPDATA 'OfficeAutomation\Scanner'
$exe = Join-Path $installRoot 'OfficeAutomation.Scanner.exe'

if (-not (Test-Path $exe)) {
    throw "OfficeAutomation.Scanner.exe was not found. Publish the helper and copy its output to $installRoot."
}

$protocolKey = 'HKCU:\Software\Classes\officeautomation-scanner'
New-Item -Path $protocolKey -Force | Out-Null
Set-Item -Path $protocolKey -Value 'URL:Office Automation Scanner Protocol'
New-ItemProperty -Path $protocolKey -Name 'URL Protocol' -Value '' -PropertyType String -Force | Out-Null
New-Item -Path "$protocolKey\DefaultIcon" -Force | Out-Null
Set-Item -Path "$protocolKey\DefaultIcon" -Value "`"$exe`",0"
New-Item -Path "$protocolKey\shell\open\command" -Force | Out-Null
Set-Item -Path "$protocolKey\shell\open\command" -Value "`"$exe`" `"%1`""

Write-Host 'Office Automation scanner protocol registered for the current Windows user.'
Write-Host 'Close and reopen the browser before testing direct scanning.'
