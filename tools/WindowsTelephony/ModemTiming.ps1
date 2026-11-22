#requires -Version 5.1
$ErrorActionPreference='Stop'
try {
 Add-Type -AssemblyName Microsoft.VisualBasic
 $port=[Microsoft.VisualBasic.Interaction]::InputBox('Modem COM port (close VentaFax first):','Modem timing check','COM3')
 if(-not $port){exit 0}
 . (Join-Path $PSScriptRoot 'VoiceTest.ps1')
 $result=Show-ModemVoiceTest -Port $port -DeviceName 'Modem stream timing'
 if($result){
  $report=@{report_type='modem_timing_check';schema_version=1;result=$result}
  [IO.File]::WriteAllText((Join-Path $PSScriptRoot 'modem-timing-result.json'),($report|ConvertTo-Json -Depth 9),[Text.UTF8Encoding]::new($false))
  Write-Host ('Saved modem-timing-result.json. Timing gate: '+$result.BridgeTimingReady)
 }
}catch{Write-Host ('ERROR: '+$_.Exception.Message) -ForegroundColor Red;exit 1}