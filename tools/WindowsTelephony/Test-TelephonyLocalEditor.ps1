#requires -Version 5.1
$ErrorActionPreference='Stop'
Import-Module (Join-Path $PSScriptRoot 'PbxLab/PbxLab.psm1') -Force -DisableNameChecking
Import-Module (Join-Path $PSScriptRoot 'TelephonyDesktop.psm1') -Force -DisableNameChecking
Import-Module (Join-Path $PSScriptRoot 'TelephonyLocalSettings.psm1') -DisableNameChecking
$root=Join-Path $env:TEMP ('office-local-editor-test-'+[guid]::NewGuid().ToString('N'))
$state=New-PbxLabProfile -Root $root -BindAddress '127.0.0.1';Set-PbxModemGateway -Root $root -Destination '1002'
$global:EditorCheck=@{phase=0;ticks=0;called=0;parameters=$null;validationPassed=$false}
function global:Start-TelephonyDesktopOperation($Action,$Parameters){
 if($Action -ne 'LocalApply'){throw 'Editor attempted non-local operation'}
 $global:EditorCheck.called++;$global:EditorCheck.parameters=$Parameters
 return @{action=$Action;handle=[pscustomobject]@{IsCompleted=$true}}
}
function global:Receive-TelephonyDesktopOperation($Operation){return [pscustomobject]@{success=$true}}
$module=Get-Module TelephonyLocalSettings
$owner=[Windows.Forms.Form]::new();$owner.Opacity=0;$owner.ShowInTaskbar=$false;$owner.Show()
$watch=[Windows.Forms.Timer]::new();$watch.Interval=150
$watch.Add_Tick({
 $global:EditorCheck.ticks++
 & (Get-Module TelephonyLocalSettings) {
  $ui=$script:LocalUi;if(-not $ui){return}
  if($global:EditorCheck.phase -eq 0){
   $ui.form.Opacity=0
   $ui.grid.Rows[1].Cells[0].Value='1001';$ui.save.PerformClick()
   $global:EditorCheck.validationPassed=($global:EditorCheck.called -eq 0 -and $ui.message.Text.Contains('غیرتکراری'))
   $ui.grid.Rows[1].Cells[0].Value='1002';$ui.save.PerformClick();$global:EditorCheck.phase=1
  }elseif($ui.success -or $global:EditorCheck.ticks -gt 25){$ui.form.Close()}
 }
})
try{$watch.Start();$success=Show-TelephonyLocalSettings -Owner $owner -LabRoot $root -EngineDirectory 'unused' -DataRoot (Join-Path $root 'Connection')}
finally{$watch.Stop();$watch.Dispose();$owner.Close();$owner.Dispose()}
if(-not $success -or -not $global:EditorCheck.validationPassed -or $global:EditorCheck.called -ne 1){throw 'Actual editor validation/save callbacks failed'}
$manifest=[IO.File]::ReadAllText($global:EditorCheck.parameters.LocalConfigurationPath)|ConvertFrom-Json
if($manifest.incoming.number -ne '1002' -or $manifest.extensions.Count -ne 2){throw 'Editor changed the selected route or extension data'}
Write-Output 'PASS: actual local editor callbacks reject duplicate extensions before apply, submit one local-only operation and show successful saving; no network/modem/PBX opened.'
