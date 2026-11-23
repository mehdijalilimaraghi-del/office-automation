#requires -Version 5.1
param([string]$LabRoot,[string]$PromptPath,[switch]$NoConfirm)
$ErrorActionPreference='Stop'
$module=Join-Path $PSScriptRoot 'PbxLab.psm1'
if(-not (Test-Path -LiteralPath $module)){$module=Join-Path $PSScriptRoot 'PbxLab/PbxLab.psm1'}
Import-Module $module -Force -DisableNameChecking
if(-not $LabRoot){$LabRoot=Join-Path $env:LOCALAPPDATA 'OfficeAutomation\Telephony\PbxLab'}
$state=Read-PbxLabState $LabRoot
if(Get-Process -Name FreeSwitchConsole -ErrorAction SilentlyContinue){throw 'Finish calls and run Stop-PbxLab.cmd first. No settings changed.'}
$service=Get-Service -Name FreeSWITCH -ErrorAction SilentlyContinue
if($service -and $service.Status -ne 'Stopped'){throw 'Stop the existing PBX service first. No settings changed.'}
$existingMenu=$state.ivr -and $state.ivr.enabled
$reuse=$existingMenu -and -not $PromptPath
if($reuse -and -not $NoConfirm){
 Add-Type -AssemblyName System.Windows.Forms
 $answer=[Windows.Forms.MessageBox]::Show('راهنمای فعال قبلی حفظ شود؟ بله: استفاده از پیام فعلی؛ خیر: انتخاب فایل WAV جدید با حفظ مقصدهای فعلی.','انتخاب پیام راهنما','YesNoCancel')
 if($answer -eq 'Cancel'){throw 'Activation cancelled. No settings changed.'}
 $reuse=$answer -eq 'Yes'
}
if($reuse){
 if(-not (Test-Path -LiteralPath $state.ivr.prompt_path)){throw 'Configured greeting is missing. Select a new WAV file.'}
 $description='راهنمای تنظیم‌شده فعلی حفظ می‌شود. تماس خط شرکت به همین راهنما هدایت خواهد شد.'
}else{
 if(-not $existingMenu -and ('1001' -notin $state.accounts.extension -or '1002' -notin $state.accounts.extension)){throw 'This quick setup needs existing extensions 1001 and 1002. Use office IVR settings for other destinations.'}
 if(-not $PromptPath){
  if($NoConfirm){throw 'A WAV prompt path is required'}
  Add-Type -AssemblyName System.Windows.Forms
  $picker=New-Object Windows.Forms.OpenFileDialog
  $picker.Title='فایل WAV پیام راهنما را انتخاب کنید';$picker.Filter='WAV audio (*.wav)|*.wav'
  $picker.InitialDirectory=$PSScriptRoot
  try{if($picker.ShowDialog() -ne 'OK'){throw 'No greeting selected. Run Record-IvrGreeting.cmd on a computer with a microphone first.'};$PromptPath=$picker.FileName}finally{$picker.Dispose()}
 }
 $description="کلید ۱: داخلی ۱۰۰۱`r`nکلید ۲: داخلی ۱۰۰۲`r`nبدون انتخاب: پس از دو نوبت انتظار ۷ ثانیه‌ای، داخلی ۱۰۰۲`r`nحساب‌ها و رمزهای همه داخلی‌ها حفظ می‌شوند."
 if($existingMenu){$description='فایل پیام جایگزین می‌شود؛ کلیدها، مقصدها، زمان انتظار و رمزهای فعلی حفظ می‌شوند.'}
}
if(-not $NoConfirm){
 Add-Type -AssemblyName System.Windows.Forms
 if([Windows.Forms.MessageBox]::Show($description+"`r`n`r`nفعال شود؟",'فعال‌سازی راهنمای تماس','YesNo') -ne 'Yes'){throw 'Activation cancelled. No settings changed.'}
}
$backup=Join-Path $LabRoot ('ivr-activation-backup-'+[guid]::NewGuid().ToString('N'))
[void][IO.Directory]::CreateDirectory($backup)
$names=@('lab-state.json','accounts.html','conf/freeswitch.xml')
foreach($name in $names){Copy-Item -LiteralPath (Join-Path $LabRoot $name) -Destination (Join-Path $backup ([IO.Path]::GetFileName($name)))}
$source=$null
try{
 if(-not $reuse){
  $source=Join-Path $env:TEMP ('office-ivr-input-'+[guid]::NewGuid().ToString('N'));[void][IO.Directory]::CreateDirectory($source)
  Copy-Item -LiteralPath $PromptPath -Destination (Join-Path $source 'greeting.wav')
  $configuration=[pscustomobject]@{
   type='office_telephony_configuration';schema_version=1
   extensions=@($state.accounts|ForEach-Object{[pscustomobject]@{number=$_.extension}})
   ivr=[pscustomobject]@{enabled=$true;timeout_seconds=7;retries=2;operator_number='1002';audio_file='greeting.wav';choices=@([pscustomobject]@{digit='1';number='1001'},[pscustomobject]@{digit='2';number='1002'})}
  }
  if($existingMenu){
   $configuration.ivr=[pscustomobject]@{enabled=$true;timeout_seconds=$state.ivr.timeout_seconds;retries=$state.ivr.retries;operator_number=$state.ivr.operator_number;audio_file='greeting.wav';choices=@($state.ivr.choices)}
  }
  Import-PbxOfficeConfiguration -Root $LabRoot -Configuration $configuration -SourceDirectory $source
 }
 Set-PbxModemGateway -Root $LabRoot -Destination '9197'
 Write-Host 'IVR enabled for incoming modem calls. Run Start-ModemBridge.cmd. Existing passwords preserved.' -ForegroundColor Green
}catch{
 foreach($name in $names){Copy-Item -LiteralPath (Join-Path $backup ([IO.Path]::GetFileName($name))) -Destination (Join-Path $LabRoot $name) -Force}
 throw
}finally{
 if($source){
  $owned=[IO.Path]::GetFullPath($source)
  $prefix=[IO.Path]::GetFullPath($env:TEMP).TrimEnd('\')+'\office-ivr-input-'
  if($owned.StartsWith($prefix,[StringComparison]::OrdinalIgnoreCase)){Remove-Item -LiteralPath $owned -Recurse -Force}
 }
}
