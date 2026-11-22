#requires -Version 5.1
[CmdletBinding()]
param([string]$LabRoot,[string]$BindAddress,[string[]]$Extensions=@('1001','1002'),[int]$SipPort=5160,[int]$EslPort=18021)
$ErrorActionPreference='Stop'
Import-Module (Join-Path $PSScriptRoot 'PbxLab.psm1') -Force -DisableNameChecking
if(-not $LabRoot){$LabRoot=Join-Path $env:LOCALAPPDATA 'OfficeAutomation\Telephony\PbxLab'}
$LabRoot=[IO.Path]::GetFullPath($LabRoot)
if(Test-Path -LiteralPath (Join-Path $LabRoot 'lab-state.json')){
    $existing=Read-PbxLabState $LabRoot
    if($BindAddress -and $BindAddress -ne $existing.bind_address){throw 'An existing lab profile uses a different address. Existing settings were preserved.'}
    if(($PSBoundParameters.ContainsKey('Extensions') -and ($Extensions -join ',') -ne (($existing.accounts|ForEach-Object {$_.extension}) -join ',')) -or ($PSBoundParameters.ContainsKey('SipPort') -and $SipPort -ne $existing.sip_port) -or ($PSBoundParameters.ContainsKey('EslPort') -and $EslPort -ne $existing.esl_port)){throw 'Existing profile differs. Use a separate LabRoot for a different lab; existing credentials were preserved.'}
    Write-Output $LabRoot;return
}
if(-not $BindAddress){
    Add-Type -AssemblyName System.Windows.Forms;Add-Type -AssemblyName System.Drawing
    $addresses=@(Get-NetIPAddress -AddressFamily IPv4 -AddressState Preferred | Where-Object {$_.IPAddress -match '^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)'} | ForEach-Object {[pscustomobject]@{ip=$_.IPAddress;label=($_.InterfaceAlias+' — '+$_.IPAddress)}})
    if(-not $addresses.Count){throw 'No private LAN IPv4 address found.'}
    $dialog=New-Object Windows.Forms.Form;$dialog.Text='آماده‌سازی تماس داخلی';$dialog.Size=New-Object Drawing.Size(680,320);$dialog.StartPosition='CenterScreen';$dialog.Font=New-Object Drawing.Font('Segoe UI',10);$dialog.RightToLeft='Yes';$dialog.RightToLeftLayout=$true;$dialog.FormBorderStyle='FixedDialog';$dialog.MaximizeBox=$false
    $layout=New-Object Windows.Forms.TableLayoutPanel;$layout.Dock='Fill';$layout.Padding=New-Object Windows.Forms.Padding(18);$layout.ColumnCount=2;$layout.RowCount=4;[void]$layout.ColumnStyles.Add((New-Object Windows.Forms.ColumnStyle([Windows.Forms.SizeType]::Absolute,160)));[void]$layout.ColumnStyles.Add((New-Object Windows.Forms.ColumnStyle([Windows.Forms.SizeType]::Percent,100)))
    foreach($height in @(70,50,50,65)){[void]$layout.RowStyles.Add((New-Object Windows.Forms.RowStyle([Windows.Forms.SizeType]::Absolute,$height)))}
    $notice=New-Object Windows.Forms.Label;$notice.Dock='Fill';$notice.Text='آدرس شبکه کامپیوتر مرکز تلفن و شماره داخلی‌های آزمایشی را انتخاب کنید. رمزهای اختصاصی ساخته می‌شوند و تنظیمات قبلی ویندوز و مودم تغییر نمی‌کنند.';[void]$layout.Controls.Add($notice,0,0);$layout.SetColumnSpan($notice,2)
    $addressLabel=New-Object Windows.Forms.Label;$addressLabel.Text='آدرس شبکه';$addressLabel.Dock='Fill'
    $choice=New-Object Windows.Forms.ComboBox;$choice.Dock='Top';$choice.DropDownStyle='DropDownList';$choice.DisplayMember='label';foreach($address in $addresses){[void]$choice.Items.Add($address)};$choice.SelectedIndex=0
    $extensionLabel=New-Object Windows.Forms.Label;$extensionLabel.Text='داخلی‌ها با کاما';$extensionLabel.Dock='Fill'
    $numbers=New-Object Windows.Forms.TextBox;$numbers.Dock='Top';$numbers.RightToLeft='No';$numbers.Text=($Extensions -join ',')
    [void]$layout.Controls.Add($addressLabel,0,1);[void]$layout.Controls.Add($choice,1,1);[void]$layout.Controls.Add($extensionLabel,0,2);[void]$layout.Controls.Add($numbers,1,2)
    $buttons=New-Object Windows.Forms.FlowLayoutPanel;$buttons.Dock='Fill';$ok=New-Object Windows.Forms.Button;$ok.Text='آماده‌سازی';$ok.AutoSize=$true;$cancel=New-Object Windows.Forms.Button;$cancel.Text='انصراف';$cancel.AutoSize=$true;$cancel.DialogResult='Cancel';[void]$buttons.Controls.Add($ok);[void]$buttons.Controls.Add($cancel);[void]$layout.Controls.Add($buttons,0,3);$layout.SetColumnSpan($buttons,2)
    $selected=@{ip=$null;numbers=@()};$ok.Add_Click({$selected.ip=$choice.SelectedItem.ip;$selected.numbers=@($numbers.Text -split '[,،\s]+'|Where-Object {$_});$dialog.DialogResult='OK';$dialog.Close()})
    [void]$dialog.Controls.Add($layout);try{if($dialog.ShowDialog() -ne 'OK'){throw 'Lab setup cancelled'};$BindAddress=$selected.ip;$Extensions=$selected.numbers}finally{$dialog.Dispose()}
}
New-PbxLabProfile -Root $LabRoot -BindAddress $BindAddress -Extensions $Extensions -SipPort $SipPort -EslPort $EslPort | Out-Null
Write-Output $LabRoot