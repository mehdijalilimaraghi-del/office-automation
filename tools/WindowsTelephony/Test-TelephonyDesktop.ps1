#requires -Version 5.1
$ErrorActionPreference='Stop'
Import-Module (Join-Path $PSScriptRoot 'TelephonyDesktop.psm1') -Force -DisableNameChecking
$module=Get-Module TelephonyDesktop
& $module {
 function New-Run {
  return @{session=$null;finished=$false;stopping=$false;nextStart=$null;resumePending=$false;lastResult=$null;desktopBusy=$false;wantClose=$false;mutex=$null}
 }
 function New-Context($Run){
  return @{run=$Run;operation=$null;pbx=@{active_calls=0};status=[pscustomobject]@{Text=''};stop=[pscustomobject]@{Enabled=$false};start=[pscustomobject]@{Enabled=$true};connect=[pscustomobject]@{Enabled=$true};apply=[pscustomobject]@{Enabled=$true};data='test';lab='test';engine='test'}
 }
 function Start-TelephonyDesktopOperation($Action,$Parameters){return @{action=$Action;parameters=$Parameters}}
 $run=New-Run;$script:Desktop=New-Context $run
 $run.session=[pscustomobject]@{Completed=$false;Progress='bridging';PauseRequests=0}
 $run.session|Add-Member ScriptMethod RequestIdlePause {$this.PauseRequests++}
 Request-TelephonyDesktopApply
 if($run.session.PauseRequests -or $script:Desktop.operation){throw 'An active conversation must not be paused or reconfigured'}
 $run.session.Progress='waiting_ring';Request-TelephonyDesktopApply
 if($run.session.PauseRequests -ne 1 -or -not $run.pauseForConfiguration -or $script:Desktop.operation){throw 'Idle receiver must request a safe pause before applying'}
 $run=New-Run;$script:Desktop=New-Context $run;$run.session=[pscustomobject]@{Completed=$true}
 Request-TelephonyDesktopApply
 if(-not $run.pauseForConfiguration -or $script:Desktop.operation){throw 'Completed receiver must persist its report before applying'}
 $run=New-Run;$script:Desktop=New-Context $run;$script:Desktop.pbx.active_calls=1
 Request-TelephonyDesktopApply
 if($script:Desktop.operation){throw 'Other active PBX calls must prevent applying'}
 $run=New-Run;$script:Desktop=New-Context $run;$run.lastResult=@{CleanupConfirmed=$false}
 Start-TelephonyDesktopApply
 if($script:Desktop.operation){throw 'Unconfirmed cleanup must prevent applying'}
 $run=New-Run;$script:Desktop=New-Context $run
 Start-TelephonyDesktopApply -Resume
 if(-not $run.desktopBusy -or -not $run.resumePending -or $script:Desktop.operation.action -ne 'Apply' -or $script:Desktop.start.Enabled){throw 'Idle apply must retain resume intent and disable duplicate starts'}
 $script:Desktop=$null
}
Write-Output 'PASS: conversation protection, idle pause, report ordering, PBX busy guard, cleanup guard and resume intent.'
