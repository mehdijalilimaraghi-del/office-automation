#requires -Version 5.1
param([string]$LabRoot)
$ErrorActionPreference='Stop'
try {
 Import-Module (Join-Path $PSScriptRoot 'PbxLab.psm1') -Force -DisableNameChecking
 if(-not $LabRoot){$LabRoot=Join-Path $env:LOCALAPPDATA 'OfficeAutomation\Telephony\PbxLab'}
 $state=Read-PbxLabState $LabRoot
 $report=[ordered]@{report_type='pbx_lab_diagnostics';schema_version=1;collected_at=[DateTimeOffset]::Now.ToString('o');bind_address=$state.bind_address;sip_port=$state.sip_port;extensions=@($state.accounts|ForEach-Object {$_.extension});modem_connected=$false;windows_service_changed=$false;ready=$false;registered_extensions=@();active_calls=$null}
 try {
  $status=Invoke-PbxLabCommand $state 'status';$report.ready=$status -match 'UP'
  $registrations=Invoke-PbxLabCommand $state 'sofia status profile office_lab reg'
  $report.registered_extensions=@($state.accounts|Where-Object {$registrations -match ('(?m)^User:\s*'+[regex]::Escape($_.extension)+'@')}|ForEach-Object {$_.extension})
  $calls=Invoke-PbxLabCommand $state 'show calls as json'|ConvertFrom-Json;$report.active_calls=$calls.row_count
 }catch{$report.control_error='Local lab control is unavailable. Start the lab or inspect its console.'}
 $destination=Join-Path $PSScriptRoot ('pbx-lab-diagnostics-'+[DateTime]::Now.ToString('yyyyMMdd-HHmmss')+'-'+[Guid]::NewGuid().ToString('N').Substring(0,6)+'.json')
 [IO.File]::WriteAllText($destination,($report|ConvertTo-Json -Depth 5),[Text.UTF8Encoding]::new($false))
 Write-Host ('Diagnostics saved: '+$destination) -ForegroundColor Green
 Write-Host 'This report contains no account passwords, configuration files or raw SIP traffic.'
}catch{Write-Host ('ERROR: '+$_.Exception.Message) -ForegroundColor Red;exit 1}