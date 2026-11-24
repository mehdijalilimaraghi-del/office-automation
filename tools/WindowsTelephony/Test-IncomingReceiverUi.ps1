#requires -Version 5.1
$ErrorActionPreference='Stop'
Add-Type -AssemblyName System.Windows.Forms
Add-Type -Path (Join-Path $PSScriptRoot 'VoiceDiagnostics.cs')
$errors=$null;$ast=[Management.Automation.Language.Parser]::ParseFile((Join-Path $PSScriptRoot 'Start-ModemBridge.ps1'),[ref]$null,[ref]$errors)
if($errors.Count){throw $errors[0]}
function Read-Handler([string]$method){$node=$ast.Find({param($n) $n -is [Management.Automation.Language.InvokeMemberExpressionAst] -and $n.Member.Value -eq $method},$true);if(-not $node){throw ('Handler missing: '+$method)};return $node.Arguments[0].ScriptBlock.GetScriptBlock()}
$tick=Read-Handler 'Add_Tick';$stopClick=Read-Handler 'Add_Click'
# Select the stop button handler explicitly: the start handler is the first Add_Click.
$stopNode=$ast.Find({param($n) $n -is [Management.Automation.Language.InvokeMemberExpressionAst] -and $n.Expression.Extent.Text -eq '$stop' -and $n.Member.Value -eq 'Add_Click'},$true)
$stopClick=$stopNode.Arguments[0].ScriptBlock.GetScriptBlock()
$ReceiveCalls=$true;$CompareGreetingModes=$false;$useLocalGreeting=$false
$stateProfile=[pscustomobject]@{modem_gateway=[pscustomobject]@{destination='1002'}}
$portChoice=[pscustomobject]@{SelectedItem='COM_TEST';Enabled=$false}
$consent=[pscustomobject]@{Checked=$true;Enabled=$false};$status=[pscustomobject]@{Text=''};$stop=[pscustomobject]@{Enabled=$true}
$start=[pscustomobject]@{Enabled=$false;Clicks=0};$start|Add-Member ScriptMethod PerformClick {$this.Clicks++}
$timer=[Windows.Forms.Timer]::new();$timer.Interval=200
$root=Join-Path $env:TEMP ('office-receiver-ui-'+[guid]::NewGuid().ToString('N'));[void][IO.Directory]::CreateDirectory($root);$reportFolder=$root
function Release-ReceiverLock {$run.released=$true}
function New-CompletedCall {
 $result=[OfficeAutomation.Telephony.Diagnostics.VoiceResult]::new();$result.Status='bridged';$result.CleanupConfirmed=$true;$result.EndReason='pbx_hangup';$result.Answered=$true
 return @{session=[pscustomobject]@{Completed=$true;Result=$result};endpoint=[pscustomobject]@{Metrics=$null};echo=$null;nextStart=$null;completedCalls=0;stopping=$false;wantClose=$false;finished=$false;report=$null;released=$false}
}
try{
 $run=New-CompletedCall;& $tick
 if(-not $timer.Enabled -or -not $run.nextStart -or $run.completedCalls -ne 1 -or -not (Test-Path -LiteralPath $run.report)){throw 'Completion must save one report and keep the next-call timer running'}
 $report=$run.report;& $tick
 if($run.completedCalls -ne 1 -or $run.report -ne $report){throw 'Waiting for rearm must not duplicate reports or postpone the deadline'}
 $run.nextStart=[datetime]::UtcNow.AddSeconds(-1);& $tick
 if($start.Clicks -ne 1 -or $run.session){throw 'The actual tick handler did not start the next session'}
 $run=New-CompletedCall;$run.session.Result.CleanupConfirmed=$false;& $tick
 if($run.nextStart -or $timer.Enabled -or -not $run.released){throw 'Unconfirmed cleanup must stop the UI receiver'}
 $run=New-CompletedCall;$reportFolder=Join-Path $root 'not-created';& $tick
 if($run.nextStart -or $timer.Enabled -or -not $run.released){throw 'Report write failure must prevent another call'}
 $run=New-CompletedCall;$run.finished=$true;$run.nextStart=[datetime]::UtcNow.AddSeconds(1);$timer.Start();& $stopClick
 if(-not $run.stopping -or $run.nextStart -or $run.session -or -not $run.released -or $stop.Enabled -or $timer.Enabled){throw 'Stop during the rearm gap must cancel the next call and release the lock'}
 $reportFolder=$root;$run=New-CompletedCall;$timer.Start();& $stopClick
 if(-not $run.session -or -not $timer.Enabled){throw 'Stop must not discard a completed call awaiting its report'}
 & $tick
 if(-not (Test-Path -LiteralPath $run.report) -or $run.session -or $run.nextStart -or -not $run.released){throw 'Stop must finish journaling the completed call before releasing the receiver'}
 Write-Output 'PASS: actual launcher event handlers save once, resume timer, start next call, stop on cleanup/report failure and honor stop in the rearm gap; no PBX/modem opened'
}finally{
 $timer.Stop();$timer.Dispose();$resolved=[IO.Path]::GetFullPath($root);$prefix=[IO.Path]::GetFullPath($env:TEMP).TrimEnd('\')+'\office-receiver-ui-'
 if($resolved.StartsWith($prefix,[StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $resolved)){Remove-Item -LiteralPath $resolved -Recurse -Force}
}
