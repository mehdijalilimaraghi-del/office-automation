#requires -Version 5.1
param([Parameter(Mandatory=$true)][string]$OutputDirectory)
$ErrorActionPreference='Stop'
Import-Module (Join-Path $PSScriptRoot 'PbxLab/PbxLab.psm1') -Force -DisableNameChecking
Import-Module (Join-Path $PSScriptRoot 'TelephonyDesktop.psm1') -Force -DisableNameChecking
Import-Module (Join-Path $PSScriptRoot 'TelephonyLocalSettings.psm1') -DisableNameChecking
$root=Join-Path $env:TEMP ('office-desktop-ui-'+[guid]::NewGuid().ToString('N'))
$LabRoot=Join-Path $root 'PbxLab';$ConnectionRoot=Join-Path $root 'Connection';$EngineDirectory='unused'
$state=New-PbxLabProfile -Root $LabRoot -BindAddress '127.0.0.1';Set-PbxModemGateway -Root $LabRoot -Destination '1002';$state=Read-PbxLabState $LabRoot
$run=@{profile=$state;session=$null;endpoint=$null;echo=$null;mutex=$null;stopping=$false;nextStart=$null;wantClose=$false;finished=$false}
$ReceiveCalls=$true;$DesktopMode=$true;$CompareGreetingModes=$false;$LineEchoCancellation=$true;$ports=@('COM_TEST')
$source=[IO.File]::ReadAllText((Join-Path $PSScriptRoot 'Start-ModemBridge.ps1'));$begin=$source.IndexOf(' $form=New-Object Windows.Forms.Form');$end=$source.IndexOf(' $timer=New-Object Windows.Forms.Timer',$begin)
. ([scriptblock]::Create($source.Substring($begin,$end-$begin)))
$errors=$null;$ast=[Management.Automation.Language.Parser]::ParseInput($source,[ref]$null,[ref]$errors);if($errors.Count){throw $errors[0]}
$node=$ast.Find({param($item)$item -is [Management.Automation.Language.IfStatementAst] -and $item.Extent.Text.StartsWith('if($DesktopMode){') -and $item.Extent.Text.Contains('Initialize-TelephonyDesktop ')},$true)
if(-not $node){throw 'Main UI initialization block missing'}
. ([scriptblock]::Create($node.Clauses[0].Item2.Extent.Text.Trim().Substring(1).TrimEnd('}')))
[void][IO.Directory]::CreateDirectory($OutputDirectory)
$form.ShowInTaskbar=$false;$form.Opacity=0;$form.Show();[Windows.Forms.Application]::DoEvents()
if($form.Font.FontFamily.Name -ne 'Vazirmatn'){throw 'Main window did not use bundled Persian font'}
if($consent.Visible -or $form.Controls.Text -match 'VentaFax'){throw 'Vendor-specific consent remains in product UI'}
$bitmap=[Drawing.Bitmap]::new($form.Width,$form.Height);try{$form.DrawToBitmap($bitmap,[Drawing.Rectangle]::new(0,0,$form.Width,$form.Height));$bitmap.Save((Join-Path $OutputDirectory 'desktop.png'))}finally{$bitmap.Dispose()}
$global:LocalUiOutput=$OutputDirectory
$probe=[Windows.Forms.Timer]::new();$probe.Interval=300
$probe.Add_Tick({
 foreach($window in @([Windows.Forms.Application]::OpenForms)){
  if($window.Text -eq 'تنظیمات مستقل تلفن سازمانی'){
   if($window.Font.FontFamily.Name -ne 'Vazirmatn'){throw 'Settings font mismatch'}
   $window.Opacity=0
   $bitmap=[Drawing.Bitmap]::new($window.Width,$window.Height)
   try{$window.DrawToBitmap($bitmap,[Drawing.Rectangle]::new(0,0,$window.Width,$window.Height));$bitmap.Save((Join-Path $global:LocalUiOutput 'settings.png'))}finally{$bitmap.Dispose()}
   $tabs=@($window.Controls[0].Controls | Where-Object {$_ -is [Windows.Forms.TabControl]})[0]
   if($tabs){
    for($index=0;$index -lt $tabs.TabPages.Count;$index++){
     $tabs.SelectedIndex=$index;$window.Refresh()
     $bitmap=[Drawing.Bitmap]::new($window.Width,$window.Height)
     try{$window.DrawToBitmap($bitmap,[Drawing.Rectangle]::new(0,0,$window.Width,$window.Height));$bitmap.Save((Join-Path $global:LocalUiOutput ('settings-tab-'+$index+'.png')))}finally{$bitmap.Dispose()}
    }
   }
   $window.Close()
  }
 }
})
try{$probe.Start();[void](Show-TelephonyLocalSettings -Owner $form -LabRoot $LabRoot -DataRoot $ConnectionRoot -EngineDirectory $EngineDirectory)}
finally{$probe.Stop();$probe.Dispose();$form.Close();$form.Dispose()}
if(-not (Test-Path -LiteralPath (Join-Path $OutputDirectory 'settings.png'))){throw 'Settings UI was not rendered'}
Write-Output 'PASS: actual desktop and independent settings load bundled Vazirmatn, render without automation, and remove vendor-specific consent; no modem/network/PBX process opened.'
