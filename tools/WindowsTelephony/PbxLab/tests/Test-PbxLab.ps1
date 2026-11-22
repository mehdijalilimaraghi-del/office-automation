#requires -Version 5.1
param([string]$EngineDirectory)
$ErrorActionPreference='Stop'
Import-Module (Join-Path $PSScriptRoot '../PbxLab.psm1') -Force -DisableNameChecking
$root=Join-Path $env:TEMP ('office-pbx-tests-'+[Guid]::NewGuid().ToString('N'))
$passed=0
function Assert($Condition,[string]$Message){if(-not $Condition){throw $Message};$script:passed++}
function Reject([scriptblock]$Action){$rejected=$false;try{& $Action|Out-Null}catch{$rejected=$true};Assert $rejected 'Unsafe input was accepted'}
try {
 $state=New-PbxLabProfile -Root $root -BindAddress '127.0.0.1' -Extensions @('1001','1002','1003') -SipPort 15660
 Assert ($state.accounts.Count -eq 3) 'Extension count was fixed to two'
 Assert (@($state.accounts.password|Select-Object -Unique).Count -eq 3) 'Passwords were reused'
 Assert ((Get-Acl -LiteralPath $root).AreAccessRulesProtected) 'Credentials inherit public permissions'
 $before=(Get-FileHash (Join-Path $root 'lab-state.json')).Hash
 Reject {New-PbxLabProfile -Root $root -BindAddress '127.0.0.1'}
 Assert ((Get-FileHash (Join-Path $root 'lab-state.json')).Hash -eq $before) 'Existing credentials were overwritten'
 foreach($bad in @(@('1001','1001'),@('9196'),@("1001`n"),@('1001<xml>'))){Reject {New-PbxLabProfile -Root ($root+'-invalid') -BindAddress '127.0.0.1' -Extensions $bad}}
 Reject {New-PbxLabProfile -Root ($root+'-invalid') -BindAddress '8.8.8.8'}
 Reject {New-PbxLabProfile -Root ($root+'-invalid') -BindAddress '127.0.0.1' -SipPort 17001}
 Assert (-not (Test-Path ($root+'-invalid'))) 'Rejected input left files behind'
 Reject {Invoke-PbxLabCommand $state 'system whoami'}
 $module=Get-Module PbxLab
 & $module {
  $stream=[IO.MemoryStream]::new([Text.Encoding]::UTF8.GetBytes("Content-Length: 5`n`nabc"));try{$caught=$false;try{Read-EslFrame $stream|Out-Null}catch{$caught=$true};if(-not $caught){throw 'Truncated frame accepted'}}finally{$stream.Dispose()}
  $stream=[IO.MemoryStream]::new([Text.Encoding]::UTF8.GetBytes("Content-Length: 1048577`n`n"));try{$caught=$false;try{Read-EslFrame $stream|Out-Null}catch{$caught=$true};if(-not $caught){throw 'Oversized frame accepted'}}finally{$stream.Dispose()}
 }
 $passed+=2
 if($EngineDirectory){
  & (Join-Path $PSScriptRoot '../Start-PbxLab.ps1') -LabRoot $root -EngineDirectory $EngineDirectory -NoBrowser -HideConsole
  Add-Type -Path (Join-Path $PSScriptRoot 'NativeSipSmoke.cs')
  try{$result=[NativeSipSmoke]::Run($state.sip_port,$state.accounts[0].extension,$state.accounts[0].password,$state.accounts[1].extension,$state.accounts[1].password);$result|ConvertTo-Json;Assert ($result.HangupAcknowledged -and $result.ReceivedPacketsA -ge 10 -and $result.ReceivedPacketsB -ge 10) 'Native call failed'}finally{& (Join-Path $PSScriptRoot '../Stop-PbxLab.ps1') -LabRoot $root}
 }
 Write-Host ('PASS: '+$passed+' checks') -ForegroundColor Green
}finally{
 # Delete only this exact, freshly created test directory after confirming no lab process remains.
 $sessionPath=Join-Path $root 'lab-session.json';$active=$false
 if(Test-Path $sessionPath){$session=Get-Content $sessionPath -Raw|ConvertFrom-Json;$active=$null -ne (Get-Process -Id $session.process_id -ErrorAction SilentlyContinue)}
 $resolved=[IO.Path]::GetFullPath($root);$expected=[IO.Path]::GetFullPath($env:TEMP).TrimEnd('\')+'\office-pbx-tests-'
 if(-not $active -and $resolved.StartsWith($expected,[StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $resolved)){Remove-Item -LiteralPath $resolved -Recurse -Force}
}