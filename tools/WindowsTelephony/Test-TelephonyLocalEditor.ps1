#requires -Version 5.1
param([string]$RuntimeDirectory)
$ErrorActionPreference='Stop'
if(-not $RuntimeDirectory){$RuntimeDirectory=$PSScriptRoot}
$pbx=Join-Path $RuntimeDirectory 'PbxLab.psm1';if(-not (Test-Path -LiteralPath $pbx)){$pbx=Join-Path $RuntimeDirectory 'PbxLab/PbxLab.psm1'}
Import-Module $pbx -Force -DisableNameChecking
Import-Module (Join-Path $RuntimeDirectory 'TelephonyDesktop.psm1') -Force -DisableNameChecking
Import-Module (Join-Path $RuntimeDirectory 'TelephonyLocalSettings.psm1') -DisableNameChecking
$root=Join-Path $env:TEMP ('office-local-editor-test-'+[guid]::NewGuid().ToString('N'))
$state=New-PbxLabProfile -Root $root -BindAddress '127.0.0.1';Set-PbxModemGateway -Root $root -Destination '1002'
$global:EditorCheck=@{phase=0;ticks=0;called=0;parameters=$null;validationPassed=$false;dialogIndex=0;duplicateTried=$false;errors=@();queue=@(@{number='1003';label='New office'},@{number='1004';label='Edited office'},@{number='1005';label='Updated destination'})}
function global:Start-TelephonyDesktopOperation($Action,$Parameters){if($Action -ne 'LocalApply'){throw 'Non-local editor operation'};$global:EditorCheck.called++;$global:EditorCheck.parameters=$Parameters;return @{action=$Action;handle=[pscustomobject]@{IsCompleted=$true}}}
function global:Receive-TelephonyDesktopOperation($Operation){return [pscustomobject]@{success=$true}}
$owner=[Windows.Forms.Form]::new();$owner.Opacity=0;$owner.ShowInTaskbar=$false;$owner.Show()
$driver=[Windows.Forms.Timer]::new();$driver.Interval=100
$driver.Add_Tick({
 & (Get-Module TelephonyLocalSettings) {
  $edit=$script:ExtensionEditor;if(-not $edit){return}
  try{
   $edit.window.Opacity=0;$check=$global:EditorCheck;$item=$check.queue[$check.dialogIndex]
   $edit.name.Text=$item.label
   $confirm=$edit.window.AcceptButton
   if($check.dialogIndex -eq 0 -and -not $check.duplicateTried){
    $check.duplicateTried=$true;$edit.number.Text='1001';$confirm.PerformClick()
    $check.validationPassed=$edit.error.Text.Contains('قبلاً');return
   }
   $edit.number.Text=$item.number;$check.dialogIndex++;$confirm.PerformClick()
  }catch{$global:EditorCheck.errors+=($_.Exception.Message);$edit.window.Close()}
 }
})
$watch=[Windows.Forms.Timer]::new();$watch.Interval=150
$watch.Add_Tick({
 $global:EditorCheck.ticks++
 & (Get-Module TelephonyLocalSettings) {
  $ui=$script:LocalUi;if(-not $ui){return}
  if($global:EditorCheck.phase -eq 0){
   $global:EditorCheck.phase=1;$ui.form.Opacity=0
   $ui.addExtension.PerformClick()
   if($ui.grid.Rows.Count -ne 3){$global:EditorCheck.errors+='Add button did not create a row'}
   $ui.grid.CurrentCell=$ui.grid.Rows[2].Cells[0];$ui.editExtension.PerformClick()
   $ui.grid.CurrentCell=$ui.grid.Rows[1].Cells[0];$ui.editExtension.PerformClick()
   $ui.save.PerformClick();$global:EditorCheck.phase=2
  }elseif($global:EditorCheck.phase -eq 2 -and ($ui.success -or $global:EditorCheck.ticks -gt 30)){$ui.form.Close()}
 }
})
try{$driver.Start();$watch.Start();$success=Show-TelephonyLocalSettings -Owner $owner -LabRoot $root -EngineDirectory 'unused' -DataRoot (Join-Path $root 'Connection')}
finally{$driver.Stop();$driver.Dispose();$watch.Stop();$watch.Dispose();$owner.Close();$owner.Dispose()}
if(-not $success -or -not $global:EditorCheck.validationPassed -or $global:EditorCheck.called -ne 1 -or $global:EditorCheck.errors.Count){throw ('Actual add/edit/save controls failed: '+($global:EditorCheck.errors -join ', '))}
$manifest=[IO.File]::ReadAllText($global:EditorCheck.parameters.LocalConfigurationPath)|ConvertFrom-Json
if($manifest.incoming.number -ne '1005' -or $manifest.extensions.Count -ne 3 -or @($manifest.extensions|Where-Object {$_.number -eq '1004' -and $_.label -eq 'Edited office'}).Count -ne 1){throw 'Editor lost added/edited data or failed to update the route'}
Write-Output 'PASS: actual Add/Edit dialogs, duplicate-number rejection, new and existing extension edits, route propagation and save submission; no direct grid mutation, modem or network.'
