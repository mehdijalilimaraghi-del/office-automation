#requires -Version 5.1
param([string]$LabRoot,[string]$Destination)
$ErrorActionPreference='Stop'
$module=Join-Path $PSScriptRoot 'PbxLab.psm1'
if(-not (Test-Path -LiteralPath $module)){$module=Join-Path $PSScriptRoot 'PbxLab/PbxLab.psm1'}
Import-Module $module -Force -DisableNameChecking
if(-not $LabRoot){$LabRoot=Join-Path $env:LOCALAPPDATA 'OfficeAutomation\Telephony\PbxLab'}
$state=Read-PbxLabState $LabRoot
if(Get-Process -Name FreeSwitchConsole -ErrorAction SilentlyContinue){throw 'Stop-PbxLab.cmd must finish before preparing the gateway. Existing calls/settings were not changed.'}
if(-not $Destination){
 Add-Type -AssemblyName System.Windows.Forms;Add-Type -AssemblyName System.Drawing
 $form=New-Object Windows.Forms.Form;$form.Text='مقصد تماس خط شرکت';$form.Size=New-Object Drawing.Size(560,250);$form.StartPosition='CenterScreen';$form.RightToLeft='Yes';$form.RightToLeftLayout=$true;$form.Font=New-Object Drawing.Font('Segoe UI',10);$form.FormBorderStyle='FixedDialog';$form.MaximizeBox=$false
 $label=New-Object Windows.Forms.Label;$label.Text='تماس ورودی خط به کدام مقصد منتقل شود؟ حساب‌ها و رمز داخلی‌های فعلی حفظ می‌شوند.';$label.SetBounds(20,20,500,60)
 $choice=New-Object Windows.Forms.ComboBox;$choice.DropDownStyle='DropDownList';$choice.DisplayMember='title';$choice.SetBounds(20,85,500,35)
 if($state.ivr -and $state.ivr.enabled){[void]$choice.Items.Add([pscustomobject]@{number='9197';title='راهنمای صوتی تنظیم‌شده در اتوماسیون'})}
 foreach($account in $state.accounts){[void]$choice.Items.Add([pscustomobject]@{number=$account.extension;title=('داخلی '+$account.extension)})}
 if(-not $choice.Items.Count){throw 'No destination is configured'}
 $choice.SelectedIndex=0;if(-not ($state.ivr -and $state.ivr.enabled)){for($i=0;$i -lt $choice.Items.Count;$i++){if($choice.Items[$i].number -eq '1002'){$choice.SelectedIndex=$i}}}
 $ok=New-Object Windows.Forms.Button;$ok.Text='آماده‌سازی';$ok.SetBounds(20,145,150,40);$ok.DialogResult='OK';$form.AcceptButton=$ok
 [void]$form.Controls.Add($label);[void]$form.Controls.Add($choice);[void]$form.Controls.Add($ok)
 try{if($form.ShowDialog() -ne 'OK'){throw 'Gateway preparation cancelled'};$Destination=$choice.SelectedItem.number}finally{$form.Dispose()}
}
Set-PbxModemGateway -Root $LabRoot -Destination $Destination
Write-Host ('Gateway prepared for destination '+$Destination+'. Existing extension passwords were preserved.')
